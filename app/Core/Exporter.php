<?php

declare(strict_types=1);

namespace Lju\Core;

use Lju\Services\ReportService;

/**
 * Ekspor laporan ke CSV, XLSX (Office Open XML), dan SpreadsheetML.
 *
 * XLSX dibuat tanpa pustaka eksternal: bila ekstensi ZipArchive tersedia,
 * file .xlsx asli dibuat; jika tidak, dipakai SpreadsheetML (.xls) yang tetap
 * dapat dibuka Excel/LibreOffice.
 */
final class Exporter
{
    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Nilai satu sel berdasarkan tipe kolom & data baris. */
    private static function sel(array $laporan, array $row, int $index): array
    {
        $kolom = $laporan['kolom'][$index] ?? ['label' => '', 'align' => 'left'];
        $tipe = (string) ($row['tipe'] ?? 'data');
        $label = (string) ($row['label'] ?? '');

        if ($index === 0) {
            if ($tipe === 'section') {
                return ['nilai' => strtoupper($label), 'teks' => strtoupper($label), 'numeric' => null, 'gaya' => 'section'];
            }
            $uraian = trim(((string) ($row['kode'] ?? '') !== '' ? (string) $row['kode'] . '  ' : '') . $label);
            return ['nilai' => $uraian, 'teks' => $uraian, 'numeric' => null, 'gaya' => $tipe === 'total' ? 'total' : 'teks'];
        }

        $nilai = $row['nilai'][$index - 1] ?? null;
        if ($nilai === null) {
            return ['nilai' => '', 'teks' => '', 'numeric' => null, 'gaya' => 'teks'];
        }

        $isPersen = str_contains((string) ($kolom['label'] ?? ''), '%') || str_contains((string) ($kolom['label'] ?? ''), 'Margin');
        $teks = $isPersen
            ? number_format((float) $nilai, 2, ',', '.') . '%'
            : ReportService::uang($nilai);

        return [
            'nilai'   => $teks,
            'teks'    => $teks,
            'numeric' => $isPersen ? (float) $nilai : (float) $nilai,
            'gaya'    => $tipe === 'total' ? 'total' : ($tipe === 'subtotal' ? 'subtotal' : 'angka'),
            'persen'  => $isPersen,
        ];
    }

    /** @return array<int,array<int,array<string,mixed>>> */
    private static function grid(array $laporan): array
    {
        $grid = [];
        foreach ($laporan['baris'] as $row) {
            if ((string) ($row['tipe'] ?? '') === 'spacer') {
                $grid[] = [];
                continue;
            }
            $cells = [];
            $jumlahKolom = count($laporan['kolom']);
            for ($i = 0; $i < $jumlahKolom; $i++) {
                $cells[] = self::sel($laporan, $row, $i);
            }
            if ($jumlahKolom === 4 && count($row['nilai']) === 2 && $row['persen'] !== null) {
                $cells[3] = [
                    'nilai'   => number_format((float) $row['persen'], 1, ',', '.') . '%',
                    'teks'    => number_format((float) $row['persen'], 1, ',', '.') . '%',
                    'numeric' => (float) $row['persen'],
                    'gaya'    => 'angka',
                    'persen'  => true,
                ];
            }
            $grid[] = $cells;
        }
        return $grid;
    }

