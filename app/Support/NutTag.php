<?php

namespace App\Support;

/**
 * NUTAG-1 - PROTOTYPE of a tag that is not a QR code.
 *
 * A standard QR is decodable by any conforming reader, so a sticker printed as
 * a QR can always be read by a camera app or a website. This tag is its own
 * format: a black frame, an orientation marker, and a grid of cells carrying
 * the asset's token and a truncated HMAC. Nothing in it is a QR, so a QR reader
 * finds no code at all and shows the user nothing - not even gibberish.
 *
 * Layout (25 x 25 cells, drawn with a 2-cell quiet zone):
 *
 *     +-------------------------------+   the outer ring is always black, and
 *     |  WWWWWWWWWWWWWWWWWWWWWWWWW    |   its four corners are what the reader
 *     |  W BBB W ................ W    |   locates; a one-cell white gap keeps
 *     |  W BBB W ................ W    |   the marker clear of the frame
 *     |  W BBB W ................ W    |   (B) 3x3 black orientation marker at
 *     |  W.....  ................ W    |   rows/cols 2-4, inside a white ring
 *     |  W......................  W    |
 *     |  ...                          |   every other cell is data
 *     +-------------------------------+
 *
 * Data cells are the 23 x 23 inner area minus that 5 x 5 marker zone: 504
 * cells for 384 bits - the 24-byte payload (16 token + 8 MAC) written twice,
 * so a smeared cell in one copy can be recovered from the other.
 *
 * Payload: 16 random token bytes + the first 8 bytes of
 * HMAC-SHA256("nutag1" . token, key). The MAC is what stops someone inventing
 * a token that the app would accept; unlike the QR sticker the check needs the
 * server (or a device holding the key), which is the trade-off this format
 * makes for being unreadable to outside scanners.
 *
 * PROTOTYPE ONLY: this class is not wired into the label, the scanners or the
 * resolvers yet, and `png()` exists so the prototype can be printed and
 * photographed before anything in production is switched over.
 */
class NutTag
{
    /** Format version this class writes. */
    public const VERSION = 1;

    /** Cells across, excluding the quiet zone. */
    public const GRID = 25;

    /** Quiet zone around the frame, in cells. */
    public const QUIET = 2;

    public const TOKEN_BYTES = 16;

    public const MAC_BYTES = 8;

    /** Bits carried by one copy: (16 + 8) * 8. */
    public const PAYLOAD_BITS = 192;

    /** The 5x5 marker zone (white ring + 3x3 black core) starts here. */
    public const MARKER_ZONE_START = 1;

    public const MARKER_ZONE_SIZE = 5;

    public const MARKER_CORE_START = 2;

    public const MARKER_CORE_SIZE = 3;

    /** Domain separator, so this MAC is never the one used for anything else. */
    private const MAC_CONTEXT = 'nutag1';

    /** A random token for one asset. */
    public static function token(): string
    {
        return random_bytes(self::TOKEN_BYTES);
    }

    /**
     * The 8-byte MAC over a token. Truncated on purpose: this tag has room for
     * 24 bytes, not the 64 an Ed25519 signature needs.
     */
    public static function mac(string $token, string $key): string
    {
        if (strlen($token) !== self::TOKEN_BYTES) {
            throw new \InvalidArgumentException('A NUTAG token is exactly '.self::TOKEN_BYTES.' bytes.');
        }

        return substr(hash_hmac('sha256', self::MAC_CONTEXT.$token, $key, true), 0, self::MAC_BYTES);
    }

