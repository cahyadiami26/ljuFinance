<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Auth;
use Lju\Core\Db;

/**
 * Approval Center: antrean persetujuan lintas modul, matriks alur
 * (nominal × bidang/divisi × level), delegasi, SLA, dan riwayat aksi.
 *
 * Modul transaksi mendaftarkan dokumennya lewat `daftarkan()` saat diajukan,
 * dan menutup permintaan lewat `selesaikanOlehModul()` saat disetujui/ditolak
 * dari dalam modul. Keputusan dari Approval Center menyinkronkan status
 * dokumen sumber pada tabel yang diizinkan.
 */
final class ApprovalService
{
    public const MODULES = [
        'journal'          => 'Jurnal Umum',
        'cash_transaction' => 'Kas & Bank',
        'cash_advance'     => 'Uang Muka',
        'expense'          => 'Pengajuan Biaya',
        'ap_invoice'       => 'Tagihan Vendor',
        'ap_payment'       => 'Pembayaran Hutang',
        'ar_invoice'       => 'Invoice Piutang',
        'budget'           => 'RKAP',
        'revenue'          => 'Pengakuan Pendapatan',
        'asset_disposal'   => 'Penghapusan Aset',
    ];

    public const STATUS = [
        'pending'   => 'Menunggu',
        'approved'  => 'Disetujui',
        'rejected'  => 'Ditolak',
        'returned'  => 'Dikembalikan',
        'cancelled' => 'Dibatalkan',
        'expired'   => 'Kedaluwarsa',
    ];

    public const AKSI = [
        'approve'  => 'Setujui',
        'reject'   => 'Tolak',
        'return'   => 'Kembalikan',
        'delegate' => 'Delegasikan',
    ];

    /**
     * Peta tabel sumber per modul: status tujuan untuk approve/reject/return.
     *
     * @var array<string,array{table:string,approve:string,reject:string,return:string}>
     */
    private const SUMBER = [
        'journal'       => ['table' => 'journal_entries', 'approve' => 'approved', 'reject' => 'rejected', 'return' => 'draft'],
        'cash_transaction' => ['table' => 'cash_transactions', 'approve' => 'approved', 'reject' => 'rejected', 'return' => 'draft'],
        'cash_advance'  => ['table' => 'cash_advances', 'approve' => 'approved', 'reject' => 'rejected', 'return' => 'draft'],
        'expense'       => ['table' => 'expense_requests', 'approve' => 'approved', 'reject' => 'rejected', 'return' => 'draft'],
        'ap_invoice'    => ['table' => 'ap_invoices', 'approve' => 'approved', 'reject' => 'draft', 'return' => 'draft'],
        'ar_invoice'    => ['table' => 'ar_invoices', 'approve' => 'approved', 'reject' => 'draft', 'return' => 'draft'],
        'revenue'       => ['table' => 'revenue_recognitions', 'approve' => 'approved', 'reject' => 'draft', 'return' => 'draft'],
        'budget'        => ['table' => 'budgets', 'approve' => 'approved', 'reject' => 'rejected', 'return' => 'draft'],
    ];

