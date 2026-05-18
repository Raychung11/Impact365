<?php
/**
 * IMPACT365 — Self-contained QR Code generator (SVG output)
 * -----------------------------------------------------------------------------
 * Pure PHP, zero dependencies, zero external API calls — suitable for offline
 * use and Hostinger shared hosting. Implements the ISO/IEC 18004 QR algorithm
 * for byte mode, error-correction level M, versions 1–6 (ample for ticket
 * codes and short check-in URLs). Output is crisp, print-friendly SVG.
 *
 * The attendance system treats the alphanumeric ticket code as authoritative;
 * this QR is the scan-convenience layer encoding that same code/URL.
 */

declare(strict_types=1);

final class QRCode
{
    /** Data codewords available at EC level M, versions 1–6. */
    private const M_DATA_CW   = [1 => 16, 2 => 28, 3 => 44, 4 => 64, 5 => 86, 6 => 108];
    /** EC codewords per block, versions 1–6 (level M). */
    private const M_ECC_CW    = [1 => 10, 2 => 16, 3 => 26, 4 => 18, 5 => 24, 6 => 16];
    /** Number of blocks, versions 1–6 (level M). */
    private const M_BLOCKS    = [1 => 1,  2 => 1,  3 => 1,  4 => 2,  5 => 2,  6 => 4];
    /** Remainder bits appended after codewords. */
    private const REMAINDER   = [1 => 0,  2 => 7,  3 => 7,  4 => 7,  5 => 7,  6 => 7];
    /** Alignment-pattern centre coordinate (single extra pattern, v2–6). */
    private const ALIGN_POS   = [2 => 18, 3 => 22, 4 => 26, 5 => 30, 6 => 34];

    private static array $expTab = [];
    private static array $logTab = [];

    /* --------------------------------------------------------------------- */
    /* Public API                                                            */
    /* --------------------------------------------------------------------- */
    public static function svg(string $text, int $scale = 6, int $margin = 4): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $dim = ($n + 2 * $margin) * $scale;

        $rects = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($m[$r][$c]) {
                    $x = ($c + $margin) * $scale;
                    $y = ($r + $margin) * $scale;
                    $rects .= "<rect x=\"$x\" y=\"$y\" width=\"$scale\" height=\"$scale\"/>";
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim
            . '" viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="' . $dim . '" height="' . $dim . '" fill="#ffffff"/>'
            . '<g fill="#0f1f17">' . $rects . '</g></svg>';
    }