    /**
     * Build the cell matrix. True is a black cell.
     *
     * @return array<int, array<int, bool>>
     */
    public static function matrix(string $token, string $mac): array
    {
        if (strlen($token) !== self::TOKEN_BYTES) {
            throw new \InvalidArgumentException('A NUTAG token is exactly '.self::TOKEN_BYTES.' bytes.');
        }

        if (strlen($mac) !== self::MAC_BYTES) {
            throw new \InvalidArgumentException('A NUTAG MAC is exactly '.self::MAC_BYTES.' bytes.');
        }

        $size = self::GRID;
        $cells = array_fill(0, $size, array_fill(0, $size, false));

        // Frame: the one dark ring the reader can always find.
        for ($i = 0; $i < $size; $i++) {
            $cells[0][$i] = true;
            $cells[$size - 1][$i] = true;
            $cells[$i][0] = true;
            $cells[$i][$size - 1] = true;
        }

        // Orientation marker: black 3x3 inside the white ring of its zone.
        for ($r = self::MARKER_CORE_START; $r < self::MARKER_CORE_START + self::MARKER_CORE_SIZE; $r++) {
            for ($c = self::MARKER_CORE_START; $c < self::MARKER_CORE_START + self::MARKER_CORE_SIZE; $c++) {
                $cells[$r][$c] = true;
            }
        }

        $bits = self::bits($token.$mac);
        $positions = self::dataPositions();

        // 504 cells hold two copies of 192 bits comfortably; a longer token or
        // MAC would have to grow the grid instead of overwriting the frame.

        if (count($bits) * 2 > count($positions)) {
            throw new \RuntimeException('The payload does not fit the tag.');
        }

        foreach ($positions as $index => [$r, $c]) {
            if ($index < count($bits)) {
                $cells[$r][$c] = $bits[$index];
            } elseif ($index < count($bits) * 2) {
                // Second copy: the reader prefers the cells that agree.
                $cells[$r][$c] = $bits[$index - count($bits)];
            }
        }

        return $cells;
    }

    /**
     * Read a matrix back into its token and MAC, or null when it is not a
     * NUTAG (frame missing, marker anywhere but the corner, wrong size).
     *
     * @param  array<int, array<int, bool>>  $cells
     * @return array{token: string, mac: string}|null
     */
    public static function read(array $cells): ?array
    {
        if (count($cells) !== self::GRID) {
            return null;
        }

        foreach ($cells as $row) {
            if (count($row) !== self::GRID) {
                return null;
            }
        }

        $oriented = self::orient($cells);

        if ($oriented === null) {
            return null;
        }

        $read = [];

        foreach (self::dataPositions() as [$r, $c]) {
            $read[] = (bool) $oriented[$r][$c];
        }

        return count($read) < self::PAYLOAD_BITS * 2 ? null : self::decodeCopy($read, 0);
    }

    /** True is a black cell. */
    public static function isTag(array $cells): bool
    {
        return self::read($cells) !== null;
    }

    /**
     * A PNG of the tag, written by hand: the deployment has no GD or Imagick,
     * and a 1-bit-per-cell bitmap needs neither.
     */
    public static function png(array $cells, int $modulePx = 8): string
    {
        $grid = self::GRID;
        $quiet = self::QUIET;
        $px = max(1, $modulePx);
        $side = ($grid + $quiet * 2) * $px;

        $raw = '';

        for ($y = 0; $y < $side; $y++) {
            $raw .= "\x00"; // filter: none

            $r = intdiv($y, $px) - $quiet;
            $line = '';

            for ($x = 0; $x < $side; $x++) {
                $c = intdiv($x, $px) - $quiet;
                $dark = $r >= 0 && $r < $grid && $c >= 0 && $c < $grid && $cells[$r][$c];
                $line .= $dark ? "\x00" : "\xFF";
            }

            $raw .= $line;
        }

        $ihdr = pack('NNCCCCC', $side, $side, 8, 0, 0, 0, 0); // 8-bit grayscale

        return "\x89PNG\r\n\x1a\n"
            .self::chunk('IHDR', $ihdr)
            .self::chunk('IDAT', gzcompress($raw, 9))
            .self::chunk('IEND', '');
    }

    /**
     * Which cells carry data, in the order the bits are written.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public static function dataPositions(): array
    {
        $grid = self::GRID;
        $positions = [];

        for ($r = 1; $r < $grid - 1; $r++) {
            for ($c = 1; $c < $grid - 1; $c++) {
                if (self::inMarkerZone($r, $c)) {
                    continue;
                }

                $positions[] = [$r, $c];
            }
        }

        return $positions;
    }

    /** Bytes to bits, most significant bit first. */
    public static function bits(string $bytes): array
    {
        $bits = [];

        foreach (str_split($bytes) as $byte) {
            $value = ord($byte);

            for ($shift = 7; $shift >= 0; $shift--) {
                $bits[] = (($value >> $shift) & 1) === 1;
            }
        }

        return $bits;
    }