    /** CSV (UTF-8, pemisah titik koma agar ramah Excel Indonesia). */
    public static function csv(array $laporan): string
    {
        $lines = [];
        $lines[] = ['LJU FINANCE — ' . (string) $laporan['judul']];
        $lines[] = ['Periode', (string) ($laporan['periode']['nama'] ?? '')];
        $lines[] = ['Rentang', (string) ($laporan['rentang']['label'] ?? '')];
        $lines[] = [];

        $header = array_map(static fn (array $k): string => (string) $k['label'], $laporan['kolom']);
        $lines[] = $header;

        foreach (self::grid($laporan) as $row) {
            if ($row === []) {
                $lines[] = [];
                continue;
            }
            $lines[] = array_map(static fn (array $cell): string => (string) $cell['teks'], $row);
        }

        $lines[] = [];
        foreach ((array) ($laporan['catatan'] ?? []) as $note) {
            $lines[] = ['Catatan: ' . $note];
        }

        $out = "\xEF\xBB\xBF";
        foreach ($lines as $line) {
            $out .= implode(';', array_map(static function (string $value): string {
                $value = str_replace(['"', "\r", "\n"], ['""', ' ', ' '], $value);
                if ($value !== '' && str_contains('=+@', $value[0])) {
                    $value = "'" . $value;
                }
                return str_contains($value, ';') || str_contains($value, ' ') ? '"' . $value . '"' : $value;
            }, $line)) . "\r\n";
        }

        return $out;
    }

    /** SpreadsheetML 2003 (cadangan bila ZipArchive tidak tersedia). */
    public static function spreadsheetXml(array $laporan): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Styles>'
            . '<Style ss:ID="judul"><Font ss:Bold="1" ss:Size="13"/></Style>'
            . '<Style ss:ID="section"><Font ss:Bold="1"/><Interior ss:Color="#EEF2F7" ss:Pattern="Solid"/></Style>'
            . '<Style ss:ID="total"><Font ss:Bold="1"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous"/></Borders></Style>'
            . '<Style ss:ID="angka"><NumberFormat ss:Format="#,##0;(#,##0)"/></Style>'
            . '</Styles>' . "\n";
        $xml .= '<Worksheet ss:Name="Laporan"><Table>' . "\n";
        foreach ($laporan['kolom'] as $kolom) {
            $xml .= '<Column ss:AutoFitWidth="0" ss:Width="' . (int) (((float) ($kolom['lebar'] ?? 15)) * 7) . '"/>';
        }

        $xml .= '<Row><Cell ss:StyleID="judul"><Data ss:Type="String">' . self::xml((string) $laporan['judul']) . '</Data></Cell></Row>';
        $xml .= '<Row><Cell><Data ss:Type="String">' . self::xml((string) ($laporan['rentang']['label'] ?? '')) . '</Data></Cell></Row>';
        $xml .= '<Row></Row>';

        $xml .= '<Row>';
        foreach ($laporan['kolom'] as $kolom) {
            $xml .= '<Cell ss:StyleID="section"><Data ss:Type="String">' . self::xml((string) $kolom['label']) . '</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach (self::grid($laporan) as $row) {
            if ($row === []) {
                $xml .= '<Row></Row>';
                continue;
            }
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $style = match ((string) $cell['gaya']) {
                    'section' => ' ss:StyleID="section"',
                    'total'   => ' ss:StyleID="total"',
                    'subtotal' => ' ss:StyleID="total"',
                    default   => ' ss:StyleID="angka"',
                };
                if ($cell['numeric'] !== null && $cell['gaya'] !== 'section') {
                    $xml .= '<Cell' . $style . '><Data ss:Type="Number">' . (float) $cell['numeric'] . '</Data></Cell>';
                } else {
                    $xml .= '<Cell' . $style . '><Data ss:Type="String">' . self::xml((string) $cell['teks']) . '</Data></Cell>';
                }
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';

        return $xml;
    }

