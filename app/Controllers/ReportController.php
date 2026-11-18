<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Exporter;
use Lju\Core\Pdf;
use Lju\Services\PeriodService;
use Lju\Services\ReportService;

/**
 * Laporan keuangan: laba rugi, neraca, arus kas, perubahan ekuitas,
 * laba rugi proyek, cetak, serta ekspor PDF/CSV/XLSX.
 */
final class ReportController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('report.view');

        $periodeId = (int) input('periode', 0);
        $ringkasan = ReportService::ringkasan($periodeId);
        $periode = $ringkasan['periode'];
        $labaRugi = $ringkasan['laba_rugi'];
        $neraca = $ringkasan['neraca'];
        $arusKas = $ringkasan['arus_kas'];

        $this->page('pages/report/index', [
            'pageTitle'    => 'Laporan Keuangan',
            'pageSubtitle' => 'Neraca, laba rugi, arus kas, perubahan ekuitas, dan laba rugi proyek.',
            'breadcrumbs'  => [
                ['label' => 'Laporan Keuangan', 'url' => null],
                ['label' => 'Ringkasan', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('trial-balance')) . '">' . icon('scale', 15) . ' Neraca Saldo</a>'
                . '<a class="btn" href="' . e(url('ledger')) . '">' . icon('book', 15) . ' Buku Besar</a>',
            'periode'     => $periode,
            'periodeId'   => (int) ($periode['id'] ?? 0),
            'periodeList' => $this->opsiPeriode(),
            'ringkasan'   => $ringkasan,
            'labaRugi'    => $labaRugi,
            'neraca'      => $neraca,
            'arusKas'     => $arusKas,
            'rasio'       => $ringkasan['rasio'],
            'tren'        => $ringkasan['tren'],
            'jenisList'   => ReportService::JENIS,
            'canExport'   => can('report.export'),
        ]);
    }

    public function income(): void
    {
        $this->tampil('laba-rugi', 'pages/report/income');
    }

    public function balanceSheet(): void
    {
        $this->tampil('neraca', 'pages/report/balance-sheet');
    }

    public function cashFlow(): void
    {
        $this->tampil('arus-kas', 'pages/report/cash-flow');
    }

    public function equity(): void
    {
        $this->tampil('ekuitas', 'pages/report/equity');
    }

    public function project(): void
    {
        $this->tampil('proyek', 'pages/report/project');
    }

    public function unitUsaha(): void
    {
        $this->requireCan('report.view');

        $periodeId = (int) input('periode', 0);
        $mode = (string) input('mode', 'bulan');
        $filter = [
            'bidang_id'        => (int) input('bidang', 0),
            'divisi_id'        => (int) input('divisi', 0),
            'business_unit_id' => (int) input('unit', 0),
            'project_id'       => (int) input('project', 0),
        ];

        $laporan = ReportService::unitUsaha($periodeId, $mode, $filter);
        $laporan['entitas'] = $this->entitas();

        $this->page('pages/report/unit-usaha', [
            'pageTitle'    => (string) $laporan['judul'],
            'pageSubtitle' => 'Periode ' . (string) ($laporan['periode']['nama'] ?? '') . ' · ' . (string) $laporan['rentang']['label'],
            'breadcrumbs'  => [
                ['label' => 'Laporan Keuangan', 'url' => url('report')],
                ['label' => (string) $laporan['judul'], 'url' => null],
            ],
            'pageActions'  => $this->aksiHalaman('unit-usaha', $periodeId, $mode, $filter),
            'laporan'      => $laporan,
            'periodeList'  => $this->opsiPeriode(),
            'periodeId'    => (int) ($laporan['periode']['id'] ?? 0),
            'mode'         => (string) ($laporan['rentang']['mode'] ?? $mode),
            'filter'       => $filter,
            'filterList'   => $this->filterList(),
            'canExport'    => can('report.export'),
            'rasio'        => [],
        ]);
    }

    public function print(string $jenis): void
    {
        $this->requireCan('report.view');

        [$laporan, $params] = $this->siapkan($jenis);

        echo \Lju\Core\View::render('pages/report/print', array_merge($params, [
            'laporan'  => $laporan,
            'entitas'  => $this->entitas(),
            'tandaTangan' => $this->tandaTangan(),
        ]), 'layouts/print');
    }

    public function export(string $jenis): void
    {
        $this->requireCan('report.export');

        [$laporan] = $this->siapkan($jenis);
        $format = strtolower((string) input('format', 'csv'));
        $base = $this->namaFile($laporan, $jenis);

        Audit::log('export', 'report', [
            'ref_table'   => 'journal_entries',
            'ref_code'    => (string) $jenis,
            'description' => 'Ekspor ' . $laporan['judul'] . ' (' . strtoupper($format) . ') periode ' . (string) ($laporan['periode']['nama'] ?? ''),
            'new'         => ['periode' => (int) ($laporan['periode']['id'] ?? 0), 'format' => $format],
        ]);

        if ($format === 'pdf') {
            $content = $this->pdf($laporan);
            $this->unduh($content, $base . '.pdf', 'application/pdf');
            return;
        }

        if ($format === 'xlsx' || $format === 'excel') {
            $content = Exporter::xlsx($laporan);
            if ($content !== null) {
                $this->unduh($content, $base . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                return;
            }
            $this->unduh(Exporter::spreadsheetXml($laporan), $base . '.xls', 'application/vnd.ms-excel');
            return;
        }

        $this->unduh(Exporter::csv($laporan), $base . '.csv', 'text/csv; charset=utf-8');
    }

    /* ------------------------------------------------------------- internal */

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function siapkan(string $jenis): array
    {
        $this->requireCan('report.view');

        $periodeId = (int) input('periode', 0);
        $mode = (string) input('mode', 'bulan');
        $filter = [
            'bidang_id'        => (int) input('bidang', 0),
            'divisi_id'        => (int) input('divisi', 0),
            'business_unit_id' => (int) input('unit', 0),
            'project_id'       => (int) input('project', 0),
        ];

        $laporan = ReportService::laporan($jenis, $periodeId, $mode, $filter);
        $laporan['entitas'] = $this->entitas();

        $params = [
            'pageTitle'    => (string) $laporan['judul'],
            'pageSubtitle' => 'Periode ' . (string) ($laporan['periode']['nama'] ?? '') . ' · ' . (string) $laporan['rentang']['label'],
            'breadcrumbs'  => [
                ['label' => 'Laporan Keuangan', 'url' => url('report')],
                ['label' => (string) $laporan['judul'], 'url' => null],
            ],
            'pageActions'  => $this->aksiHalaman($jenis, $periodeId, $mode, $filter),
            'laporan'      => $laporan,
            'periodeList'  => $this->opsiPeriode(),
            'periodeId'    => (int) ($laporan['periode']['id'] ?? 0),
            'mode'         => (string) ($laporan['rentang']['mode'] ?? $mode),
            'filter'       => $filter,
            'filterList'   => $this->filterList(),
            'canExport'    => can('report.export'),
            'rasio'        => in_array($jenis, ['laba-rugi', 'neraca'], true)
                ? ReportService::rasio($periodeId, array_filter($filter))
                : [],
        ];

        return [$laporan, $params];
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    private function filterList(): array
    {
        return [
            'bidang'        => Db::all('SELECT id, nama FROM ms_bidang ORDER BY nama'),
            'divisi'        => Db::all('SELECT id, nama FROM ms_divisi ORDER BY nama'),
            'business_unit' => Db::all('SELECT id, nama FROM ms_business_units ORDER BY kode'),
            'project'       => Db::all('SELECT id, nama FROM ms_projects ORDER BY nama'),
        ];
    }

    private function tampil(string $jenis, string $view): void
    {
        $this->requireCan('report.view');
        [, $params] = $this->siapkan($jenis);
        $this->page($view, $params);
    }

    private function aksiHalaman(string $jenis, int $periodeId, string $mode, array $filter): string
    {
        $params = array_filter([
            'periode' => $periodeId ?: null,
            'mode'    => $mode,
            'bidang'  => $filter['bidang_id'] ?: null,
            'divisi'  => $filter['divisi_id'] ?: null,
            'unit'    => $filter['business_unit_id'] ?: null,
            'project' => $filter['project_id'] ?: null,
        ]);

        $html = '<a class="btn" href="' . e(url('report')) . '">' . icon('arrow-left', 15) . ' Semua Laporan</a>';
        $html .= '<a class="btn" target="_blank" href="' . e(url('report/print/' . $jenis, $params)) . '">' . icon('printer', 15) . ' Cetak</a>';

        if (can('report.export')) {
            $html .= '<a class="btn" href="' . e(url('report/export/' . $jenis, $params + ['format' => 'pdf'])) . '">' . icon('download', 15) . ' PDF</a>';
            $html .= '<a class="btn" href="' . e(url('report/export/' . $jenis, $params + ['format' => 'xlsx'])) . '">' . icon('download', 15) . ' Excel</a>';
            $html .= '<a class="btn" href="' . e(url('report/export/' . $jenis, $params + ['format' => 'csv'])) . '">' . icon('download', 15) . ' CSV</a>';
        }

        return $html;
    }

    /** @return array{name:string,address:string,npwp:string,phone:string,email:string} */
    private function entitas(): array
    {
        return [
            'name'    => (string) setting('company_name', config('app.company', 'PT. Lampung Jasa Utama')),
            'address' => (string) setting('company_address', ''),
            'npwp'    => (string) setting('company_npwp', ''),
            'phone'   => (string) setting('company_phone', ''),
            'email'   => (string) setting('company_email', ''),
        ];
    }

    /** @return array<int,array{label:string,title:string,name:string}> */
    private function tandaTangan(): array
    {
        return [
            [
                'label' => (string) setting('report_sign1_label', 'Disiapkan oleh'),
                'title' => (string) setting('report_sign1_title', 'Kepala Bagian Keuangan'),
                'name'  => (string) setting('report_sign1_name', ''),
            ],
            [
                'label' => (string) setting('report_sign2_label', 'Diperiksa oleh'),
                'title' => (string) setting('report_sign2_title', 'Manajer Akuntansi'),
                'name'  => (string) setting('report_sign2_name', ''),
            ],
            [
                'label' => (string) setting('report_sign3_label', 'Disetujui oleh'),
                'title' => (string) setting('report_sign3_title', 'Direktur Utama'),
                'name'  => (string) setting('report_sign3_name', ''),
            ],
        ];
    }

    private function pdf(array $laporan): string
    {
        $entitas = $this->entitas();

        $npwp = trim((string) $entitas['npwp']);
        $pdf = new Pdf([
            'paper'       => (string) setting('pdf_paper', (string) config('pdf.paper', 'A4')),
            'orientation' => (string) config('pdf.orientation', 'portrait'),
        ]);
        $pdf->company($entitas['name'], $npwp !== '' ? 'NPWP ' . $npwp : '');
        $pdf->document((string) $laporan['judul'], 'Periode ' . (string) ($laporan['periode']['nama'] ?? '') . ' · ' . (string) $laporan['rentang']['label']);
        $pdf->meta([
            'Periode'   => (string) ($laporan['periode']['nama'] ?? '-'),
            'Rentang'   => (string) $laporan['rentang']['label'],
            'Mata Uang' => (string) setting('currency', 'IDR'),
            'Status'    => ucfirst((string) ($laporan['periode']['status'] ?? '-')),
        ]);

        $kolom = [];
        foreach ($laporan['kolom'] as $i => $k) {
            $kolom[] = [
                'label' => (string) $k['label'],
                'align' => (string) ($k['align'] ?? 'left'),
                'lebar' => (float) ($k['lebar'] ?? (100 / count($laporan['kolom']))),
            ];
        }

        $isPersen = [];
        foreach ($laporan['kolom'] as $i => $k) {
            $isPersen[$i] = str_contains((string) $k['label'], '%') || str_contains((string) $k['label'], 'Margin');
        }

        $baris = [];
        foreach ($laporan['baris'] as $row) {
            $tipe = (string) ($row['tipe'] ?? 'data');
            if ($tipe === 'spacer') {
                $baris[] = ['tipe' => 'spacer', 'label' => '', 'nilai' => []];
                continue;
            }

            $label = (string) ($row['label'] ?? '');
            if ($tipe === 'data' && (string) ($row['kode'] ?? '') !== '') {
                $label = (string) $row['kode'] . '  ' . $label;
            }

            $nilai = [];
            foreach (array_values((array) ($row['nilai'] ?? [])) as $i => $value) {
                $index = $i + 1;
                if (!isset($laporan['kolom'][$index])) {
                    break;
                }
                $nilai[] = ($isPersen[$index] ?? false)
                    ? number_format((float) $value, 2, ',', '.') . '%'
                    : Pdf::money($value);
            }
            if (count($laporan['kolom']) === 4) {
                $nilai[] = $row['persen'] !== null ? number_format((float) $row['persen'], 1, ',', '.') . '%' : '-';
            }

            $baris[] = [
                'tipe'  => $tipe,
                'label' => $label,
                'nilai' => $nilai,
            ];
        }

        $pdf->table($kolom, $baris);

        foreach ((array) $laporan['catatan'] as $note) {
            $pdf->footnote((string) $note);
        }

        $footer = (string) setting('report_footer_note', '');
        if ($footer !== '') {
            $pdf->spacer(10);
            $pdf->paragraph($footer, false, 7.5, [0.45, 0.50, 0.58]);
        }

        // Blok tanda tangan.
        $ttd = $this->tandaTangan();
        $pdf->spacer(14);
        $pdf->reserve(90);
        $lebar = ($pdf->contentWidthSafe()) / max(1, count($ttd));
        $y = $pdf->currentY() - 6;

        foreach ($ttd as $i => $item) {
            $x = 42 + ($i * $lebar);
            $pdf->text($x, $y, 7, 'F1', [0.45, 0.50, 0.58], (string) $item['label']);
            $pdf->text($x, $y - 48, 8, 'F2', [0.16, 0.20, 0.27], $item['name'] !== '' ? $item['name'] : '(..............................)');
            $pdf->line($x, $y - 40, $x + min(120, $lebar - 20), $y - 40, [0.55, 0.60, 0.68], 0.6);
            $pdf->text($x, $y - 58, 7, 'F1', [0.35, 0.40, 0.48], (string) $item['title']);
        }

        return $pdf->output();
    }

    private function unduh(string $content, string $filename, string $mime): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) strlen($content));
        echo $content;
        exit;
    }

    private function namaFile(array $laporan, string $jenis): string
    {
        $periode = preg_replace('/[^0-9A-Za-z\-]/', '', (string) ($laporan['periode']['kode'] ?? 'periode')) ?: 'periode';
        $mode = (string) ($laporan['rentang']['mode'] ?? 'bulan');
        return 'laporan-' . $jenis . '-' . $periode . ($mode === 'ytd' ? '-ytd' : '');
    }

    /** @return array<int,array<string,mixed>> */
    private function opsiPeriode(): array
    {
        $rows = [];
        foreach (array_slice(PeriodService::years(), 0, 3) as $year) {
            foreach (PeriodService::forYear((int) $year) as $periode) {
                $rows[] = $periode;
            }
        }

        usort($rows, static fn ($a, $b) => [$b['tahun'], $b['bulan']] <=> [$a['tahun'], $a['bulan']]);
        return $rows;
    }
}