    /**
     * Daftarkan dokumen ke antrean persetujuan (idempotent per modul + ref).
     *
     * @param array{nomor?:string,judul?:string,amount?:float|int|string,bidang_id?:int|null,divisi_id?:int|null,project_id?:int|null,requested_by?:int|null} $meta
     */
    public static function daftarkan(string $module, int $refId, array $meta): void
    {
        if (!isset(self::MODULES[$module]) || $refId <= 0) {
            return;
        }

        try {
            $amount = JournalService::uang($meta['amount'] ?? 0);
            $bidangId = !empty($meta['bidang_id']) ? (int) $meta['bidang_id'] : null;
            $divisiId = !empty($meta['divisi_id']) ? (int) $meta['divisi_id'] : null;

            $flows = Db::all(
                'SELECT level, sla_jam FROM approval_flows
                 WHERE module = ? AND is_active = 1
                   AND (min_amount = 0 OR min_amount <= ?)
                   AND (max_amount = 0 OR max_amount >= ?)
                   AND (bidang_id IS NULL OR bidang_id = ?)
                   AND (divisi_id IS NULL OR divisi_id = ?)
                 ORDER BY level',
                [$module, $amount, $amount, $bidangId ?? 0, $divisiId ?? 0]
            );

            $maxLevel = 1;
            $sla = (int) setting('approval_sla_hours', 24);
            if ($flows !== []) {
                $maxLevel = max(array_map(static fn ($row) => (int) $row['level'], $flows));
                foreach ($flows as $flow) {
                    if ((int) $flow['level'] === 1) {
                        $sla = (int) $flow['sla_jam'] ?: $sla;
                        break;
                    }
                }
            }

            Db::exec(
                "INSERT INTO approval_requests
                    (module, ref_id, nomor_dokumen, judul, amount, bidang_id, divisi_id, project_id,
                     requested_by, current_level, max_level, status, deadline)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'pending', DATE_ADD(NOW(), INTERVAL ? HOUR))
                 ON DUPLICATE KEY UPDATE
                    judul = VALUES(judul), amount = VALUES(amount), bidang_id = VALUES(bidang_id),
                    divisi_id = VALUES(divisi_id), project_id = VALUES(project_id),
                    current_level = 1, max_level = VALUES(max_level), status = 'pending',
                    deadline = VALUES(deadline), completed_at = NULL",
                [
                    $module,
                    $refId,
                    mb_substr((string) ($meta['nomor'] ?? ('#' . $refId)), 0, 50),
                    mb_substr((string) ($meta['judul'] ?? self::MODULES[$module]), 0, 200),
                    number_format($amount, 2, '.', ''),
                    $bidangId,
                    $divisiId,
                    !empty($meta['project_id']) ? (int) $meta['project_id'] : null,
                    !empty($meta['requested_by']) ? (int) $meta['requested_by'] : (auth_id() ?: null),
                    $maxLevel,
                    max(1, $sla),
                ]
            );

            $requestId = (int) Db::val(
                'SELECT id FROM approval_requests WHERE module = ? AND ref_id = ?',
                [$module, $refId]
            );
            if ($requestId > 0) {
                self::notifikasiAntrean($module, $requestId, (string) ($meta['nomor'] ?? ''), (string) ($meta['judul'] ?? ''), $bidangId, $divisiId);
            }
        } catch (\Throwable) {
            // Antrean persetujuan tidak boleh memblokir alur transaksi modul.
        }
    }

    /**
     * Kirim notifikasi ke pejabat level 1 (best-effort).
     */
    private static function notifikasiAntrean(
        string $module,
        int $requestId,
        string $nomor,
        string $judul,
        ?int $bidangId,
        ?int $divisiId
    ): void {
        try {
            $flows = Db::all(
                'SELECT approver_user_id, approver_role_id FROM approval_flows
                 WHERE module = ? AND level = 1 AND is_active = 1
                   AND (bidang_id IS NULL OR bidang_id = ?)
                   AND (divisi_id IS NULL OR divisi_id = ?)',
                [$module, $bidangId ?? 0, $divisiId ?? 0]
            );

            $penerima = [];
            foreach ($flows as $flow) {
                if (!empty($flow['approver_user_id'])) {
                    $penerima[] = (int) $flow['approver_user_id'];
                }
                if (!empty($flow['approver_role_id'])) {
                    foreach (Db::all(
                        'SELECT user_id FROM app_user_roles WHERE role_id = ?',
                        [(int) $flow['approver_role_id']]
                    ) as $user) {
                        $penerima[] = (int) $user['user_id'];
                    }
                }
            }

            self::kirimNotifikasi(
                $penerima,
                'approval',
                'Permintaan persetujuan baru',
                trim($nomor . ' — ' . $judul),
                'approval/' . $requestId
            );
        } catch (\Throwable) {
            // notifikasi opsional
        }
    }

    /**
     * @param array<int,int> $userIds
     */
    private static function kirimNotifikasi(array $userIds, string $tipe, string $judul, string $isi, string $link): void
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn ($id) => (int) $id > 0)));
        if ($userIds === []) {
            return;
        }

        try {
            foreach (array_slice($userIds, 0, 50) as $userId) {
                Db::insert('notifications', [
                    'user_id' => $userId,
                    'type'    => $tipe,
                    'title'   => mb_substr($judul, 0, 150),
                    'body'    => mb_substr($isi, 0, 500),
                    'link'    => mb_substr($link, 0, 255),
                ]);
            }
        } catch (\Throwable) {
            // abaikan bila tabel notifikasi belum tersedia
        }
    }

    /** Tutup permintaan ketika modul menyelesaikan persetujuannya sendiri. */
    public static function selesaikanOlehModul(string $module, int $refId, string $status = 'approved'): void
    {
        if (!isset(self::MODULES[$module])) {
            return;
        }
        if (!in_array($status, ['approved', 'rejected', 'returned', 'cancelled'], true)) {
            $status = 'approved';
        }
        try {
            Db::exec(
                "UPDATE approval_requests SET status = ?, completed_at = NOW()
                 WHERE module = ? AND ref_id = ? AND status = 'pending'",
                [$status, $module, $refId]
            );
        } catch (\Throwable) {
            // diamkan
        }
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT r.*, u.name AS requested_by_name, b.nama AS bidang_nama, d.nama AS divisi_nama,
                    p.nama AS project_nama,
                    (SELECT MAX(level) FROM approval_actions a WHERE a.request_id = r.id) AS level_terakhir
             FROM approval_requests r
             LEFT JOIN app_users u ON u.id = r.requested_by
             LEFT JOIN ms_bidang b ON b.id = r.bidang_id
             LEFT JOIN ms_divisi d ON d.id = r.divisi_id
             LEFT JOIN ms_projects p ON p.id = r.project_id
             WHERE r.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function actions(int $requestId): array
    {
        return Db::all(
            'SELECT a.*, u.name AS approver_nama, d.name AS delegated_nama
             FROM approval_actions a
             LEFT JOIN app_users u ON u.id = a.approver_user_id
             LEFT JOIN app_users d ON d.id = a.delegated_to_user_id
             WHERE a.request_id = ?
             ORDER BY a.acted_at, a.id',
            [$requestId]
        );
    }

    /**
     * @param array{status?:string,module?:string,q?:string,overdue?:bool} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function antrian(array $filters = [], int $limit = 200): array
    {
        $where = ['1 = 1'];
        $params = [];

        $status = (string) ($filters['status'] ?? 'pending');
        if ($status !== '' && array_key_exists($status, self::STATUS)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        $module = (string) ($filters['module'] ?? '');
        if ($module !== '' && isset(self::MODULES[$module])) {
            $where[] = 'r.module = ?';
            $params[] = $module;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(r.nomor_dokumen LIKE ? OR r.judul LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%');
        }
        if (!empty($filters['overdue'])) {
            $where[] = "r.status = 'pending' AND r.deadline IS NOT NULL AND r.deadline < NOW()";
        }

        return Db::all(
            'SELECT r.*, u.name AS requested_by_name, b.nama AS bidang_nama, d.nama AS divisi_nama
             FROM approval_requests r
             LEFT JOIN app_users u ON u.id = r.requested_by
             LEFT JOIN ms_bidang b ON b.id = r.bidang_id
             LEFT JOIN ms_divisi d ON d.id = r.divisi_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY (r.status = \'pending\') DESC, r.deadline IS NULL, r.deadline, r.created_at DESC
             LIMIT ' . max(1, min(500, $limit)),
            $params
        );
    }

    /** @return array<string,mixed> */
    public static function statistik(): array
    {
        $row = Db::one(
            "SELECT
                COALESCE(SUM(status = 'pending'),0) AS pending,
                COALESCE(SUM(status = 'pending' AND deadline IS NOT NULL AND deadline < NOW()),0) AS terlambat,
                COALESCE(SUM(status = 'approved' AND DATE(completed_at) = CURDATE()),0) AS disetujui_hari_ini,
                COALESCE(SUM(status = 'rejected' AND DATE(completed_at) = CURDATE()),0) AS ditolak_hari_ini,
                COALESCE(AVG(CASE WHEN status IN ('approved','rejected') AND completed_at IS NOT NULL
                                  THEN TIMESTAMPDIFF(HOUR, created_at, completed_at) END),0) AS rata_jam
             FROM approval_requests"
        );

        $total = (int) Db::val('SELECT COUNT(*) FROM approval_requests');
        $selesai = (int) Db::val("SELECT COUNT(*) FROM approval_requests WHERE status IN ('approved','rejected')");

        return [
            'pending'    => (int) ($row['pending'] ?? 0),
            'terlambat'  => (int) ($row['terlambat'] ?? 0),
            'hari_ini'   => (int) ($row['disetujui_hari_ini'] ?? 0),
            'ditolak'    => (int) ($row['ditolak_hari_ini'] ?? 0),
            'rata_jam'   => round((float) ($row['rata_jam'] ?? 0), 1),
            'total'      => $total,
            'selesai'    => $selesai,
        ];
    }

    /**
     * Proses keputusan atas permintaan.
     *
     * @param array{catatan?:string,delegasi_ke?:int|null} $input
     */
    public static function putuskan(int $id, string $aksi, array $input = []): array
    {
        if (!isset(self::AKSI[$aksi])) {
            throw new \RuntimeException('Aksi persetujuan tidak dikenal.');
        }

        return Db::transaction(static function () use ($id, $aksi, $input): array {
            $row = Db::one('SELECT * FROM approval_requests WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('Permintaan persetujuan tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'pending') {
                throw new \RuntimeException('Permintaan ini sudah diproses (' . (self::STATUS[(string) $row['status']] ?? $row['status']) . ').');
            }

            if (in_array($aksi, ['approve', 'reject', 'return'], true)) {
                self::pastikanBerwenang($row, (int) $row['current_level']);
            }

            $catatan = trim((string) ($input['catatan'] ?? ''));
            $level = (int) $row['current_level'];
            $delegasiKe = !empty($input['delegasi_ke']) ? (int) $input['delegasi_ke'] : null;

            if ($aksi === 'delegate' && $delegasiKe === null) {
                throw new \RuntimeException('Pilih pejabat penerima delegasi.');
            }
            if ($delegasiKe !== null && $delegasiKe === (int) (auth_id() ?: 0)) {
                throw new \RuntimeException('Tidak dapat mendelegasikan kepada diri sendiri.');
            }

            Db::insert('approval_actions', [
                'request_id'            => $id,
                'level'                 => $level,
                'approver_user_id'      => auth_id() ?: null,
                'action'                => $aksi,
                'notes'                 => $catatan !== '' ? mb_substr($catatan, 0, 255) : null,
                'delegated_to_user_id'  => $delegasiKe,
            ]);

            $statusBaru = 'pending';
            $pesan = '';

            if ($aksi === 'approve') {
                if ($level < (int) $row['max_level']) {
                    Db::exec('UPDATE approval_requests SET current_level = ? WHERE id = ?', [$level + 1, $id]);
                    $pesan = 'Disetujui pada level ' . $level . '; menunggu level ' . ($level + 1) . '.';
                } else {
                    $statusBaru = 'approved';
                    Db::exec(
                        'UPDATE approval_requests SET status = ?, completed_at = NOW() WHERE id = ?',
                        ['approved', $id]
                    );
                    self::sinkronSumber((string) $row['module'], (int) $row['ref_id'], 'approve');
                    $pesan = 'Permintaan disetujui penuh.';
                }
            } elseif ($aksi === 'reject') {
                $statusBaru = 'rejected';
                Db::exec('UPDATE approval_requests SET status = ?, completed_at = NOW() WHERE id = ?', ['rejected', $id]);
                self::sinkronSumber((string) $row['module'], (int) $row['ref_id'], 'reject');
                $pesan = 'Permintaan ditolak.';
            } elseif ($aksi === 'return') {
                $statusBaru = 'returned';
                Db::exec(
                    'UPDATE approval_requests SET status = ?, current_level = 1, completed_at = NOW() WHERE id = ?',
                    ['returned', $id]
                );
                self::sinkronSumber((string) $row['module'], (int) $row['ref_id'], 'return');
                $pesan = 'Permintaan dikembalikan ke pengaju.';
            } else {
                $pesan = 'Delegasi dicatat untuk ' . ($delegasiKe !== null ? 'user #' . $delegasiKe : 'pejabat lain') . '.';

                if ($delegasiKe !== null) {
                    self::kirimNotifikasi(
                        [$delegasiKe],
                        'info',
                        'Delegasi persetujuan untuk Anda',
                        trim((string) $row['nomor_dokumen'] . ' — ' . (string) $row['judul']),
                        'approval/' . $id
                    );
                }
            }

            if (in_array($statusBaru, ['approved', 'rejected', 'returned'], true) && !empty($row['requested_by'])) {
                self::kirimNotifikasi(
                    [(int) $row['requested_by']],
                    $statusBaru === 'approved' ? 'success' : ($statusBaru === 'rejected' ? 'warning' : 'info'),
                    'Keputusan persetujuan: ' . (self::STATUS[$statusBaru] ?? $statusBaru),
                    trim((string) $row['nomor_dokumen'] . ' — ' . (string) $row['judul']),
                    'approval/' . $id
                );
            }

            Audit::log($aksi === 'approve' ? 'approve' : ($aksi === 'reject' ? 'reject' : 'update'), 'approval', [
                'ref_table'   => 'approval_requests',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['nomor_dokumen'],
                'description' => 'Approval Center: ' . (string) (self::AKSI[$aksi]) . ' ' . $row['nomor_dokumen']
                    . ($catatan !== '' ? ' — ' . $catatan : ''),
                'old'         => ['status' => $row['status'], 'level' => $level],
                'new'         => ['status' => $statusBaru, 'level' => $aksi === 'approve' ? $level + 1 : $level],
            ]);

            return ['status' => $statusBaru, 'pesan' => $pesan];
        });
    }

    /** Batalkan permintaan oleh pengaju (selama masih menunggu). */
    public static function batalkan(int $id): void
    {
        $row = Db::one('SELECT status FROM approval_requests WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Permintaan persetujuan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'pending') {
            throw new \RuntimeException('Hanya permintaan yang masih menunggu dapat dibatalkan.');
        }
        Db::exec("UPDATE approval_requests SET status = 'cancelled', completed_at = NOW() WHERE id = ?", [$id]);
    }

    /**
     * Pastikan pengguna berwenang memutuskan level aktif.
     *
     * Berwenang bila: (a) pengguna adalah approver yang ditunjuk alur pada level
     * tersebut, (b) perannya sesuai `approver_role_id`, (c) menerima delegasi
     * aktif dari approver tersebut, atau (d) Super Administrator.
     *
     * @param array<string,mixed> $row
     */
    private static function pastikanBerwenang(array $row, int $level): void
    {
        if (Auth::hasRole('super_admin')) {
            return;
        }

        $flows = Db::all(
            'SELECT approver_user_id, approver_role_id FROM approval_flows
             WHERE module = ? AND level = ? AND is_active = 1
               AND (min_amount = 0 OR min_amount <= ?)
               AND (max_amount = 0 OR max_amount >= ?)
               AND (bidang_id IS NULL OR bidang_id = ?)
               AND (divisi_id IS NULL OR divisi_id = ?)',
            [
                (string) $row['module'],
                $level,
                (float) $row['amount'],
                (float) $row['amount'],
                (int) ($row['bidang_id'] ?? 0),
                (int) ($row['divisi_id'] ?? 0),
            ]
        );

        if ($flows === []) {
            return; // modul tanpa matriks alur: cukup izin approval.act
        }

        $userId = Auth::id();
        $roles = [];
        foreach (Db::all('SELECT role_id FROM app_user_roles WHERE user_id = ?', [$userId]) as $item) {
            $roles[] = (int) $item['role_id'];
        }

        $userIdDicek = [];
        foreach ($flows as $flow) {
            $approver = (int) ($flow['approver_user_id'] ?? 0);
            $roleId = (int) ($flow['approver_role_id'] ?? 0);

            if ($approver > 0) {
                $userIdDicek[] = $approver;
                if ($approver === $userId) {
                    return;
                }
            }
            if ($roleId > 0 && in_array($roleId, $roles, true)) {
                return;
            }
        }

        if ($userIdDicek !== []) {
            $in = implode(',', array_fill(0, count($userIdDicek), '?'));
            $delegasi = Db::val(
                "SELECT COUNT(*) FROM approval_delegations
                 WHERE to_user_id = ? AND is_active = 1
                   AND CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai
                   AND (module IS NULL OR module = '' OR module = ?)
                   AND from_user_id IN ($in)",
                array_merge([$userId, (string) $row['module']], $userIdDicek)
            );
            if ((int) $delegasi > 0) {
                return;
            }
        }

        throw new \RuntimeException('Anda bukan pejabat berwenang untuk level ' . $level . ' pada permintaan ini.');
    }

    /** Sinkronkan status dokumen sumber setelah keputusan dari Approval Center. */
    private static function sinkronSumber(string $module, int $refId, string $aksi): void
    {
        if (!isset(self::SUMBER[$module])) {
            return;
        }
        $peta = self::SUMBER[$module];
        $status = $peta[$aksi] ?? null;
        if ($status === null) {
            return;
        }

        try {
            $kolom = [];
            try {
                foreach (Db::all('SHOW COLUMNS FROM `' . $peta['table'] . '`') as $col) {
                    $kolom[] = (string) $col['Field'];
                }
            } catch (\Throwable) {
                $kolom = [];
            }

            $set = ['status = ?'];
            $params = [$status];

            if ($aksi === 'approve' && in_array('approved_by', $kolom, true)) {
                $set[] = 'approved_by = ?';
                $params[] = auth_id() ?: null;
            }
            if ($aksi === 'approve' && in_array('approved_at', $kolom, true)) {
                $set[] = 'approved_at = ?';
                $params[] = now();
            }

            $params[] = $refId;
            Db::exec(
                'UPDATE `' . $peta['table'] . '` SET ' . implode(', ', $set) . ' WHERE id = ?',
                $params
            );
        } catch (\Throwable) {
            // Sinkronisasi bersifat best-effort; dokumen tetap dapat diproses di modulnya.
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function delegations(bool $hanyaAktif = false): array
    {
        $where = $hanyaAktif ? "WHERE d.is_active = 1 AND CURDATE() BETWEEN d.tanggal_mulai AND d.tanggal_selesai" : '';

        return Db::all(
            'SELECT d.*, f.name AS from_nama, t.name AS to_nama, c.name AS created_by_name
             FROM approval_delegations d
             LEFT JOIN app_users f ON f.id = d.from_user_id
             LEFT JOIN app_users t ON t.id = d.to_user_id
             LEFT JOIN app_users c ON c.id = d.created_by
             ' . $where . '
             ORDER BY d.tanggal_mulai DESC, d.id DESC'
        );
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,string> error validasi
     */
    public static function simpanDelegasi(array $input): array
    {
        $errors = [];
        $dari = (int) ($input['from_user_id'] ?? 0);
        $ke = (int) ($input['to_user_id'] ?? 0);
        $mulai = trim((string) ($input['tanggal_mulai'] ?? ''));
        $selesai = trim((string) ($input['tanggal_selesai'] ?? ''));

        if ($dari <= 0 || $ke <= 0) {
            $errors['from_user_id'] = 'Pejabat asal dan penerima delegasi wajib dipilih.';
        } elseif ($dari === $ke) {
            $errors['to_user_id'] = 'Penerima delegasi tidak boleh sama dengan pejabat asal.';
        }
        if (strtotime($mulai) === false || strtotime($selesai) === false) {
            $errors['tanggal_mulai'] = 'Rentang tanggal delegasi tidak valid.';
        } elseif ($selesai < $mulai) {
            $errors['tanggal_selesai'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        if ($errors !== []) {
            return $errors;
        }

        Db::insert('approval_delegations', [
            'from_user_id'    => $dari,
            'to_user_id'      => $ke,
            'module'          => trim((string) ($input['module'] ?? '')) ?: null,
            'tanggal_mulai'   => $mulai,
            'tanggal_selesai' => $selesai,
            'alasan'          => trim((string) ($input['alasan'] ?? '')) ?: null,
            'is_active'       => 1,
            'created_by'      => auth_id() ?: null,
        ]);

        Audit::log('create', 'approval', [
            'ref_table'   => 'approval_delegations',
            'ref_code'    => 'delegasi-' . $dari . '-' . $ke,
            'description' => 'Buat delegasi persetujuan',
        ]);

        return [];
    }

    public static function tutupDelegasi(int $id): void
    {
        $row = Db::one('SELECT from_user_id, to_user_id FROM approval_delegations WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Delegasi tidak ditemukan.');
        }
        Db::exec('UPDATE approval_delegations SET is_active = 0 WHERE id = ?', [$id]);
    }
}
