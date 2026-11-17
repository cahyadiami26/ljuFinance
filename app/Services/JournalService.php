<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Mesin jurnal: penyimpanan draft, alur persetujuan, posting atomik,
 * pembalik (reversal), serta pemeliharaan saldo per akun per periode.
 *
 * Konvensi saldo: debit bertanda positif, kredit bertanda negatif.
 * account_period_balances.saldo_awal & saldo_akhir memakai konvensi ini,
 * sehingga saldo_akhir = saldo_awal + mutasi_debit - mutasi_kredit.
 */
final class JournalService
{
    public const TIPE = [
        'umum'        => 'Jurnal Umum',
        'penerimaan'  => 'Penerimaan Kas/Bank',
        'pengeluaran' => 'Pengeluaran Kas/Bank',
        'transfer'    => 'Transfer Antar Rekening',
        'penyesuaian' => 'Jurnal Penyesuaian',
        'akrual'      => 'Jurnal Akrual',
        'pembalik'    => 'Jurnal Pembalik',
        'penutup'     => 'Jurnal Penutup',
        'pembukaan'   => 'Saldo Awal / Pembukaan',
        'otomatis'    => 'Jurnal Otomatis',
    ];

    public const SUMBER = [
        'manual'       => 'Input Manual',
        'kas_bank'     => 'Kas & Bank',
        'uang_muka'    => 'Uang Muka',
        'biaya'        => 'Biaya',
        'ap'           => 'Hutang (AP)',
        'ar'           => 'Piutang (AR)',
        'aset'         => 'Aset Tetap',
        'pajak'        => 'Perpajakan',
        'penyesuaian'  => 'Penyesuaian',
        'penutup'      => 'Tutup Buku',
        'pembalik'     => 'Pembalik',
        'integrasi'    => 'Integrasi',
    ];

    public const STATUS = [
        'draft'     => 'Draft',
        'submitted' => 'Diajukan',
        'approved'  => 'Disetujui',
        'posted'    => 'Posted',
        'rejected'  => 'Ditolak',
        'reversed'  => 'Dibalik',
    ];

    /** Status yang masih dapat diubah / dihapus. */
    public const EDITABLE = ['draft', 'rejected'];

