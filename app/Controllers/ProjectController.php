<?php

declare(strict_types=1);

namespace Lju\Controllers;

use Lju\Core\App;
use Lju\Core\Audit;
use Lju\Core\Db;
use Lju\Core\Flash;
use Lju\Services\ProjectService;

/**
 * Proyek & kontrak: portofolio proyek, kontrak dengan termin,
 * penagihan termin ke AR, dan profitabilitas proyek.
 */
final class ProjectController extends BaseController
{
    public function index(): void
    {
        $this->requireCan('project.view');

        $q = trim((string) input('q', ''));
        $businessUnitId = (int) input('unit', 0);

        $this->page('pages/project/index', [
            'pageTitle'    => 'Portofolio Proyek',
            'pageSubtitle' => 'Nilai kontrak, pendapatan diakui, biaya, dan margin setiap proyek.',
            'breadcrumbs'  => [
                ['label' => 'Proyek & Kontrak', 'url' => null],
                ['label' => 'Portofolio', 'url' => null],
            ],
            'pageActions'  => (can('project.manage')
                    ? '<a class="btn btn--primary" href="' . e(url('project/create')) . '">' . icon('plus', 15) . ' Proyek Baru</a>'
                    : '')
                . '<a class="btn" href="' . e(url('project/contracts')) . '">' . icon('file', 15) . ' Kontrak</a>'
                . '<a class="btn" href="' . e(url('master/project')) . '">' . icon('list', 15) . ' Master Proyek</a>',
            'rows'         => ProjectService::portfolio($q, $businessUnitId),
            'q'            => $q,
            'businessUnitId' => $businessUnitId,
            'businessUnits' => Db::all('SELECT id, nama FROM ms_business_units ORDER BY kode'),
            'ringkasan'    => ProjectService::ringkasan(),
            'statusList'   => ProjectService::STATUS_PROYEK,
            'canManage'    => can('project.manage'),
        ]);
    }

    public function create(): void
    {
        $this->requireCan('project.manage');
        $this->renderForm(null, $this->defaultProject());
    }

