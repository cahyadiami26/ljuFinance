<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;

/**
 * Persediaan: master barang, gudang, kartu stok, dan mutasi masuk/keluar.
 *
 * Nilai persediaan memakai rata-rata bergerak. Penerimaan barang menambah
 * stok tanpa jurnal (jurnal pembelian dibuat saat tagihan AP diposting);
 * pengeluaran barang membentuk jurnal Dr beban / Cr persediaan.
 */
final class InventoryService
{
    public const TIPE = [
        'masuk'       => 'Masuk',
        'keluar'      => 'Keluar',
        'penyesuaian' => 'Penyesuaian',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function warehouses(bool $aktifSaja = true): array
    {
        $sql = 'SELECT * FROM warehouses';
        if ($aktifSaja) {
            $sql .= ' WHERE is_active = 1';
        }
        return Db::all($sql . ' ORDER BY kode');
    }

    /** @return array<string,mixed>|null */
    public static function item(int $id): ?array
    {
        return Db::one(
            'SELECT i.*, c.kode AS coa_persediaan_kode, c.nama AS coa_persediaan_nama,
                    b.kode AS coa_beban_kode, b.nama AS coa_beban_nama
             FROM inventory_items i
             JOIN coa_accounts c ON c.id = i.coa_persediaan_id
             LEFT JOIN coa_accounts b ON b.id = i.coa_beban_id
             WHERE i.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(string $q = '', string $kategori = '', int $limit = 200): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(i.kode LIKE ? OR i.nama LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%');
        }
        if ($kategori !== '') {
            $where[] = 'i.kategori = ?';
            $params[] = $kategori;
        }

        return Db::all(
            'SELECT i.*, (i.stok * i.harga_rata) AS nilai_stok,
                    c.kode AS coa_persediaan_kode
             FROM inventory_items i
             JOIN coa_accounts c ON c.id = i.coa_persediaan_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY i.kategori, i.nama
             LIMIT ' . max(1, min(1000, $limit)),
            $params
        );
    }

    /** @return array<string,mixed> */
    public static function ringkasan(): array
    {
        return [
            'jumlah_item'   => (int) Db::val('SELECT COUNT(*) FROM inventory_items WHERE is_active = 1'),
            'nilai_stok'    => (float) Db::val('SELECT COALESCE(SUM(stok * harga_rata), 0) FROM inventory_items'),
            'stok_minimum'  => (int) Db::val('SELECT COUNT(*) FROM inventory_items WHERE is_active = 1 AND stok_min > 0 AND stok <= stok_min'),
            'mutasi_bulan'  => (int) Db::val("SELECT COUNT(*) FROM inventory_movements WHERE DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"),
            'gudang'        => (int) Db::val('SELECT COUNT(*) FROM warehouses WHERE is_active = 1'),
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalisasiItem(array $input): array
    {
        return [
            'kode'              => strtoupper(trim((string) ($input['kode'] ?? ''))),
            'nama'              => trim((string) ($input['nama'] ?? '')),
            'kategori'          => trim((string) ($input['kategori'] ?? 'material')) ?: 'material',
            'satuan'            => trim((string) ($input['satuan'] ?? 'unit')) ?: 'unit',
            'coa_persediaan_id' => (int) ($input['coa_persediaan_id'] ?? 0),
            'coa_beban_id'      => (int) ($input['coa_beban_id'] ?? 0) ?: null,
            'stok_min'          => max(0.0, JournalService::uang($input['stok_min'] ?? 0)),
            'is_active'         => (int) ($input['is_active'] ?? 1) ? 1 : 0,
        ];
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasiItem(array $data, ?int $id = null): array
    {
        $errors = [];

        if ($data['kode'] === '') {
            $errors['kode'] = 'Kode barang wajib diisi.';
        } elseif (mb_strlen((string) $data['kode']) > 30) {
            $errors['kode'] = 'Kode barang maksimal 30 karakter.';
        } else {
            $dupe = (int) Db::val('SELECT COUNT(*) FROM inventory_items WHERE kode = ? AND id <> ?', [$data['kode'], $id ?? 0]);
            if ($dupe > 0) {
                $errors['kode'] = 'Kode barang sudah dipakai.';
            }
        }

        if ($data['nama'] === '') {
            $errors['nama'] = 'Nama barang wajib diisi.';
        }
        if ((int) $data['coa_persediaan_id'] <= 0) {
            $errors['coa_persediaan_id'] = 'Akun persediaan wajib dipilih.';
        }

        return $errors;
    }

    /** @param array<string,mixed> $data */
    public static function simpanItem(?int $id, array $data): int
    {
        $payload = $data;
        $payload['stok_min'] = number_format((float) $data['stok_min'], 2, '.', '');

        if ($id === null) {
            $payload['harga_rata'] = '0.0000';
            $payload['stok'] = '0.0000';
            return Db::insert('inventory_items', $payload);
        }

        Db::update('inventory_items', $payload, 'id = :id', [':id' => $id]);
        return $id;
    }

    /** @return array<int,array<string,mixed>> */
    public static function movements(int $itemId = 0, int $limit = 100, string $tipe = ''): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($itemId > 0) {
            $where[] = 'm.item_id = ?';
            $params[] = $itemId;
        }
        if ($tipe !== '' && array_key_exists($tipe, self::TIPE)) {
            $where[] = 'm.tipe = ?';
            $params[] = $tipe;
        }

        return Db::all(
            'SELECT m.*, i.kode AS item_kode, i.nama AS item_nama, i.satuan,
                    w.nama AS warehouse_nama, p.nama AS project_nama, j.nomor AS journal_nomor
             FROM inventory_movements m
             JOIN inventory_items i ON i.id = m.item_id
             LEFT JOIN warehouses w ON w.id = m.warehouse_id
             LEFT JOIN ms_projects p ON p.id = m.project_id
             LEFT JOIN journal_entries j ON j.id = m.journal_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY m.tanggal DESC, m.id DESC
             LIMIT ' . max(1, min(500, $limit)),
            $params
        );
    }

    /**
     * Catat penerimaan barang (stok masuk) dengan rata-rata bergerak.
     */
    public static function catatMasuk(
        int $itemId,
        float $qty,
        float $harga,
        string $tanggal,
        ?int $warehouseId = null,
        string $refModule = '',
        ?int $refId = null,
        ?int $projectId = null,
        string $keterangan = ''
    ): int {
        if ($qty <= 0) {
            throw new \RuntimeException('Qty penerimaan harus lebih besar dari nol.');
        }
        if ($harga < 0) {
            throw new \RuntimeException('Harga satuan tidak boleh negatif.');
        }

        return Db::transaction(static function () use ($itemId, $qty, $harga, $tanggal, $warehouseId, $refModule, $refId, $projectId, $keterangan): int {
            $item = Db::one('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$itemId]);
            if ($item === null) {
                throw new \RuntimeException('Barang persediaan tidak ditemukan.');
            }

            $stokLama = (float) $item['stok'];
            $hargaLama = (float) $item['harga_rata'];
            $stokBaru = $stokLama + $qty;
            $hargaBaru = $stokBaru > 0 ? (($stokLama * $hargaLama) + ($qty * $harga)) / $stokBaru : $harga;

            Db::exec(
                'UPDATE inventory_items SET stok = ?, harga_rata = ? WHERE id = ?',
                [number_format($stokBaru, 4, '.', ''), number_format($hargaBaru, 4, '.', ''), $itemId]
            );

            return Db::insert('inventory_movements', [
                'nomor'        => NumberingService::next('INV', $tanggal),
                'item_id'      => $itemId,
                'warehouse_id' => $warehouseId,
                'tanggal'      => $tanggal,
                'tipe'         => 'masuk',
                'qty'          => number_format($qty, 4, '.', ''),
                'harga'        => number_format($harga, 4, '.', ''),
                'nilai'        => number_format($qty * $harga, 2, '.', ''),
                'stok_setelah' => number_format($stokBaru, 4, '.', ''),
                'ref_module'   => $refModule !== '' ? mb_substr($refModule, 0, 30) : null,
                'ref_id'       => $refId,
                'project_id'   => $projectId,
                'keterangan'   => $keterangan !== '' ? mb_substr($keterangan, 0, 255) : null,
                'created_by'   => auth_id() ?: null,
            ]);
        });
    }

    /**
     * Catat pengeluaran barang + jurnal Dr beban / Cr persediaan.
     *
     * @return array{movement:int,journal:int}
     */
    public static function catatKeluar(
        int $itemId,
        float $qty,
        string $tanggal,
        ?int $warehouseId = null,
        ?int $projectId = null,
        ?int $businessUnitId = null,
        ?int $bidangId = null,
        ?int $divisiId = null,
        string $keterangan = ''
    ): array {
        if ($qty <= 0) {
            throw new \RuntimeException('Qty pengeluaran harus lebih besar dari nol.');
        }

        return Db::transaction(static function () use ($itemId, $qty, $tanggal, $warehouseId, $projectId, $businessUnitId, $bidangId, $divisiId, $keterangan): array {
            $item = Db::one('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$itemId]);
            if ($item === null) {
                throw new \RuntimeException('Barang persediaan tidak ditemukan.');
            }
            if ((float) $item['stok'] + 0.0001 < $qty) {
                throw new \RuntimeException('Stok ' . $item['nama'] . ' tidak mencukupi (tersedia ' . rtrim(rtrim(number_format((float) $item['stok'], 4, ',', '.'), '0'), ',') . ' ' . $item['satuan'] . ').');
            }
            if ((int) $item['coa_beban_id'] <= 0) {
                throw new \RuntimeException('Barang ' . $item['kode'] . ' belum memiliki akun beban pengeluaran.');
            }

            $harga = (float) $item['harga_rata'];
            $nilai = round($qty * $harga, 2);
            if ($nilai <= 0) {
                throw new \RuntimeException('Nilai pengeluaran nol. Periksa harga rata-rata barang.');
            }

            $stokBaru = (float) $item['stok'] - $qty;
            Db::exec('UPDATE inventory_items SET stok = ? WHERE id = ?', [number_format($stokBaru, 4, '.', ''), $itemId]);

            $journalId = JournalService::catat([
                'tanggal'      => $tanggal,
                'tipe'         => 'otomatis',
                'sumber'       => 'persediaan',
                'referensi'    => 'INV/' . $item['kode'],
                'deskripsi'    => 'Pengeluaran persediaan ' . $item['nama'] . ($projectId !== null ? ' untuk proyek' : ''),
                'kode_dokumen' => 'INV',
            ], [
                [
                    'coa_id'           => (int) $item['coa_beban_id'],
                    'deskripsi'        => 'Pemakaian ' . $item['nama'],
                    'debit'            => $nilai,
                    'project_id'       => $projectId,
                    'business_unit_id' => $businessUnitId,
                    'bidang_id'        => $bidangId,
                    'divisi_id'        => $divisiId,
                ],
                [
                    'coa_id'    => (int) $item['coa_persediaan_id'],
                    'deskripsi' => 'Pengurangan persediaan ' . $item['nama'],
                    'kredit'    => $nilai,
                ],
            ]);

            $movementId = Db::insert('inventory_movements', [
                'nomor'        => NumberingService::next('INV', $tanggal),
                'item_id'      => $itemId,
                'warehouse_id' => $warehouseId,
                'tanggal'      => $tanggal,
                'tipe'         => 'keluar',
                'qty'          => number_format($qty, 4, '.', ''),
                'harga'        => number_format($harga, 4, '.', ''),
                'nilai'        => number_format($nilai, 2, '.', ''),
                'stok_setelah' => number_format($stokBaru, 4, '.', ''),
                'ref_module'   => 'manual',
                'project_id'   => $projectId,
                'keterangan'   => $keterangan !== '' ? mb_substr($keterangan, 0, 255) : null,
                'journal_id'   => $journalId,
                'created_by'   => auth_id() ?: null,
            ]);

            return ['movement' => $movementId, 'journal' => $journalId];
        });
    }

    /** Nilai stok satu barang. */
    public static function nilaiStok(int $itemId): float
    {
        return (float) Db::val('SELECT COALESCE(stok * harga_rata, 0) FROM inventory_items WHERE id = ?', [$itemId]);
    }

    /** @return array<int,string> */
    public static function kategori(): array
    {
        return [
            'material'    => 'Material',
            'sparepart'   => 'Suku Cadang',
            'atk'         => 'ATK & Perlengkapan',
            'barang_dagang' => 'Barang Dagang',
            'lain'        => 'Lain-lain',
        ];
    }
}
