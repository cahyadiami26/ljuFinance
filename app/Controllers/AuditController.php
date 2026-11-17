<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\Db;
use Lju\Core\Paginator;

/**
 * Audit trail: seluruh perubahan data tercatat dan tidak dapat diubah.
 */
final class AuditController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('audit.view');

        $q = (string) input('q', '');
        $module = (string) input('module', '');
        $action = (string) input('action', '');
        $from = (string) input('from', '');
        $to = (string) input('to', '');
        $page = max(1, (int) input('page', 1));

        $where = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(description LIKE ? OR ref_code LIKE ? OR user_name LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        if ($module !== '') {
            $where[] = 'module = ?';
            $params[] = $module;
        }
        if ($action !== '') {
            $where[] = 'action = ?';
            $params[] = $action;
        }
        if ($from !== '') {
            $where[] = 'created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = 'created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM audit_logs WHERE ' . $whereSql, $params);
        $paginator = new Paginator($total, $page, 25);

        $rows = Db::all(
            'SELECT * FROM audit_logs WHERE ' . $whereSql . '
             ORDER BY id DESC LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset(),
            $params
        );

        $modules = array_column(Db::all('SELECT DISTINCT module FROM audit_logs ORDER BY module'), 'module');
        $actions = array_column(Db::all('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action');

        $this->page('pages/audit/index', [
            'pageTitle'    => 'Audit Trail',
            'pageSubtitle' => 'Rekam jejak seluruh aktivitas pengguna dan perubahan data keuangan.',
            'breadcrumbs'  => [
                ['label' => 'System Admin', 'url' => null],
                ['label' => 'Audit Trail', 'url' => null],
            ],
            'rows'      => $rows,
            'modules'   => $modules,
            'actions'   => $actions,
            'paginator' => $paginator,
            'q'         => $q,
            'module'    => $module,
            'action'    => $action,
            'from'      => $from,
            'to'        => $to,
            'total'     => $total,
        ]);
    }
}