    public function store(): void
    {
        $this->requireCan('project.manage');

        $data = $this->normalisasiProject($_POST);
        $errors = $this->validasiProject($data);

        if ($errors !== []) {
            $this->renderForm(null, $data, $errors);
            return;
        }

        $id = Db::insert('ms_projects', $this->payloadProject($data));
        Audit::log('create', 'master.project', [
            'ref_table'   => 'ms_projects',
            'ref_id'      => $id,
            'ref_code'    => (string) $data['kode'],
            'description' => 'Menambah proyek ' . $data['kode'] . ' — ' . $data['nama'],
            'new'         => $data,
        ]);
        Flash::success('Proyek ' . $data['kode'] . ' berhasil dibuat.');
        redirect('project/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireCan('project.view');

        $projectId = (int) $id;
        $project = ProjectService::find($projectId);
        if ($project === null) {
            App::abort(404, 'Proyek tidak ditemukan.');
        }

        $invoices = Db::all(
            'SELECT id, nomor, tanggal, total, dibayar, sisa, status, termin_ke
             FROM ar_invoices WHERE project_id = ? ORDER BY tanggal DESC, id DESC LIMIT 30',
            [$projectId]
        );

        $beban = Db::all(
            'SELECT c.kode, c.nama, COALESCE(SUM(jl.debit - jl.kredit), 0) AS nilai
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.journal_id
             JOIN coa_accounts c ON c.id = jl.coa_id
             WHERE je.status = \'posted\' AND jl.project_id = ? AND c.tipe = \'beban\'
             GROUP BY c.id, c.kode, c.nama
             ORDER BY nilai DESC
             LIMIT 10',
            [$projectId]
        );

        $this->page('pages/project/show', [
            'pageTitle'    => $project['kode'] . ' — ' . $project['nama'],
            'pageSubtitle' => 'Profitabilitas proyek, kontrak, tagihan, dan komposisi biaya.',
            'breadcrumbs'  => [
                ['label' => 'Proyek & Kontrak', 'url' => url('project')],
                ['label' => (string) $project['kode'], 'url' => null],
            ],
            'pageActions'  => (can('project.manage')
                    ? '<a class="btn btn--primary" href="' . e(url('project/contract/create', ['project' => $projectId])) . '">' . icon('plus', 15) . ' Kontrak</a>'
                    . '<a class="btn" href="' . e(url('project/' . $projectId . '/edit')) . '">' . icon('edit', 15) . ' Edit Proyek</a>'
                    : '')
                . '<a class="btn" href="' . e(url('revenue')) . '">' . icon('chart', 15) . ' Pengakuan Pendapatan</a>',
            'project'      => $project,
            'contracts'    => ProjectService::contracts($projectId),
            'recognitions' => ProjectService::recognitions($projectId),
            'invoices'     => $invoices,
            'beban'        => $beban,
            'statusList'   => ProjectService::STATUS_PROYEK,
            'canManage'    => can('project.manage'),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireCan('project.manage');

        $projectId = (int) $id;
        $row = Db::one('SELECT * FROM ms_projects WHERE id = ?', [$projectId]);
        if ($row === null) {
            App::abort(404, 'Proyek tidak ditemukan.');
        }

        $this->renderForm($projectId, $row);
    }

    public function update(string $id): void
    {
        $this->requireCan('project.manage');

        $projectId = (int) $id;
        if (Db::one('SELECT id FROM ms_projects WHERE id = ?', [$projectId]) === null) {
            App::abort(404, 'Proyek tidak ditemukan.');
        }

        $data = $this->normalisasiProject($_POST);
        $errors = $this->validasiProject($data, $projectId);

        if ($errors !== []) {
            $this->renderForm($projectId, $data, $errors);
            return;
        }

        Db::update('ms_projects', $this->payloadProject($data), 'id = :id', [':id' => $projectId]);
        Audit::log('update', 'master.project', [
            'ref_table'   => 'ms_projects',
            'ref_id'      => $projectId,
            'ref_code'    => (string) $data['kode'],
            'description' => 'Memperbarui proyek ' . $data['kode'],
            'new'         => $data,
        ]);
        Flash::success('Proyek ' . $data['kode'] . ' berhasil diperbarui.');
        redirect('project/' . $projectId);
    }

    /* ---------------------------------------------------------- kontrak */

    public function contracts(): void
    {
        $this->requireCan('project.view');

        $this->page('pages/project/contracts', [
            'pageTitle'    => 'Kontrak Proyek',
            'pageSubtitle' => 'Kontrak, nilai, dan realisasi penagihan termin.',
            'breadcrumbs'  => [
                ['label' => 'Proyek & Kontrak', 'url' => url('project')],
                ['label' => 'Kontrak', 'url' => null],
            ],
            'pageActions'  => (can('project.manage')
                    ? '<a class="btn btn--primary" href="' . e(url('project/contract/create')) . '">' . icon('plus', 15) . ' Kontrak Baru</a>'
                    : ''),
            'rows'         => ProjectService::contracts(),
            'ringkasan'    => ProjectService::ringkasan(),
            'statusList'   => ProjectService::STATUS_KONTRAK,
            'canManage'    => can('project.manage'),
        ]);
    }

    public function contractCreate(): void
    {
        $this->requireCan('project.manage');

        $projectId = (int) input('project', 0);
        $nilai = 0.0;
        if ($projectId > 0) {
            $nilai = (float) Db::val('SELECT COALESCE(nilai_kontrak, 0) FROM ms_projects WHERE id = ?', [$projectId]);
        } elseif ((int) input('project', 0) === 0) {
            $projectId = (int) Db::val('SELECT id FROM ms_projects WHERE status = \'berjalan\' ORDER BY id LIMIT 1');
        }

        $this->renderContractForm(null, array_merge($this->defaultContract(), [
            'project_id'    => $projectId ?: null,
            'nilai_kontrak' => $nilai,
            'terms'         => [[
                'termin_ke'  => 1,
                'deskripsi'  => 'Termin 1',
                'persentase' => 100,
                'nilai'      => $nilai,
            ]],
        ]));
    }

    public function contractStore(): void
    {
        $this->requireCan('project.manage');

        $data = ProjectService::normalisasiContract($_POST);
        $errors = ProjectService::validasiContract($data);

        if ($errors !== []) {
            $this->renderContractForm(null, $data, $errors);
            return;
        }

        $id = ProjectService::simpanContract(null, $data);
        $this->catatAudit('create', $id, $data);
        Flash::success('Kontrak ' . $data['nomor'] . ' berhasil dibuat.');
        redirect('project/contract/' . $id);
    }

    public function contractShow(string $id): void
    {
        $this->requireCan('project.view');

        $contractId = (int) $id;
        $contract = ProjectService::contract($contractId);
        if ($contract === null) {
            App::abort(404, 'Kontrak tidak ditemukan.');
        }

        $this->page('pages/project/contract-show', [
            'pageTitle'    => 'Kontrak ' . $contract['nomor'],
            'pageSubtitle' => (string) $contract['judul'],
            'breadcrumbs'  => [
                ['label' => 'Proyek & Kontrak', 'url' => url('project')],
                ['label' => 'Kontrak', 'url' => url('project/contracts')],
                ['label' => (string) $contract['nomor'], 'url' => null],
            ],
            'pageActions'  => (can('project.manage')
                    ? '<a class="btn" href="' . e(url('project/contract/' . $contractId . '/edit')) . '">' . icon('edit', 15) . ' Edit Kontrak</a>'
                    : '')
                . '<a class="btn" href="' . e(url('project/' . (int) $contract['project_id'])) . '">' . icon('briefcase', 15) . ' Proyek</a>',
            'contract'     => $contract,
            'terms'        => ProjectService::terms($contractId),
            'recognitions' => ProjectService::recognitions(0, $contractId),
            'statusList'   => ProjectService::STATUS_KONTRAK,
            'termList'     => ProjectService::STATUS_TERMIN,
            'canManage'    => can('project.manage'),
        ]);
    }

    public function contractEdit(string $id): void
    {
        $this->requireCan('project.manage');

        $contractId = (int) $id;
        $contract = ProjectService::contract($contractId);
        if ($contract === null) {
            App::abort(404, 'Kontrak tidak ditemukan.');
        }

        $contract['terms'] = ProjectService::terms($contractId);
        $this->renderContractForm($contractId, $contract);
    }

    public function contractUpdate(string $id): void
    {
        $this->requireCan('project.manage');

        $contractId = (int) $id;
        $existing = ProjectService::contract($contractId);
        if ($existing === null) {
            App::abort(404, 'Kontrak tidak ditemukan.');
        }
        if ((string) $existing['status'] === 'selesai' && !can('project.manage')) {
            App::abort(403, 'Kontrak selesai tidak dapat diubah.');
        }

        $data = ProjectService::normalisasiContract($_POST);
        $errors = ProjectService::validasiContract($data, $contractId);

        if ($errors !== []) {
            $this->renderContractForm($contractId, $data, $errors);
            return;
        }

        ProjectService::simpanContract($contractId, $data);
        $this->catatAudit('update', $contractId, $data);
        Flash::success('Kontrak ' . $data['nomor'] . ' berhasil diperbarui.');
        redirect('project/contract/' . $contractId);
    }

    public function contractStatus(string $id): void
    {
        $this->requireCan('project.manage');

        $contractId = (int) $id;
        $status = (string) input('status', '');

        try {
            ProjectService::ubahStatusContract($contractId, $status);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('project/contract/' . $contractId);
        }

        Audit::log('approve', 'project.contract', [
            'ref_table'   => 'ms_contracts',
            'ref_id'      => $contractId,
            'description' => 'Mengubah status kontrak menjadi ' . $status,
            'new'         => ['status' => $status],
        ]);
        Flash::success('Status kontrak diubah menjadi ' . $status . '.');
        redirect('project/contract/' . $contractId);
    }

    public function tagihTermin(string $id): void
    {
        $this->requireCan('project.manage');

        $contractId = (int) $id;
        $termId = (int) input('term_id', 0);

        try {
            if ($termId <= 0) {
                throw new \RuntimeException('Termin tidak dipilih.');
            }
            $invoiceId = ProjectService::tagihTermin($termId);
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
            redirect('project/contract/' . $contractId);
        }

        Audit::log('create', 'project.contract', [
            'ref_table'   => 'ms_contract_terms',
            'ref_id'      => $termId,
            'description' => 'Menagih termin kontrak menjadi draft invoice AR #' . $invoiceId,
            'new'         => ['ar_invoice_id' => $invoiceId],
        ]);
        Flash::success('Draft invoice AR berhasil dibuat dari termin. Lengkapi dan posting di modul Piutang.');
        redirect('ar/' . $invoiceId);
    }

    /* --------------------------------------------------------- internal */

    /** @return array<string,mixed> */
    private function defaultProject(): array
    {
        return [
            'kode'              => '',
            'nama'              => '',
            'partner_id'        => null,
            'business_unit_id'  => null,
            'bidang_id'         => null,
            'divisi_id'         => null,
            'cost_center_id'    => null,
            'manager_user_id'   => null,
            'nilai_kontrak'     => 0,
            'uang_muka_persen'  => 0,
            'tanggal_mulai'     => today(),
            'tanggal_selesai'   => '',
            'status'            => 'perencanaan',
            'keterangan'        => '',
            'is_active'         => 1,
        ];
    }

    /** @return array<string,mixed> */
    private function defaultContract(): array
    {
        return [
            'nomor'         => '',
            'judul'         => '',
            'partner_id'    => null,
            'project_id'    => null,
            'nilai_kontrak' => 0,
            'tanggal'       => today(),
            'tanggal_mulai' => today(),
            'tanggal_akhir' => '',
            'ppn_persen'    => 11,
            'status'        => 'draft',
            'keterangan'    => '',
            'terms'         => [],
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function normalisasiProject(array $input): array
    {
        $nilai = $this->toDecimal($input['nilai_kontrak'] ?? 0);
        return [
            'kode'             => strtoupper(trim((string) ($input['kode'] ?? ''))),
            'nama'             => trim((string) ($input['nama'] ?? '')),
            'partner_id'       => (int) ($input['partner_id'] ?? 0) ?: null,
            'business_unit_id' => (int) ($input['business_unit_id'] ?? 0) ?: null,
            'bidang_id'        => (int) ($input['bidang_id'] ?? 0) ?: null,
            'divisi_id'        => (int) ($input['divisi_id'] ?? 0) ?: null,
            'cost_center_id'   => (int) ($input['cost_center_id'] ?? 0) ?: null,
            'manager_user_id'  => (int) ($input['manager_user_id'] ?? 0) ?: null,
            'nilai_kontrak'    => (float) $nilai,
            'uang_muka_persen' => max(0.0, min(100.0, (float) ($input['uang_muka_persen'] ?? 0))),
            'tanggal_mulai'    => trim((string) ($input['tanggal_mulai'] ?? '')) ?: null,
            'tanggal_selesai'  => trim((string) ($input['tanggal_selesai'] ?? '')) ?: null,
            'status'           => array_key_exists((string) ($input['status'] ?? ''), ProjectService::STATUS_PROYEK) ? (string) $input['status'] : 'perencanaan',
            'keterangan'       => trim((string) ($input['keterangan'] ?? '')),
            'is_active'        => isset($input['is_active']) ? 1 : 0,
        ];
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    private function validasiProject(array $data, ?int $id = null): array
    {
        $errors = [];
        if ($data['kode'] === '') {
            $errors['kode'] = 'Kode proyek wajib diisi.';
        } elseif ((int) Db::val('SELECT COUNT(*) FROM ms_projects WHERE kode = ? AND id <> ?', [$data['kode'], $id ?? 0]) > 0) {
            $errors['kode'] = 'Kode proyek sudah dipakai.';
        }
        if ($data['nama'] === '') {
            $errors['nama'] = 'Nama proyek wajib diisi.';
        }
        if ($data['tanggal_mulai'] !== null && $data['tanggal_selesai'] !== null
            && strtotime((string) $data['tanggal_selesai']) < strtotime((string) $data['tanggal_mulai'])) {
            $errors['tanggal_selesai'] = 'Target selesai tidak boleh sebelum tanggal mulai.';
        }

        return $errors;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function payloadProject(array $data): array
    {
        return [
            'kode'             => $data['kode'],
            'nama'             => mb_substr((string) $data['nama'], 0, 150),
            'partner_id'       => $data['partner_id'],
            'business_unit_id' => $data['business_unit_id'],
            'bidang_id'        => $data['bidang_id'],
            'divisi_id'        => $data['divisi_id'],
            'cost_center_id'   => $data['cost_center_id'],
            'manager_user_id'  => $data['manager_user_id'],
            'nilai_kontrak'    => number_format((float) $data['nilai_kontrak'], 2, '.', ''),
            'uang_muka_persen' => number_format((float) $data['uang_muka_persen'], 2, '.', ''),
            'tanggal_mulai'    => $data['tanggal_mulai'],
            'tanggal_selesai'  => $data['tanggal_selesai'],
            'status'           => $data['status'],
            'keterangan'       => $data['keterangan'] !== '' ? mb_substr((string) $data['keterangan'], 0, 255) : null,
            'is_active'        => (int) $data['is_active'],
        ];
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/project/form', [
            'pageTitle'    => $id === null ? 'Proyek Baru' : 'Edit Proyek',
            'pageSubtitle' => 'Data proyek dipakai sebagai dimensi jurnal, anggaran, dan laporan.',
            'breadcrumbs'  => [
                ['label' => 'Proyek & Kontrak', 'url' => url('project')],
                ['label' => $id === null ? 'Proyek Baru' : 'Edit Proyek', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url($id === null ? 'project' : 'project/' . $id)) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors,
            'partners'     => Db::all("SELECT id, nama FROM ms_partners WHERE tipe IN ('customer','both') ORDER BY nama"),
            'businessUnits' => Db::all('SELECT id, nama FROM ms_business_units ORDER BY kode'),
            'bidang'       => Db::all('SELECT id, nama FROM ms_bidang WHERE is_active = 1 ORDER BY kode'),
            'divisi'       => Db::all('SELECT id, nama FROM ms_divisi WHERE is_active = 1 ORDER BY kode'),
            'costCenters'  => Db::all('SELECT id, nama FROM ms_cost_centers WHERE is_active = 1 ORDER BY kode'),
            'users'        => Db::all('SELECT id, name FROM app_users WHERE is_active = 1 ORDER BY name'),
            'statusList'   => ProjectService::STATUS_PROYEK,
        ]);
    }

    /** @param array<string,mixed> $row @param array<string,string> $errors */
    private function renderContractForm(?int $id, array $row, array $errors = []): void
    {
        $this->page('pages/project/contract-form', [
            'pageTitle'    => $id === null ? 'Kontrak Baru' : 'Edit Kontrak',
            'pageSubtitle' => 'Kontrak menjadi dasar termin penagihan dan pengakuan pendapatan proyek.',
            'breadcrumbs'  => [
                ['label' => 'Proyek & Kontrak', 'url' => url('project')],
                ['label' => 'Kontrak', 'url' => url('project/contracts')],
                ['label' => $id === null ? 'Kontrak Baru' : 'Edit Kontrak', 'url' => null],
            ],
            'pageActions'  => '<a class="btn" href="' . e(url($id === null ? 'project/contracts' : 'project/contract/' . $id)) . '">' . icon('arrow-left', 15) . ' Kembali</a>',
            'row'          => $row,
            'id'           => $id,
            'errors'       => $errors,
            'partners'     => Db::all("SELECT id, nama FROM ms_partners WHERE tipe IN ('customer','both') ORDER BY nama"),
            'projects'     => Db::all('SELECT id, kode, nama, nilai_kontrak FROM ms_projects WHERE is_active = 1 ORDER BY kode DESC'),
            'statusList'   => ProjectService::STATUS_KONTRAK,
        ]);
    }

    /** @param array<string,mixed> $data */
    private function catatAudit(string $action, int $contractId, array $data): void
    {
        Audit::log($action, 'project.contract', [
            'ref_table'   => 'ms_contracts',
            'ref_id'      => $contractId,
            'ref_code'    => (string) $data['nomor'],
            'description' => ucfirst($action) . ' kontrak ' . $data['nomor'] . ' — ' . $data['judul'],
            'new'         => [
                'nilai_kontrak' => $data['nilai_kontrak'],
                'status'        => $data['status'],
                'termin'        => count($data['terms']),
            ],
        ]);
    }
}
