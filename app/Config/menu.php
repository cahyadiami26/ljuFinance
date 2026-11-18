<?php
/**
 * Struktur navigasi sidebar.
 * Setiap item: label, route, icon, permission (null = selalu tampil), badge (opsional).
 */

return [
    [
        'type'  => 'link',
        'label' => 'Dashboard',
        'route' => 'dashboard',
        'icon'  => 'dashboard',
        'permission' => 'dashboard.view',
    ],
    [
        'type'  => 'group',
        'label' => 'Master Data',
        'icon'  => 'database',
        'items' => [
            ['label' => 'Chart of Accounts', 'route' => 'master/coa', 'icon' => 'list', 'permission' => 'coa.view'],
            ['label' => 'Bidang & Divisi', 'route' => 'master/bidang', 'icon' => 'org', 'permission' => 'master.view'],
            ['label' => 'Cost Center', 'route' => 'master/cost-center', 'icon' => 'grid', 'permission' => 'master.view'],
            ['label' => 'Unit Usaha', 'route' => 'master/business-unit', 'icon' => 'layers', 'permission' => 'master.view'],
            ['label' => 'Vendor & Customer', 'route' => 'master/partner', 'icon' => 'users', 'permission' => 'master.view'],
            ['label' => 'Proyek', 'route' => 'master/project', 'icon' => 'briefcase', 'permission' => 'project.view'],
            ['label' => 'Rekening Bank', 'route' => 'master/bank-account', 'icon' => 'bank', 'permission' => 'master.view'],
            ['label' => 'Periode Akuntansi', 'route' => 'master/period', 'icon' => 'calendar', 'permission' => 'period.view'],
        ],
    ],
    [
        'type'  => 'group',
        'label' => 'Anggaran & Pengendalian',
        'icon'  => 'chart',
        'items' => [
            ['label' => 'RKAP & Versi', 'route' => 'budget', 'icon' => 'chart', 'permission' => 'budget.view'],
            ['label' => 'Monitoring Realisasi', 'route' => 'budget/monitoring', 'icon' => 'trending-up', 'permission' => 'budget.view'],
            ['label' => 'Komitmen PO', 'route' => 'budget/commitments', 'icon' => 'briefcase', 'permission' => 'budget.view'],
        ],
    ],
    [
        'type'  => 'group',
        'label' => 'Kas & Bank',
        'icon'  => 'wallet',
        'items' => [
            ['label' => 'Kas & Bank', 'route' => 'cash', 'icon' => 'wallet', 'permission' => 'cash.view'],
            ['label' => 'Uang Muka', 'route' => 'advance', 'icon' => 'coins', 'permission' => 'advance.view'],
            ['label' => 'Pengajuan Biaya', 'route' => 'expense', 'icon' => 'receipt', 'permission' => 'expense.view'],
            ['label' => 'Rekonsiliasi Bank', 'route' => 'cash/reconciliation', 'icon' => 'scale', 'permission' => 'cash.view'],
        ],
    ],
    [
        'type'  => 'group',
        'label' => 'Hutang & Piutang',
        'icon'  => 'scale',
        'items' => [
            ['label' => 'Hutang Usaha (AP)', 'route' => 'ap', 'icon' => 'arrow-down', 'permission' => 'ap.view'],
            ['label' => 'Piutang Usaha (AR)', 'route' => 'ar', 'icon' => 'arrow-up', 'permission' => 'ar.view'],
            ['label' => 'Pengakuan Pendapatan', 'route' => 'revenue', 'icon' => 'chart', 'permission' => 'ar.view'],
        ],
    ],
    [
        'type'  => 'group',
        'label' => 'Accounting',
        'icon'  => 'book',
        'items' => [
            ['label' => 'Jurnal Umum', 'route' => 'journal', 'icon' => 'edit', 'permission' => 'journal.view'],
            ['label' => 'Buku Besar', 'route' => 'ledger', 'icon' => 'book', 'permission' => 'journal.view'],
            ['label' => 'Neraca Saldo', 'route' => 'trial-balance', 'icon' => 'scale', 'permission' => 'journal.view'],
            ['label' => 'Tutup Buku', 'route' => 'closing', 'icon' => 'lock', 'permission' => 'period.view'],
        ],
    ],
    [
        'type'  => 'group',
        'label' => 'Aset Tetap',
        'icon'  => 'asset',
        'items' => [
            ['label' => 'Daftar Aset', 'route' => 'asset', 'icon' => 'asset', 'permission' => 'asset.view'],
            ['label' => 'Penyusutan', 'route' => 'asset/depreciation', 'icon' => 'trending-down', 'permission' => 'asset.view'],
        ],
    ],
    [
        'type'  => 'group',
        'label' => 'Perpajakan',
        'icon'  => 'receipt',
        'items' => [
            ['label' => 'Dokumen Pajak', 'route' => 'tax', 'icon' => 'receipt', 'permission' => 'tax.view'],
            ['label' => 'Rekap SPT', 'route' => 'tax/report', 'icon' => 'report', 'permission' => 'tax.view'],
        ],
    ],
    [
        'type'  => 'group',
        'label' => 'Pengadaan & Persediaan',
        'icon'  => 'package',
        'stage' => 8,
        'items' => [
            ['label' => 'Purchase Order', 'route' => 'procurement', 'icon' => 'truck', 'permission' => 'procurement.view'],
            ['label' => 'Persediaan', 'route' => 'inventory', 'icon' => 'package', 'permission' => 'inventory.view'],
            ['label' => 'Kartu Stok', 'route' => 'inventory/movements', 'icon' => 'history', 'permission' => 'inventory.view'],
        ],
    ],
    [
        'type'  => 'group',
        'label' => 'Proyek & Kontrak',
        'icon'  => 'briefcase',
        'stage' => 8,
        'items' => [
            ['label' => 'Portofolio Proyek', 'route' => 'project', 'icon' => 'briefcase', 'permission' => 'project.view'],
            ['label' => 'Kontrak & Termin', 'route' => 'project/contracts', 'icon' => 'file', 'permission' => 'project.view'],
        ],
    ],
    [
        'type'  => 'group',
        'label' => 'Laporan Keuangan',
        'icon'  => 'report',
        'stage' => 7,
        'items' => [
            ['label' => 'Ringkasan Laporan', 'route' => 'report', 'icon' => 'chart', 'permission' => 'report.view'],
            ['label' => 'Laba Rugi', 'route' => 'report/income', 'icon' => 'chart', 'permission' => 'report.view'],
            ['label' => 'Neraca', 'route' => 'report/balance-sheet', 'icon' => 'scale', 'permission' => 'report.view'],
            ['label' => 'Arus Kas', 'route' => 'report/cash-flow', 'icon' => 'wallet', 'permission' => 'report.view'],
            ['label' => 'Perubahan Ekuitas', 'route' => 'report/equity', 'icon' => 'layers', 'permission' => 'report.view'],
            ['label' => 'Laba Rugi Proyek', 'route' => 'report/project', 'icon' => 'briefcase', 'permission' => 'report.view'],
            ['label' => 'Kinerja Unit Usaha', 'route' => 'report/unit-usaha', 'icon' => 'building', 'permission' => 'report.view'],
        ],
    ],
    [
        'type'  => 'link',
        'label' => 'Approval Center',
        'route' => 'approval',
        'icon'  => 'check-circle',
        'permission' => 'approval.view',
        'badge' => 'approval',
    ],
    [
        'type'  => 'group',
        'label' => 'System Admin',
        'icon'  => 'settings',
        'items' => [
            ['label' => 'Pengguna & Hak Akses', 'route' => 'master/user', 'icon' => 'users', 'permission' => 'user.view'],
            ['label' => 'Role & Permission', 'route' => 'master/role', 'icon' => 'shield', 'permission' => 'role.manage'],
            ['label' => 'Audit Trail', 'route' => 'system/audit', 'icon' => 'history', 'permission' => 'audit.view'],
            ['label' => 'Pengaturan', 'route' => 'system/settings', 'icon' => 'sliders', 'permission' => 'settings.manage'],
        ],
    ],
];
