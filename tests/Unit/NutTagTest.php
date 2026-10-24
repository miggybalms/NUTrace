<?php

namespace Tests\Unit;

use App\Support\NutTag;
use PHPUnit\Framework\TestCase;

/**
 * The NUTAG-1 prototype: the encoder, the reader and the hand-written PNG.
 */
class NutTagTest extends TestCase
{
    private const KEY = 'prototype-mac-key';

    private function tag(): array
    {
        $token = 'prototype-token-'; // exactly 16 bytes
        $token = substr(str_pad($token, NutTag::TOKEN_BYTES, 'x'), 0, NutTag::TOKEN_BYTES);

        return [$token, NutTag::mac($token, self::KEY)];
    }

    public function test_a_tag_round_trips_its_token_and_mac(): void
    {
        [$token, $mac] = $this->tag();

        $read = NutTag::read(NutTag::matrix($token, $mac));

        $this->assertNotNull($read);
        $this->assertSame($token, $read['token']);
        $this->assertSame($mac, $read['mac']);
    }

    public function test_both_copies_carry_the_same_payload(): void
    {
        [$token, $mac] = $this->tag();

        $copies = NutTag::readCopies(NutTag::matrix($token, $mac));

        $this->assertNotNull($copies);
        $this->assertSame($token, $copies['first']['token']);
        $this->assertSame($token, $copies['second']['token']);
        $this->assertSame($mac, $copies['first']['mac']);
    }

    public function test_the_frame_and_marker_are_where_the_reader_expects_them(): void
    {
        [$token, $mac] = $this->tag();
        $cells = NutTag::matrix($token, $mac);

        $last = NutTag::GRID - 1;

        for ($i = 0; $i < NutTag::GRID; $i++) {
            $this->assertTrue($cells[0][$i], 'top edge of the frame');
            $this->assertTrue($cells[$last][$i], 'bottom edge of the frame');
            $this->assertTrue($cells[$i][0], 'left edge of the frame');
            $this->assertTrue($cells[$i][$last], 'right edge of the frame');
        }

        // 3x3 black core inside a white ring.
        for ($r = 1; $r <= 5; $r++) {
            for ($c = 1; $c <= 5; $c++) {
                $core = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
                $this->assertSame($core, $cells[$r][$c], "marker cell $r,$c");
            }
        }
    }

    public function test_a_rotated_tag_still_reads(): void
    {
        [$token, $mac] = $this->tag();
        $cells = NutTag::matrix($token, $mac);

        foreach ([1, 2, 3] as $turns) {
            $rotated = $cells;

            for ($t = 0; $t < $turns; $t++) {
                $size = count($rotated);
                $next = [];

                for ($r = 0; $r < $size; $r++) {
                    for ($c = 0; $c < $size; $c++) {
                        $next[$c][$size - 1 - $r] = $rotated[$r][$c];
                    }
                }

                $rotated = $next;
            }

            $read = NutTag::read($rotated);

            $this->assertNotNull($read, "a tag rotated {$turns} quarter turns");
            $this->assertSame($token, $read['token']);
        }
    }

    public function test_a_damaged_frame_is_not_a_tag(): void
    {
        [$token, $mac] = $this->tag();
        $cells = NutTag::matrix($token, $mac);

        $cells[0][5] = false;

        $this->assertNull(NutTag::read($cells));
        $this->assertFalse(NutTag::isTag($cells));
    }

    public function test_a_matrix_of_the_wrong_size_is_not_a_tag(): void
    {
        $this->assertNull(NutTag::read(array_fill(0, 10, array_fill(0, 10, true))));
    }

    public function test_the_png_is_valid_and_draws_the_matrix(): void
    {
        [$token, $mac] = $this->tag();
        $cells = NutTag::matrix($token, $mac);

        $png = NutTag::png($cells, 8);

        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));

        $offset = 8;
        $chunks = [];

        while ($offset < strlen($png)) {
            $length = unpack('N', substr($png, $offset, 4))[1];
            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $length);
            $crc = unpack('N', substr($png, $offset + 8 + $length, 4))[1];

            $this->assertSame(crc32($type.$data), $crc, "CRC of the {$type} chunk");

            $chunks[$type] = $data;
            $offset += 12 + $length;
        }

        $this->assertSame(['IHDR', 'IDAT', 'IEND'], array_keys($chunks));

        $header = unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace', $chunks['IHDR']);
        $side = (NutTag::GRID + NutTag::QUIET * 2) * 8;

        $this->assertSame($side, $header['width']);
        $this->assertSame($side, $header['height']);
        $this->assertSame(8, $header['depth']);
        $this->assertSame(0, $header['color']);

        $raw = gzuncompress($chunks['IDAT']);
        $stride = $side + 1; // one filter byte per row

        $this->assertSame($stride * $side, strlen($raw));
        $this->assertSame(0, ord($raw[0]), 'the first row uses filter 0');

        // The top-left frame cell is black, the quiet zone around it white.
        $pixel = fn (int $x, int $y) => ord($raw[$y * $stride + 1 + $x]);
        $quiet = NutTag::QUIET * 8;

        $this->assertSame(0, $pixel($quiet + 2, $quiet + 2), 'frame cell is black');
        $this->assertSame(255, $pixel(1, 1), 'quiet zone is white');
    }

    public function test_a_mac_is_eight_bytes_and_depends_on_the_key(): void
    {
        $token = random_bytes(NutTag::TOKEN_BYTES);

        $mac = NutTag::mac($token, self::KEY);

        $this->assertSame(NutTag::MAC_BYTES, strlen($mac));
        $this->assertNotSame($mac, NutTag::mac($token, 'another-key'));
        $this->assertSame($mac, NutTag::mac($token, self::KEY));
    }

    public function test_a_token_of_the_wrong_length_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NutTag::mac('too-short', self::KEY);
    }
}
