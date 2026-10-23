<?php

namespace App\Support\Import;

use Carbon\Carbon;
use DOMDocument;
use Generator;
use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Read a .xlsx or .csv inventory one row at a time, and resume where it stopped.
 *
 * The client's workbook is around 300 MB. Nothing here loads it: an .xlsx is a
 * zip of XML, so the first worksheet is unpacked once to a cache directory and
 * then read in windows of `</row>` boundaries, remembering the byte offset after
 * every row. The next request seeks straight back to that offset, so a huge
 * sheet is checked in chunks instead of in one request that has to finish.
 *
 * Values come back as text, because that is what the office's identifiers are:
 * a Property ID No. may look numeric, but it is not a number to do arithmetic
 * on and must never be printed as 3.0014E+07.
 *
 * Dates are the one place the raw cell has to be interpreted. Excel stores a
 * date as a serial number plus a style that says "this is a date"; the style is
 * resolved through xl/styles.xml, so 19-Jan-22 arrives as 2022-01-19 instead of
 * 44580.
 */
class SpreadsheetReader
{
    /** Columns beyond this are formatting noise, not data. */
    public const MAX_COLUMNS = 80;

    /** Built-in Excel number formats that mean "date", with or without a time. */
    private const DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47];

    /** The spreadsheet namespace every worksheet element belongs to. */
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** The package-relationship namespace (workbook.xml.rels). */
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private string $path;

    private string $type;

    private ?ZipArchive $zip = null;

    private ?string $sheetFile = null;

    /** @var resource|null */
    private $sharedHandle = null;

    /** @var array<int, int> shared string index => byte offset in the record file */
    private array $sharedOffsets = [];

    /** @var array<int, bool> style index => is a date */
    private array $dateStyles = [];

    private ?string $sharedFile = null;

    private ?string $cacheDir = null;

    private ?string $sheetEntry = null;

    /** @var array<int, string> column index => heading */
    private array $headers = [];

    /** Byte offset in the data source where the first data row starts. */
    private int $dataOffset = 0;

    private bool $headersRead = false;

    private function __construct(string $path, string $type)
    {
        $this->path = $path;
        $this->type = $type;
    }

    /**
     * Open a file whose real path the caller already has.
     *
     * The format is decided by the bytes, not only the extension: a workbook
     * saved under the wrong name gets an honest message instead of a parse
     * error, and an old .xls is named as such.
     *
     * `$cacheDir` keeps the unpacked worksheet and the shared string table
     * between requests. Without it (tests, one-off reads) everything is
     * temporary and removed by close().
     */
    public static function open(string $absolutePath, ?string $extension = null, ?string $cacheDir = null): self
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException('The uploaded file could not be read. Please upload it again.');
        }

        $magic = (string) file_get_contents($absolutePath, false, null, 0, 4);

        if (str_starts_with($magic, "PK\x03\x04")) {
            $reader = new self($absolutePath, 'xlsx');
            $reader->cacheDir = $cacheDir;

            if ($cacheDir !== null) {
                self::ensureDirectory($cacheDir);
            }

            $reader->openXlsx();

            return $reader;
        }

        if (str_starts_with($magic, "\xD0\xCF\x11\xE0")) {
            throw new RuntimeException('That is an old .xls workbook. Open it in Excel and save it as .xlsx or .csv first.');
        }

        if ($magic === '') {
            throw new RuntimeException('That file is empty.');
        }

        $reader = new self($absolutePath, 'csv');

        return $reader;
    }

    public function isXlsx(): bool
    {
        return $this->type === 'xlsx';
    }

    /** @return array<int, string> heading by column index */
    public function headers(): array
    {
        if (! $this->headersRead) {
            $this->readHeader();
        }

        return $this->headers;
    }

    /** Byte offset to start the data rows at (just past the header row). */
    public function dataOffset(): int
    {
        if (! $this->headersRead) {
            $this->readHeader();
        }

        return $this->dataOffset;
    }

    /**
     * Walk the data rows from a byte offset, yielding the offset to resume at.
     *
     * `line` is the row number the office sees in Excel, so a message can say
     * "line 412" and the person can go straight to it.
     *
     * @return Generator<int, array{offset:int, line:int, cells:array<int,string>}>
     */
    public function rowsFrom(int $offset): Generator
    {
        if ($this->type === 'xlsx') {
            yield from $this->xlsxRowsFrom($offset);

            return;
        }

        yield from $this->csvRowsFrom($offset);
    }

    public function close(): void
    {
        if (is_resource($this->sharedHandle)) {
            fclose($this->sharedHandle);
        }

        $this->sharedHandle = null;

        if ($this->zip) {
            $this->zip->close();
            $this->zip = null;
        }

        // With a cache directory the unpacked parts belong to the batch and are
        // removed when the batch finishes; without one they are ours to delete.
        if ($this->cacheDir === null) {
            foreach ([$this->sheetFile, $this->sharedFile] as $file) {
                if ($file && is_file($file)) {
                    @unlink($file);
                }
            }
        }

        $this->sheetFile = null;
        $this->sharedFile = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    /* ═══════════════════════════ xlsx ═══════════════════════════ */

    private function openXlsx(): void
    {
        $zip = new ZipArchive();

        if ($zip->open($this->path) !== true) {
            throw new RuntimeException('That workbook could not be opened. Save it again in Excel and retry.');
        }

        $this->zip = $zip;
        $this->sheetEntry = $this->firstSheetEntry();

        if ($this->sheetEntry === null) {
            throw new RuntimeException('That workbook has no worksheet in it.');
        }

        $this->sheetFile = $this->unpack($this->sheetEntry, 'sheet.xml');
        $this->sharedFile = $this->unpackSharedStrings();
        $this->sharedHandle = $this->sharedFile ? fopen($this->sharedFile, 'rb') : null;

        if ($this->sharedHandle) {
            $this->indexSharedStrings();
        }

        $this->loadDateStyles();
    }

    /**
     * Copy a zip entry to the cache directory, once.
     *
     * A 300 MB workbook is a few hundred MB of sheet XML at most, and Railway's
     * disk holds that for the few minutes an import takes. When it cannot, the
     * office is told to split the file rather than the request dying halfway.
     */
    private function unpack(string $entry, string $name): string
    {
        $destination = $this->cacheDir !== null
            ? $this->cacheDir . DIRECTORY_SEPARATOR . $name
            : (string) tempnam(sys_get_temp_dir(), 'nutrace-' . pathinfo($name, PATHINFO_EXTENSION));

        if (is_file($destination) && filesize($destination) > 0) {
            return $destination;
        }

        $input = $this->zip?->getStream($entry);

        if (! $input) {
            throw new RuntimeException('The worksheet could not be unpacked. Save the workbook again and retry.');
        }

        $output = @fopen($destination, 'wb');

        if ($output === false) {
            throw new RuntimeException('The server has no room to unpack that workbook. Split it and import it in parts.');
        }

        $copied = stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        if (! $copied) {
            @unlink($destination);

            throw new RuntimeException('The worksheet could not be unpacked. Save the workbook again and retry.');
        }

        return $destination;
    }

    /**
     * The shared string table, as length-prefixed records in a temporary file.
     *
     * A real registry repeats the same few hundred descriptions tens of
     * thousands of times, so this table stays small - but it is the one part of
     * a workbook that is only written compressed, and re-reading it per request
     * would be wasteful. Records are length-prefixed because a description may
     * itself contain a newline.
     */
    private function unpackSharedStrings(): ?string
    {
        if (! $this->zip?->locateName('xl/sharedStrings.xml', ZipArchive::FL_NOCASE)) {
            return null;
        }

        $destination = $this->cacheDir !== null
            ? $this->cacheDir . DIRECTORY_SEPARATOR . 'shared.dat'
            : (string) tempnam(sys_get_temp_dir(), 'nutrace-sst');

        if (is_file($destination) && filesize($destination) > 0) {
            return $destination;
        }

        $output = @fopen($destination, 'w+b');

        if ($output === false) {
            throw new RuntimeException('The server has no room to unpack that workbook. Split it and import it in parts.');
        }

        $reader = new XMLReader();

        if (! $reader->open('zip://' . $this->path . '#xl/sharedStrings.xml')) {
            fclose($output);
            @unlink($destination);

            throw new RuntimeException('The workbook\'s text table could not be read.');
        }

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                continue;
            }

            $text = $this->textOf($reader->readOuterXml());
            fwrite($output, strlen($text) . ':' . $text);
        }

        $reader->close();
        fclose($output);

        return $destination;
    }

    /** Record where each shared string starts. */
    private function indexSharedStrings(): void
    {
        if (! is_resource($this->sharedHandle)) {
            return;
        }

        rewind($this->sharedHandle);

        $index = 0;

        while (! feof($this->sharedHandle)) {
            $offset = ftell($this->sharedHandle);

            if ($offset === false) {
                break;
            }

            $header = '';

            while (($character = fgetc($this->sharedHandle)) !== false && $character !== ':') {
                if (strlen($header) > 12) {
                    return;
                }

                $header .= $character;
            }

            if ($character === false) {
                break;
            }

            $length = (int) $header;
            $this->sharedOffsets[$index] = $offset;
            $index++;

            if ($length > 0) {
                fseek($this->sharedHandle, $length, SEEK_CUR);
            }
        }
    }

    private function sharedString(int $index): string
    {
        if (! isset($this->sharedOffsets[$index]) || ! is_resource($this->sharedHandle)) {
            return '';
        }

        fseek($this->sharedHandle, $this->sharedOffsets[$index]);

        $header = '';

        while (($character = fgetc($this->sharedHandle)) !== false && $character !== ':') {
            if (strlen($header) > 12) {
                return '';
            }

            $header .= $character;
        }

        $length = (int) $header;

        return $length > 0 ? (string) fread($this->sharedHandle, $length) : '';
    }

    /** Which style indexes mean "date", from xl/styles.xml. */
    private function loadDateStyles(): void
    {
        $styles = $this->zip?->getFromName('xl/styles.xml');

        if (! $styles) {
            return;
        }

        $document = $this->dom($styles);

        if (! $document) {
            return;
        }

        $customDate = [];

        foreach (self::elements($document, 'numFmt') as $format) {
            $code = strtolower($format->getAttribute('formatCode'));

            // A date format prints a day/month/year token; a plain number format
            // with a thousands separator must not be read as one.
            if (preg_match('/[dmy]/', preg_replace('/"[^"]*"/', '', $code) ?? '')
                && ! preg_match('/^[#0.,%]+$/', $code)) {
                $customDate[$format->getAttribute('numFmtId')] = true;
            }
        }

        $cellXfs = self::firstElement($document, 'cellXfs');

        if (! $cellXfs) {
            return;
        }

        $index = 0;

        foreach (self::elements($cellXfs, 'xf') as $xf) {
            $formatId = (int) $xf->getAttribute('numFmtId');

            $this->dateStyles[$index] = in_array($formatId, self::DATE_FORMATS, true)
                || isset($customDate[(string) $formatId]);

            $index++;
        }
    }

    private function firstSheetEntry(): ?string
    {
        $target = null;
        $workbook = $this->zip?->getFromName('xl/workbook.xml');

        if ($workbook) {
            $document = $this->dom($workbook);
            $sheet = $document ? self::firstElement($document, 'sheet') : null;

            if ($sheet instanceof \DOMElement) {
                $relId = $sheet->getAttributeNS(self::REL_NS, 'id')
                    ?: $sheet->getAttribute('r:id');

                if ($relId !== '' && ($rels = $this->zip?->getFromName('xl/_rels/workbook.xml.rels'))) {
                    $relationships = $this->dom($rels);

                    foreach ($relationships ? self::elements($relationships, 'Relationship') : [] as $relationship) {
                        if ($relationship->getAttribute('Id') === $relId) {
                            $target = $relationship->getAttribute('Target');
                            break;
                        }
                    }
                }
            }
        }

        $target = ltrim((string) $target, '/');

        if ($target !== '') {
            $entry = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;

            if ($this->zip?->locateName($entry, ZipArchive::FL_NOCASE) !== false) {
                return $entry;
            }
        }

        for ($index = 0; $index < (int) $this->zip?->numFiles; $index++) {
            $name = (string) $this->zip?->getNameIndex($index);

            if (preg_match('#^xl/worksheets/[^/]+\.xml$#i', $name)) {
                return $name;
            }
        }

        return null;
    }

    /** @return Generator<int, array{offset:int, line:int, cells:array<int,string>}> */
    private function xlsxRowsFrom(int $offset): Generator
    {
        $handle = $this->sheetFile ? fopen($this->sheetFile, 'rb') : false;

        if ($handle === false) {
            throw new RuntimeException('The unpacked worksheet could not be read.');
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $buffer = '';
        $bytesRead = $offset;
        $line = 0;

        // The first row's r attribute gives the real starting row number; a
        // sheet that omits it is counted from one.
        $firstRowInBuffer = true;

        while (true) {
            $chunk = fread($handle, 262144);
            $done = $chunk === false || $chunk === '';

            if (! $done) {
                $buffer .= $chunk;
                $bytesRead += strlen($chunk);
            }

            // A self-closing row has no closing tag to split on; make it
            // explicit so every row ends at exactly one boundary. The element
            // may carry a namespace prefix (`<x:row>`), which is the same
            // OOXML as Excel's `<row>`: writers such as PhpSpreadsheet prefix
            // everything, so both spellings have to be found here.
            $buffer = preg_replace('/<((?:[A-Za-z0-9_.-]+:)?row)\b([^>]*?)\/>/i', '<$1$2></$1>', $buffer) ?? $buffer;

            $extracted = 0;

            while (preg_match('/<\/(?:[A-Za-z0-9_.-]+:)?row>/i', $buffer, $closing, PREG_OFFSET_CAPTURE, $extracted)) {
                $end = $closing[0][1] + strlen($closing[0][0]);
                $fragment = substr($buffer, $extracted, $end - $extracted);
                $extracted = $end;

                $row = $this->readRowFragment($fragment);

                if ($row === null) {
                    continue;
                }

                $declared = (int) $row['line'];

                if ($declared > 0) {
                    $line = $declared;
                } elseif ($firstRowInBuffer) {
                    $line = max($line + 1, 1);
                } else {
                    $line++;
                }

                $firstRowInBuffer = false;

                $cells = $this->trimEmptyTail($row['cells']);

                if ($this->isBlankRow($cells)) {
                    continue;
                }

                // Where the next request has to start: this row's end.
                $resume = $bytesRead - strlen($buffer) + $extracted;

                yield ['offset' => $resume, 'line' => $line, 'cells' => $cells];
            }

            $buffer = substr($buffer, $extracted);
            $extracted = 0;

            if ($done) {
                break;
            }
        }

        fclose($handle);
    }

    /**
     * One <row> element as column index => text.
     *
     * @return array{line:int, cells:array<int,string>}|null
     */
    private function readRowFragment(string $fragment): ?array
    {
        // The very first fragment still carries the XML declaration and the
        // unclosed <worksheet>/<sheetData> wrappers ahead of it, which is not a
        // well-formed document on its own. Start at the row element, whatever
        // prefix the writer gave it.
        if (! preg_match('/<((?:[A-Za-z0-9_.-]+:)?row)\b/i', $fragment, $opening, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $qualified = $opening[1][0];
        $prefix = substr($qualified, 0, -3);
        $namespace = $prefix === '' ? '' : ' xmlns:'.rtrim($prefix, ':').'="'.self::MAIN_NS.'"';

        // A row cut out of the worksheet loses the namespace declarations that
        // live on the root element, so it is read inside a wrapper carrying
        // them. Without this, a prefixed row is a namespace error and an empty
        // row is all that comes back.
        $document = $this->dom(
            '<nutrace xmlns="'.self::MAIN_NS.'" xmlns:r="'.self::REL_NS.'"'.$namespace.'>'
            .substr($fragment, $opening[0][1])
            .'</nutrace>'
        );

        if (! $document) {
            return null;
        }

        $rowNode = self::firstElement($document, 'row');

        if (! $rowNode instanceof \DOMElement) {
            return null;
        }

        $cells = [];
        $position = 0;

        foreach (self::elements($rowNode, 'c') as $cell) {

            $reference = $cell->getAttribute('r');
            $index = $reference !== '' ? self::columnIndex($reference) : $position;

            $position = $index + 1;

            if ($index < 0 || $index >= self::MAX_COLUMNS) {
                continue;
            }

            $cells[$index] = $this->cellText($cell);
        }

        ksort($cells);

        return ['line' => (int) $rowNode->getAttribute('r'), 'cells' => $cells];
    }

    private function cellText(\DOMElement $cell): string
    {
        $type = $cell->getAttribute('t');
        $style = $cell->getAttribute('s');

        $value = '';
        $inline = '';

        foreach ($cell->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 'v') {
                $value = $child->textContent;
            } elseif ($child->localName === 'is') {
                foreach (self::elements($child, 't') as $text) {
                    $inline .= $text->textContent;
                }
            }
        }

        if ($type === 's') {
            return trim($this->sharedString((int) $value));
        }

        if ($type === 'inlineStr') {
            return trim($inline);
        }

        if ($type === 'str') {
            return trim($value);
        }

        if ($type === 'b') {
            return $value === '1' ? 'TRUE' : 'FALSE';
        }

        if ($type === 'e') {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ($style !== '' && ($this->dateStyles[(int) $style] ?? false) && is_numeric($value)) {
            return self::excelDate((float) $value);
        }

        return self::formatNumber($value);
    }

    /* ═══════════════════════════ csv ═══════════════════════════ */

    /** @return Generator<int, array{offset:int, line:int, cells:array<int,string>}> */
    private function csvRowsFrom(int $offset): Generator
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        } else {
            $first = fgets($handle);

            if ($first !== false && str_starts_with($first, "\xEF\xBB\xBF")) {
                // Skip the BOM only; the row is re-read below.
                fseek($handle, 3);
            } else {
                rewind($handle);
            }
        }

        $delimiter = self::delimiter($this->path);

        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $resume = ftell($handle) ?: 0;

            $cells = array_map(static fn ($cell) => trim((string) $cell, "\r\n"), $cells);

            if ($cells === [null] || $this->isBlankRow(array_map('strval', $cells))) {
                continue;
            }

            yield [
                'offset' => $resume,
                'line'     => $this->csvLineAt($resume),
                'cells'    => $this->trimEmptyTail(array_map('strval', $cells)),
            ];
        }

        fclose($handle);
    }

    /** Separator of the file, read from its first line (Excel writes commas). */
    private static function delimiter(string $path): string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return ',';
        }

        $line = (string) fgets($handle);
        fclose($handle);

        $commas = substr_count($line, ',');
        $semicolons = substr_count($line, ';');
        $tabs = substr_count($line, "\t");

        if ($semicolons > $commas && $semicolons >= $tabs) {
            return ';';
        }

        return $tabs > $commas ? "\t" : ',';
    }

    /**
     * The record number for a byte offset, by counting line breaks before it.
     *
     * Only used for the `line` a message names, so a quoted field spanning
     * several physical lines is counted as the line it starts on.
     */
    private function csvLineAt(int $offset): int
    {
        static $cache = [];

        if (isset($cache[$this->path][$offset])) {
            return $cache[$this->path][$offset];
        }

        $handle = @fopen($this->path, 'rb');

        if ($handle === false) {
            return 0;
        }

        $line = 0;
        $read = 0;

        while ($read < $offset && ($chunk = fread($handle, min(1048576, $offset - $read))) !== false && $chunk !== '') {
            $line += substr_count($chunk, "\n");
            $read += strlen($chunk);
        }

        // A record that ends at the very end of the file has no newline after
        // it, so the line it occupies is one past the last newline counted.
        if ($offset > 0) {
            fseek($handle, $offset - 1);
            $last = fgetc($handle);

            if ($last !== "\n" && $last !== "\r") {
                $line++;
            }
        }

        fclose($handle);

        $line = max($line, 1);
        $cache[$this->path][$offset] = $line;

        return $line;
    }

    /* ═══════════════════════════ shared ═══════════════════════════ */

    private function readHeader(): void
    {
        $this->headersRead = true;

        foreach ($this->rowsFrom(0) as $row) {
            $headings = [];

            foreach ($row['cells'] as $index => $heading) {
                $headings[$index] = trim((string) $heading);
            }

            $this->headers = $headings;
            $this->dataOffset = $row['offset'];

            return;
        }

        $this->headers = [];
        $this->dataOffset = 0;
    }

    /** Column letters to a zero-based index: "A" => 0, "AB" => 27. */
    public static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $reference) ?? '';

        if ($letters === '') {
            return -1;
        }

        $index = 0;

        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /** Excel's 1900 date system, including its deliberate 1900 leap-year bug. */
    public static function excelDate(float $serial): string
    {
        if ($serial <= 0) {
            return '';
        }

        return Carbon::create(1899, 12, 30, 0, 0, 0)
            ->addDays((int) floor($serial))
            ->format('Y-m-d');
    }

    /** A numeric cell as text, without an invented decimal point. */
    public static function formatNumber(string $raw): string
    {
        if (! is_numeric($raw)) {
            return trim($raw);
        }

        if (preg_match('/^-?\d+$/', $raw)) {
            return $raw;
        }

        $float = (float) $raw;

        if (floor($float) == $float && abs($float) < 1.0e15) {
            return number_format($float, 0, '.', '');
        }

        return rtrim(rtrim(sprintf('%.10F', $float), '0'), '.');
    }

    /** @param array<int, string> $cells */
    private function trimEmptyTail(array $cells): array
    {
        ksort($cells);
        $trimmed = [];

        foreach ($cells as $index => $value) {
            if ($index < self::MAX_COLUMNS) {
                $trimmed[$index] = (string) $value;
            }
        }

        return $trimmed;
    }

    /** @param array<int, string> $cells */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** Text of every <t> in a fragment (handles rich-text runs). */
    private function textOf(string $markup): string
    {
        // The fragment is a <si> (or <is>) cut out of the shared string table,
        // so it arrives without the root element that declares the namespace
        // prefix; wrapping it keeps a prefixed table readable.
        $document = $this->dom('<nutrace xmlns="'.self::MAIN_NS.'" xmlns:x="'.self::MAIN_NS.'">'.$markup.'</nutrace>');

        if (! $document) {
            return '';
        }

        $text = '';

        foreach (self::elements($document, 't') as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }

    /**
     * Every element with this local name, whatever namespace prefix the writer
     * used.
     *
     * Excel writes the spreadsheet namespace as the default one (`<row>`);
     * other writers — PhpSpreadsheet among them — prefix every element
     * (`<x:row>`). Both are the same OOXML, and matching on the local name is
     * what lets one reader take either.
     *
     * @return list<\DOMElement>
     */
    private static function elements(\DOMNode $scope, string $localName): array
    {
        $found = [];

        foreach ($scope->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === $localName) {
                $found[] = $element;
            }
        }

        return $found;
    }

    private static function firstElement(\DOMNode $scope, string $localName): ?\DOMElement
    {
        return self::elements($scope, $localName)[0] ?? null;
    }

    private function dom(string $markup): ?DOMDocument
    {
        if (trim($markup) === '') {
            return null;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        // No LIBXML_NOENT: a workbook is user-supplied input and must never be
        // able to expand entities or reach the network.
        $loaded = $document->loadXML($markup, LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    private static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('The server could not prepare a place to read that workbook.');
        }
    }
}
