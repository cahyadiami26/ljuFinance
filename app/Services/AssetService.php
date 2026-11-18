<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Audit;
use Lju\Core\Db;

/**
 * Aset tetap: kartu inventaris, perolehan, penyusutan otomatis
 * (garis lurus / saldo menurun ganda / jumlah angka tahun), mutasi,
 * penjualan, dan penghapusan beserta jurnalnya.
 */
final class AssetService
{
    public const STATUS = [
        'draft'    => 'Draft',
        'aktif'    => 'Aktif',
        'nonaktif' => 'Nonaktif',
        'dijual'   => 'Dijual',
        'dihapus'  => 'Dihapus',
    ];

    public const METODE = [
        'garis_lurus'          => 'Garis Lurus',
        'saldo_menurun_ganda'  => 'Saldo Menurun Ganda',
        'jumlah_angka_tahun'   => 'Jumlah Angka Tahun',
        'unit_produksi'        => 'Unit Produksi',
    ];

    public const MUTASI = [
        'perolehan'      => 'Perolehan',
        'mutasi_lokasi'  => 'Mutasi Lokasi',
        'penjualan'      => 'Penjualan',
        'penghapusan'    => 'Penghapusan',
        'revaluasi'      => 'Revaluasi',
    ];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT a.*, c.kode AS kategori_kode, c.nama AS kategori_nama, c.metode, c.tarif_tahunan,
                    c.coa_aset_id, c.coa_akumulasi_id, c.coa_beban_penyusutan_id,
                    u.name AS penanggung_jawab_nama, b.nama AS bidang_nama, d.nama AS divisi_nama,
                    pr.nama AS project_nama, j.nomor AS journal_nomor
             FROM fixed_assets a
             LEFT JOIN asset_categories c ON c.id = a.category_id
             LEFT JOIN app_users u ON u.id = a.penanggung_jawab_user_id
             LEFT JOIN ms_bidang b ON b.id = a.bidang_id
             LEFT JOIN ms_divisi d ON d.id = a.divisi_id
             LEFT JOIN ms_projects pr ON pr.id = a.project_id
             LEFT JOIN journal_entries j ON j.id = a.journal_id
             WHERE a.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function categories(): array
    {
        return Db::all('SELECT * FROM asset_categories WHERE is_active = 1 ORDER BY kode');
    }