    public static function dataUri(string $text, int $scale = 6, int $margin = 4): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $scale, $margin));
    }

    /* --------------------------------------------------------------------- */
    /* Galois field GF(256)                                                  */
    /* --------------------------------------------------------------------- */
    private static function initGF(): void
    {
        if (self::$expTab) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$expTab[$i] = $x;
            self::$logTab[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$expTab[$i] = self::$expTab[$i - 255];
        }
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$expTab[(self::$logTab[$a] + self::$logTab[$b]) % 255];
    }

    /** Reed–Solomon ECC codewords for a data block. */
    private static function ecc(array $data, int $ecLen): array
    {
        self::initGF();
        // Generator polynomial.
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j]     ^= self::gfMul($coef, self::$expTab[$i]);
                $next[$j + 1] ^= $coef;
            }
            $gen = $next;
        }
        $rem = array_merge($data, array_fill(0, $ecLen, 0));
        for ($i = 0, $dn = count($data); $i < $dn; $i++) {
            $factor = $rem[$i];
            if ($factor === 0) {
                continue;
            }
            for ($j = 0, $gl = count($gen); $j < $gl; $j++) {
                $rem[$i + $j] ^= self::gfMul($gen[$j], $factor);
            }
        }
        return array_slice($rem, count($data), $ecLen);
    }

    /* --------------------------------------------------------------------- */
    /* Encoding                                                              */
    /* --------------------------------------------------------------------- */
    private static function chooseVersion(int $byteLen): int
    {
        for ($v = 1; $v <= 6; $v++) {
            // 4 mode bits + 8 count bits + 8*len bits, rounded to codewords.
            $needBits = 4 + 8 + 8 * $byteLen;
            if ((int) ceil($needBits / 8) <= self::M_DATA_CW[$v]) {
                return $v;
            }
        }
        throw new RuntimeException('QR payload too long for supported versions (max ~106 bytes).');
    }

    private static function matrix(string $text): array
    {
        self::initGF();
        $bytes = array_values(unpack('C*', $text) ?: []);
        $len   = count($bytes);
        $ver   = self::chooseVersion($len);
        $totalDataCw = self::M_DATA_CW[$ver];

        /* --- Bit buffer --------------------------------------------------- */
        $bits = [];
        $put  = static function (int $val, int $n) use (&$bits): void {
            for ($i = $n - 1; $i >= 0; $i--) {
                $bits[] = ($val >> $i) & 1;
            }
        };
        $put(0b0100, 4);           // byte mode
        $put($len, 8);             // char count (v1–9 byte mode = 8 bits)
        foreach ($bytes as $b) {
            $put($b, 8);
        }
        // Terminator.
        $cap = $totalDataCw * 8;
        for ($i = 0, $t = min(4, $cap - count($bits)); $i < $t; $i++) {
            $bits[] = 0;
        }
        // Pad to byte boundary.
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        // Pad codewords.
        $dataCw = [];
        for ($i = 0, $c = count($bits); $i < $c; $i += 8) {
            $byte = 0;
            for ($k = 0; $k < 8; $k++) {
                $byte = ($byte << 1) | $bits[$i + $k];
            }
            $dataCw[] = $byte;
        }
        $padToggle = true;
        while (count($dataCw) < $totalDataCw) {
            $dataCw[] = $padToggle ? 0xEC : 0x11;
            $padToggle = !$padToggle;
        }

        /* --- Split into blocks, compute ECC, interleave ------------------- */
        $numBlocks = self::M_BLOCKS[$ver];
        $ecLen     = self::M_ECC_CW[$ver];
        $perBlock  = intdiv($totalDataCw, $numBlocks);

        $dBlocks = [];
        $eBlocks = [];
        for ($b = 0; $b < $numBlocks; $b++) {
            $slice = array_slice($dataCw, $b * $perBlock, $perBlock);
            $dBlocks[] = $slice;
            $eBlocks[] = self::ecc($slice, $ecLen);
        }
        $final = [];
        for ($i = 0; $i < $perBlock; $i++) {
            foreach ($dBlocks as $blk) {
                $final[] = $blk[$i];
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($eBlocks as $blk) {
                $final[] = $blk[$i];
            }
        }

        // Codewords → bitstream (+ remainder bits).
        $stream = [];
        foreach ($final as $cw) {
            for ($i = 7; $i >= 0; $i--) {
                $stream[] = ($cw >> $i) & 1;
            }
        }
        for ($i = 0, $r = self::REMAINDER[$ver]; $i < $r; $i++) {
            $stream[] = 0;
        }

        /* --- Build module matrix ----------------------------------------- */
        $size = 17 + 4 * $ver;
        $mod  = array_fill(0, $size, array_fill(0, $size, 0));   // module value
        $fn   = array_fill(0, $size, array_fill(0, $size, false)); // function area?

        $setFinder = static function (int $top, int $left) use (&$mod, &$fn, $size): void {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $rr = $top + $r;
                    $cc = $left + $c;
                    if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                        continue;
                    }
                    $fn[$rr][$cc] = true;
                    $inRing = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                        || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6));
                    $inCore = ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                    $mod[$rr][$cc] = ($inRing || $inCore) ? 1 : 0;
                }
            }
        };
        $setFinder(0, 0);
        $setFinder(0, $size - 7);
        $setFinder($size - 7, 0);

        // Timing patterns.
        for ($i = 8; $i < $size - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            $mod[6][$i] = $v; $fn[6][$i] = true;
            $mod[$i][6] = $v; $fn[$i][6] = true;
        }

        // Alignment pattern (single, versions 2–6).
        if ($ver >= 2) {
            $p = self::ALIGN_POS[$ver];
            for ($r = -2; $r <= 2; $r++) {
                for ($c = -2; $c <= 2; $c++) {
                    $rr = $p + $r;
                    $cc = $p + $c;
                    $fn[$rr][$cc] = true;
                    $ring = (abs($r) === 2 || abs($c) === 2);
                    $mod[$rr][$cc] = ($ring || ($r === 0 && $c === 0)) ? 1 : 0;
                }
            }
        }

        // Dark module + reserve format areas.
        $mod[$size - 8][8] = 1;
        $fn[$size - 8][8]  = true;
        for ($i = 0; $i < 9; $i++) {
            if (!$fn[8][$i])             { $fn[8][$i] = true; }
            if (!$fn[$i][8])             { $fn[$i][8] = true; }
        }
        for ($i = 0; $i < 8; $i++) {
            $fn[8][$size - 1 - $i] = true;
            $fn[$size - 1 - $i][8] = true;
        }

        /* --- Place data bits (zigzag) ------------------------------------ */
        $bit = 0;
        $total = count($stream);
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col = 5; // skip vertical timing column
            }
            for ($i = 0; $i < $size; $i++) {
                $up  = ((($size - 1 - $col) >> 1) & 1) === 0;
                $row = $up ? ($size - 1 - $i) : $i;
                for ($c = 0; $c < 2; $c++) {
                    $cc = $col - $c;
                    if ($fn[$row][$cc]) {
                        continue;
                    }
                    $mod[$row][$cc] = $bit < $total ? $stream[$bit] : 0;
                    $bit++;
                }
            }
        }

        /* --- Mask selection ---------------------------------------------- */
        $best = null;
        $bestPenalty = PHP_INT_MAX;
        $bestMask = 0;
        for ($mask = 0; $mask < 8; $mask++) {
            $cand = $mod;
            for ($r = 0; $r < $size; $r++) {
                for ($c = 0; $c < $size; $c++) {
                    if ($fn[$r][$c]) {
                        continue;
                    }
                    if (self::maskBit($mask, $r, $c)) {
                        $cand[$r][$c] ^= 1;
                    }
                }
            }
            self::applyFormat($cand, $size, $mask);
            $pen = self::penalty($cand, $size);
            if ($pen < $bestPenalty) {
                $bestPenalty = $pen;
                $best = $cand;
                $bestMask = $mask;
            }
        }

        return $best;
    }

    private static function maskBit(int $mask, int $r, int $c): bool
    {
        return match ($mask) {
            0 => ($r + $c) % 2 === 0,
            1 => $r % 2 === 0,
            2 => $c % 3 === 0,
            3 => ($r + $c) % 3 === 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
            5 => (($r * $c) % 2) + (($r * $c) % 3) === 0,
            6 => ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0,
            7 => ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0,
            default => false,
        };
    }

    /** Write the 15-bit BCH format information (EC level M = 0b00). */
    private static function applyFormat(array &$m, int $size, int $mask): void
    {
        $data = (0b00 << 3) | $mask;       // level M
        $rem  = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) ? 0b10100110111 : 0);
        }
        $bits = (($data << 10) | $rem) ^ 0b101010000010010;

        for ($i = 0; $i < 15; $i++) {
            $b = ($bits >> $i) & 1;
            // Around top-left finder.
            if ($i < 6) {
                $m[8][$i] = $b;
            } elseif ($i === 6) {
                $m[8][7] = $b;
            } elseif ($i === 7) {
                $m[8][8] = $b;
            } elseif ($i === 8) {
                $m[7][8] = $b;
            } else {
                $m[14 - $i][8] = $b;
            }
            // Mirrored copy near the other two finders.
            if ($i < 8) {
                $m[$size - 1 - $i][8] = $b;
            } else {
                $m[8][$size - 15 + $i] = $b;
            }
        }
        $m[$size - 8][8] = 1; // dark module
    }

    /* --------------------------------------------------------------------- */
    /* Penalty scoring (ISO/IEC 18004 §8.8.2)                                */
    /* --------------------------------------------------------------------- */
    private static function penalty(array $m, int $n): int
    {
        $score = 0;

        // Rule 1: runs of 5+ same-colour modules in rows and columns.
        for ($r = 0; $r < $n; $r++) {
            $runC = 1;
            for ($c = 1; $c < $n; $c++) {
                if ($m[$r][$c] === $m[$r][$c - 1]) {
                    $runC++;
                } else {
                    if ($runC >= 5) { $score += 3 + ($runC - 5); }
                    $runC = 1;
                }
            }
            if ($runC >= 5) { $score += 3 + ($runC - 5); }
        }
        for ($c = 0; $c < $n; $c++) {
            $runR = 1;
            for ($r = 1; $r < $n; $r++) {
                if ($m[$r][$c] === $m[$r - 1][$c]) {
                    $runR++;
                } else {
                    if ($runR >= 5) { $score += 3 + ($runR - 5); }
                    $runR = 1;
                }
            }
            if ($runR >= 5) { $score += 3 + ($runR - 5); }
        }

        // Rule 2: 2x2 blocks of one colour.
        for ($r = 0; $r < $n - 1; $r++) {
            for ($c = 0; $c < $n - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        // Rule 3: finder-like 1:1:3:1:1 patterns.
        $pat1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $pat2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n - 10; $c++) {
                $h = []; $v = [];
                for ($k = 0; $k < 11; $k++) {
                    $h[] = $m[$r][$c + $k];
                    $v[] = $m[$c + $k][$r];
                }
                if ($h === $pat1 || $h === $pat2) { $score += 40; }
                if ($v === $pat1 || $v === $pat2) { $score += 40; }
            }
        }

        // Rule 4: dark/light balance.
        $dark = 0;
        for ($r = 0; $r < $n; $r++) {
            $dark += array_sum($m[$r]);
        }
        $pct = $dark * 100 / ($n * $n);
        $score += (int) (abs($pct - 50) / 5) * 10;

        return $score;
    }
}