    public static function kodeDokumen(string $tipe): string
    {
        return match ($tipe) {
            'penerimaan'  => 'KM',
            'pengeluaran' => 'KK',
            'transfer'    => 'TRF',
            default       => 'JU',
        };
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT j.*, p.kode AS periode_kode, p.nama AS periode_nama, p.status AS periode_status,
                    c.name AS created_by_name, a.name AS approved_by_name, s.name AS posted_by_name,
                    r.name AS rejected_by_name
             FROM journal_entries j
             LEFT JOIN fiscal_periods p ON p.id = j.periode_id
             LEFT JOIN app_users c ON c.id = j.created_by
             LEFT JOIN app_users a ON a.id = j.approved_by
             LEFT JOIN app_users s ON s.id = j.posted_by
             LEFT JOIN app_users r ON r.id = j.rejected_by
             WHERE j.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function lines(int $id): array
    {
        return Db::all(
            'SELECT jl.*, c.kode AS coa_kode, c.nama AS coa_nama, c.tipe AS coa_tipe, c.saldo_normal,
                    b.nama AS bidang_nama, d.nama AS divisi_nama, pr.nama AS project_nama,
                    cc.nama AS cost_center_nama, pt.nama AS partner_nama
             FROM journal_lines jl
             JOIN coa_accounts c ON c.id = jl.coa_id
             LEFT JOIN ms_bidang b ON b.id = jl.bidang_id
             LEFT JOIN ms_divisi d ON d.id = jl.divisi_id
             LEFT JOIN ms_projects pr ON pr.id = jl.project_id
             LEFT JOIN ms_cost_centers cc ON cc.id = jl.cost_center_id
             LEFT JOIN ms_partners pt ON pt.id = jl.partner_id
             WHERE jl.journal_id = ?
             ORDER BY jl.urutan, jl.id',
            [$id]
        );
    }

    /* ------------------------------------------------------------ normalisasi */

    public static function uang(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }
        $clean = preg_replace('/[^0-9,.\-]/', '', (string) $value) ?? '';

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            // format Indonesia: 1.234.567,89
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})+$/', $clean) === 1) {
            // pemisah ribuan memakai koma
            $clean = str_replace(',', '', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $clean) === 1) {
            // pemisah ribuan memakai titik: 1.500.000
            $clean = str_replace('.', '', $clean);
        } elseif (substr_count($clean, '.') > 1) {
            $clean = str_replace('.', '', $clean);
        }

        return round((float) $clean, 2);
    }

    private static function intOrNull(mixed $value): ?int
    {
        $int = (int) ($value ?? 0);
        return $int > 0 ? $int : null;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}
     */
    public static function normalisasi(array $input): array
    {
        $header = [
            'tanggal'   => trim((string) ($input['tanggal'] ?? '')) ?: today(),
            'tipe'      => (string) ($input['tipe'] ?? 'umum'),
            'sumber'    => (string) ($input['sumber'] ?? 'manual'),
            'referensi' => trim((string) ($input['referensi'] ?? '')),
            'deskripsi' => trim((string) ($input['deskripsi'] ?? '')),
        ];

        $lines = [];
        foreach ((array) ($input['lines'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $coa = self::intOrNull($row['coa_id'] ?? null) ?? 0;
            $debit = self::uang($row['debit'] ?? 0);
            $kredit = self::uang($row['kredit'] ?? 0);
            $desc = trim((string) ($row['deskripsi'] ?? ''));

            if ($coa === 0 && $debit === 0.0 && $kredit === 0.0 && $desc === '') {
                continue;
            }

            $lines[] = [
                'urutan'           => count($lines) + 1,
                'coa_id'           => $coa,
                'deskripsi'        => $desc !== '' ? mb_substr($desc, 0, 255) : null,
                'debit'            => $debit,
                'kredit'           => $kredit,
                'bidang_id'        => self::intOrNull($row['bidang_id'] ?? null),
                'divisi_id'        => self::intOrNull($row['divisi_id'] ?? null),
                'business_unit_id' => self::intOrNull($row['business_unit_id'] ?? null),
                'project_id'       => self::intOrNull($row['project_id'] ?? null),
                'cost_center_id'   => self::intOrNull($row['cost_center_id'] ?? null),
                'partner_id'       => self::intOrNull($row['partner_id'] ?? null),
                'tax_type_id'      => self::intOrNull($row['tax_type_id'] ?? null),
            ];
        }

        return [$header, $lines];
    }

    /**
     * Validasi header + baris jurnal.
     *
     * @param array<string,mixed> $header
     * @param array<int,array<string,mixed>> $lines
     * @return array<string,string>
     */
    public static function validasi(array $header, array $lines): array
    {
        $errors = [];

        if (!array_key_exists($header['tipe'], self::TIPE)) {
            $errors['tipe'] = 'Tipe jurnal tidak dikenal.';
        }

        $timestamp = strtotime($header['tanggal']);
        if ($timestamp === false) {
            $errors['tanggal'] = 'Tanggal tidak valid.';
        } else {
            $periode = PeriodService::forDate($header['tanggal']);
            if ($periode === null) {
                $errors['tanggal'] = 'Belum ada periode akuntansi untuk tanggal ini. Buka periode terlebih dahulu.';
            } elseif ((string) $periode['status'] !== 'open') {
                $errors['tanggal'] = 'Periode ' . $periode['nama'] . ' sudah ' . ($periode['status'] === 'closed' ? 'ditutup' : 'dikunci') . '.';
            }
        }

        if ($header['deskripsi'] === '') {
            $errors['deskripsi'] = 'Deskripsi jurnal wajib diisi.';
        } elseif (mb_strlen($header['deskripsi']) > 255) {
            $errors['deskripsi'] = 'Deskripsi maksimal 255 karakter.';
        }

        if (mb_strlen($header['referensi']) > 80) {
            $errors['referensi'] = 'Referensi maksimal 80 karakter.';
        }

        if (count($lines) < 2) {
            $errors['lines'] = 'Jurnal membutuhkan minimal dua baris (debit dan kredit).';
            return $errors;
        }

        $totalDebit = 0.0;
        $totalKredit = 0.0;

        foreach ($lines as $index => $line) {
            $nomor = $index + 1;
            $totalDebit += (float) $line['debit'];
            $totalKredit += (float) $line['kredit'];

            if ((int) $line['coa_id'] <= 0) {
                $errors['lines'] = 'Baris ' . $nomor . ': akun belum dipilih.';
                continue;
            }

            $akun = Db::one('SELECT kode, nama, allow_posting, is_active FROM coa_accounts WHERE id = ?', [(int) $line['coa_id']]);
            if ($akun === null) {
                $errors['lines'] = 'Baris ' . $nomor . ': akun tidak ditemukan.';
                continue;
            }
            if ((int) $akun['allow_posting'] !== 1) {
                $errors['lines'] = 'Baris ' . $nomor . ': akun ' . $akun['kode'] . ' adalah akun induk (tidak boleh dipakai transaksi).';
                continue;
            }
            if ((int) $akun['is_active'] !== 1) {
                $errors['lines'] = 'Baris ' . $nomor . ': akun ' . $akun['kode'] . ' tidak aktif.';
                continue;
            }

            if ($line['debit'] < 0 || $line['kredit'] < 0) {
                $errors['lines'] = 'Baris ' . $nomor . ': nilai tidak boleh negatif.';
                continue;
            }
            if ($line['debit'] > 0 && $line['kredit'] > 0) {
                $errors['lines'] = 'Baris ' . $nomor . ': isi salah satu kolom debit atau kredit.';
                continue;
            }
            if ($line['debit'] == 0.0 && $line['kredit'] == 0.0) {
                $errors['lines'] = 'Baris ' . $nomor . ': nilai debit/kredit masih nol.';
            }
        }

        if (abs($totalDebit - $totalKredit) > 0.005) {
            $errors['balance'] = 'Jurnal tidak seimbang: debit ' . rupiah($totalDebit) . ' vs kredit ' . rupiah($totalKredit) . '.';
        }
        if ($totalDebit <= 0) {
            $errors['balance'] = 'Total jurnal harus lebih besar dari nol.';
        }

        return $errors;
    }

    /* -------------------------------------------------------------- penyimpanan */

    /**
     * Simpan jurnal (draft baru atau perubahan draft).
     *
     * @param array<string,mixed> $header
     * @param array<int,array<string,mixed>> $lines
     */
    public static function simpan(?int $id, array $header, array $lines): int
    {
        return Db::transaction(static function () use ($id, $header, $lines): int {
            $periode = PeriodService::forDate($header['tanggal']);
            if ($periode === null) {
                throw new \RuntimeException('Periode akuntansi untuk tanggal tersebut tidak tersedia.');
            }

            $totalDebit = array_sum(array_map(static fn ($l) => (float) $l['debit'], $lines));
            $totalKredit = array_sum(array_map(static fn ($l) => (float) $l['kredit'], $lines));

            $payload = [
                'tanggal'      => $header['tanggal'],
                'periode_id'   => (int) $periode['id'],
                'fiscal_year'  => (int) $periode['tahun'],
                'tipe'         => (string) $header['tipe'],
                'sumber'       => (string) ($header['sumber'] ?? 'manual'),
                'referensi'    => $header['referensi'] !== '' ? $header['referensi'] : null,
                'deskripsi'    => (string) $header['deskripsi'],
                'total_debit'  => number_format($totalDebit, 2, '.', ''),
                'total_kredit' => number_format($totalKredit, 2, '.', ''),
            ];

            if ($id === null) {
                $payload['nomor'] = NumberingService::next(self::kodeDokumen((string) $header['tipe']), (string) $header['tanggal']);
                $payload['status'] = 'draft';
                $payload['created_by'] = auth_id() ?: null;
                $id = Db::insert('journal_entries', $payload);
            } else {
                Db::update('journal_entries', $payload, 'id = :id', [':id' => $id]);
                Db::exec('DELETE FROM journal_lines WHERE journal_id = ?', [$id]);
            }

            foreach ($lines as $line) {
                Db::insert('journal_lines', array_merge($line, ['journal_id' => $id]));
            }

            return $id;
        });
    }

    /**
     * Catat jurnal otomatis yang langsung berstatus posted (dipakai modul kas/bank,
     * uang muka, biaya, dan rekonsiliasi). Total dibulatkan & wajib seimbang.
     *
     * @param array{tanggal:string,tipe?:string,sumber?:string,referensi?:?string,deskripsi:string} $header
     * @param array<int,array<string,mixed>> $lines
     */
    public static function catat(array $header, array $lines): int
    {
        $work = static function () use ($header, $lines): int {
            $tanggal = (string) ($header['tanggal'] ?? today());
            $periode = PeriodService::forDate($tanggal);
            if ($periode === null) {
                throw new \RuntimeException('Tidak ada periode akuntansi untuk tanggal ' . $tanggal . '.');
            }
            if ((string) $periode['status'] !== 'open') {
                throw new \RuntimeException('Periode ' . $periode['nama'] . ' sudah ditutup/dikunci.');
            }

            $totalDebit = 0.0;
            $totalKredit = 0.0;
            foreach ($lines as $line) {
                $totalDebit += (float) ($line['debit'] ?? 0);
                $totalKredit += (float) ($line['kredit'] ?? 0);
            }

            if (abs($totalDebit - $totalKredit) > 0.005) {
                throw new \RuntimeException('Jurnal otomatis tidak seimbang (debit ' . rupiah($totalDebit) . ' vs kredit ' . rupiah($totalKredit) . ').');
            }
            if ($totalDebit <= 0) {
                throw new \RuntimeException('Nilai jurnal otomatis harus lebih besar dari nol.');
            }

            $tipe = (string) ($header['tipe'] ?? 'otomatis');
            $journalId = Db::insert('journal_entries', [
                'nomor'        => NumberingService::next((string) ($header['kode_dokumen'] ?? self::kodeDokumen($tipe)), $tanggal),
                'tanggal'      => $tanggal,
                'periode_id'   => (int) $periode['id'],
                'fiscal_year'  => (int) $periode['tahun'],
                'tipe'         => $tipe,
                'sumber'       => (string) ($header['sumber'] ?? 'integrasi'),
                'referensi'    => isset($header['referensi']) && $header['referensi'] !== '' ? $header['referensi'] : null,
                'deskripsi'    => mb_substr((string) $header['deskripsi'], 0, 255),
                'total_debit'  => number_format($totalDebit, 2, '.', ''),
                'total_kredit' => number_format($totalKredit, 2, '.', ''),
                'status'       => 'posted',
                'created_by'   => auth_id() ?: null,
                'posted_by'    => auth_id() ?: null,
                'posted_at'    => now(),
            ]);

            foreach (array_values($lines) as $index => $line) {
                Db::insert('journal_lines', [
                    'journal_id'       => $journalId,
                    'urutan'           => $index + 1,
                    'coa_id'           => (int) $line['coa_id'],
                    'deskripsi'        => isset($line['deskripsi']) ? mb_substr((string) $line['deskripsi'], 0, 255) : null,
                    'debit'            => number_format((float) ($line['debit'] ?? 0), 2, '.', ''),
                    'kredit'           => number_format((float) ($line['kredit'] ?? 0), 2, '.', ''),
                    'bidang_id'        => $line['bidang_id'] ?? null,
                    'divisi_id'        => $line['divisi_id'] ?? null,
                    'business_unit_id' => $line['business_unit_id'] ?? null,
                    'project_id'       => $line['project_id'] ?? null,
                    'cost_center_id'   => $line['cost_center_id'] ?? null,
                    'partner_id'       => $line['partner_id'] ?? null,
                    'tax_type_id'      => $line['tax_type_id'] ?? null,
                ]);
            }

            self::rebuildYear((int) $periode['tahun']);

            return $journalId;
        };

        // terbuka untuk dipanggil dari dalam transaksi modul pemanggil
        return Db::pdo()->inTransaction() ? $work() : Db::transaction($work);
    }

    public static function ubahStatus(int $id, string $status, array $extra = []): void
    {
        Db::update('journal_entries', array_merge(['status' => $status], $extra), 'id = :id', [':id' => $id]);
    }

    public static function ajukan(int $id): void
    {
        Db::transaction(static function () use ($id): void {
            $journal = self::find($id);
            if ($journal === null) {
                throw new \RuntimeException('Jurnal tidak ditemukan.');
            }
            if (!in_array((string) $journal['status'], ['draft', 'rejected'], true)) {
                throw new \RuntimeException('Hanya jurnal draft/ditolak yang dapat diajukan.');
            }
            if (!PeriodService::isOpen((int) $journal['periode_id'])) {
                throw new \RuntimeException('Periode jurnal sudah ditutup.');
            }
            self::ubahStatus($id, 'submitted', ['submitted_at' => now()]);
        });
    }

    public static function setujui(int $id): void
    {
        Db::transaction(static function () use ($id): void {
            $journal = self::find($id);
            if ($journal === null) {
                throw new \RuntimeException('Jurnal tidak ditemukan.');
            }
            if ((string) $journal['status'] !== 'submitted') {
                throw new \RuntimeException('Hanya jurnal yang diajukan dapat disetujui.');
            }
            self::ubahStatus($id, 'approved', ['approved_by' => auth_id() ?: null, 'approved_at' => now()]);
        });
    }

    public static function tolak(int $id, string $alasan): void
    {
        Db::transaction(static function () use ($id, $alasan): void {
            $journal = self::find($id);
            if ($journal === null) {
                throw new \RuntimeException('Jurnal tidak ditemukan.');
            }
            if ((string) $journal['status'] !== 'submitted') {
                throw new \RuntimeException('Hanya jurnal yang diajukan dapat ditolak.');
            }
            self::ubahStatus($id, 'rejected', [
                'rejected_by'      => auth_id() ?: null,
                'rejected_at'      => now(),
                'rejection_reason' => mb_substr($alasan !== '' ? $alasan : 'Tidak disebutkan', 0, 255),
            ]);
        });
    }

    public static function posting(int $id): void
    {
        Db::transaction(static function () use ($id): void {
            $journal = self::find($id);
            if ($journal === null) {
                throw new \RuntimeException('Jurnal tidak ditemukan.');
            }
            if ((string) $journal['status'] !== 'approved') {
                throw new \RuntimeException('Jurnal harus disetujui sebelum diposting.');
            }
            if (!PeriodService::isOpen((int) $journal['periode_id'])) {
                throw new \RuntimeException('Periode jurnal sudah ditutup atau dikunci.');
            }
            if (abs((float) $journal['total_debit'] - (float) $journal['total_kredit']) > 0.005) {
                throw new \RuntimeException('Jurnal tidak seimbang, posting dibatalkan.');
            }

            self::ubahStatus($id, 'posted', ['posted_by' => auth_id() ?: null, 'posted_at' => now()]);
            self::rebuildYear((int) $journal['fiscal_year']);

            Audit::log('post', 'journal', [
                'ref_table'   => 'journal_entries',
                'ref_id'      => $id,
                'ref_code'    => (string) $journal['nomor'],
                'description' => 'Posting jurnal ' . $journal['nomor'],
                'new'         => ['status' => 'posted', 'total_debit' => $journal['total_debit']],
            ]);
        });
    }

    /**
     * Buat jurnal pembalik atas jurnal yang sudah posted.
     */
    public static function balik(int $id, ?string $tanggal = null, string $alasan = ''): int
    {
        return Db::transaction(static function () use ($id, $tanggal, $alasan): int {
            $journal = self::find($id);
            if ($journal === null) {
                throw new \RuntimeException('Jurnal tidak ditemukan.');
            }
            if ((string) $journal['status'] !== 'posted') {
                throw new \RuntimeException('Hanya jurnal posted yang dapat dibalik.');
            }
            if (!empty($journal['reversed_by_id'])) {
                throw new \RuntimeException('Jurnal ini sudah dibalik sebelumnya.');
            }

            $tanggal = $tanggal ?: today();
            $periode = PeriodService::forDate($tanggal);
            if ($periode === null || (string) $periode['status'] !== 'open') {
                throw new \RuntimeException('Periode untuk tanggal pembalik tidak terbuka.');
            }

            $lines = self::lines($id);
            if ($lines === []) {
                throw new \RuntimeException('Jurnal tidak memiliki baris.');
            }

            $reversalId = Db::insert('journal_entries', [
                'nomor'         => NumberingService::next('JU', $tanggal),
                'tanggal'       => $tanggal,
                'periode_id'    => (int) $periode['id'],
                'fiscal_year'   => (int) $periode['tahun'],
                'tipe'          => 'pembalik',
                'sumber'        => 'pembalik',
                'referensi'     => (string) $journal['nomor'],
                'deskripsi'     => mb_substr('Pembalik ' . $journal['nomor'] . ($alasan !== '' ? ' — ' . $alasan : ''), 0, 255),
                'total_debit'   => $journal['total_debit'],
                'total_kredit'  => $journal['total_kredit'],
                'status'        => 'posted',
                'created_by'    => auth_id() ?: null,
                'posted_by'     => auth_id() ?: null,
                'posted_at'     => now(),
                'reversal_of_id' => $id,
            ]);

            foreach ($lines as $line) {
                Db::insert('journal_lines', [
                    'journal_id'       => $reversalId,
                    'urutan'           => (int) $line['urutan'],
                    'coa_id'           => (int) $line['coa_id'],
                    'deskripsi'        => $line['deskripsi'],
                    'debit'            => $line['kredit'],
                    'kredit'           => $line['debit'],
                    'bidang_id'        => $line['bidang_id'],
                    'divisi_id'        => $line['divisi_id'],
                    'business_unit_id' => $line['business_unit_id'],
                    'project_id'       => $line['project_id'],
                    'cost_center_id'   => $line['cost_center_id'],
                    'partner_id'       => $line['partner_id'],
                    'tax_type_id'      => $line['tax_type_id'],
                ]);
            }

            Db::update('journal_entries', [
                'status'         => 'reversed',
                'reversed_by_id' => $reversalId,
            ], 'id = :id', [':id' => $id]);

            self::rebuildYear((int) $journal['fiscal_year']);
            if ((int) $periode['tahun'] !== (int) $journal['fiscal_year']) {
                self::rebuildYear((int) $periode['tahun']);
            }

            return $reversalId;
        });
    }

    public static function hapus(int $id): void
    {
        Db::transaction(static function () use ($id): void {
            $journal = self::find($id);
            if ($journal === null) {
                throw new \RuntimeException('Jurnal tidak ditemukan.');
            }
            if (!in_array((string) $journal['status'], self::EDITABLE, true)) {
                throw new \RuntimeException('Jurnal yang sudah diajukan tidak dapat dihapus.');
            }
            Db::exec('DELETE FROM journal_lines WHERE journal_id = ?', [$id]);
            Db::exec('DELETE FROM journal_entries WHERE id = ?', [$id]);
        });
    }

    /* ----------------------------------------------------------------- saldo */

    /**
     * Hitung ulang account_period_balances untuk satu tahun buku.
     * Aman dipanggil berulang kali (idempotent).
     */
    public static function rebuildYear(int $tahun): void
    {
        $periods = Db::all('SELECT * FROM fiscal_periods WHERE tahun = ? ORDER BY bulan, id', [$tahun]);
        if ($periods === []) {
            return;
        }

        $carry = [];

        // saldo awal tahun = saldo akhir periode terakhir sebelum tahun ini
        $previous = Db::one(
            'SELECT id FROM fiscal_periods WHERE tanggal_selesai < ? ORDER BY tanggal_selesai DESC LIMIT 1',
            [$periods[0]['tanggal_mulai']]
        );
        if ($previous !== null) {
            foreach (Db::all('SELECT coa_id, saldo_akhir FROM account_period_balances WHERE periode_id = ?', [(int) $previous['id']]) as $row) {
                $carry[(int) $row['coa_id']] = (float) $row['saldo_akhir'];
            }
        }

        $periodIds = array_map(static fn ($p) => (int) $p['id'], $periods);
        $placeholders = implode(',', array_fill(0, count($periodIds), '?'));

        $mutasi = [];
        foreach (Db::all(
            'SELECT je.periode_id, jl.coa_id, SUM(jl.debit) AS debit, SUM(jl.kredit) AS kredit
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             WHERE je.status = ? AND je.periode_id IN (' . $placeholders . ')
             GROUP BY je.periode_id, jl.coa_id',
            array_merge(['posted'], $periodIds)
        ) as $row) {
            $mutasi[(int) $row['periode_id']][(int) $row['coa_id']] = [
                'debit'  => (float) $row['debit'],
                'kredit' => (float) $row['kredit'],
            ];
        }

        Db::exec('DELETE FROM account_period_balances WHERE periode_id IN (' . $placeholders . ')', $periodIds);

        foreach ($periods as $period) {
            $periodeId = (int) $period['id'];
            $rows = $mutasi[$periodeId] ?? [];

            // akun yang punya saldo awal harus tetap muncul walau tanpa mutasi
            foreach (array_keys($carry) as $coaId) {
                if (!isset($rows[$coaId])) {
                    $rows[$coaId] = ['debit' => 0.0, 'kredit' => 0.0];
                }
            }

            foreach ($rows as $coaId => $values) {
                $awal = $carry[$coaId] ?? 0.0;
                $akhir = $awal + $values['debit'] - $values['kredit'];

                Db::insert('account_period_balances', [
                    'coa_id'        => $coaId,
                    'periode_id'    => $periodeId,
                    'saldo_awal'    => number_format($awal, 2, '.', ''),
                    'mutasi_debit'  => number_format($values['debit'], 2, '.', ''),
                    'mutasi_kredit' => number_format($values['kredit'], 2, '.', ''),
                    'saldo_akhir'   => number_format($akhir, 2, '.', ''),
                ]);

                $carry[$coaId] = $akhir;
            }
        }
    }

    /** Ringkasan jurnal satu periode (untuk halaman tutup buku). */
    public static function ringkasanPeriode(int $periodeId): array
    {
        $counts = ['draft' => 0, 'submitted' => 0, 'approved' => 0, 'posted' => 0, 'rejected' => 0, 'reversed' => 0];
        foreach (Db::all('SELECT status, COUNT(*) AS jumlah FROM journal_entries WHERE periode_id = ? GROUP BY status', [$periodeId]) as $row) {
            $counts[(string) $row['status']] = (int) $row['jumlah'];
        }

        $total = Db::one(
            'SELECT COALESCE(SUM(total_debit),0) AS debit, COALESCE(SUM(total_kredit),0) AS kredit
             FROM journal_entries WHERE periode_id = ? AND status = ?',
            [$periodeId, 'posted']
        ) ?? ['debit' => 0, 'kredit' => 0];

        return [
            'counts'       => $counts,
            'belum_posting' => $counts['draft'] + $counts['submitted'] + $counts['approved'],
            'total_debit'  => (float) $total['debit'],
            'total_kredit' => (float) $total['kredit'],
        ];
    }
}
