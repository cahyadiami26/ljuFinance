<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Penulis PDF minimal tanpa dependensi (tanpa domPDF/composer).
 *
 * Mendukung teks Helvetica/Helvetica-Bold, garis, blok warna, tabel
 * multi-kolom dengan perataan angka, pemotongan halaman otomatis, header
 * perusahaan, dan footer bernomor halaman.
 *
 * Catatan: hanya karakter WinAnsi/Latin-1 yang didukung. Karakter di luar
 * itu dinormalisasi (mis. – menjadi -, € menjadi EUR) agar PDF tetap valid.
 */
final class Pdf
{
    private const PAGE_SIZES = [
        'A4'     => [595.28, 841.89],
        'LETTER' => [612.00, 792.00],
    ];

    private float $width;
    private float $height;

    /** @var array<int,string> */
    private array $pages = [];

    private string $current = '';
    private float $y;
    private float $bottom;

    private float $marginX = 42.0;
    private float $marginTop = 42.0;
    private float $marginBottom = 46.0;

    private string $company = '';
    private string $companyMeta = '';
    private string $docTitle = '';
    private string $docSubtitle = '';
    private bool $headerDrawn = false;

    /** @var array<string,string> */
    private array $meta = [];

    /** @var array<int,string> */
    private array $footnotes = [];

    /**
     * @param array{paper?:string,orientation?:string} $options
     */
    public function __construct(array $options = [])
    {
        $paper = strtoupper((string) ($options['paper'] ?? 'A4'));
        [$w, $h] = self::PAGE_SIZES[$paper] ?? self::PAGE_SIZES['A4'];

        if (strtolower((string) ($options['orientation'] ?? 'portrait')) === 'landscape') {
            [$w, $h] = [$h, $w];
        }

        $this->width = $w;
        $this->height = $h;
        $this->y = $h - $this->marginTop;
        $this->bottom = $this->marginBottom;
        $this->pages[] = '';
    }

    public function company(string $name, string $meta = ''): self
    {
        $this->company = $name;
        $this->companyMeta = $meta;
        return $this;
    }

    public function document(string $title, string $subtitle = ''): self
    {
        $this->docTitle = $title;
        $this->docSubtitle = $subtitle;
        return $this;
    }

    /** @param array<string,string> $meta */
    public function meta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function footnote(string $note): self
    {
        $this->footnotes[] = $note;
        return $this;
    }

