<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Hutang usaha (AP) — tagihan vendor, persetujuan, posting jurnal,
 * pembayaran sebagian/penuh, dan aging hutang.
 *
 * Jurnal tagihan : D beban/aset (rincian) + D PPN Masukan, K Utang Usaha.
 * Jurnal bayar   : D Utang Usaha (nilai dilunasi), K Bank (kas keluar),
 *                  K Utang PPh (potongan pajak).
 */
final class ApService
{
    public const STATUS = [
        'draft'     => 'Draft',
        'submitted' => 'Diajukan',
        'approved'  => 'Disetujui',
        'posted'    => 'Posted',
        'partial'   => 'Dibayar Sebagian',
        'paid'      => 'Lunas',
        'overdue'   => 'Jatuh Tempo',
        'void'      => 'Dibatalkan',
    ];

    public const METODE = [
        'transfer' => 'Transfer Bank',
        'tunai'    => 'Tunai',
        'giro'     => 'Cek / Giro',
        'lain'     => 'Lainnya',
    ];

    private const BUCKETS = [
        'current' => 'Belum Jatuh Tempo',
        'b1_30'   => '1–30 Hari',
        'b31_60'  => '31–60 Hari',
        'b61_90'  => '61–90 Hari',
        'b90'     => '> 90 Hari',
    ];

