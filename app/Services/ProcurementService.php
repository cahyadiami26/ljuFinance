<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;

/**
 * Pengadaan: purchase order, persetujuan, penerimaan barang, dan tarik tagihan AP.
 *
 * PO disetujui otomatis mengikat pagu melalui `budget_commitments`
 * (status open → sebagian → closed). Penerimaan barang menambah stok
 * lewat `InventoryService`; tagihan AP dibuat sebagai draft agar tetap
 * melewati alur persetujuan AP yang sudah ada.
 */
final class ProcurementService
{
    public const STATUS = [
        'draft'     => 'Draft',
        'diajukan'  => 'Diajukan',
        'disetujui' => 'Disetujui',
        'sebagian'  => 'Diterima Sebagian',
        'selesai'   => 'Selesai',
        'batal'     => 'Batal',
    ];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT po.*, p.nama AS partner_nama, p.kode AS partner_kode, p.termin_hari AS partner_termin,
                    pr.nama AS project_nama, pr.kode AS project_kode, b.nama AS bidang_nama,
                    d.nama AS divisi_nama, bu.nama AS business_unit_nama,
                    u.name AS pembuat, a.nomor AS ap_nomor
             FROM purchase_orders po
             JOIN ms_partners p ON p.id = po.partner_id
             LEFT JOIN ms_projects pr ON pr.id = po.project_id
             LEFT JOIN ms_bidang b ON b.id = po.bidang_id
             LEFT JOIN ms_divisi d ON d.id = po.divisi_id
             LEFT JOIN ms_business_units bu ON bu.id = po.business_unit_id
             LEFT JOIN app_users u ON u.id = po.created_by
             LEFT JOIN ap_invoices a ON a.id = po.ap_invoice_id
             WHERE po.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $poId): array
    {
        return Db::all(
            'SELECT pi.*, i.kode AS item_kode, i.nama AS item_nama, i.satuan AS item_satuan,
                    c.kode AS coa_kode, c.nama AS coa_nama
             FROM purchase_order_items pi
             LEFT JOIN inventory_items i ON i.id = pi.item_id
             LEFT JOIN coa_accounts c ON c.id = pi.coa_id
             WHERE pi.po_id = ?
             ORDER BY pi.id',
            [$poId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function receipts(int $poId): array
    {
        return Db::all(
            'SELECT g.*, w.nama AS warehouse_nama, u.name AS pembuat
             FROM goods_receipts g
             LEFT JOIN warehouses w ON w.id = g.warehouse_id
             LEFT JOIN app_users u ON u.id = g.created_by
             WHERE g.po_id = ?
             ORDER BY g.tanggal DESC, g.id DESC',
            [$poId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function receiptItems(int $receiptId): array
    {
        return Db::all(
            'SELECT gi.*, i.kode AS item_kode, i.nama AS item_nama, i.satuan
             FROM goods_receipt_items gi
             LEFT JOIN inventory_items i ON i.id = gi.item_id
             WHERE gi.receipt_id = ?
             ORDER BY gi.id',
            [$receiptId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function list(string $q = '', string $status = '', int $limit = 100): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(po.nomor LIKE ? OR p.nama LIKE ? OR pr.nama LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($status !== '' && array_key_exists($status, self::STATUS)) {
            $where[] = 'po.status = ?';
            $params[] = $status;
        }

        return Db::all(
            'SELECT po.*, p.nama AS partner_nama, pr.nama AS project_nama,
                    (SELECT COALESCE(SUM(qty), 0) FROM purchase_order_items WHERE po_id = po.id) AS total_qty,
                    (SELECT COALESCE(SUM(diterima_qty), 0) FROM purchase_order_items WHERE po_id = po.id) AS diterima_qty
             FROM purchase_orders po
             JOIN ms_partners p ON p.id = po.partner_id
             LEFT JOIN ms_projects pr ON pr.id = po.project_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY po.tanggal DESC, po.id DESC
             LIMIT ' . max(1, min(500, $limit)),
            $params
        );
    }

    /** @return array<string,mixed> */
    public static function ringkasan(): array
    {
        $rows = Db::all('SELECT status, COUNT(*) AS jumlah, COALESCE(SUM(total), 0) AS nilai FROM purchase_orders GROUP BY status');
        $status = [];
        $nilai = [];
        foreach ($rows as $row) {
            $status[(string) $row['status']] = (int) $row['jumlah'];
            $nilai[(string) $row['status']] = (float) $row['nilai'];
        }

        return [
            'status'       => $status,
            'nilai'        => $nilai,
            'total_po'     => (int) array_sum($status),
            'nilai_total'  => (float) array_sum($nilai),
            'menunggu'     => (int) (($status['diajukan'] ?? 0)),
            'terbuka'      => (int) (($status['disetujui'] ?? 0) + ($status['sebagian'] ?? 0)),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<int,array<string,mixed>>
     */
    public static function normalisasiItems(array $input): array
    {
        $rows = $input['items'] ?? $input;
        $hasil = [];

        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $deskripsi = trim((string) ($row['deskripsi'] ?? ''));
            $qty = max(0.0, JournalService::uang($row['qty'] ?? 0));
            $harga = max(0.0, JournalService::uang($row['harga'] ?? 0));
            $itemId = (int) ($row['item_id'] ?? 0) ?: null;

            if ($deskripsi === '' && $itemId === null && $qty <= 0) {
                continue;
            }

            if ($deskripsi === '' && $itemId !== null) {
                $deskripsi = (string) Db::val('SELECT nama FROM inventory_items WHERE id = ?', [$itemId], '');
            }

            $ppnPersen = max(0.0, min(100.0, (float) ($row['ppn_persen'] ?? 11)));
            $total = round($qty * $harga, 2);

            $hasil[] = [
                'item_id'    => $itemId,
                'deskripsi'  => mb_substr($deskripsi, 0, 255),
                'coa_id'     => (int) ($row['coa_id'] ?? 0) ?: null,
                'qty'        => $qty,
                'satuan'     => mb_substr(trim((string) ($row['satuan'] ?? 'unit')) ?: 'unit', 0, 20),
                'harga'      => $harga,
                'ppn_persen' => $ppnPersen,
                'total'      => $total,
                'ppn'        => round($total * $ppnPersen / 100, 2),
            ];
        }

        return $hasil;
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    public static function validasi(array $data): array
    {
        $errors = [];

        if ((int) ($data['partner_id'] ?? 0) <= 0) {
            $errors['partner_id'] = 'Vendor wajib dipilih.';
        }
        if (($data['tanggal'] ?? '') === '' || strtotime((string) $data['tanggal']) === false) {
            $errors['tanggal'] = 'Tanggal PO tidak valid.';
        }
        if ((array) ($data['items'] ?? []) === []) {
            $errors['items'] = 'Minimal satu baris barang/jasa.';
        }
        foreach ((array) ($data['items'] ?? []) as $index => $item) {
            if ((float) $item['qty'] <= 0) {
                $errors['items_' . $index] = 'Qty baris ' . ($index + 1) . ' harus lebih dari nol.';
            }
            if ((float) $item['harga'] <= 0) {
                $errors['items_' . $index] = 'Harga baris ' . ($index + 1) . ' harus lebih dari nol.';
            }
            if ((int) ($item['coa_id'] ?? 0) <= 0) {
                $errors['items_' . $index] = 'Akun baris ' . ($index + 1) . ' wajib dipilih.';
            }
        }

        return $errors;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalisasi(array $input): array
    {
        $items = self::normalisasiItems($input['items'] ?? []);
        $subtotal = 0.0;
        $ppn = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['total'];
            $ppn += (float) $item['ppn'];
        }

        return [
            'tanggal'          => trim((string) ($input['tanggal'] ?? '')) ?: today(),
            'partner_id'       => (int) ($input['partner_id'] ?? 0),
            'project_id'       => (int) ($input['project_id'] ?? 0) ?: null,
            'bidang_id'        => (int) ($input['bidang_id'] ?? 0) ?: null,
            'divisi_id'        => (int) ($input['divisi_id'] ?? 0) ?: null,
            'business_unit_id' => (int) ($input['business_unit_id'] ?? 0) ?: null,
            'cost_center_id'   => (int) ($input['cost_center_id'] ?? 0) ?: null,
            'termin_hari'      => max(0, min(365, (int) ($input['termin_hari'] ?? 30))),
            'catatan'          => trim((string) ($input['catatan'] ?? '')),
            'items'            => $items,
            'subtotal'         => round($subtotal, 2),
            'ppn'              => round($ppn, 2),
            'total'            => round($subtotal + $ppn, 2),
        ];
    }

    /** @param array<string,mixed> $data */
    public static function simpan(?int $id, array $data): int
    {
        return Db::transaction(static function () use ($id, $data): int {
            $payload = [
                'tanggal'          => $data['tanggal'],
                'partner_id'       => (int) $data['partner_id'],
                'project_id'       => $data['project_id'],
                'bidang_id'        => $data['bidang_id'],
                'divisi_id'        => $data['divisi_id'],
                'business_unit_id' => $data['business_unit_id'],
                'cost_center_id'   => $data['cost_center_id'],
                'termin_hari'      => (int) $data['termin_hari'],
                'catatan'          => $data['catatan'] !== '' ? mb_substr((string) $data['catatan'], 0, 255) : null,
                'subtotal'         => number_format((float) $data['subtotal'], 2, '.', ''),
                'ppn'              => number_format((float) $data['ppn'], 2, '.', ''),
                'total'            => number_format((float) $data['total'], 2, '.', ''),
            ];

            if ($id === null) {
                $payload['nomor'] = NumberingService::next('PO', (string) $data['tanggal']);
                $payload['status'] = 'draft';
                $payload['created_by'] = auth_id() ?: null;
                $id = Db::insert('purchase_orders', $payload);
            } else {
                $row = Db::one('SELECT * FROM purchase_orders WHERE id = ?', [$id]);
                if ($row === null) {
                    throw new \RuntimeException('PO tidak ditemukan.');
                }
                if ((string) $row['status'] !== 'draft') {
                    throw new \RuntimeException('Hanya PO draft yang dapat diubah.');
                }
                Db::update('purchase_orders', $payload, 'id = :id', [':id' => $id]);
                Db::exec('DELETE FROM purchase_order_items WHERE po_id = ?', [$id]);
            }

            foreach ($data['items'] as $item) {
                Db::insert('purchase_order_items', [
                    'po_id'      => $id,
                    'item_id'    => $item['item_id'],
                    'deskripsi'  => $item['deskripsi'],
                    'coa_id'     => $item['coa_id'],
                    'qty'        => number_format((float) $item['qty'], 4, '.', ''),
                    'satuan'     => $item['satuan'],
                    'harga'      => number_format((float) $item['harga'], 4, '.', ''),
                    'ppn_persen' => number_format((float) $item['ppn_persen'], 2, '.', ''),
                    'total'      => number_format((float) $item['total'], 2, '.', ''),
                ]);
            }

            return $id;
        });
    }

    public static function ajukan(int $id): void
    {
        $po = self::find($id);
        if ($po === null) {
            throw new \RuntimeException('PO tidak ditemukan.');
        }
        if ((string) $po['status'] !== 'draft') {
            throw new \RuntimeException('Hanya PO draft yang dapat diajukan.');
        }

        Db::exec("UPDATE purchase_orders SET status = 'diajukan' WHERE id = ?", [$id]);
        self::sinkronKomitmen($id, 'open');
    }

    public static function setujui(int $id): void
    {
        $po = self::find($id);
        if ($po === null) {
            throw new \RuntimeException('PO tidak ditemukan.');
        }
        if ((string) $po['status'] !== 'diajukan') {
            throw new \RuntimeException('Hanya PO yang diajukan dapat disetujui.');
        }

        Db::exec(
            "UPDATE purchase_orders SET status = 'disetujui', approved_by = ?, approved_at = NOW() WHERE id = ?",
            [auth_id() ?: null, $id]
        );
        self::sinkronKomitmen($id, 'open');
    }

    public static function batal(int $id, string $alasan = ''): void
    {
        $po = self::find($id);
        if ($po === null) {
            throw new \RuntimeException('PO tidak ditemukan.');
        }
        if (in_array((string) $po['status'], ['sebagian', 'selesai'], true)) {
            throw new \RuntimeException('PO yang sudah menerima barang tidak dapat dibatalkan.');
        }

        $catatan = (string) ($po['catatan'] ?? '');
        if (trim($alasan) !== '') {
            $catatan = mb_substr(rtrim($catatan . ' | Batal: ' . trim($alasan), ' |'), 0, 255);
        }
        Db::exec("UPDATE purchase_orders SET status = 'batal', catatan = ? WHERE id = ?", [$catatan, $id]);
        self::sinkronKomitmen($id, 'cancelled');
    }

    public static function hapus(int $id): void
    {
        $po = self::find($id);
        if ($po === null) {
            throw new \RuntimeException('PO tidak ditemukan.');
        }
        if ((string) $po['status'] !== 'draft') {
            throw new \RuntimeException('Hanya PO draft tanpa penerimaan yang dapat dihapus.');
        }

        Db::transaction(static function () use ($id): void {
            Db::exec('DELETE FROM purchase_order_items WHERE po_id = ?', [$id]);
            Db::exec('DELETE FROM purchase_orders WHERE id = ?', [$id]);
        });
    }

    /**
     * Terima barang dan langsung posting stok.
     *
     * @param array<string,mixed> $input tanggal, warehouse_id, catatan, items (po_item_id => qty)
     */
    public static function terima(int $poId, array $input): int
    {
        $po = self::find($poId);
        if ($po === null) {
            throw new \RuntimeException('PO tidak ditemukan.');
        }
        if (!in_array((string) $po['status'], ['disetujui', 'sebagian'], true)) {
            throw new \RuntimeException('Penerimaan hanya untuk PO yang sudah disetujui.');
        }

        $tanggal = (string) ($input['tanggal'] ?? today());
        if (strtotime($tanggal) === false) {
            throw new \RuntimeException('Tanggal penerimaan tidak valid.');
        }
        $warehouseId = (int) ($input['warehouse_id'] ?? 0) ?: null;
        $catatan = trim((string) ($input['catatan'] ?? ''));
        $qtyInput = (array) ($input['items'] ?? []);

        $items = self::items($poId);
        $rencana = [];
        foreach ($items as $item) {
            $qty = max(0.0, JournalService::uang($qtyInput[(string) $item['id']] ?? $qtyInput[(int) $item['id']] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $sisa = (float) $item['qty'] - (float) $item['diterima_qty'];
            if ($qty > $sisa + 0.0001) {
                throw new \RuntimeException('Qty ' . $item['deskripsi'] . ' melebihi sisa PO (' . $sisa . ').');
            }
            if ((int) ($item['item_id'] ?? 0) <= 0) {
                throw new \RuntimeException('Baris ' . $item['deskripsi'] . ' bukan barang persediaan sehingga tidak dapat diterima.');
            }
            $rencana[] = ['item' => $item, 'qty' => $qty];
        }

        if ($rencana === []) {
            throw new \RuntimeException('Isi qty penerimaan minimal satu baris.');
        }

        return Db::transaction(static function () use ($poId, $po, $tanggal, $warehouseId, $catatan, $rencana): int {
            $receiptId = Db::insert('goods_receipts', [
                'nomor'        => NumberingService::next('GR', $tanggal),
                'po_id'        => $poId,
                'tanggal'      => $tanggal,
                'warehouse_id' => $warehouseId,
                'status'       => 'posted',
                'catatan'      => $catatan !== '' ? mb_substr($catatan, 0, 255) : null,
                'created_by'   => auth_id() ?: null,
                'posted_at'    => now(),
            ]);
            $nomor = (string) Db::val('SELECT nomor FROM goods_receipts WHERE id = ?', [$receiptId], '');

            foreach ($rencana as $row) {
                $item = $row['item'];
                $qty = (float) $row['qty'];
                $harga = (float) $item['harga'];

                Db::insert('goods_receipt_items', [
                    'receipt_id'  => $receiptId,
                    'po_item_id'  => (int) $item['id'],
                    'item_id'     => (int) $item['item_id'],
                    'qty'         => number_format($qty, 4, '.', ''),
                    'harga'       => number_format($harga, 4, '.', ''),
                    'total'       => number_format($qty * $harga, 2, '.', ''),
                ]);

                Db::exec('UPDATE purchase_order_items SET diterima_qty = diterima_qty + ? WHERE id = ?', [
                    number_format($qty, 4, '.', ''),
                    (int) $item['id'],
                ]);

                InventoryService::catatMasuk(
                    (int) $item['item_id'],
                    $qty,
                    $harga,
                    $tanggal,
                    $warehouseId,
                    'penerimaan',
                    $receiptId,
                    $po['project_id'] !== null ? (int) $po['project_id'] : null,
                    'Penerimaan ' . $nomor
                );
            }

            // Baris jasa (non-stok) dianggap selesai saat PO diterima sebagian,
            // karena tidak memiliki qty fisik yang dapat diterima.
            $sisa = (float) Db::val(
                'SELECT COALESCE(SUM(CASE WHEN item_id IS NULL THEN 0 ELSE qty - diterima_qty END), 0)
                 FROM purchase_order_items WHERE po_id = ?',
                [$poId]
            );
            $status = $sisa <= 0.0001 ? 'selesai' : 'sebagian';
            Db::exec('UPDATE purchase_orders SET status = ? WHERE id = ?', [$status, $poId]);
            self::sinkronKomitmen($poId, $status === 'selesai' ? 'closed' : 'sebagian');

            return $receiptId;
        });
    }

    /**
     * Buat draft tagihan AP dari PO yang sudah menerima barang.
     */
    public static function tarikTagihan(int $poId): int
    {
        $po = self::find($poId);
        if ($po === null) {
            throw new \RuntimeException('PO tidak ditemukan.');
        }
        if ((int) ($po['ap_invoice_id'] ?? 0) > 0) {
            throw new \RuntimeException('PO ini sudah memiliki tagihan ' . $po['ap_nomor'] . '.');
        }
        if (!in_array((string) $po['status'], ['sebagian', 'selesai'], true)) {
            throw new \RuntimeException('Tagihan hanya dapat dibuat setelah barang diterima.');
        }

        $items = [];
        foreach (self::items($poId) as $item) {
            // Jasa / non-stok ditagih penuh mengikuti nilai PO, barang mengikuti qty diterima.
            $qty = (int) ($item['item_id'] ?? 0) > 0 ? (float) $item['diterima_qty'] : (float) $item['qty'];
            if ($qty <= 0) {
                continue;
            }
            $jumlah = round($qty * (float) $item['harga'], 2);
            $items[] = [
                'keterangan' => $item['deskripsi'],
                'coa_id'     => (int) ($item['coa_id'] ?? 0),
                'jumlah'     => $jumlah,
                'ppn'        => round($jumlah * (float) $item['ppn_persen'] / 100, 2),
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('Belum ada barang diterima untuk ditagih.');
        }

        $data = ApService::normalisasi([
            'nomor_supplier'      => $po['nomor'],
            'partner_id'          => (int) $po['partner_id'],
            'tanggal'             => today(),
            'termin_hari'         => (int) $po['termin_hari'],
            'project_id'          => $po['project_id'],
            'bidang_id'           => $po['bidang_id'],
            'divisi_id'           => $po['divisi_id'],
            'cost_center_id'      => $po['cost_center_id'],
            'keterangan'          => 'Tagihan pengadaan ' . $po['nomor'] . ' — ' . $po['partner_nama'],
            'items'               => $items,
        ]);

        $invoiceId = ApService::simpan(null, $data);

        Db::exec('UPDATE purchase_orders SET ap_invoice_id = ? WHERE id = ?', [$invoiceId, $poId]);
        return $invoiceId;
    }

    /** Sinkronkan PO ke komitmen anggaran. */
    private static function sinkronKomitmen(int $poId, string $status): void
    {
        $po = Db::one('SELECT * FROM purchase_orders WHERE id = ?', [$poId]);
        if ($po === null) {
            return;
        }

        $firstCoa = (int) Db::val('SELECT coa_id FROM purchase_order_items WHERE po_id = ? AND coa_id IS NOT NULL ORDER BY id LIMIT 1', [$poId]);
        $tahun = (int) date('Y', strtotime((string) $po['tanggal']));
        $budget = BudgetService::aktif($tahun);
        $budgetId = $budget !== null ? (int) $budget['id'] : null;

        $existing = Db::one('SELECT id FROM budget_commitments WHERE nomor_po = ?', [(string) $po['nomor']]);
        if ($existing !== null) {
            Db::exec('UPDATE budget_commitments SET nilai = ?, status = ?, budget_id = ? WHERE id = ?', [
                number_format((float) $po['subtotal'], 2, '.', ''),
                $status,
                $budgetId,
                (int) $existing['id'],
            ]);
            return;
        }

        Db::insert('budget_commitments', [
            'nomor_po'       => (string) $po['nomor'],
            'budget_id'      => $budgetId,
            'partner_id'     => (int) $po['partner_id'],
            'coa_id'         => $firstCoa ?: null,
            'project_id'     => $po['project_id'],
            'bidang_id'      => $po['bidang_id'],
            'divisi_id'      => $po['divisi_id'],
            'cost_center_id' => $po['cost_center_id'],
            'tanggal'        => (string) $po['tanggal'],
            'nilai'          => number_format((float) $po['subtotal'], 2, '.', ''),
            'status'         => $status,
            'keterangan'     => 'Komitmen PO ' . $po['nomor'],
            'created_by'     => auth_id() ?: null,
        ]);
    }
}