    private function contentWidth(): float
    {
        return $this->width - ($this->marginX * 2);
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y - $height < $this->bottom) {
            $this->pages[] = '';
            $this->current = '';
            $this->y = $this->height - $this->marginTop;
            $this->headerDrawn = false;
            $this->drawPageHeader(true);
        }
    }

    private function drawPageHeader(bool $lanjutan = false): void
    {
        if ($this->headerDrawn) {
            return;
        }
        $this->headerDrawn = true;

        $top = $this->height - $this->marginTop;

        if ($lanjutan) {
            $this->text($this->marginX, $top, 8, 'F2', [0.32, 0.38, 0.47], $this->docTitle . ' (lanjutan)');
            $this->line($this->marginX, $top - 8, $this->width - $this->marginX, $top - 8, [0.85, 0.89, 0.94]);
            $this->y = $top - 20;
            return;
        }

        if ($this->company !== '') {
            $this->text($this->marginX, $top, 11, 'F2', [0.08, 0.11, 0.17], $this->company);
            if ($this->companyMeta !== '') {
                $logoWidth = 0.0;
                $this->textRight($this->width - $this->marginX, $top, 7, 'F1', [0.39, 0.45, 0.55], $this->companyMeta);
            }
        }

        $titleY = $top - 22;
        if ($this->docTitle !== '') {
            $this->text($this->marginX, $titleY, 14, 'F2', [0.06, 0.09, 0.16], $this->docTitle);
        }
        if ($this->docSubtitle !== '') {
            $this->text($this->marginX, $titleY - 14, 8.5, 'F1', [0.42, 0.47, 0.55], $this->docSubtitle);
        }

        $y = $titleY - 26;
        if ($this->meta !== []) {
            $x = $this->marginX;
            $col = $this->contentWidth() / max(1, count($this->meta));
            foreach ($this->meta as $label => $value) {
                $this->text($x, $y, 6.6, 'F1', [0.52, 0.57, 0.64], strtoupper((string) $label));
                $this->text($x, $y - 9, 8, 'F2', [0.16, 0.20, 0.27], (string) $value);
                $x += $col;
            }
            $y -= 20;
        }

        $this->line($this->marginX, $y, $this->width - $this->marginX, $y, [0.80, 0.84, 0.90]);
        $this->y = $y - 14;
    }

    private function encode(string $text): string
    {
        $map = [
            "\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-', "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'",
            "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"', "\xE2\x80\xA6" => '...', "\xC2\xA0" => ' ',
            "\xE2\x82\xAC" => 'EUR', "\xC2\xB7" => '·', "\xE2\x80\xA2" => '-',
            "\xCE\x94" => 'Delta', "\xC3\x97" => 'x', "\xC3\xB7" => '/', "\xE2\x89\xA4" => '<=',
            "\xE2\x89\xA5" => '>=', "\xE2\x86\x92" => '->',
        ];
        $text = strtr($text, $map);

        $text = $this->toWinAnsi($text);

        // Buang sisa byte yang tidak valid agar stream tetap dapat dibaca.
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\xFF]/', '', $text) ?? '';
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        return $text;
    }

    /** Konversi UTF-8 ke Windows-1252 tanpa extension iconv. */
    private function toWinAnsi(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        $out = '';
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($text[$i]);
            if ($byte < 0x80) {
                $out .= $text[$i];
                continue;
            }
            if ($i + 1 >= $len) {
                break;
            }
            $next = ord($text[$i + 1]);
            if ($byte === 0xC2 && $next >= 0xA0 && $next <= 0xBF) {
                $out .= chr($next);
                $i++;
                continue;
            }
            if ($byte === 0xC3 && $next >= 0x80 && $next <= 0xBF) {
                $out .= chr($next + 0x40);
                $i++;
                continue;
            }
            // Lewati karakter multi-byte lain (emoji, simbol) yang tidak ada di WinAnsi.
            while ($i + 1 < $len && (ord($text[$i + 1]) & 0xC0) === 0x80) {
                $i++;
            }
        }

        return $out;
    }

    private function write(string $ops): void
    {
        $this->pages[count($this->pages) - 1] .= $ops . "\n";
    }

    /** @param array{0:float,1:float,2:float} $color */
    private function color(array $color, bool $stroke = false): string
    {
        return sprintf('%.3F %.3F %.3F %s' . "\n", $color[0], $color[1], $color[2], $stroke ? 'RG' : 'rg');
    }

    public function text(float $x, float $y, float $size, string $font, array $color, string $text): void
    {
        if ($text === '') {
            return;
        }
        $this->write(
            "BT\n" . $this->color($color) . sprintf('/%s %.2F Tf\n', $font, $size)
            . sprintf('%.2F %.2F Td\n', $x, $y) . '(' . $this->encode($text) . ") Tj\nET"
        );
    }

    public function textRight(float $right, float $y, float $size, string $font, array $color, string $text): void
    {
        $this->text($right - $this->textWidth($text, $size, $font), $y, $size, $font, $color, $text);
    }

    public function textCenter(float $center, float $y, float $size, string $font, array $color, string $text): void
    {
        $this->text($center - $this->textWidth($text, $size, $font) / 2, $y, $size, $font, $color, $text);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $color = [0.85, 0.88, 0.92], float $lineWidth = 0.6): void
    {
        $this->write(
            $this->color($color, true) . sprintf('%.2F w' . "\n", $lineWidth)
            . sprintf('%.2F %.2F m %.2F %.2F l S' . "\n", $x1, $y1, $x2, $y2)
        );
    }

    public function rect(float $x, float $y, float $w, float $h, array $color): void
    {
        $this->write($this->color($color) . sprintf('%.2F %.2F %.2F %.2F re f' . "\n", $x, $y, $w, $h));
    }

    private function textWidth(string $text, float $size, string $font = 'F1'): float
    {
        return strlen($text) * $size * ($font === 'F2' ? 0.535 : 0.502);
    }

    /** @return array<int,string> */
    private function wrap(string $text, float $width, float $size, string $font = 'F1'): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->textWidth($candidate, $size, $font) <= $width || $line === '') {
                $line = $candidate;
                continue;
            }
            $lines[] = $line;
            $line = $word;
        }
        $lines[] = $line;

        return $lines;
    }

    public function currentY(): float
    {
        return $this->y;
    }

    public function contentWidthSafe(): float
    {
        return $this->contentWidth();
    }

    /** Pastikan tersedia ruang; mulai halaman baru bila perlu. */
    public function reserve(float $height): void
    {
        $this->ensureSpace($height);
    }

    public function heading(string $text, ?string $note = null): void
    {
        $this->drawPageHeader();
        $this->ensureSpace(26);
        $this->text($this->marginX, $this->y, 9.5, 'F2', [0.12, 0.16, 0.23], $text);
        if ($note !== null && $note !== '') {
            $this->textRight($this->width - $this->marginX, $this->y, 7.5, 'F1', [0.45, 0.50, 0.58], $note);
        }
        $this->y -= 12;
    }

    public function paragraph(string $text, bool $bold = false, float $size = 8.5, ?array $color = null): void
    {
        $color ??= [0.22, 0.27, 0.35];
        $lineHeight = $size * 1.45;
        foreach ($this->wrap($text, $this->contentWidth(), $size, $bold ? 'F2' : 'F1') as $line) {
            $this->ensureSpace($lineHeight);
            $this->text($this->marginX, $this->y, $size, $bold ? 'F2' : 'F1', $color, $line);
            $this->y -= $lineHeight;
        }
    }

    public function spacer(float $height = 8): void
    {
        $this->y -= $height;
    }

    /** Format angka ala laporan keuangan: negatif dalam tanda kurung. */
    public static function money(mixed $value): string
    {
        $number = (float) $value;
        if (abs($number) < 0.005) {
            return '-';
        }
        $body = number_format(abs($number), 0, ',', '.');
        return $number < 0 ? '(' . $body . ')' : $body;
    }

    /**
     * Tabel laporan.
     *
     * @param array<int,array{label:string,align?:string,lebar?:float}> $columns
     * @param array<int,array<string,mixed>> $rows
     *   setiap baris: tipe (section|data|subtotal|total|catatan), label, kode, nilai (array), level
     */
    public function table(array $columns, array $rows): void
    {
        $this->drawPageHeader();
        $this->ensureSpace(40);

        $colCount = max(1, count($columns));
        $totalWeight = 0.0;
        foreach ($columns as $col) {
            $totalWeight += (float) ($col['lebar'] ?? (100 / $colCount));
        }
        $totalWeight = $totalWeight > 0 ? $totalWeight : $colCount;

        $widths = [];
        foreach ($columns as $col) {
            $widths[] = $this->contentWidth() * ((float) ($col['lebar'] ?? (100 / $colCount)) / $totalWeight);
        }

        // Header tabel.
        $headerSize = 7.2;
        $headerHeight = 18;
        $this->rect($this->marginX, $this->y - $headerHeight + 5, $this->contentWidth(), $headerHeight, [0.945, 0.960, 0.980]);
        $x = $this->marginX + 5;
        foreach ($columns as $i => $col) {
            $align = (string) ($col['align'] ?? 'left');
            $label = (string) $col['label'];
            if ($align === 'right') {
                $this->textRight($x + $widths[$i] - 10, $this->y - 7, $headerSize, 'F2', [0.28, 0.33, 0.41], $label);
            } elseif ($align === 'center') {
                $this->textCenter($x + $widths[$i] / 2, $this->y - 7, $headerSize, 'F2', [0.28, 0.33, 0.41], $label);
            } else {
                $this->text($x, $this->y - 7, $headerSize, 'F2', [0.28, 0.33, 0.41], $label);
            }
            $x += $widths[$i];
        }
        $this->y -= $headerHeight;
        $this->line($this->marginX, $this->y + 5, $this->width - $this->marginX, $this->y + 5, [0.75, 0.80, 0.87], 1.0);

        foreach ($rows as $row) {
            $tipe = (string) ($row['tipe'] ?? 'data');
            if ($tipe === 'spacer') {
                $this->y -= 6;
                continue;
            }

            $size = match ($tipe) {
                'section' => 8.4,
                'total'   => 9.0,
                'subtotal' => 8.6,
                default   => 8.2,
            };
            $font = in_array($tipe, ['section', 'total', 'subtotal'], true) ? 'F2' : 'F1';
            $level = (int) ($row['level'] ?? 0);
            $indent = max(0, $level) * 9;
            $labelText = trim(((string) ($row['kode'] ?? '') !== '' ? (string) $row['kode'] . '  ' : '') . (string) ($row['label'] ?? ''));

            $labelWidth = $widths[0] - 12 - $indent;
            $labelLines = $this->wrap($labelText, max(30.0, $labelWidth), $size, $font);

            $valueLines = 1;
            foreach (array_values((array) ($row['nilai'] ?? [])) as $i => $value) {
                if (!isset($widths[$i + 1])) {
                    break;
                }
                $lines = $this->wrap((string) $value, max(24.0, $widths[$i + 1] - 12), $size, $font);
                $valueLines = max($valueLines, count($lines));
            }

            $lineHeight = $size * 1.35;
            $rowHeight = max(count($labelLines), $valueLines) * $lineHeight + 6;

            if ($this->y - $rowHeight < $this->bottom) {
                $this->pages[] = '';
                $this->current = '';
                $this->y = $this->height - $this->marginTop;
                $this->headerDrawn = false;
                $this->drawPageHeader(true);
                $this->ensureSpace($rowHeight);
            }

            if ($tipe === 'total') {
                $this->line($this->marginX, $this->y + 3, $this->width - $this->marginX, $this->y + 3, [0.35, 0.42, 0.52], 0.8);
                $this->rect($this->marginX, $this->y - $rowHeight + 7, $this->contentWidth(), $rowHeight - 4, [0.965, 0.975, 0.990]);
            } elseif ($tipe === 'section') {
                $this->rect($this->marginX, $this->y - $rowHeight + 7, $this->contentWidth(), $rowHeight - 4, [0.980, 0.985, 0.995]);
            }

            $textColor = $tipe === 'section' ? [0.10, 0.15, 0.24] : [0.16, 0.20, 0.27];
            $lineY = $this->y - 6;
            foreach ($labelLines as $line) {
                $this->text($this->marginX + 6 + $indent, $lineY, $size, $font, $textColor, $line);
                $lineY -= $lineHeight;
            }

            $x = $this->marginX + $widths[0];
            $values = array_values((array) ($row['nilai'] ?? []));
            foreach ($values as $i => $value) {
                if (!isset($widths[$i + 1])) {
                    break;
                }
                $col = $columns[$i + 1] ?? ['align' => 'right'];
                $align = (string) ($col['align'] ?? 'right');
                $cellLines = $this->wrap((string) $value, $widths[$i + 1] - 12, $size, $font);
                $cellY = $this->y - 6;
                foreach ($cellLines as $line) {
                    if ($align === 'right') {
                        $this->textRight($x + $widths[$i + 1] - 8, $cellY, $size, $font, $textColor, $line);
                    } elseif ($align === 'center') {
                        $this->textCenter($x + $widths[$i + 1] / 2, $cellY, $size, $font, $textColor, $line);
                    } else {
                        $this->text($x, $cellY, $size, $font, $textColor, $line);
                    }
                    $cellY -= $lineHeight;
                }
                $x += $widths[$i + 1];
            }

            $this->y -= $rowHeight;

            if (!in_array($tipe, ['total', 'section'], true)) {
                $this->line($this->marginX, $this->y + 3, $this->width - $this->marginX, $this->y + 3, [0.93, 0.94, 0.96], 0.4);
            }
        }

        $this->y -= 6;
    }

    /** Output dokumen PDF lengkap. */
    public function output(): string
    {
        $pageCount = count($this->pages);
        $printedAt = date('d M Y H:i') . ' WIB';

        foreach ($this->pages as $index => $content) {
            $footer = '';

            // Footer: catatan + nomor halaman.
            $footerY = 30.0;
            $note = $this->footnotes === [] ? '' : implode(' · ', array_slice($this->footnotes, 0, 2));
            if ($note !== '') {
                $footer .= "BT\n" . $this->color([0.48, 0.53, 0.60]) . "/F1 6.5 Tf\n"
                    . sprintf('%.2F %.2F Td\n', $this->marginX, $footerY + 10) . '(' . $this->encode($note) . ") Tj\nET\n";
            }
            $footer .= "BT\n" . $this->color([0.48, 0.53, 0.60]) . "/F1 6.5 Tf\n"
                . sprintf('%.2F %.2F Td\n', $this->marginX, $footerY) . '(' . $this->encode('Dicetak ' . $printedAt) . ") Tj\nET\n";
            $pageLabel = 'Halaman ' . ($index + 1) . ' / ' . $pageCount;
            $footer .= "BT\n" . $this->color([0.48, 0.53, 0.60]) . "/F1 6.5 Tf\n"
                . sprintf('%.2F %.2F Td\n', $this->width - $this->marginX - $this->textWidth($pageLabel, 6.5, 'F1'), $footerY)
                . '(' . $this->encode($pageLabel) . ") Tj\nET\n";
            $footer .= $this->color([0.85, 0.88, 0.92], true) . sprintf('0.5 w' . "\n")
                . sprintf('%.2F %.2F m %.2F %.2F l S' . "\n", $this->marginX, $footerY + 14, $this->width - $this->marginX, $footerY + 14);

            $this->pages[$index] = $footer . $content;
        }

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $kids = [];
        $nextId = 5;
        foreach ($this->pages as $content) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $kids[] = $pageId . ' 0 R';

            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R '
                . sprintf('/MediaBox [0 0 %.2F %.2F] ', $this->width, $this->height)
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
                . '/Contents ' . $contentId . ' 0 R >>';

            $stream = $content;
            $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->pages) . ' >>';

        $infoId = $nextId;
        $objects[$infoId] = '<< /Title (' . $this->encode($this->docTitle !== '' ? $this->docTitle : 'Laporan Keuangan') . ') '
            . '/Author (' . $this->encode($this->company !== '' ? $this->company : 'LJU Finance') . ') '
            . '/Producer (LJU Finance) /CreationDate (D:' . date('YmdHis') . ') >>';

        ksort($objects);
        $maxId = $infoId;

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $offset = $offsets[$id] ?? 0;
            $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        }
        $pdf .= 'trailer' . "\n<< /Size " . ($maxId + 1) . " /Root 1 0 R /Info " . $infoId . " 0 R >>\n";
        $pdf .= 'startxref' . "\n" . $xrefPos . "\n%%EOF";

        return $pdf;
    }
}
