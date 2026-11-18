<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\PeriodService;
use Lju\Services\TaxService;

/**
 * Perpajakan: dokumen pajak, penyetoran, rekap SPT bulanan, dan e-Faktur.
 */
final class TaxController extends BaseController
{
    use TransaksiTrait;

    public function index(): void
    {
        $this->requireCan('tax.view');

        $q = trim((string) input('q', ''));
        $tipe = (string) input('tipe', '');
        $jenis = (string) input('jenis', '');
        $periodeId = (int) input('periode', 0);
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(t.nomor LIKE ? OR t.npwp LIKE ? OR p.nama LIKE ? OR t.keterangan LIKE ?)';
            $params = array_merge($params, ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%']);
        }
        if ($tipe !== '' && array_key_exists($tipe, TaxService::TIPE)) {
            $where[] = 't.tipe = ?';
            $params[] = $tipe;
        }
        if ($jenis !== '' && array_key_exists($jenis, TaxService::JENIS)) {
            $where[] = 'tt.jenis = ?';
            $params[] = $jenis;
        }
        if ($periodeId > 0) {
            $where[] = 't.periode_id = ?';
            $params[] = $periodeId;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val(
            'SELECT COUNT(*) FROM tax_documents t
             JOIN tax_types tt ON tt.id = t.tax_type_id
             LEFT JOIN ms_partners p ON p.id = t.partner_id
             WHERE ' . $whereSql,
            $params
        );
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT t.*, tt.kode AS tax_kode, tt.nama AS tax_nama, tt.jenis,
                    p.nama AS partner_nama, pe.nama AS periode_nama, j.nomor AS journal_nomor
             FROM tax_documents t
             JOIN tax_types tt ON tt.id = t.tax_type_id
             LEFT JOIN ms_partners p ON p.id = t.partner_id
             LEFT JOIN fiscal_periods pe ON pe.id = t.periode_id
             LEFT JOIN journal_entries j ON j.id = t.journal_id
             WHERE ' . $whereSql . '
             ORDER BY t.tanggal DESC, t.id DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $periodeRingkasan = $periodeId > 0 ? $periodeId : (int) (periode_aktif()['id'] ?? 0);

        $this->page('pages/tax/index', [
            'pageTitle'    => 'Dokumen Pajak',
            'pageSubtitle' => 'Faktur keluaran/masukan, bukti potong, dan SSP beserta status pelaporannya.',
            'breadcrumbs'  => [
                ['label' => 'Perpajakan', 'url' => null],
                ['label' => 'Dokumen Pajak', 'url' => null],
            ],
            'pageActions'  => (can('tax.manage')
                    ? '<a class="btn btn--primary" href="' . e(url('tax/create')) . '">' . icon('plus', 15) . ' Dokumen Baru</a>'
                    : '')
                . '<a class="btn" href="' . e(url('tax/report')) . '">' . icon('receipt', 15) . ' Rekap SPT</a>',
            'rows'        => $rows,
            'paginator'   => $paginator,
            'q'           => $q,
            'tipe'        => $tipe,
            'jenis'       => $jenis,
            'periodeId'   => $periodeId,
            'periodeList' => $this->opsiPeriode(),
            'ringkasan'   => TaxService::ringkasan($periodeRingkasan),
            'tipeList'    => TaxService::TIPE,
            'jenisList'   => TaxService::JENIS,
            'statusList'  => TaxService::STATUS,
            'canManage'   => can('tax.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('tax.manage');

        $this->renderForm(null, [
            'nomor'       => '',
            'tipe'        => 'faktur_keluaran',
            'tax_type_id' => 1,
            'partner_id'  => null,
            'tanggal'     => today(),
            'npwp'        => '',
            'dpp'         => 0,
            'tarif'       => 11,
            'nilai_pajak' => 0,
            'ref_module'  => '',
            'ref_id'      => null,
            'keterangan'  => '',
            'status'      => 'draft',
        ]);
    }

    public function store(): void
    {
        $this->requireCan('tax.manage');

        $data = TaxService::normalisasi($_POST);
        $errors = TaxService::validasi($data);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm(null, $data, $errors);
            return;
        }

        try {
            $id = TaxService::simpan(null, $data);
        } catch (\Throwable $e) {
            $this->renderForm(null, $data, ['general' => $e->getMessage()]);
            return;
        }

        $row = TaxService::find($id);
        flash_success('Dokumen pajak ' . ($row['nomor'] ?? '') . ' disimpan sebagai draft.');
        redirect('tax/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('tax.view');

        $row = TaxService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pajak tidak ditemukan.');
        }

        $status = (string) $row['status'];

        $this->page('pages/tax/show', [
            'pageTitle'    => 'Dokumen Pajak ' . $row['nomor'],
            'pageSubtitle' => (string) $row['tax_nama'],
            'breadcrumbs'  => [
                ['label' => 'Perpajakan', 'url' => url('tax')],
                ['label' => (string) $row['nomor'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('tax')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'         => $row,
            'banks'       => $this->opsiBank(),
            'tipeList'    => TaxService::TIPE,
            'jenisList'   => TaxService::JENIS,
            'statusList'  => TaxService::STATUS,
            'canEdit'     => can('tax.manage') && $status === 'draft',
            'canSetor'    => can('tax.manage') && $status === 'draft' && (string) $row['tipe'] === 'ssp',
            'canPost'     => can('tax.manage') && $status === 'draft' && (string) $row['tipe'] !== 'ssp',
            'canLapor'    => can('tax.manage') && in_array($status, ['draft', 'posted'], true),
            'canVoid'     => can('tax.manage') && $status === 'draft',
            'canDelete'   => can('tax.manage') && $status === 'draft' && empty($row['journal_id']),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('tax.manage');

        $row = TaxService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pajak tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft') {
            flash_error('Hanya dokumen draft yang dapat diubah.');
            redirect('tax/' . (int) $id);
        }

        $this->renderForm((int) $id, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('tax.manage');

        $row = TaxService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pajak tidak ditemukan.');
        }

        $data = TaxService::normalisasi($_POST);
        $errors = TaxService::validasi($data, (int) $id);

        if ($errors !== []) {
            error_bag($errors);
            $this->renderForm((int) $id, $data, $errors);
            return;
        }

        try {
            TaxService::simpan((int) $id, $data);
        } catch (\Throwable $e) {
            $this->renderForm((int) $id, $data, ['general' => $e->getMessage()]);
            return;
        }

        flash_success('Dokumen pajak ' . $row['nomor'] . ' diperbarui.');
        redirect('tax/' . (int) $id);
    }

    public function setor(string $id): void
    {
        $this->requireCan('tax.manage');

        try {
            $journalId = TaxService::setor((int) $id, (int) input('bank_account_id', 0));
            flash_success('Penyetoran pajak dicatat pada jurnal #' . $journalId . '.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('tax/' . (int) $id);
    }

    public function posting(string $id): void
    {
        $this->requireCan('tax.manage');

        try {
            TaxService::posting((int) $id);
            flash_success('Dokumen pajak diposting.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('tax/' . (int) $id);
    }

    public function lapor(string $id): void
    {
        $this->requireCan('tax.manage');

        try {
            TaxService::lapor((int) $id);
            flash_success('Dokumen ditandai sudah dilaporkan.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('tax/' . (int) $id);
    }

    public function void(string $id): void
    {
        $this->requireCan('tax.manage');

        try {
            TaxService::void((int) $id, (string) input('alasan', ''));
            flash_success('Dokumen pajak di-void.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('tax/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('tax.manage');

        $row = TaxService::find((int) $id);
        if ($row === null) {
            App::abort(404, 'Dokumen pajak tidak ditemukan.');
        }

        try {
            TaxService::hapus((int) $id);
            flash_success('Dokumen pajak ' . $row['nomor'] . ' dihapus.');
            redirect('tax');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('tax/' . (int) $id);
        }
    }

    public function report(): void
    {
        $this->requireCan('tax.view');

        $periodeId = (int) input('periode', 0);
        if ($periodeId <= 0) {
            $periodeId = (int) (periode_aktif()['id'] ?? 0);
        }

        $periode = $periodeId > 0 ? Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId]) : null;

        $this->page('pages/tax/report', [
            'pageTitle'    => 'Rekap Pajak Bulanan',
            'pageSubtitle' => 'Rekapitulasi PPN, PPh dipotong, dan status pelaporan SPT per periode.',
            'breadcrumbs'  => [
                ['label' => 'Perpajakan', 'url' => url('tax')],
                ['label' => 'Rekap SPT', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('tax')) . '">' . icon('arrow-left', 15) . ' Dokumen Pajak</a>',
            'periode'     => $periode,
            'periodeId'   => $periodeId,
            'periodeList' => $this->opsiPeriode(),
            'rekap'       => $periodeId > 0 ? TaxService::rekap($periodeId) : ['per_tipe' => [], 'per_jenis' => []],
            'reports'     => TaxService::reports($periodeId > 0 ? $periodeId : null),
            'efaktur'     => $periodeId > 0 ? TaxService::efaktur($periodeId) : [],
            'tipeList'    => TaxService::TIPE,
            'jenisList'   => TaxService::JENIS,
            'reportJenis' => TaxService::REPORT_JENIS,
            'canManage'   => can('tax.manage'),
        ]);
    }

    public function reportGenerate(): void
    {
        $this->requireCan('tax.manage');

        $periodeId = (int) input('periode_id', 0);
        $jenis = (string) input('jenis', 'ppn');

        try {
            TaxService::generateReport($periodeId, $jenis);
            flash_success('Rekap ' . (TaxService::REPORT_JENIS[$jenis] ?? $jenis) . ' dihitung ulang.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('tax/report?periode=' . $periodeId);
    }

    public function reportStatus(): void
    {
        $this->requireCan('tax.manage');

        $id = (int) input('id', 0);
        $status = (string) input('status', 'draft');
        $tanggal = (string) input('tanggal_lapor', '');
        $referensi = (string) input('referensi', '');
        $periodeId = (int) input('periode_id', 0);

        try {
            TaxService::statusReport($id, $status, $tanggal !== '' ? $tanggal : null, $referensi);
            flash_success('Status SPT diperbarui.');
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        redirect('tax/report?periode=' . $periodeId);
    }

    public function efaktur(): void
    {
        $this->requireCan('tax.view');

        $periodeId = (int) input('periode', (int) (periode_aktif()['id'] ?? 0));

        try {
            $file = TaxService::efakturCsv($periodeId);
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('tax/report?periode=' . $periodeId);
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
        header('Content-Length: ' . (string) strlen($file['content']));
        echo $file['content'];
        exit;
    }

    /** @return array<int,array<string,mixed>> */
    private function opsiPeriode(): array
    {
        $rows = [];
        foreach (array_slice(PeriodService::years(), 0, 2) as $year) {
            foreach (PeriodService::forYear((int) $year) as $periode) {
                $rows[] = $periode;
            }
        }

        usort($rows, static fn ($a, $b) => [$b['tahun'], $b['bulan']] <=> [$a['tahun'], $a['bulan']]);
        return $rows;
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/tax/form', [
            'pageTitle'    => $id === null ? 'Dokumen Pajak Baru' : 'Ubah Dokumen Pajak',
            'pageSubtitle' => 'Nomor faktur/bukti potong, DPP, tarif, dan nilai pajak.',
            'breadcrumbs'  => [
                ['label' => 'Perpajakan', 'url' => url('tax')],
                ['label' => $id === null ? 'Baru' : (string) ($row['nomor'] ?? 'Ubah'), 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e($id === null ? url('tax') : url('tax/' . $id)) . '">' . icon('arrow-left', 15) . ' Batal</a>',
            'row'         => $row,
            'id'          => $id,
            'errors'      => $errors === [] ? errors() : $errors,
            'types'       => TaxService::types(),
            'tipeList'    => TaxService::TIPE,
            'jenisList'   => TaxService::JENIS,
            'partners'    => $this->opsiPartner(),
            'periodeList' => $this->opsiPeriode(),
        ]);
    }
}