    /** @return array<string,mixed> */
    public static function ringkasan(?int $tahun = null): array
    {
        $where = "status <> 'dihapus'";
        $params = [];
        if ($tahun !== null && $tahun > 0) {
            $where .= ' AND YEAR(tanggal_perolehan) <= ?';
            $params[] = $tahun;
        }

        $row = Db::one(
            'SELECT COUNT(*) AS jumlah,
                    COALESCE(SUM(harga_perolehan),0) AS harga,
                    COALESCE(SUM(akumulasi_penyusutan),0) AS akumulasi,
                    COALESCE(SUM(nilai_buku),0) AS nilai_buku,
                    COALESCE(SUM(CASE WHEN status = \'aktif\' THEN 1 ELSE 0 END),0) AS aktif,
                    COALESCE(SUM(CASE WHEN status = \'draft\' THEN 1 ELSE 0 END),0) AS draft,
                    COALESCE(SUM(CASE WHEN status = \'dijual\' THEN 1 ELSE 0 END),0) AS dijual
             FROM fixed_assets WHERE ' . $where,
            $params
        );

        return [
            'jumlah'    => (int) ($row['jumlah'] ?? 0),
            'harga'     => (float) ($row['harga'] ?? 0),
            'akumulasi' => (float) ($row['akumulasi'] ?? 0),
            'nilai_buku' => (float) ($row['nilai_buku'] ?? 0),
            'aktif'     => (int) ($row['aktif'] ?? 0),
            'draft'     => (int) ($row['draft'] ?? 0),
            'dijual'    => (int) ($row['dijual'] ?? 0),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function perKategori(): array
    {
        return Db::all(
            "SELECT c.nama, c.kode, COUNT(a.id) AS jumlah,
                    COALESCE(SUM(a.harga_perolehan),0) AS harga,
                    COALESCE(SUM(a.akumulasi_penyusutan),0) AS akumulasi,
                    COALESCE(SUM(a.nilai_buku),0) AS nilai_buku
             FROM asset_categories c
             LEFT JOIN fixed_assets a ON a.category_id = c.id AND a.status IN ('aktif','nonaktif')
             GROUP BY c.id, c.nama, c.kode
             ORDER BY c.kode"
        );
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalisasi(array $input): array
    {
        $harga = JournalService::uang($input['harga_perolehan'] ?? 0);
        $residu = JournalService::uang($input['nilai_residu'] ?? 0);
        $umur = (int) ($input['umur_bulan'] ?? 0);

        return [
            'kode'                  => trim((string) ($input['kode'] ?? '')),
            'nama'                  => trim((string) ($input['nama'] ?? '')),
            'category_id'           => (int) ($input['category_id'] ?? 0) ?: null,
            'tanggal_perolehan'     => trim((string) ($input['tanggal_perolehan'] ?? '')) ?: today(),
            'harga_perolehan'       => $harga,
            'nilai_residu'          => $residu,
            'umur_bulan'            => $umur,
            'lokasi'                => trim((string) ($input['lokasi'] ?? '')),
            'penanggung_jawab_user_id' => (int) ($input['penanggung_jawab_user_id'] ?? 0) ?: null,
            'bidang_id'             => (int) ($input['bidang_id'] ?? 0) ?: null,
            'divisi_id'             => (int) ($input['divisi_id'] ?? 0) ?: null,
            'project_id'            => (int) ($input['project_id'] ?? 0) ?: null,
            'no_invoice'            => trim((string) ($input['no_invoice'] ?? '')),
            'keterangan'            => trim((string) ($input['keterangan'] ?? '')),
            'bank_account_id'       => (int) ($input['bank_account_id'] ?? 0) ?: null,
        ];
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasi(array $data, ?int $id = null): array
    {
        $errors = [];

        if ($data['nama'] === '') {
            $errors['nama'] = 'Nama aset wajib diisi.';
        }
        if ($data['category_id'] === null) {
            $errors['category_id'] = 'Kategori aset wajib dipilih.';
        }
        if (strtotime((string) $data['tanggal_perolehan']) === false) {
            $errors['tanggal_perolehan'] = 'Tanggal perolehan tidak valid.';
        }
        if ($data['harga_perolehan'] <= 0) {
            $errors['harga_perolehan'] = 'Harga perolehan harus lebih besar dari nol.';
        }
        if ($data['nilai_residu'] < 0 || $data['nilai_residu'] > $data['harga_perolehan']) {
            $errors['nilai_residu'] = 'Nilai residu tidak boleh negatif atau melebihi harga perolehan.';
        }
        if ($data['umur_bulan'] < 0) {
            $errors['umur_bulan'] = 'Umur ekonomis tidak boleh negatif.';
        }
        if ($data['umur_bulan'] === 0 && $data['nilai_residu'] < $data['harga_perolehan']) {
            $kategori = $data['category_id'] !== null
                ? Db::one('SELECT umur_bulan FROM asset_categories WHERE id = ?', [$data['category_id']])
                : null;
            if ($kategori !== null && (int) $kategori['umur_bulan'] > 0) {
                $errors['umur_bulan'] = 'Umur ekonomis wajib diisi (kategori memiliki umur default).';
            }
        }
        if ($data['kode'] !== '') {
            $duplikat = Db::val(
                'SELECT COUNT(*) FROM fixed_assets WHERE kode = ? AND id <> ?',
                [$data['kode'], $id ?? 0]
            );
            if ((int) $duplikat > 0) {
                $errors['kode'] = 'Kode inventaris sudah dipakai aset lain.';
            }
        }

        return $errors;
    }

    /** Simpan aset baru/perubahan. Nomor inventaris otomatis bila kosong. */
    public static function simpan(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            $kategori = $data['category_id'] !== null
                ? Db::one('SELECT umur_bulan FROM asset_categories WHERE id = ?', [$data['category_id']])
                : null;

            $umur = (int) $data['umur_bulan'];
            if ($umur <= 0 && $kategori !== null) {
                $umur = (int) $kategori['umur_bulan'];
            }

            $payload = [
                'nama'              => mb_substr((string) $data['nama'], 0, 150),
                'category_id'       => $data['category_id'],
                'tanggal_perolehan' => (string) $data['tanggal_perolehan'],
                'harga_perolehan'   => number_format((float) $data['harga_perolehan'], 2, '.', ''),
                'nilai_residu'      => number_format((float) $data['nilai_residu'], 2, '.', ''),
                'umur_bulan'        => $umur,
                'lokasi'            => $data['lokasi'] !== '' ? mb_substr((string) $data['lokasi'], 0, 150) : null,
                'penanggung_jawab_user_id' => $data['penanggung_jawab_user_id'],
                'bidang_id'         => $data['bidang_id'],
                'divisi_id'         => $data['divisi_id'],
                'project_id'        => $data['project_id'],
                'no_invoice'        => $data['no_invoice'] !== '' ? mb_substr((string) $data['no_invoice'], 0, 80) : null,
                'keterangan'        => $data['keterangan'] !== '' ? mb_substr((string) $data['keterangan'], 0, 255) : null,
            ];

            if ($id === null) {
                $kode = (string) $data['kode'];
                if ($kode === '') {
                    $kode = NumberingService::next('AST', (string) $data['tanggal_perolehan']);
                }

                $payload['kode'] = $kode;
                $payload['akumulasi_penyusutan'] = '0.00';
                $payload['nilai_buku'] = number_format((float) $data['harga_perolehan'], 2, '.', '');
                $payload['status'] = 'draft';
                $payload['created_by'] = auth_id() ?: null;

                $id = Db::insert('fixed_assets', $payload);

                Db::insert('asset_mutations', [
                    'asset_id'   => $id,
                    'tipe'       => 'perolehan',
                    'tanggal'    => (string) $data['tanggal_perolehan'],
                    'keterangan' => 'Perolehan aset' . ($data['no_invoice'] !== '' ? ' (invoice ' . $data['no_invoice'] . ')' : ''),
                    'nilai'      => number_format((float) $data['harga_perolehan'], 2, '.', ''),
                    'created_by' => auth_id() ?: null,
                ]);
            } else {
                $lama = self::find($id);
                if ($lama === null) {
                    throw new \RuntimeException('Aset tidak ditemukan.');
                }
                if (!in_array((string) $lama['status'], ['draft', 'aktif', 'nonaktif'], true)) {
                    throw new \RuntimeException('Aset berstatus ' . self::STATUS[(string) $lama['status']] . ' tidak dapat diubah.');
                }

                $payload['nilai_buku'] = number_format(
                    max(0.0, (float) $data['harga_perolehan'] - (float) $lama['akumulasi_penyusutan']),
                    2,
                    '.',
                    ''
                );

                if ((string) $data['kode'] !== '' && (string) $data['kode'] !== (string) $lama['kode']) {
                    $payload['kode'] = (string) $data['kode'];
                }

                Db::update('fixed_assets', $payload, 'id = :id', [':id' => $id]);
            }

            return $id;
        });
    }

    /**
     * Aktifkan aset dan catat jurnal perolehan (D aset / K bank atau K utang).
     */
    public static function aktifkan(int $id, ?int $bankAccountId = null, ?int $coaKreditId = null): int
    {
        return Db::transaction(static function () use ($id, $bankAccountId, $coaKreditId): int {
            $row = self::find($id);
            if ($row === null) {
                throw new \RuntimeException('Aset tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'draft') {
                throw new \RuntimeException('Hanya aset draft yang dapat diaktifkan.');
            }
            if (empty($row['coa_aset_id'])) {
                throw new \RuntimeException('Kategori aset belum memiliki akun COA aset.');
            }

            if ($bankAccountId !== null && $bankAccountId > 0) {
                $coaKredit = AccountMap::bankCoa($bankAccountId);
            } elseif ($coaKreditId !== null && $coaKreditId > 0) {
                $coaKredit = $coaKreditId;
            } else {
                $coaKredit = AccountMap::utangUsaha();
            }

            $journalId = JournalService::catat([
                'tanggal'      => (string) $row['tanggal_perolehan'],
                'tipe'         => 'otomatis',
                'sumber'       => 'aset',
                'referensi'    => (string) $row['kode'],
                'deskripsi'    => 'Perolehan aset tetap ' . $row['kode'] . ' — ' . $row['nama'],
                'kode_dokumen' => 'AST',
            ], [
                [
                    'coa_id'     => (int) $row['coa_aset_id'],
                    'deskripsi'  => 'Perolehan ' . $row['nama'],
                    'debit'      => (float) $row['harga_perolehan'],
                    'bidang_id'  => $row['bidang_id'],
                    'divisi_id'  => $row['divisi_id'],
                    'project_id' => $row['project_id'],
                ],
                [
                    'coa_id'     => $coaKredit,
                    'deskripsi'  => 'Pembayaran perolehan ' . $row['nama'],
                    'kredit'     => (float) $row['harga_perolehan'],
                    'bidang_id'  => $row['bidang_id'],
                    'divisi_id'  => $row['divisi_id'],
                    'project_id' => $row['project_id'],
                ],
            ]);

            Db::exec('UPDATE fixed_assets SET status = ?, journal_id = ? WHERE id = ?', ['aktif', $journalId, $id]);
            Db::exec(
                "UPDATE asset_mutations SET journal_id = ? WHERE asset_id = ? AND tipe = 'perolehan' ORDER BY id DESC LIMIT 1",
                [$journalId, $id]
            );

            Audit::log('activate', 'asset', [
                'ref_table'   => 'fixed_assets',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['kode'],
                'description' => 'Aktifkan aset ' . $row['kode'] . ' dan catat jurnal perolehan',
                'new'         => ['journal_id' => $journalId],
            ]);

            return $journalId;
        });
    }

    /** Nonaktifkan aset tanpa menghapus histori. */
    public static function nonaktifkan(int $id): void
    {
        $row = self::find($id);
        if ($row === null) {
            throw new \RuntimeException('Aset tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'aktif') {
            throw new \RuntimeException('Hanya aset aktif yang dapat dinonaktifkan.');
        }

        Db::exec('UPDATE fixed_assets SET status = ? WHERE id = ?', ['nonaktif', $id]);

        Audit::log('update', 'asset', [
            'ref_table'   => 'fixed_assets',
            'ref_id'      => $id,
            'ref_code'    => (string) $row['kode'],
            'description' => 'Nonaktifkan aset ' . $row['kode'],
        ]);
    }

    /**
     * Nilai penyusutan satu periode berdasarkan metode kategori.
     *
     * @param array<string,mixed> $asset baris fixed_assets + kolom kategori
     */
    public static function hitungPenyusutan(array $asset, int $bulanKe): float
    {
        $harga = (float) $asset['harga_perolehan'];
        $residu = (float) $asset['nilai_residu'];
        $umur = (int) $asset['umur_bulan'];
        $akumulasi = (float) $asset['akumulasi_penyusutan'];
        $dasar = max(0.0, $harga - $residu);

        if ($umur <= 0 || $bulanKe <= 0) {
            return 0.0;
        }

        $sisa = max(0.0, $dasar - $akumulasi);
        if ($sisa <= 0.0) {
            return 0.0;
        }

        $metode = (string) ($asset['metode'] ?? 'garis_lurus');

        $nilai = match ($metode) {
            'saldo_menurun_ganda' => (max(0.0, $harga - $akumulasi)) * (2 / $umur),
            'jumlah_angka_tahun'  => $umur >= $bulanKe
                ? $dasar * (($umur - $bulanKe + 1) / (($umur * ($umur + 1)) / 2))
                : 0.0,
            default               => $dasar / $umur,
        };

        return round(min($nilai, $sisa), 2);
    }

    /**
     * Hitung penyusutan seluruh aset aktif untuk satu periode (belum disimpan).
     *
     * @return array{rows:array<int,array<string,mixed>>,total:float,periode:array<string,mixed>}
     */
    public static function rencanaPenyusutan(int $periodeId): array
    {
        $periode = Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId]);
        if ($periode === null) {
            throw new \RuntimeException('Periode akuntansi tidak ditemukan.');
        }

        $rows = [];
        $total = 0.0;
        $daftar = Db::all(
            "SELECT a.*, c.metode, c.nama AS kategori_nama, c.coa_akumulasi_id, c.coa_beban_penyusutan_id
             FROM fixed_assets a
             JOIN asset_categories c ON c.id = a.category_id
             WHERE a.status = 'aktif' AND a.umur_bulan > 0
             ORDER BY a.kode"
        );

        foreach ($daftar as $asset) {
            $rows[$asset['id']] = $asset + ['penyusutan' => 0.0, 'bulan_ke' => 0, 'alasan' => ''];

            if (empty($asset['coa_akumulasi_id']) || empty($asset['coa_beban_penyusutan_id'])) {
                $rows[$asset['id']]['alasan'] = 'Kategori belum memiliki akun akumulasi/beban penyusutan.';
                continue;
            }

            $bulanKe = self::bulanBerjalan((string) $asset['tanggal_perolehan'], (int) $periode['tahun'], (int) $periode['bulan']);
            if ($bulanKe <= 0) {
                $rows[$asset['id']]['alasan'] = 'Aset belum memasuki masa penyusutan pada periode ini.';
                continue;
            }

            $sudah = Db::val(
                'SELECT COUNT(*) FROM asset_depreciations WHERE asset_id = ? AND periode_id = ?',
                [(int) $asset['id'], $periodeId]
            );
            if ((int) $sudah > 0) {
                $rows[$asset['id']]['alasan'] = 'Penyusutan periode ini sudah dibuat.';
                continue;
            }

            if ($bulanKe > (int) $asset['umur_bulan']) {
                $rows[$asset['id']]['alasan'] = 'Umur ekonomis sudah berakhir.';
                continue;
            }

            $nilai = self::hitungPenyusutan($asset, $bulanKe);
            if ($nilai <= 0) {
                $rows[$asset['id']]['alasan'] = 'Nilai buku sudah sama dengan nilai residu.';
                continue;
            }

            $rows[$asset['id']]['penyusutan'] = $nilai;
            $rows[$asset['id']]['bulan_ke'] = $bulanKe;
            $total += $nilai;
        }

        return ['rows' => array_values($rows), 'total' => round($total, 2), 'periode' => $periode];
    }

    /**
     * Simpan rencana penyusutan sebagai draft (idempotent per aset+periode).
     *
     * @return int jumlah baris draft yang dibuat
     */
    public static function prosesPenyusutan(int $periodeId): int
    {
        return Db::transaction(static function () use ($periodeId): int {
            $rencana = self::rencanaPenyusutan($periodeId);
            $periode = $rencana['periode'];
            $jumlah = 0;

            foreach ($rencana['rows'] as $row) {
                if ((float) $row['penyusutan'] <= 0) {
                    continue;
                }

                $akumulasi = (float) $row['akumulasi_penyusutan'] + (float) $row['penyusutan'];
                Db::exec(
                    'INSERT INTO asset_depreciations (asset_id, periode_id, tanggal, penyusutan, akumulasi, nilai_buku, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE penyusutan = VALUES(penyusutan), akumulasi = VALUES(akumulasi),
                        nilai_buku = VALUES(nilai_buku), status = \'draft\', journal_id = NULL',
                    [
                        (int) $row['id'],
                        $periodeId,
                        sprintf('%04d-%02d-%02d', (int) $periode['tahun'], (int) $periode['bulan'], 1),
                        number_format((float) $row['penyusutan'], 2, '.', ''),
                        number_format($akumulasi, 2, '.', ''),
                        number_format(max(0.0, (float) $row['harga_perolehan'] - $akumulasi), 2, '.', ''),
                        'draft',
                        auth_id() ?: null,
                    ]
                );
                $jumlah++;
            }

            Audit::log('create', 'asset', [
                'ref_table'   => 'asset_depreciations',
                'ref_id'      => $periodeId,
                'ref_code'    => 'DEP/' . $periode['kode'],
                'description' => 'Buat draft penyusutan ' . $periode['nama'] . ' untuk ' . $jumlah . ' aset',
            ]);

            return $jumlah;
        });
    }

    /**
     * Posting penyusutan periode: satu jurnal agregat per kategori
     * (D beban penyusutan / K akumulasi penyusutan).
     */
    public static function postingPenyusutan(int $periodeId): int
    {
        return Db::transaction(static function () use ($periodeId): int {
            $periode = Db::one('SELECT * FROM fiscal_periods WHERE id = ?', [$periodeId]);
            if ($periode === null) {
                throw new \RuntimeException('Periode akuntansi tidak ditemukan.');
            }

            $rows = Db::all(
                "SELECT d.*, a.harga_perolehan, a.nilai_buku AS nilai_buku_aset, a.kode AS asset_kode,
                        c.coa_beban_penyusutan_id, c.coa_akumulasi_id
                 FROM asset_depreciations d
                 JOIN fixed_assets a ON a.id = d.asset_id
                 JOIN asset_categories c ON c.id = a.category_id
                 WHERE d.periode_id = ? AND d.status = 'draft'
                 ORDER BY a.kode",
                [$periodeId]
            );

            if ($rows === []) {
                throw new \RuntimeException('Tidak ada draft penyusutan untuk periode ini.');
            }

            $beban = [];
            $akumulasi = [];
            $total = 0.0;

            foreach ($rows as $row) {
                $nilai = (float) $row['penyusutan'];
                $coaBeban = (int) $row['coa_beban_penyusutan_id'];
                $coaAkumulasi = (int) $row['coa_akumulasi_id'];

                if ($coaBeban <= 0 || $coaAkumulasi <= 0) {
                    throw new \RuntimeException('Kategori aset ' . $row['asset_kode'] . ' belum memiliki akun penyusutan.');
                }

                $beban[$coaBeban] = ($beban[$coaBeban] ?? 0.0) + $nilai;
                $akumulasi[$coaAkumulasi] = ($akumulasi[$coaAkumulasi] ?? 0.0) + $nilai;
                $total += $nilai;
            }

            $lines = [];
            foreach ($beban as $coaId => $nilai) {
                $lines[] = [
                    'coa_id'    => $coaId,
                    'deskripsi' => 'Beban penyusutan ' . $periode['nama'],
                    'debit'     => $nilai,
                ];
            }
            foreach ($akumulasi as $coaId => $nilai) {
                $lines[] = [
                    'coa_id'    => $coaId,
                    'deskripsi' => 'Akumulasi penyusutan ' . $periode['nama'],
                    'kredit'    => $nilai,
                ];
            }

            $journalId = JournalService::catat([
                'tanggal'      => (string) $rows[0]['tanggal'],
                'tipe'         => 'otomatis',
                'sumber'       => 'aset',
                'referensi'    => 'DEP/' . $periode['kode'],
                'deskripsi'    => 'Penyusutan aset tetap ' . $periode['nama'],
                'kode_dokumen' => 'DEP',
            ], $lines);

            foreach ($rows as $row) {
                Db::exec(
                    "UPDATE asset_depreciations SET status = 'posted', journal_id = ? WHERE id = ?",
                    [$journalId, (int) $row['id']]
                );

                Db::exec(
                    'UPDATE fixed_assets SET akumulasi_penyusutan = ?, nilai_buku = ? WHERE id = ?',
                    [
                        number_format((float) $row['akumulasi'], 2, '.', ''),
                        number_format((float) $row['nilai_buku'], 2, '.', ''),
                        (int) $row['asset_id'],
                    ]
                );
            }

            Audit::log('post', 'asset', [
                'ref_table'   => 'asset_depreciations',
                'ref_id'      => $periodeId,
                'ref_code'    => 'DEP/' . $periode['kode'],
                'description' => 'Posting penyusutan ' . $periode['nama'] . ' sebesar ' . rupiah($total),
                'new'         => ['journal_id' => $journalId, 'total' => $total, 'aset' => count($rows)],
            ]);

            return $journalId;
        });
    }

    /** Hapus seluruh draft penyusutan periode (belum posted). */
    public static function hapusDraftPenyusutan(int $periodeId): int
    {
        $jumlah = (int) Db::val(
            "SELECT COUNT(*) FROM asset_depreciations WHERE periode_id = ? AND status = 'draft'",
            [$periodeId]
        );
        Db::exec("DELETE FROM asset_depreciations WHERE periode_id = ? AND status = 'draft'", [$periodeId]);
        return $jumlah;
    }

    /** @return array<int,array<string,mixed>> */
    public static function riwayatPenyusutan(int $assetId): array
    {
        return Db::all(
            'SELECT d.*, p.nama AS periode_nama, j.nomor AS journal_nomor
             FROM asset_depreciations d
             LEFT JOIN fiscal_periods p ON p.id = d.periode_id
             LEFT JOIN journal_entries j ON j.id = d.journal_id
             WHERE d.asset_id = ?
             ORDER BY d.tanggal DESC, d.id DESC',
            [$assetId]
        );
    }

    /**
     * Daftar penyusutan satu periode beserta statusnya.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function daftarPenyusutan(int $periodeId): array
    {
        return Db::all(
            'SELECT d.*, a.kode AS asset_kode, a.nama AS asset_nama, c.nama AS kategori_nama,
                    j.nomor AS journal_nomor
             FROM asset_depreciations d
             JOIN fixed_assets a ON a.id = d.asset_id
             LEFT JOIN asset_categories c ON c.id = a.category_id
             LEFT JOIN journal_entries j ON j.id = d.journal_id
             WHERE d.periode_id = ?
             ORDER BY a.kode',
            [$periodeId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function mutasi(int $assetId): array
    {
        return Db::all(
            'SELECT m.*, j.nomor AS journal_nomor, u.name AS created_by_name
             FROM asset_mutations m
             LEFT JOIN journal_entries j ON j.id = m.journal_id
             LEFT JOIN app_users u ON u.id = m.created_by
             WHERE m.asset_id = ?
             ORDER BY m.tanggal, m.id',
            [$assetId]
        );
    }

    public static function mutasiLokasi(int $id, string $lokasi, ?string $tanggal = null, string $keterangan = ''): void
    {
        Db::transaction(static function () use ($id, $lokasi, $tanggal, $keterangan): void {
            $row = self::find($id);
            if ($row === null) {
                throw new \RuntimeException('Aset tidak ditemukan.');
            }
            if (!in_array((string) $row['status'], ['aktif', 'nonaktif'], true)) {
                throw new \RuntimeException('Mutasi hanya untuk aset aktif/nonaktif.');
            }
            if (trim($lokasi) === '') {
                throw new \RuntimeException('Lokasi tujuan wajib diisi.');
            }

            Db::insert('asset_mutations', [
                'asset_id'    => $id,
                'tipe'        => 'mutasi_lokasi',
                'tanggal'     => $tanggal !== null && $tanggal !== '' ? $tanggal : today(),
                'keterangan'  => $keterangan !== '' ? mb_substr($keterangan, 0, 255) : 'Mutasi lokasi aset',
                'nilai'       => '0.00',
                'dari_lokasi' => $row['lokasi'] !== null ? mb_substr((string) $row['lokasi'], 0, 150) : null,
                'ke_lokasi'   => mb_substr(trim($lokasi), 0, 150),
                'created_by'  => auth_id() ?: null,
            ]);

            Db::exec('UPDATE fixed_assets SET lokasi = ? WHERE id = ?', [mb_substr(trim($lokasi), 0, 150), $id]);

            Audit::log('update', 'asset', [
                'ref_table'   => 'fixed_assets',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['kode'],
                'description' => 'Mutasi lokasi aset ' . $row['kode'] . ' ke ' . $lokasi,
                'old'         => ['lokasi' => $row['lokasi']],
                'new'         => ['lokasi' => $lokasi],
            ]);
        });
    }

    /**
     * Penjualan aset: D Bank + D Akumulasi / K Aset / K Laba (atau D Rugi).
     */
    public static function jual(int $id, array $input): int
    {
        return Db::transaction(static function () use ($id, $input): int {
            $row = self::find($id);
            if ($row === null) {
                throw new \RuntimeException('Aset tidak ditemukan.');
            }
            if ((string) $row['status'] !== 'aktif') {
                throw new \RuntimeException('Hanya aset aktif yang dapat dijual.');
            }

            $hargaJual = JournalService::uang($input['harga_jual'] ?? 0);
            if ($hargaJual <= 0) {
                throw new \RuntimeException('Harga jual harus lebih besar dari nol.');
            }

            $tanggal = trim((string) ($input['tanggal'] ?? '')) ?: today();
            $bankId = (int) ($input['bank_account_id'] ?? 0);
            if ($bankId <= 0) {
                throw new \RuntimeException('Rekening penerimaan wajib dipilih.');
            }

            $harga = (float) $row['harga_perolehan'];
            $akumulasi = (float) $row['akumulasi_penyusutan'];
            $nilaiBuku = max(0.0, $harga - $akumulasi);
            $selisih = round($hargaJual - $nilaiBuku, 2);

            $lines = [[
                'coa_id'    => AccountMap::bankCoa($bankId),
                'deskripsi' => 'Penerimaan penjualan ' . $row['nama'],
                'debit'     => $hargaJual,
                'bidang_id' => $row['bidang_id'],
                'divisi_id' => $row['divisi_id'],
            ]];

            if ($akumulasi > 0) {
                $lines[] = [
                    'coa_id'    => (int) $row['coa_akumulasi_id'],
                    'deskripsi' => 'Akumulasi penyusutan ' . $row['nama'],
                    'debit'     => $akumulasi,
                ];
            }

            $lines[] = [
                'coa_id'    => (int) $row['coa_aset_id'],
                'deskripsi' => 'Penghapusan nilai perolehan ' . $row['nama'],
                'kredit'    => $harga,
                'bidang_id' => $row['bidang_id'],
                'divisi_id' => $row['divisi_id'],
            ];

            if ($selisih > 0) {
                $lines[] = [
                    'coa_id'    => AccountMap::labaPenjualanAset(),
                    'deskripsi' => 'Laba penjualan aset ' . $row['kode'],
                    'kredit'    => $selisih,
                ];
            } elseif ($selisih < 0) {
                $lines[] = [
                    'coa_id'    => AccountMap::rugiPenjualanAset(),
                    'deskripsi' => 'Rugi penjualan aset ' . $row['kode'],
                    'debit'     => abs($selisih),
                ];
            }

            $journalId = JournalService::catat([
                'tanggal'      => $tanggal,
                'tipe'         => 'otomatis',
                'sumber'       => 'aset',
                'referensi'    => (string) $row['kode'],
                'deskripsi'    => 'Penjualan aset tetap ' . $row['kode'] . ' — ' . $row['nama'],
                'kode_dokumen' => 'AST',
            ], $lines);

            Db::exec('UPDATE fixed_assets SET status = ?, journal_id = ? WHERE id = ?', ['dijual', $journalId, $id]);

            Db::insert('asset_mutations', [
                'asset_id'    => $id,
                'tipe'        => 'penjualan',
                'tanggal'     => $tanggal,
                'keterangan'  => mb_substr('Penjualan aset, harga ' . rupiah($hargaJual) . ', laba/rugi ' . rupiah($selisih), 0, 255),
                'nilai'       => number_format($hargaJual, 2, '.', ''),
                'journal_id'  => $journalId,
                'created_by'  => auth_id() ?: null,
            ]);

            Audit::log('sell', 'asset', [
                'ref_table'   => 'fixed_assets',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['kode'],
                'description' => 'Jual aset ' . $row['kode'] . ' sebesar ' . rupiah($hargaJual),
                'new'         => ['journal_id' => $journalId, 'laba_rugi' => $selisih],
            ]);

            return $journalId;
        });
    }

    /**
     * Penghapusan aset (tanpa penerimaan kas): D Akumulasi + D Rugi / K Aset.
     */
    public static function hapuskan(int $id, array $input): int
    {
        return Db::transaction(static function () use ($id, $input): int {
            $row = self::find($id);
            if ($row === null) {
                throw new \RuntimeException('Aset tidak ditemukan.');
            }
            if (!in_array((string) $row['status'], ['aktif', 'nonaktif'], true)) {
                throw new \RuntimeException('Hanya aset aktif/nonaktif yang dapat dihapuskan.');
            }

            $tanggal = trim((string) ($input['tanggal'] ?? '')) ?: today();
            $alasan = trim((string) ($input['keterangan'] ?? ''));
            if ($alasan === '') {
                throw new \RuntimeException('Alasan penghapusan wajib diisi.');
            }

            $harga = (float) $row['harga_perolehan'];
            $akumulasi = (float) $row['akumulasi_penyusutan'];
            $nilaiBuku = max(0.0, $harga - $akumulasi);

            $lines = [];
            if ($akumulasi > 0) {
                $lines[] = [
                    'coa_id'    => (int) $row['coa_akumulasi_id'],
                    'deskripsi' => 'Akumulasi penyusutan ' . $row['nama'],
                    'debit'     => $akumulasi,
                ];
            }
            if ($nilaiBuku > 0) {
                $lines[] = [
                    'coa_id'    => AccountMap::rugiPenjualanAset(),
                    'deskripsi' => 'Rugi penghapusan aset ' . $row['kode'],
                    'debit'     => $nilaiBuku,
                ];
            }
            $lines[] = [
                'coa_id'    => (int) $row['coa_aset_id'],
                'deskripsi' => 'Penghapusan aset ' . $row['nama'],
                'kredit'    => $harga,
                'bidang_id' => $row['bidang_id'],
                'divisi_id' => $row['divisi_id'],
            ];

            $journalId = JournalService::catat([
                'tanggal'      => $tanggal,
                'tipe'         => 'otomatis',
                'sumber'       => 'aset',
                'referensi'    => (string) $row['kode'],
                'deskripsi'    => 'Penghapusan aset tetap ' . $row['kode'] . ' — ' . $alasan,
                'kode_dokumen' => 'AST',
            ], $lines);

            Db::exec('UPDATE fixed_assets SET status = ?, journal_id = ? WHERE id = ?', ['dihapus', $journalId, $id]);

            Db::insert('asset_mutations', [
                'asset_id'    => $id,
                'tipe'        => 'penghapusan',
                'tanggal'     => $tanggal,
                'keterangan'  => mb_substr('Penghapusan aset: ' . $alasan, 0, 255),
                'nilai'       => number_format($nilaiBuku, 2, '.', ''),
                'journal_id'  => $journalId,
                'created_by'  => auth_id() ?: null,
            ]);

            Audit::log('dispose', 'asset', [
                'ref_table'   => 'fixed_assets',
                'ref_id'      => $id,
                'ref_code'    => (string) $row['kode'],
                'description' => 'Hapus aset ' . $row['kode'] . ': ' . $alasan,
                'new'         => ['journal_id' => $journalId],
            ]);

            return $journalId;
        });
    }

    /** Hapus aset draft (belum pernah dijurnal). */
    public static function hapus(int $id): void
    {
        $row = self::find($id);
        if ($row === null) {
            throw new \RuntimeException('Aset tidak ditemukan.');
        }
        if ((string) $row['status'] !== 'draft' || !empty($row['journal_id'])) {
            throw new \RuntimeException('Hanya aset draft tanpa jurnal yang dapat dihapus.');
        }

        Db::exec('DELETE FROM fixed_assets WHERE id = ?', [$id]);
    }

    /** Bulan ke-n masa penyusutan (mulai bulan berikutnya setelah perolehan). */
    private static function bulanBerjalan(string $tanggalPerolehan, int $tahun, int $bulan): int
    {
        $perolehan = strtotime($tanggalPerolehan);
        if ($perolehan === false) {
            return 0;
        }

        $selisih = ($tahun - (int) date('Y', $perolehan)) * 12 + ($bulan - (int) date('n', $perolehan));
        return $selisih;
    }
}