    /** XLSX asli; null bila ZipArchive tidak tersedia. */
    public static function xlsx(array $laporan): ?string
    {
        $sheetName = preg_replace('/[\\\\\\/?*\\[\\]:]/', ' ', (string) $laporan['judul']) ?: 'Laporan';
        $sheetName = mb_substr(trim($sheetName), 0, 28);

        $rows = [];
        $rowIndex = 1;

        $renderRow = static function (int $index, array $cells, int $colCount): string {
            $xml = '<row r="' . $index . '">';
            foreach ($cells as $i => $cell) {
                $ref = self::colName($i) . $index;
                $style = match ((string) $cell['gaya']) {
                    'section'  => 1,
                    'total'    => 2,
                    'subtotal' => 2,
                    default    => 0,
                };
                if (!empty($cell['persen']) && $cell['numeric'] !== null) {
                    $xml .= '<c r="' . $ref . '" s="6"><v>' . (float) $cell['numeric'] . '</v></c>';
                } elseif ($cell['numeric'] !== null && $cell['gaya'] !== 'section') {
                    $style = ($cell['gaya'] === 'total' || $cell['gaya'] === 'subtotal') ? 4 : 3;
                    $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . (float) $cell['numeric'] . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t>' . self::xml((string) $cell['teks']) . '</t></is></c>';
                }
            }
            return $xml . '</row>';
        };

        $colCount = count($laporan['kolom']);
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        $sheet .= '<cols>';
        foreach ($laporan['kolom'] as $i => $kolom) {
            $sheet .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . max(10, (float) ($kolom['lebar'] ?? 15)) . '" customWidth="1"/>';
        }
        $sheet .= '</cols><sheetData>';

        $sheet .= '<row r="1"><c r="A1" s="5" t="inlineStr"><is><t>' . self::xml((string) $laporan['judul']) . '</t></is></c></row>';
        $sheet .= '<row r="2"><c r="A2" s="0" t="inlineStr"><is><t>' . self::xml((string) ($laporan['rentang']['label'] ?? '') . ' · ' . (string) ($laporan['periode']['nama'] ?? '')) . '</t></is></c></row>';
        $sheet .= '<row r="3"/>';

        $headerRow = '<row r="4">';
        foreach ($laporan['kolom'] as $i => $kolom) {
            $headerRow .= '<c r="' . self::colName($i) . '4" s="1" t="inlineStr"><is><t>' . self::xml((string) $kolom['label']) . '</t></is></c>';
        }
        $sheet .= $headerRow . '</row>';

        $rowIndex = 5;
        foreach (self::grid($laporan) as $cells) {
            if ($cells === []) {
                $rowIndex++;
                continue;
            }
            $sheet .= $renderRow($rowIndex, $cells, $colCount);
            $rowIndex++;
        }

        $sheet .= '</sheetData></worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0;(#,##0)"/>'
            . '<numFmt numFmtId="165" formatCode="0.0&quot;%&quot;"/></numFmts>'
            . '<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top style="thin"/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="164" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs></styleSheet>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $parts = [
            '[Content_Types].xml'          => $contentTypes,
            '_rels/.rels'                  => $rootRels,
            'xl/workbook.xml'              => $workbook,
            'xl/_rels/workbook.xml.rels'   => $workbookRels,
            'xl/styles.xml'                => $styles,
            'xl/worksheets/sheet1.xml'     => $sheet,
        ];

        if (class_exists(\ZipArchive::class)) {
            $tmp = tempnam(sys_get_temp_dir(), 'lxlsx');
            if ($tmp === false) {
                return null;
            }

            $zip = new \ZipArchive();
            if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                @unlink($tmp);
                return self::zipStore($parts);
            }

            foreach ($parts as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();

            $content = (string) file_get_contents($tmp);
            @unlink($tmp);
            return $content;
        }

        return self::zipStore($parts);
    }

    /**
     * Pembuat ZIP tanpa ekstensi (metode store / tanpa kompresi).
     *
     * @param array<string,string> $files
     */
    private static function zipStore(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;

        foreach ($files as $name => $data) {
            $crc = crc32($data);
            $size = strlen($data);
            $header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, 0, 0, $crc, $size, $size, strlen($name), 0);
            $local .= $header . $name . $data;

            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0x0800,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                strlen($name),
                0,
                0,
                0,
                0,
                0,
                $offset
            ) . $name;

            $offset += strlen($header) + strlen($name) + $size;
        }

        $cdOffset = strlen($local);
        $cdSize = strlen($central);
        $count = count($files);

        $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $cdSize, $cdOffset, 0);

        return $local . $central . $eocd;
    }

    private static function colName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = (int) (($index - $mod) / 26);
        }
        return $name;
    }
}
