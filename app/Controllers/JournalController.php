<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Paginator;
use Lju\Services\JournalService;
use Lju\Services\LedgerService;
use Lju\Services\NumberingService;
use Lju\Services\PeriodService;

/**
 * Jurnal umum: daftar, input multi-baris, alur persetujuan, posting,
 * pembalik, dan lampiran bukti transaksi.
 */
final class JournalController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('journal.view');

        $q = trim((string) input('q', ''));
        $status = (string) input('status', '');
        $tipe = (string) input('tipe', '');
        $periodeId = (int) input('periode', 0);
        $page = max(1, (int) input('page', 1));
        $perPage = (int) config('app.items_per_page', 15);

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(j.nomor LIKE ? OR j.deskripsi LIKE ? OR j.referensi LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($status !== '' && array_key_exists($status, JournalService::STATUS)) {
            $where[] = 'j.status = ?';
            $params[] = $status;
        }
        if ($tipe !== '' && array_key_exists($tipe, JournalService::TIPE)) {
            $where[] = 'j.tipe = ?';
            $params[] = $tipe;
        }
        if ($periodeId > 0) {
            $where[] = 'j.periode_id = ?';
            $params[] = $periodeId;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM journal_entries j WHERE ' . $whereSql, $params);
        $paginator = new Paginator($total, $page, $perPage);

        $rows = Db::all(
            'SELECT j.*, p.nama AS periode_nama, u.name AS created_by_name
             FROM journal_entries j
             LEFT JOIN fiscal_periods p ON p.id = j.periode_id
             LEFT JOIN app_users u ON u.id = j.created_by
             WHERE ' . $whereSql . '
             ORDER BY j.tanggal DESC, j.id DESC
             LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $summary = ['draft' => 0, 'submitted' => 0, 'approved' => 0, 'posted' => 0, 'rejected' => 0, 'reversed' => 0];
        $nilai = ['posted' => 0.0];
        foreach (Db::all(
            'SELECT status, COUNT(*) AS jumlah, COALESCE(SUM(total_debit),0) AS nilai
             FROM journal_entries WHERE fiscal_year = ? GROUP BY status',
            [(int) (periode_aktif()['tahun'] ?? date('Y'))]
        ) as $row) {
            $summary[(string) $row['status']] = (int) $row['jumlah'];
            $nilai[(string) $row['status']] = (float) $row['nilai'];
        }

        $this->page('pages/journal/index', [
            'pageTitle'    => 'Jurnal Umum',
            'pageSubtitle' => 'Pencatatan transaksi dengan validasi debit-kredit dan alur persetujuan.',
            'breadcrumbs'  => [
                ['label' => 'Accounting', 'url' => null],
                ['label' => 'Jurnal Umum', 'url' => null],
            ],
            'pageActions'  => '<a class="btn btn--primary" href="' . e(url('journal/create')) . '">' . icon('plus', 15) . ' Jurnal Baru</a>',
            'rows'         => $rows,
            'paginator'    => $paginator,
            'q'            => $q,
            'status'       => $status,
            'tipe'         => $tipe,
            'periodeId'    => $periodeId,
            'periods'      => Db::all('SELECT id, kode, nama FROM fiscal_periods ORDER BY tanggal_mulai DESC LIMIT 24'),
            'summary'      => $summary,
            'nilai'        => $nilai,
            'canCreate'    => can('journal.create'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('journal.create');

        $this->renderForm(null, [
            'tanggal'   => today(),
            'tipe'      => (string) input('tipe', 'umum'),
            'sumber'    => 'manual',
            'referensi' => '',
            'deskripsi' => '',
        ], $this->emptyLines(4));
    }

    public function store(): void
    {
        $this->requireCan('journal.create');

        [$header, $lines] = JournalService::normalisasi($_POST);
        $errors = JournalService::validasi($header, $lines);

        if ($errors !== []) {
            $this->renderForm(null, $header, $lines, $errors);
            return;
        }

        try {
            $id = JournalService::simpan(null, $header, $lines);
        } catch (\Throwable $e) {
            $this->renderForm(null, $header, $lines, ['general' => $e->getMessage()]);
            return;
        }

        $journal = JournalService::find($id);
        Audit::log('create', 'journal', [
            'ref_table'   => 'journal_entries',
            'ref_id'      => $id,
            'ref_code'    => (string) ($journal['nomor'] ?? ''),
            'description' => 'Buat draft jurnal ' . ($journal['nomor'] ?? ''),
            'new'         => [
                'tanggal'     => $header['tanggal'],
                'deskripsi'   => $header['deskripsi'],
                'total_debit' => array_sum(array_column($lines, 'debit')),
            ],
        ]);

        flash_success('Draft jurnal ' . ($journal['nomor'] ?? '') . ' berhasil disimpan.');
        redirect('journal/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('journal.view');

        $journal = JournalService::find((int) $id);
        if ($journal === null) {
            App::abort(404, 'Jurnal tidak ditemukan.');
        }

        $lines = JournalService::lines((int) $id);
        $attachments = Db::tableExists('attachments')
            ? Db::all('SELECT * FROM attachments WHERE module = ? AND ref_id = ? ORDER BY id DESC', ['journal', (int) $id])
            : [];

        $status = (string) $journal['status'];
        $periodeOpen = (string) ($journal['periode_status'] ?? '') === 'open';

        $timeline = [];
        if (!empty($journal['created_at'])) {
            $timeline[] = ['label' => 'Dibuat', 'at' => $journal['created_at'], 'by' => $journal['created_by_name'] ?? null, 'icon' => 'edit', 'variant' => 'primary'];
        }
        if (!empty($journal['submitted_at'])) {
            $timeline[] = ['label' => 'Diajukan', 'at' => $journal['submitted_at'], 'by' => $journal['created_by_name'] ?? null, 'icon' => 'arrow-up', 'variant' => 'info'];
        }
        if (!empty($journal['approved_at'])) {
            $timeline[] = ['label' => 'Disetujui', 'at' => $journal['approved_at'], 'by' => $journal['approved_by_name'] ?? null, 'icon' => 'check', 'variant' => 'sukses'];
        }
        if (!empty($journal['rejected_at'])) {
            $timeline[] = ['label' => 'Ditolak', 'at' => $journal['rejected_at'], 'by' => $journal['rejected_by_name'] ?? null, 'icon' => 'x', 'variant' => 'bahaya'];
        }
        if (!empty($journal['posted_at'])) {
            $timeline[] = ['label' => 'Diposting', 'at' => $journal['posted_at'], 'by' => $journal['posted_by_name'] ?? null, 'icon' => 'check-circle', 'variant' => 'sukses'];
        }

        $this->page('pages/journal/show', [
            'pageTitle'    => 'Jurnal ' . $journal['nomor'],
            'pageSubtitle' => (string) $journal['deskripsi'],
            'breadcrumbs'  => [
                ['label' => 'Accounting', 'url' => null],
                ['label' => 'Jurnal Umum', 'url' => url('journal')],
                ['label' => (string) $journal['nomor'], 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('journal')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'journal'      => $journal,
            'lines'        => $lines,
            'attachments'  => $attachments,
            'timeline'     => $timeline,
            'periodeOpen'  => $periodeOpen,
            'canEdit'      => can('journal.create') && in_array($status, JournalService::EDITABLE, true),
            'canSubmit'    => can('journal.submit') && in_array($status, ['draft', 'rejected'], true),
            'canApprove'   => can('journal.approve') && $status === 'submitted',
            'canPost'      => can('journal.post') && $status === 'approved' && $periodeOpen,
            'canReverse'   => can('journal.reverse') && $status === 'posted' && empty($journal['reversed_by_id']),
            'canDelete'    => can('journal.create') && in_array($status, JournalService::EDITABLE, true),
            'canAttach'    => can('journal.create') && !in_array($status, ['reversed'], true),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('journal.create');

        $journal = JournalService::find((int) $id);
        if ($journal === null) {
            App::abort(404, 'Jurnal tidak ditemukan.');
        }
        if (!in_array((string) $journal['status'], JournalService::EDITABLE, true)) {
            flash_error('Jurnal berstatus ' . JournalService::STATUS[(string) $journal['status']] . ' tidak dapat diubah.');
            redirect('journal/' . (int) $id);
        }

        $lines = JournalService::lines((int) $id);
        $lines = array_map(static function (array $line): array {
            return [
                'coa_id'         => (int) $line['coa_id'],
                'deskripsi'      => (string) ($line['deskripsi'] ?? ''),
                'debit'          => (float) $line['debit'],
                'kredit'         => (float) $line['kredit'],
                'bidang_id'      => $line['bidang_id'] ?? null,
                'divisi_id'      => $line['divisi_id'] ?? null,
                'project_id'     => $line['project_id'] ?? null,
                'cost_center_id' => $line['cost_center_id'] ?? null,
                'partner_id'     => $line['partner_id'] ?? null,
            ];
        }, $lines);

        while (count($lines) < 2) {
            $lines[] = $this->emptyLine();
        }

        $this->renderForm($journal, [
            'tanggal'   => (string) $journal['tanggal'],
            'tipe'      => (string) $journal['tipe'],
            'sumber'    => (string) $journal['sumber'],
            'referensi' => (string) ($journal['referensi'] ?? ''),
            'deskripsi' => (string) $journal['deskripsi'],
        ], $lines);
    }

    public function update(string $id): void
    {
        $this->requireCan('journal.create');

        $journal = JournalService::find((int) $id);
        if ($journal === null) {
            App::abort(404, 'Jurnal tidak ditemukan.');
        }
        if (!in_array((string) $journal['status'], JournalService::EDITABLE, true)) {
            flash_error('Jurnal ini tidak dapat diubah lagi.');
            redirect('journal/' . (int) $id);
        }

        [$header, $lines] = JournalService::normalisasi($_POST);
        $errors = JournalService::validasi($header, $lines);

        if ($errors !== []) {
            $this->renderForm($journal, $header, $lines, $errors);
            return;
        }

        try {
            JournalService::simpan((int) $id, $header, $lines);
        } catch (\Throwable $e) {
            $this->renderForm($journal, $header, $lines, ['general' => $e->getMessage()]);
            return;
        }

        Audit::log('update', 'journal', [
            'ref_table'   => 'journal_entries',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $journal['nomor'],
            'description' => 'Ubah draft jurnal ' . $journal['nomor'],
        ]);

        flash_success('Jurnal ' . $journal['nomor'] . ' diperbarui.');
        redirect('journal/' . (int) $id);
    }

    public function submit(string $id): void
    {
        $this->requireCan('journal.submit');
        $this->guard(function () use ($id): void {
            JournalService::ajukan((int) $id);
        }, 'Jurnal diajukan untuk persetujuan.', (int) $id);
    }

    public function approve(string $id): void
    {
        $this->requireCan('journal.approve');
        $this->guard(function () use ($id): void {
            JournalService::setujui((int) $id);
        }, 'Jurnal disetujui.', (int) $id);
    }

    public function reject(string $id): void
    {
        $this->requireCan('journal.approve');
        $alasan = trim((string) input('alasan', ''));
        $this->guard(function () use ($id, $alasan): void {
            JournalService::tolak((int) $id, $alasan);
        }, 'Jurnal ditolak.', (int) $id);
    }

    public function post(string $id): void
    {
        $this->requireCan('journal.post');
        $this->guard(function () use ($id): void {
            JournalService::posting((int) $id);
        }, 'Jurnal berhasil diposting ke buku besar.', (int) $id);
    }

    public function reverse(string $id): void
    {
        $this->requireCan('journal.reverse');
        $alasan = trim((string) input('alasan', ''));
        $tanggal = (string) input('tanggal', today());

        try {
            $reversalId = JournalService::balik((int) $id, $tanggal, $alasan);
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('journal/' . (int) $id);
        }

        $reversal = JournalService::find($reversalId);
        Audit::log('reverse', 'journal', [
            'ref_table'   => 'journal_entries',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) ($reversal['referensi'] ?? ''),
            'description' => 'Balik jurnal, dibuat ' . ($reversal['nomor'] ?? ''),
        ]);

        flash_success('Jurnal pembalik ' . ($reversal['nomor'] ?? '') . ' dibuat dan diposting.');
        redirect('journal/' . $reversalId);
    }

    public function destroy(string $id): void
    {
        $this->requireCan('journal.create');

        $journal = JournalService::find((int) $id);
        if ($journal === null) {
            App::abort(404, 'Jurnal tidak ditemukan.');
        }

        try {
            JournalService::hapus((int) $id);
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('journal/' . (int) $id);
        }

        Audit::log('delete', 'journal', [
            'ref_table'   => 'journal_entries',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $journal['nomor'],
            'description' => 'Hapus draft jurnal ' . $journal['nomor'],
        ]);

        flash_success('Draft jurnal ' . $journal['nomor'] . ' dihapus.');
        redirect('journal');
    }

    /* ---------------------------------------------------------- lampiran */

    public function upload(string $id): void
    {
        $this->requireCan('journal.create');

        $journal = JournalService::find((int) $id);
        if ($journal === null) {
            App::abort(404, 'Jurnal tidak ditemukan.');
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash_error('Berkas gagal diunggah. Periksa ukuran berkas (maksimal ' . round(((int) config('upload.max_size', 5242880)) / 1048576) . ' MB).');
            redirect('journal/' . (int) $id);
        }

        $maxSize = (int) config('upload.max_size', 5242880);
        if ((int) $file['size'] > $maxSize) {
            flash_error('Ukuran berkas melebihi batas ' . round($maxSize / 1048576) . ' MB.');
            redirect('journal/' . (int) $id);
        }

        $ext = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowed = (array) config('upload.allowed_ext', []);
        if (!in_array($ext, $allowed, true)) {
            flash_error('Tipe berkas .' . $ext . ' tidak diizinkan. Gunakan: ' . implode(', ', $allowed) . '.');
            redirect('journal/' . (int) $id);
        }

        $relativeDir = 'uploads/journal/' . date('Y') . '/' . date('m');
        $absoluteDir = BASE_PATH . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            flash_error('Folder unggahan tidak dapat dibuat. Periksa izin folder uploads/.');
            redirect('journal/' . (int) $id);
        }

        $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $absolutePath = $absoluteDir . '/' . $storedName;

        if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
            flash_error('Berkas gagal dipindahkan ke folder unggahan.');
            redirect('journal/' . (int) $id);
        }

        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $absolutePath) ?: null;
                finfo_close($finfo);
            }
        }

        Db::insert('attachments', [
            'module'      => 'journal',
            'ref_id'      => (int) $id,
            'file_name'   => mb_substr((string) $file['name'], 0, 255),
            'stored_name' => $storedName,
            'file_path'   => $relativeDir . '/' . $storedName,
            'mime_type'   => $mime !== null ? mb_substr($mime, 0, 100) : null,
            'file_size'   => (int) $file['size'],
            'uploaded_by' => auth_id() ?: null,
        ]);

        Audit::log('create', 'journal.attachment', [
            'ref_table'   => 'journal_entries',
            'ref_id'      => (int) $id,
            'ref_code'    => (string) $journal['nomor'],
            'description' => 'Unggah bukti transaksi ' . $file['name'],
        ]);

        flash_success('Bukti transaksi berhasil diunggah.');
        redirect('journal/' . (int) $id);
    }

    public function download(string $id, string $attachmentId): void
    {
        $this->requireCan('journal.view');

        $attachment = Db::one('SELECT * FROM attachments WHERE id = ? AND module = ? AND ref_id = ?', [(int) $attachmentId, 'journal', (int) $id]);
        if ($attachment === null) {
            App::abort(404, 'Lampiran tidak ditemukan.');
        }

        $path = BASE_PATH . '/' . ltrim((string) $attachment['file_path'], '/');
        $real = realpath($path);
        $root = realpath(BASE_PATH . '/uploads');
        if ($real === false || $root === false || !str_starts_with($real, $root) || !is_file($real)) {
            App::abort(404, 'Berkas lampiran tidak tersedia.');
        }

        header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($real));
        header('Content-Disposition: inline; filename="' . basename((string) $attachment['file_name']) . '"');
        readfile($real);
        exit;
    }

    public function deleteAttachment(string $id, string $attachmentId): void
    {
        $this->requireCan('journal.create');

        $attachment = Db::one('SELECT * FROM attachments WHERE id = ? AND module = ? AND ref_id = ?', [(int) $attachmentId, 'journal', (int) $id]);
        if ($attachment !== null) {
            $path = BASE_PATH . '/' . ltrim((string) $attachment['file_path'], '/');
            if (is_file($path)) {
                @unlink($path);
            }
            Db::exec('DELETE FROM attachments WHERE id = ?', [(int) $attachmentId]);
            Audit::log('delete', 'journal.attachment', [
                'ref_table'   => 'journal_entries',
                'ref_id'      => (int) $id,
                'description' => 'Hapus lampiran ' . $attachment['file_name'],
            ]);
            flash_success('Lampiran dihapus.');
        }

        redirect('journal/' . (int) $id);
    }

    /* ----------------------------------------------------------- internal */

    /** @param array<string,mixed> $journal */
    private function renderForm(?array $journal, array $header, array $lines, array $errors = []): void
    {
        $isEdit = $journal !== null;

        $this->page('pages/journal/form', [
            'pageTitle'    => $isEdit ? 'Ubah Jurnal ' . $journal['nomor'] : 'Jurnal Baru',
            'pageSubtitle' => $isEdit
                ? 'Perubahan hanya diizinkan selama jurnal masih draft atau ditolak.'
                : 'Nomor jurnal dibuat otomatis saat disimpan.',
            'breadcrumbs'  => [
                ['label' => 'Accounting', 'url' => null],
                ['label' => 'Jurnal Umum', 'url' => url('journal')],
                ['label' => $isEdit ? (string) $journal['nomor'] : 'Baru', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url('journal')) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'journal'      => $journal,
            'header'       => $header,
            'lines'        => $lines,
            'errors'       => $errors,
            'isEdit'       => $isEdit,
            'akunPerTipe'  => LedgerService::akunPerTipe(),
            'bidangList'   => Db::all('SELECT id, nama FROM ms_bidang ORDER BY nama'),
            'divisiList'   => Db::all('SELECT id, nama FROM ms_divisi ORDER BY nama'),
            'projectList'  => Db::all('SELECT id, nama FROM ms_projects ORDER BY nama'),
            'ccList'       => Db::all('SELECT id, nama FROM ms_cost_centers ORDER BY nama'),
            'partnerList'  => Db::all('SELECT id, nama FROM ms_partners ORDER BY nama'),
            'nomorPreview' => $isEdit ? (string) $journal['nomor'] : NumberingService::preview(JournalService::kodeDokumen($header['tipe'] ?? 'umum'), $header['tanggal'] ?? today()),
            'periode'      => PeriodService::forDate((string) ($header['tanggal'] ?? today())),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function emptyLines(int $count): array
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = $this->emptyLine();
        }
        return $lines;
    }

    /** @return array<string,mixed> */
    private function emptyLine(): array
    {
        return [
            'coa_id'         => 0,
            'deskripsi'      => '',
            'debit'          => 0.0,
            'kredit'         => 0.0,
            'bidang_id'      => null,
            'divisi_id'      => null,
            'project_id'     => null,
            'cost_center_id' => null,
            'partner_id'     => null,
        ];
    }

    private function guard(callable $action, string $success, int $journalId): void
    {
        try {
            $action();
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
            redirect('journal/' . $journalId);
        }

        flash_success($success);
        redirect('journal/' . $journalId);
    }
}
