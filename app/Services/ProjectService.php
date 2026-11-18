<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;

/**
 * Project accounting: portofolio proyek, kontrak, termin, dan profitabilitas.
 *
 * Pendapatan & biaya proyek diambil dari dimensi `project_id` pada baris
 * jurnal posted, sehingga laporan proyek konsisten dengan buku besar.
 */
final class ProjectService
{
    public const STATUS_PROYEK = [
        'perencanaan' => 'Perencanaan',
        'berjalan'    => 'Berjalan',
        'selesai'     => 'Selesai',
        'batal'       => 'Batal',
    ];

    public const STATUS_KONTRAK = [
        'draft'  => 'Draft',
        'aktif'  => 'Aktif',
        'selesai' => 'Selesai',
        'batal'  => 'Batal',
    ];

    public const STATUS_TERMIN = [
        'belum'   => 'Belum Ditagih',
        'diajukan' => 'Diajukan',
        'ditagih' => 'Sudah Ditagih',
        'dibayar' => 'Dibayar',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function portfolio(string $q = '', int $businessUnitId = 0): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(p.kode LIKE ? OR p.nama LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%');
        }
        if ($businessUnitId > 0) {
            $where[] = 'p.business_unit_id = ?';
            $params[] = $businessUnitId;
        }

        $rows = Db::all(
            'SELECT p.*, pt.nama AS partner_nama, bu.nama AS business_unit_nama, d.nama AS divisi_nama,
                    (SELECT COALESCE(SUM(nilai_kontrak), 0) FROM ms_contracts c WHERE c.project_id = p.id AND c.status <> \'batal\') AS nilai_kontrak,
                    (SELECT COUNT(*) FROM ms_contracts c WHERE c.project_id = p.id) AS jumlah_kontrak,
                    (SELECT COALESCE(SUM(r.nilai), 0) FROM revenue_recognitions r WHERE r.project_id = p.id AND r.status = \'posted\') AS pendapatan_diakui,
                    (SELECT COALESCE(SUM(a.total), 0) FROM ar_invoices a WHERE a.project_id = p.id AND a.status IN (\'posted\',\'partial\',\'paid\',\'overdue\')) AS nilai_tagihan,
                    (SELECT COALESCE(SUM(jl.kredit - jl.debit), 0)
                       FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_id JOIN coa_accounts c ON c.id = jl.coa_id
                      WHERE je.status = \'posted\' AND jl.project_id = p.id AND c.tipe = \'pendapatan\') AS pendapatan_jurnal,
                    (SELECT COALESCE(SUM(jl.debit - jl.kredit), 0)
                       FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_id JOIN coa_accounts c ON c.id = jl.coa_id
                      WHERE je.status = \'posted\' AND jl.project_id = p.id AND c.tipe = \'beban\') AS biaya_jurnal
             FROM ms_projects p
             LEFT JOIN ms_partners pt ON pt.id = p.partner_id
             LEFT JOIN ms_business_units bu ON bu.id = p.business_unit_id
             LEFT JOIN ms_divisi d ON d.id = p.divisi_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.status = \'berjalan\' DESC, p.kode DESC
             LIMIT 300',
            $params
        );

        foreach ($rows as &$row) {
            self::lengkapiMetrik($row);
        }
        unset($row);

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private static function lengkapiMetrik(array &$row): void
    {
        $kontrak = (float) ($row['nilai_kontrak'] ?? 0);
        $diakui = (float) ($row['pendapatan_diakui'] ?? 0);
        $pendapatan = (float) ($row['pendapatan_jurnal'] ?? 0);
        $biaya = (float) ($row['biaya_jurnal'] ?? 0);
        $laba = $pendapatan - $biaya;

        $row['nilai_kontrak'] = $kontrak;
        $row['pendapatan_diakui'] = $diakui;
        $row['pendapatan_jurnal'] = $pendapatan;
        $row['biaya_jurnal'] = $biaya;
        $row['laba'] = $laba;
        $row['margin'] = $pendapatan > 0 ? ($laba / $pendapatan) * 100 : 0.0;
        $row['progress'] = $kontrak > 0 ? min(100, max(0, ($diakui / $kontrak) * 100)) : 0.0;
        $row['sisa_kontrak'] = max(0, $kontrak - $diakui);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $row = Db::one(
            'SELECT p.*, pt.nama AS partner_nama, bu.nama AS business_unit_nama, b.nama AS bidang_nama,
                    d.nama AS divisi_nama, cc.nama AS cost_center_nama, u.name AS manager_nama
             FROM ms_projects p
             LEFT JOIN ms_partners pt ON pt.id = p.partner_id
             LEFT JOIN ms_business_units bu ON bu.id = p.business_unit_id
             LEFT JOIN ms_bidang b ON b.id = p.bidang_id
             LEFT JOIN ms_divisi d ON d.id = p.divisi_id
             LEFT JOIN ms_cost_centers cc ON cc.id = p.cost_center_id
             LEFT JOIN app_users u ON u.id = p.manager_user_id
             WHERE p.id = ?',
            [$id]
        );
        if ($row === null) {
            return null;
        }

        $row['nilai_kontrak'] = (float) Db::val('SELECT COALESCE(SUM(nilai_kontrak), 0) FROM ms_contracts WHERE project_id = ? AND status <> \'batal\'', [$id]);
        $row['pendapatan_diakui'] = (float) Db::val('SELECT COALESCE(SUM(nilai), 0) FROM revenue_recognitions WHERE project_id = ? AND status = \'posted\'', [$id]);
        $row['nilai_tagihan'] = (float) Db::val('SELECT COALESCE(SUM(total), 0) FROM ar_invoices WHERE project_id = ? AND status IN (\'posted\',\'partial\',\'paid\',\'overdue\')', [$id]);
        $row['pendapatan_jurnal'] = (float) Db::val(
            'SELECT COALESCE(SUM(jl.kredit - jl.debit), 0) FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = \'posted\' AND jl.project_id = ? AND c.tipe = \'pendapatan\'',
            [$id]
        );
        $row['biaya_jurnal'] = (float) Db::val(
            'SELECT COALESCE(SUM(jl.debit - jl.kredit), 0) FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = \'posted\' AND jl.project_id = ? AND c.tipe = \'beban\'',
            [$id]
        );
        $row['jumlah_kontrak'] = (int) Db::val('SELECT COUNT(*) FROM ms_contracts WHERE project_id = ?', [$id]);
        self::lengkapiMetrik($row);

        return $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function contracts(int $projectId = 0): array
    {
        $where = $projectId > 0 ? ' WHERE c.project_id = ' . $projectId : '';
        return Db::all(
            'SELECT c.*, p.nama AS project_nama, pt.nama AS partner_nama,
                    (SELECT COALESCE(SUM(nilai), 0) FROM revenue_recognitions r WHERE r.contract_id = c.id AND r.status = \'posted\') AS diakui,
                    (SELECT COUNT(*) FROM ms_contract_terms t WHERE t.contract_id = c.id) AS jumlah_termin,
                    (SELECT COALESCE(SUM(total), 0) FROM ar_invoices a WHERE a.contract_id = c.id AND a.status IN (\'posted\',\'partial\',\'paid\',\'overdue\')) AS ditagih
             FROM ms_contracts c
             LEFT JOIN ms_projects p ON p.id = c.project_id
             LEFT JOIN ms_partners pt ON pt.id = c.partner_id'
            . $where . ' ORDER BY c.tanggal DESC, c.id DESC',
            []
        );
    }

    /** @return array<string,mixed>|null */
    public static function contract(int $id): ?array
    {
        return Db::one(
            'SELECT c.*, p.kode AS project_kode, p.nama AS project_nama, p.business_unit_id,
                    pt.nama AS partner_nama, pt.kode AS partner_kode
             FROM ms_contracts c
             LEFT JOIN ms_projects p ON p.id = c.project_id
             LEFT JOIN ms_partners pt ON pt.id = c.partner_id
             WHERE c.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function terms(int $contractId): array
    {
        return Db::all(
            'SELECT t.*, a.nomor AS ar_nomor, a.status AS ar_status, a.id AS ar_id
             FROM ms_contract_terms t
             LEFT JOIN ar_invoices a ON a.contract_id = t.contract_id AND a.termin_ke = t.termin_ke
             WHERE t.contract_id = ?
             ORDER BY t.termin_ke',
            [$contractId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function recognitions(int $projectId = 0, int $contractId = 0): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($projectId > 0) {
            $where[] = 'r.project_id = ?';
            $params[] = $projectId;
        }
        if ($contractId > 0) {
            $where[] = 'r.contract_id = ?';
            $params[] = $contractId;
        }

        return Db::all(
            'SELECT r.*, p.nama AS project_nama, c.nomor AS contract_nomor, f.nama AS periode_nama, j.nomor AS journal_nomor
             FROM revenue_recognitions r
             LEFT JOIN ms_projects p ON p.id = r.project_id
             LEFT JOIN ms_contracts c ON c.id = r.contract_id
             LEFT JOIN fiscal_periods f ON f.id = r.periode_id
             LEFT JOIN journal_entries j ON j.id = r.journal_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY r.tanggal DESC, r.id DESC
             LIMIT 100',
            $params
        );
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalisasiContract(array $input): array
    {
        $ppn = (float) ($input['ppn_persen'] ?? 11);
        return [
            'nomor'         => strtoupper(trim((string) ($input['nomor'] ?? ''))),
            'judul'         => trim((string) ($input['judul'] ?? '')),
            'partner_id'    => (int) ($input['partner_id'] ?? 0) ?: null,
            'project_id'    => (int) ($input['project_id'] ?? 0) ?: null,
            'nilai_kontrak' => max(0.0, JournalService::uang($input['nilai_kontrak'] ?? 0)),
            'tanggal'       => trim((string) ($input['tanggal'] ?? '')) ?: today(),
            'tanggal_mulai' => trim((string) ($input['tanggal_mulai'] ?? '')) ?: null,
            'tanggal_akhir' => trim((string) ($input['tanggal_akhir'] ?? '')) ?: null,
            'ppn_persen'    => max(0.0, min(100.0, $ppn)),
            'status'        => array_key_exists((string) ($input['status'] ?? ''), self::STATUS_KONTRAK) ? (string) $input['status'] : 'draft',
            'keterangan'    => trim((string) ($input['keterangan'] ?? '')),
            'terms'         => self::normalisasiTerms($input['terms'] ?? [], max(0.0, JournalService::uang($input['nilai_kontrak'] ?? 0))),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function normalisasiTerms(array $rows, float $nilaiKontrak): array
    {
        $hasil = [];
        $no = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $deskripsi = trim((string) ($row['deskripsi'] ?? ''));
            $persen = max(0.0, min(100.0, (float) ($row['persentase'] ?? 0)));
            $nilai = JournalService::uang($row['nilai'] ?? 0);

            if ($deskripsi === '' && $persen <= 0 && $nilai <= 0) {
                continue;
            }
            if ($nilai <= 0 && $nilaiKontrak > 0 && $persen > 0) {
                $nilai = round($nilaiKontrak * $persen / 100, 2);
            }
            $no++;
        $hasil[] = [
                'termin_ke'  => $no,
                'deskripsi'  => mb_substr($deskripsi !== '' ? $deskripsi : 'Termin ' . $no, 0, 150),
                'persentase' => $persen,
                'nilai'      => $nilai,
            ];
        }

        return $hasil;
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasiContract(array $data, ?int $id = null): array
    {
        $errors = [];
        if ($data['nomor'] === '') {
            $errors['nomor'] = 'Nomor kontrak wajib diisi.';
        } elseif ((int) Db::val('SELECT COUNT(*) FROM ms_contracts WHERE nomor = ? AND id <> ?', [$data['nomor'], $id ?? 0]) > 0) {
            $errors['nomor'] = 'Nomor kontrak sudah dipakai.';
        }
        if ($data['judul'] === '') {
            $errors['judul'] = 'Judul kontrak wajib diisi.';
        }
        if ((int) ($data['project_id'] ?? 0) <= 0) {
            $errors['project_id'] = 'Proyek wajib dipilih.';
        }
        if ((float) $data['nilai_kontrak'] <= 0) {
            $errors['nilai_kontrak'] = 'Nilai kontrak harus lebih dari nol.';
        }
        if ($data['tanggal_mulai'] !== null && $data['tanggal_akhir'] !== null
            && strtotime((string) $data['tanggal_akhir']) < strtotime((string) $data['tanggal_mulai'])) {
            $errors['tanggal_akhir'] = 'Tanggal akhir tidak boleh sebelum tanggal mulai.';
        }
        if ($data['terms'] === []) {
            $errors['terms'] = 'Minimal satu termin kontrak.';
        }

        return $errors;
    }

    /** @param array<string,mixed> $data */
    public static function simpanContract(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            if (($data['partner_id'] ?? null) === null && (int) ($data['project_id'] ?? 0) > 0) {
                $partner = (int) Db::val('SELECT partner_id FROM ms_projects WHERE id = ?', [(int) $data['project_id']]);
                $data['partner_id'] = $partner > 0 ? $partner : null;
            }

            $statusLama = [];
            if ($id !== null) {
                foreach (Db::all('SELECT termin_ke, status FROM ms_contract_terms WHERE contract_id = ?', [$id]) as $term) {
                    $statusLama[(int) $term['termin_ke']] = (string) $term['status'];
                }
            }

            $payload = [
                'nomor'         => $data['nomor'],
                'judul'         => $data['judul'],
                'partner_id'    => $data['partner_id'],
                'project_id'    => $data['project_id'],
                'nilai_kontrak' => number_format((float) $data['nilai_kontrak'], 2, '.', ''),
                'tanggal'       => $data['tanggal'],
                'tanggal_mulai' => $data['tanggal_mulai'],
                'tanggal_akhir' => $data['tanggal_akhir'],
                'ppn_persen'    => number_format((float) $data['ppn_persen'], 2, '.', ''),
                'status'        => $data['status'],
                'keterangan'    => $data['keterangan'] !== '' ? mb_substr((string) $data['keterangan'], 0, 255) : null,
            ];

            if ($id === null) {
                $payload['created_by'] = auth_id() ?: null;
                $id = Db::insert('ms_contracts', $payload);
            } else {
                $payload['updated_by'] = auth_id() ?: null;
                Db::update('ms_contracts', $payload, 'id = :id', [':id' => $id]);
                Db::exec('DELETE FROM ms_contract_terms WHERE contract_id = ?', [$id]);
            }

            foreach ($data['terms'] as $term) {
                Db::insert('ms_contract_terms', [
                    'contract_id' => $id,
                    'termin_ke'   => (int) $term['termin_ke'],
                    'deskripsi'   => $term['deskripsi'],
                    'persentase'  => number_format((float) $term['persentase'], 2, '.', ''),
                    'nilai'       => number_format((float) $term['nilai'], 2, '.', ''),
                    'status'      => $statusLama[(int) $term['termin_ke']] ?? 'belum',
                ]);
            }

            return $id;
        });
    }

    public static function ubahStatusContract(int $id, string $status): void
    {
        if (!array_key_exists($status, self::STATUS_KONTRAK)) {
            throw new \RuntimeException('Status kontrak tidak dikenal.');
        }
        Db::exec('UPDATE ms_contracts SET status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * Tagih satu termin kontrak menjadi draft invoice AR.
     */
    public static function tagihTermin(int $termId): int
    {
        $term = Db::one(
            'SELECT t.*, c.nomor AS contract_nomor, c.project_id, c.partner_id, c.ppn_persen, c.status AS contract_status
             FROM ms_contract_terms t JOIN ms_contracts c ON c.id = t.contract_id
             WHERE t.id = ?',
            [$termId]
        );
        if ($term === null) {
            throw new \RuntimeException('Termin tidak ditemukan.');
        }
        if (!in_array((string) $term['contract_status'], ['aktif', 'selesai'], true)) {
            throw new \RuntimeException('Kontrak harus aktif sebelum termin dapat ditagih.');
        }
        if ((string) $term['status'] !== 'belum') {
            throw new \RuntimeException('Termin ini sudah pernah ditagih.');
        }
        $sudah = (int) Db::val(
            'SELECT COUNT(*) FROM ar_invoices WHERE contract_id = ? AND termin_ke = ? AND status <> \'void\'',
            [(int) $term['contract_id'], (int) $term['termin_ke']]
        );
        if ($sudah > 0) {
            throw new \RuntimeException('Invoice untuk termin ini sudah ada.');
        }
        if ((int) $term['partner_id'] <= 0) {
            throw new \RuntimeException('Kontrak belum memiliki pemberi kerja.');
        }

        $nilai = (float) $term['nilai'];
        $ppnPersen = (float) $term['ppn_persen'];
        $data = ArService::normalisasi([
            'partner_id'    => (int) $term['partner_id'],
            'project_id'    => $term['project_id'],
            'contract_id'   => (int) $term['contract_id'],
            'termin_ke'     => (int) $term['termin_ke'],
            'tanggal'       => today(),
            'termin_hari'   => 30,
            'deskripsi'     => 'Termin ' . $term['termin_ke'] . ' ' . $term['contract_nomor'] . ' — ' . $term['deskripsi'],
            'items'         => [[
                'keterangan' => 'Termin ' . $term['termin_ke'] . ': ' . $term['deskripsi'],
                'coa_id'     => AccountMap::pendapatanUsaha(),
                'jumlah'     => $nilai,
                'ppn'        => round($nilai * $ppnPersen / 100, 2),
            ]],
        ]);

        $invoiceId = ArService::simpan(null, $data);
        Db::exec('UPDATE ms_contract_terms SET status = ? WHERE id = ?', ['ditagih', $termId]);

        return $invoiceId;
    }

    /** @return array<string,mixed> */
    public static function ringkasan(): array
    {
        return [
            'proyek_aktif'  => (int) Db::val("SELECT COUNT(*) FROM ms_projects WHERE status = 'berjalan' AND is_active = 1"),
            'nilai_kontrak' => (float) Db::val("SELECT COALESCE(SUM(nilai_kontrak), 0) FROM ms_contracts WHERE status IN ('aktif','selesai')"),
            'diakui'        => (float) Db::val("SELECT COALESCE(SUM(nilai), 0) FROM revenue_recognitions WHERE status = 'posted'"),
            'termin_belum'  => (int) Db::val("SELECT COUNT(*) FROM ms_contract_terms WHERE status = 'belum'"),
            'kontrak_aktif' => (int) Db::val("SELECT COUNT(*) FROM ms_contracts WHERE status = 'aktif'"),
        ];
    }
}