    public static function buckets(): array
    {
        return self::BUCKETS;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT a.*, p.nama AS partner_nama, p.kode AS partner_kode, p.npwp, p.termin_hari AS partner_termin,
                    pr.nama AS project_nama, bd.nama AS bidang_nama, dv.nama AS divisi_nama, cc.nama AS cost_center_nama,
                    u.name AS created_by_name, ap.name AS approved_by_name,
                    j.nomor AS journal_nomor, j.status AS journal_status
             FROM ap_invoices a
             LEFT JOIN ms_partners p ON p.id = a.partner_id
             LEFT JOIN ms_projects pr ON pr.id = a.project_id
             LEFT JOIN ms_bidang bd ON bd.id = a.bidang_id
             LEFT JOIN ms_divisi dv ON dv.id = a.divisi_id
             LEFT JOIN ms_cost_centers cc ON cc.id = a.cost_center_id
             LEFT JOIN app_users u ON u.id = a.created_by
             LEFT JOIN app_users ap ON ap.id = a.approved_by
             LEFT JOIN journal_entries j ON j.id = a.journal_id
             WHERE a.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $id): array
    {
        return Db::all(
            'SELECT i.*, c.kode AS coa_kode, c.nama AS coa_nama
             FROM ap_invoice_items i
             JOIN coa_accounts c ON c.id = i.coa_id
             WHERE i.invoice_id = ?
             ORDER BY i.id',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function payments(int $id): array
    {
        return Db::all(
            'SELECT pay.*, b.nama AS bank_nama, b.kode AS bank_kode, j.nomor AS journal_nomor,
                    u.name AS created_by_name
             FROM ap_payments pay
             LEFT JOIN ms_bank_accounts b ON b.id = pay.bank_account_id
             LEFT JOIN journal_entries j ON j.id = pay.journal_id
             LEFT JOIN app_users u ON u.id = pay.created_by
             WHERE pay.invoice_id = ?
             ORDER BY pay.tanggal, pay.id',
            [$id]
        );
    }

    /**
     * Status efektif: tagihan posted/partial yang lewat jatuh tempo ditampilkan overdue.
     *
     * @param array<string,mixed> $row
     */
    public static function statusEfektif(array $row): string
    {
        $status = (string) ($row['status'] ?? 'draft');
        $sisa = (float) ($row['sisa'] ?? 0);
        $due = (string) ($row['tanggal_jatuh_tempo'] ?? '');

        if (in_array($status, ['posted', 'partial', 'overdue'], true) && $sisa > 0.005 && $due !== '' && strtotime($due) < strtotime(today())) {
            return 'overdue';
        }
        if ($status === 'overdue') {
            return $sisa <= 0.005 ? 'paid' : 'posted';
        }
        return $status;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalisasi(array $input): array
    {
        $tanggal = trim((string) ($input['tanggal'] ?? '')) ?: today();
        $termin = (int) ($input['termin_hari'] ?? 30);
        $jatuhTempo = trim((string) ($input['tanggal_jatuh_tempo'] ?? ''));

        if ($jatuhTempo === '' && strtotime($tanggal) !== false) {
            $jatuhTempo = date('Y-m-d', strtotime($tanggal . ' +' . max(0, $termin) . ' days'));
        }

        return [
            'nomor_supplier'      => trim((string) ($input['nomor_supplier'] ?? '')),
            'partner_id'          => (int) ($input['partner_id'] ?? 0),
            'tanggal'             => $tanggal,
            'tanggal_jatuh_tempo' => $jatuhTempo,
            'termin_hari'         => max(0, min(365, $termin)),
            'termin_ke'           => (int) ($input['termin_ke'] ?? 0) ?: null,
            'project_id'          => (int) ($input['project_id'] ?? 0) ?: null,
            'bidang_id'           => (int) ($input['bidang_id'] ?? 0) ?: null,
            'divisi_id'           => (int) ($input['divisi_id'] ?? 0) ?: null,
            'cost_center_id'      => (int) ($input['cost_center_id'] ?? 0) ?: null,
            'keterangan'          => trim((string) ($input['keterangan'] ?? '')),
            'items'               => self::normalisasiItems($input['items'] ?? []),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    public static function normalisasiItems(array $items): array
    {
        $hasil = [];
        foreach ($items as $item) {
            $keterangan = trim((string) ($item['keterangan'] ?? ''));
            $coaId = (int) ($item['coa_id'] ?? 0);
            $jumlah = JournalService::uang($item['jumlah'] ?? 0);
            $ppn = JournalService::uang($item['ppn'] ?? 0);

            if ($keterangan === '' && $coaId <= 0 && $jumlah <= 0) {
                continue;
            }

            $hasil[] = [
                'keterangan' => $keterangan,
                'coa_id'     => $coaId,
                'jumlah'     => $jumlah,
                'ppn'        => max(0.0, $ppn),
                'total'      => round($jumlah + max(0.0, $ppn), 2),
            ];
        }

        return $hasil;
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasi(array $data): array
    {
        $errors = [];

        $partner = $data['partner_id'] > 0 ? Db::one('SELECT * FROM ms_partners WHERE id = ?', [$data['partner_id']]) : null;
        if ($partner === null) {
            $errors['partner_id'] = 'Vendor wajib dipilih.';
        } elseif ((int) $partner['is_active'] !== 1) {
            $errors['partner_id'] = 'Vendor tidak aktif.';
        } elseif (!in_array((string) $partner['tipe'], ['vendor', 'both'], true)) {
            $errors['partner_id'] = 'Partner ini bukan vendor.';
        }

        if (strtotime((string) $data['tanggal']) === false) {
            $errors['tanggal'] = 'Tanggal tagihan tidak valid.';
        } else {
            $periode = PeriodService::forDate((string) $data['tanggal']);
            if ($periode === null) {
                $errors['tanggal'] = 'Periode akuntansi belum tersedia.';
            } elseif ((string) $periode['status'] !== 'open') {
                $errors['tanggal'] = 'Periode ' . $periode['nama'] . ' sudah ditutup/dikunci.';
            }
        }

        if (strtotime((string) $data['tanggal_jatuh_tempo']) === false) {
            $errors['tanggal_jatuh_tempo'] = 'Tanggal jatuh tempo tidak valid.';
        } elseif ($data['tanggal_jatuh_tempo'] < $data['tanggal']) {
            $errors['tanggal_jatuh_tempo'] = 'Jatuh tempo tidak boleh sebelum tanggal tagihan.';
        }

        if ($data['items'] === []) {
            $errors['items'] = 'Minimal satu rincian tagihan harus diisi.';
            return $errors;
        }

        $subtotal = 0.0;
        foreach ($data['items'] as $index => $item) {
            $n = $index + 1;
            if ($item['keterangan'] === '') {
                $errors['items'] = 'Rincian #' . $n . ': keterangan wajib diisi.';
                break;
            }
            if ($item['coa_id'] <= 0) {
                $errors['items'] = 'Rincian #' . $n . ': akun COA wajib dipilih.';
                break;
            }
            if (!self::coaPosting($item['coa_id'])) {
                $errors['items'] = 'Rincian #' . $n . ': akun COA tidak boleh menerima posting.';
                break;
            }
            if ($item['jumlah'] <= 0) {
                $errors['items'] = 'Rincian #' . $n . ': jumlah harus lebih besar dari nol.';
                break;
            }
            $subtotal += $item['jumlah'];
        }

        if (!isset($errors['items']) && $subtotal <= 0) {
            $errors['items'] = 'Subtotal tagihan harus lebih besar dari nol.';
        }

        return $errors;
    }

    private static function coaPosting(int $coaId): bool
    {
        return (int) Db::val('SELECT COUNT(*) FROM coa_accounts WHERE id = ? AND allow_posting = 1 AND is_active = 1', [$coaId]) > 0;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array{subtotal:float,ppn:float,total:float}
     */
    public static function hitung(array $items): array
    {
        $subtotal = 0.0;
        $ppn = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['jumlah'];
            $ppn += (float) $item['ppn'];
        }
        $subtotal = round($subtotal, 2);
        $ppn = round($ppn, 2);

        return ['subtotal' => $subtotal, 'ppn' => $ppn, 'total' => round($subtotal + $ppn, 2)];
    }

    /** @param array<string,mixed> $data */
    public static function simpan(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            $hitung = self::hitung($data['items']);

            $payload = [
                'nomor_supplier'      => $data['nomor_supplier'] !== '' ? mb_substr((string) $data['nomor_supplier'], 0, 80) : null,
                'partner_id'          => (int) $data['partner_id'],
                'tanggal'             => $data['tanggal'],
                'tanggal_jatuh_tempo' => $data['tanggal_jatuh_tempo'],
                'termin_hari'         => (int) $data['termin_hari'],
                'termin_ke'           => $data['termin_ke'],
                'project_id'          => $data['project_id'],
                'bidang_id'           => $data['bidang_id'],
                'divisi_id'           => $data['divisi_id'],
                'cost_center_id'      => $data['cost_center_id'],
                'subtotal'            => number_format($hitung['subtotal'], 2, '.', ''),
                'ppn'                 => number_format($hitung['ppn'], 2, '.', ''),
                'pph'                 => '0.00',
                'total'               => number_format($hitung['total'], 2, '.', ''),
                'keterangan'          => $data['keterangan'] !== '' ? mb_substr((string) $data['keterangan'], 0, 255) : null,
            ];

            if ($id === null) {
                $payload['nomor'] = NumberingService::next('AP', (string) $data['tanggal']);
                $payload['status'] = 'draft';
                $payload['dibayar'] = '0.00';
                $payload['sisa'] = number_format($hitung['total'], 2, '.', '');
                $payload['created_by'] = auth_id() ?: null;
                $id = Db::insert('ap_invoices', $payload);
            } else {
                $row = Db::one('SELECT dibayar FROM ap_invoices WHERE id = ?', [$id]);
                $dibayar = (float) ($row['dibayar'] ?? 0);
                $payload['sisa'] = number_format($hitung['total'] - $dibayar, 2, '.', '');
                Db::update('ap_invoices', $payload, 'id = :id', [':id' => $id]);
            }

            Db::exec('DELETE FROM ap_invoice_items WHERE invoice_id = ?', [$id]);
            foreach ($data['items'] as $item) {
                Db::insert('ap_invoice_items', [
                    'invoice_id' => $id,
                    'keterangan' => mb_substr((string) $item['keterangan'], 0, 255),
                    'coa_id'     => (int) $item['coa_id'],
                    'jumlah'     => number_format((float) $item['jumlah'], 2, '.', ''),
                    'ppn'        => number_format((float) $item['ppn'], 2, '.', ''),
                    'total'      => number_format((float) $item['total'], 2, '.', ''),
                ]);
            }

            return $id;
        });
    }

    public static function ajukan(int $id): void
    {
        $row = Db::one('SELECT status FROM ap_invoices WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Tagihan tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft'], true)) {
            throw new \RuntimeException('Hanya draft yang dapat diajukan.');
        }
        Db::exec('UPDATE ap_invoices SET status = ? WHERE id = ?', ['submitted', $id]);
    }

    public static function setujui(int $id): void
    {
        $row = Db::one('SELECT status FROM ap_invoices WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Tagihan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya tagihan yang diajukan dapat disetujui.');
        }
        Db::exec('UPDATE ap_invoices SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?', [
            'approved', auth_id() ?: null, now(), $id,
        ]);
    }

    public static function tolak(int $id, string $alasan = ''): void
    {
        $row = Db::one('SELECT nomor, status FROM ap_invoices WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Tagihan tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'submitted') {
            throw new \RuntimeException('Hanya tagihan yang diajukan dapat ditolak.');
        }
        Db::exec('UPDATE ap_invoices SET status = ? WHERE id = ?', ['draft', $id]);

        Audit::log('reject', 'ap', [
            'ref_table'   => 'ap_invoices',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['nomor'],
            'description' => 'Tolak tagihan vendor: ' . ($alasan !== '' ? $alasan : 'tanpa alasan'),
        ]);
    }

    /** Posting tagihan: D beban/aset + D PPN Masukan, K Utang Usaha. */
    public static function posting(int $id): int
    {
        return Db::transaction(static function () use ($id): int {
            $row = Db::one('SELECT * FROM ap_invoices WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('Tagihan tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'approved') {
                throw new \RuntimeException('Tagihan harus disetujui sebelum diposting.');
            }

            $periode = PeriodService::forDate((string) $row['tanggal']);
            if ($periode === null || (string) $periode['status'] !== 'open') {
                throw new \RuntimeException('Periode tagihan sudah ditutup/dikunci.');
            }

            $items = self::items($id);
            if ($items === []) {
                throw new \RuntimeException('Rincian tagihan belum diisi.');
            }

            $dimensi = [
                'bidang_id'      => $row['bidang_id'] ?: null,
                'divisi_id'      => $row['divisi_id'] ?: null,
                'project_id'     => $row['project_id'] ?: null,
                'cost_center_id' => $row['cost_center_id'] ?: null,
                'partner_id'     => (int) $row['partner_id'],
            ];

            $lines = [];
            $ppn = 0.0;
            foreach ($items as $item) {
                $lines[] = array_merge([
                    'coa_id'    => (int) $item['coa_id'],
                    'deskripsi' => (string) $item['keterangan'],
                    'debit'     => (float) $item['jumlah'],
                    'kredit'    => 0.0,
                ], $dimensi);
                $ppn += (float) $item['ppn'];
            }

            if ($ppn > 0.005) {
                $lines[] = [
                    'coa_id'    => AccountMap::ppnMasukan(),
                    'deskripsi' => 'PPN Masukan ' . $row['nomor'],
                    'debit'     => round($ppn, 2),
                    'kredit'    => 0.0,
                ];
            }

            $lines[] = [
                'coa_id'    => AccountMap::utangUsaha(),
                'deskripsi' => 'Utang vendor ' . $row['nomor'],
                'debit'     => 0.0,
                'kredit'    => (float) $row['total'],
            ];

            $journalId = JournalService::catat([
                'tanggal'      => (string) $row['tanggal'],
                'tipe'         => 'pengeluaran',
                'sumber'       => 'ap',
                'referensi'    => (string) $row['nomor'],
                'deskripsi'    => 'Tagihan vendor ' . $row['nomor'],
                'kode_dokumen' => 'AP',
            ], $lines);

            Db::exec('UPDATE ap_invoices SET status = ?, journal_id = ?, sisa = total - dibayar WHERE id = ?', [
                'posted', $journalId, $id,
            ]);

            Audit::log('post', 'ap', [
                'ref_table'   => 'ap_invoices',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['nomor'],
                'description' => 'Posting tagihan vendor ' . $row['nomor'],
                'new'         => ['journal_id' => $journalId, 'total' => $row['total']],
            ]);

            return $journalId;
        });
    }

    /**
     * Pembayaran tagihan (sebagian/penuh).
     *
     * @param array{tanggal?:string,bank_account_id?:int,jumlah?:mixed,potongan_pph?:mixed,metode?:string,referensi?:string} $input
     */
    public static function bayar(int $id, array $input): int
    {
        return Db::transaction(static function () use ($id, $input): int {
            $row = Db::one('SELECT * FROM ap_invoices WHERE id = ?', [$id]);
            if ($row === null) {
                throw new \RuntimeException('Tagihan tidak ditemukan.');
            }
            if (!in_array((string) $row['status'], ['posted', 'partial', 'overdue'], true)) {
                throw new \RuntimeException('Tagihan harus diposting sebelum dibayar.');
            }

            $sisa = (float) $row['sisa'];
            $tanggal = trim((string) ($input['tanggal'] ?? '')) ?: today();
            $jumlah = JournalService::uang($input['jumlah'] ?? 0);
            $potongan = JournalService::uang($input['potongan_pph'] ?? 0);
            $bankId = (int) ($input['bank_account_id'] ?? 0);
            $metode = (string) ($input['metode'] ?? 'transfer');

            if ($sisa <= 0.005) {
                throw new \RuntimeException('Tagihan ini sudah lunas.');
            }
            if ($jumlah <= 0) {
                throw new \RuntimeException('Jumlah pembayaran harus lebih besar dari nol.');
            }
            if ($jumlah > $sisa + 0.005) {
                throw new \RuntimeException('Jumlah pembayaran melebihi sisa hutang (' . rupiah($sisa) . ').');
            }
            if ($potongan < 0 || $potongan >= $jumlah) {
                throw new \RuntimeException('Potongan PPh tidak valid.');
            }
            if (!array_key_exists($metode, self::METODE)) {
                $metode = 'transfer';
            }

            $bank = $bankId > 0 ? Db::one('SELECT * FROM ms_bank_accounts WHERE id = ?', [$bankId]) : null;
            if ($bank === null || (int) $bank['is_active'] !== 1) {
                throw new \RuntimeException('Rekening pembayaran tidak aktif.');
            }

            $periode = PeriodService::forDate($tanggal);
            if ($periode === null || (string) $periode['status'] !== 'open') {
                throw new \RuntimeException('Periode tanggal pembayaran tidak terbuka.');
            }

            $kasKeluar = round($jumlah - $potongan, 2);

            $lines = [
                [
                    'coa_id'    => AccountMap::utangUsaha(),
                    'deskripsi' => 'Pelunasan tagihan ' . $row['nomor'],
                    'debit'     => $jumlah,
                    'kredit'    => 0.0,
                    'partner_id' => (int) $row['partner_id'],
                    'project_id' => $row['project_id'] ?: null,
                    'bidang_id'  => $row['bidang_id'] ?: null,
                    'divisi_id'  => $row['divisi_id'] ?: null,
                ],
                [
                    'coa_id'    => AccountMap::bankCoa($bankId),
                    'deskripsi' => 'Pembayaran tagihan ' . $row['nomor'],
                    'debit'     => 0.0,
                    'kredit'    => $kasKeluar,
                ],
            ];

            if ($potongan > 0.005) {
                $lines[] = [
                    'coa_id'    => AccountMap::pajakPotongan(),
                    'deskripsi' => 'Potongan PPh tagihan ' . $row['nomor'],
                    'debit'     => 0.0,
                    'kredit'    => $potongan,
                    'partner_id' => (int) $row['partner_id'],
                ];
            }

            $paymentId = Db::insert('ap_payments', [
                'nomor'           => NumberingService::next('APP', $tanggal),
                'invoice_id'      => $id,
                'tanggal'         => $tanggal,
                'periode_id'      => (int) $periode['id'],
                'bank_account_id' => $bankId,
                'jumlah'          => number_format($jumlah, 2, '.', ''),
                'potongan_pph'    => number_format($potongan, 2, '.', ''),
                'metode'          => $metode,
                'referensi'       => isset($input['referensi']) && trim((string) $input['referensi']) !== ''
                    ? mb_substr((string) $input['referensi'], 0, 80) : null,
                'status'          => 'posted',
                'created_by'      => auth_id() ?: null,
                'approved_by'     => auth_id() ?: null,
            ]);

            $journalId = JournalService::catat([
                'tanggal'      => $tanggal,
                'tipe'         => 'pengeluaran',
                'sumber'       => 'ap',
                'referensi'    => (string) $row['nomor'],
                'deskripsi'    => 'Pembayaran tagihan ' . $row['nomor'],
                'kode_dokumen' => 'APP',
            ], $lines);

            Db::exec('UPDATE ap_payments SET journal_id = ? WHERE id = ?', [$journalId, $paymentId]);

            $dibayarBaru = round((float) $row['dibayar'] + $jumlah, 2);
            $sisaBaru = round((float) $row['total'] - $dibayarBaru, 2);
            $statusBaru = $sisaBaru <= 0.005 ? 'paid' : 'partial';

            Db::exec('UPDATE ap_invoices SET dibayar = ?, sisa = ?, status = ? WHERE id = ?', [
                number_format($dibayarBaru, 2, '.', ''),
                number_format(max(0.0, $sisaBaru), 2, '.', ''),
                $statusBaru,
                $id,
            ]);

            Db::exec('UPDATE ms_bank_accounts SET saldo_berjalan = saldo_berjalan - ? WHERE id = ?', [
                number_format($kasKeluar, 2, '.', ''), $bankId,
            ]);

            Audit::log('pay', 'ap', [
                'ref_table'   => 'ap_payments',
                'ref_id'      => $paymentId,
                'ref_code'    => 'APP-' . $paymentId,
                'description' => 'Pembayaran tagihan ' . $row['nomor'],
                'new'         => ['jumlah' => $jumlah, 'potongan_pph' => $potongan, 'journal_id' => $journalId],
            ]);

            return $journalId;
        });
    }

    public static function hapus(int $id): void
    {
        $row = Db::one('SELECT status FROM ap_invoices WHERE id = ?', [$id]);
        if ($row === null) {
            throw new \RuntimeException('Tagihan tidak ditemukan.');
        }
        if (!in_array((string) $row['status'], ['draft'], true)) {
            throw new \RuntimeException('Tagihan yang sudah diajukan tidak dapat dihapus.');
        }
        Db::exec('DELETE FROM ap_invoices WHERE id = ?', [$id]);
    }

    /**
     * Aging hutang per bucket umur jatuh tempo.
     *
     * @return array<string,float>
     */
    public static function aging(): array
    {
        $row = Db::one(
            "SELECT
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) <= 0 THEN sisa ELSE 0 END),0) AS current,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 1 AND 30 THEN sisa ELSE 0 END),0) AS b1_30,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 31 AND 60 THEN sisa ELSE 0 END),0) AS b31_60,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 61 AND 90 THEN sisa ELSE 0 END),0) AS b61_90,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) > 90 THEN sisa ELSE 0 END),0) AS b90
             FROM ap_invoices
             WHERE sisa > 0 AND status IN ('posted','partial','overdue')"
        );

        $hasil = [];
        foreach (array_keys(self::BUCKETS) as $key) {
            $hasil[$key] = (float) ($row[$key] ?? 0);
        }
        return $hasil;
    }

    /** @return array<string,mixed> */
    public static function ringkasan(int $tahun): array
    {
        $row = Db::one(
            "SELECT COUNT(*) AS jumlah,
                    COALESCE(SUM(total),0) AS total,
                    COALESCE(SUM(sisa),0) AS outstanding,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END),0) AS lunas,
                    COALESCE(SUM(CASE WHEN sisa > 0 AND tanggal_jatuh_tempo < CURDATE() THEN sisa ELSE 0 END),0) AS jatuh_tempo
             FROM ap_invoices
             WHERE YEAR(tanggal) = ?",
            [$tahun]
        );

        return [
            'jumlah'      => (int) ($row['jumlah'] ?? 0),
            'total'       => (float) ($row['total'] ?? 0),
            'outstanding' => (float) ($row['outstanding'] ?? 0),
            'lunas'       => (float) ($row['lunas'] ?? 0),
            'jatuh_tempo' => (float) ($row['jatuh_tempo'] ?? 0),
        ];
    }
}