    /** Bits back to bytes; a partial trailing byte is padded with zeros. */
    public static function bytes(array $bits): string
    {
        $bytes = '';
        $value = 0;
        $count = 0;

        foreach ($bits as $bit) {
            $value = ($value << 1) | ($bit ? 1 : 0);

            if (++$count === 8) {
                $bytes .= chr($value);
                $value = 0;
                $count = 0;
            }
        }

        if ($count > 0) {
            $bytes .= chr($value << (8 - $count));
        }

        return $bytes;
    }

    /**
     * Both copies of the payload, so a damaged first copy can fall back to the
     * second one - the MAC decides which is the real one.
     *
     * @param  array<int, bool>  $read
     * @return array{first: array{token: string, mac: string}, second: array{token: string, mac: string}}|null
     */
    public static function readCopies(array $cells): ?array
    {
        $cells = self::orient($cells);

        if ($cells === null) {
            return null;
        }

        $read = [];

        foreach (self::dataPositions() as [$r, $c]) {
            $read[] = (bool) $cells[$r][$c];
        }

        if (count($read) < self::PAYLOAD_BITS * 2) {
            return null;
        }

        return [
            'first' => self::decodeCopy($read, 0),
            'second' => self::decodeCopy($read, self::PAYLOAD_BITS),
        ];
    }

    /**
     * One copy of the payload.
     *
     * @param  array<int, bool>  $read
     * @return array{token: string, mac: string}
     */
    private static function decodeCopy(array $read, int $offset): array
    {
        $bits = array_slice($read, $offset, self::PAYLOAD_BITS);
        $bytes = self::bytes($bits);

        return [
            'token' => substr($bytes, 0, self::TOKEN_BYTES),
            'mac' => substr($bytes, self::TOKEN_BYTES, self::MAC_BYTES),
        ];
    }

    /**
     * Rotate the cells until the orientation marker sits in its corner, or
     * null when no rotation produces one.
     */
    private static function orient(array $cells): ?array
    {
        foreach ([0, 1, 2, 3] as $turns) {
            $oriented = self::rotate($cells, $turns);

            if (self::frameMatches($oriented) && self::orientationMatches($oriented)) {
                return $oriented;
            }
        }

        return null;
    }

    /** The outer ring must be dark on every side, or this is not a tag. */
    private static function frameMatches(array $cells): bool
    {
        $last = self::GRID - 1;

        for ($i = 0; $i < self::GRID; $i++) {
            if (! $cells[0][$i] || ! $cells[$last][$i] || ! $cells[$i][0] || ! $cells[$i][$last]) {
                return false;
            }
        }

        return true;
    }

    /** A 3x3 black core inside an otherwise white 5x5 zone. */
    private static function orientationMatches(array $cells): bool
    {
        $start = self::MARKER_ZONE_START;
        $end = $start + self::MARKER_ZONE_SIZE;

        for ($r = $start; $r < $end; $r++) {
            for ($c = $start; $c < $end; $c++) {
                $coreR = $r >= self::MARKER_CORE_START && $r < self::MARKER_CORE_START + self::MARKER_CORE_SIZE;
                $coreC = $c >= self::MARKER_CORE_START && $c < self::MARKER_CORE_START + self::MARKER_CORE_SIZE;
                $expected = $coreR && $coreC;

                if ((bool) $cells[$r][$c] !== $expected) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function inMarkerZone(int $r, int $c): bool
    {
        $start = self::MARKER_ZONE_START;
        $end = $start + self::MARKER_ZONE_SIZE;

        return $r >= $start && $r < $end && $c >= $start && $c < $end;
    }

    /** Rotate the cells 90 degrees clockwise, $turns times. */
    private static function rotate(array $cells, int $turns): array
    {
        $turns = (($turns % 4) + 4) % 4;

        for ($i = 0; $i < $turns; $i++) {
            $size = count($cells);
            $rotated = [];

            for ($r = 0; $r < $size; $r++) {
                for ($c = 0; $c < $size; $c++) {
                    $rotated[$c][$size - 1 - $r] = $cells[$r][$c];
                }
            }

            $cells = $rotated;
        }

        return $cells;
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
