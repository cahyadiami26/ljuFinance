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
        'type'  => 'link',
        'label' => 'RKAP & Budget',
        'route' => 'budget',
        'icon'  => 'chart',
        'permission' => 'budget.view',
        'stage' => 5,
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
        'type'  => 'link',
        'label' => 'Fixed Asset',
        'route' => 'asset',
        'icon'  => 'asset',
        'permission' => 'asset.view',
        'stage' => 6,
    ],
    [
        'type'  => 'link',
        'label' => 'Tax Management',
        'route' => 'tax',
        'icon'  => 'receipt',
        'permission' => 'tax.view',
        'stage' => 6,
    ],
    [
        'type'  => 'link',
        'label' => 'Financial Reports',
        'route' => 'report',
        'icon'  => 'report',
        'permission' => 'report.view',
        'stage' => 7,
    ],
    [
        'type'  => 'link',
        'label' => 'Approval Center',
        'route' => 'approval',
        'icon'  => 'check-circle',
        'permission' => 'approval.view',
        'badge' => 'approval',
        'stage' => 5,
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
