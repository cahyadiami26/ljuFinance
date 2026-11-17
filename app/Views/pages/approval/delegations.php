<?php
/**
 * Delegasi persetujuan antar pejabat.
 *
 * @var array $rows
 * @var array $users
 * @var array $moduleList
 * @var bool $canManage
 */
$hariIni = date('Y-m-d');
?>
<div class="notice notice--info mb-16">
    <?= icon('info', 16) ?>
    <div>Delegasi berlaku pada rentang tanggal yang ditentukan. Jika modul dikosongkan, delegasi berlaku untuk seluruh modul persetujuan.</div>
</div>

<?php if ($canManage): ?>
    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Buat Delegasi</h2>
                <div class="card-sub">Pelimpahan kewenangan sementara, misalnya saat pejabat cuti atau dinas luar.</div>
            </div>
        </div>
        <form method="post" action="<?= e(url('approval/delegations')) ?>">
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="d-dari">Pejabat Asal</label>
                        <select class="select" id="d-dari" name="from_user_id" required>
                            <option value="">— pilih pejabat —</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int) $user['id'] ?>"><?= e((string) $user['name']) ?><?= !empty($user['position']) ? ' · ' . e((string) $user['position']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="d-ke">Penerima Delegasi</label>
                        <select class="select" id="d-ke" name="to_user_id" required>
                            <option value="">— pilih penerima —</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int) $user['id'] ?>"><?= e((string) $user['name']) ?><?= !empty($user['position']) ? ' · ' . e((string) $user['position']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="d-mulai">Tanggal Mulai</label>
                        <input class="input" type="date" id="d-mulai" name="tanggal_mulai" value="<?= e($hariIni) ?>" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="d-selesai">Tanggal Selesai</label>
                        <input class="input" type="date" id="d-selesai" name="tanggal_selesai" value="<?= e($hariIni) ?>" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="d-modul">Modul</label>
                        <select class="select" id="d-modul" name="module">
                            <option value="">Seluruh modul</option>
                            <?php foreach ($moduleList as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="d-alasan">Alasan</label>
                        <input class="input" type="text" id="d-alasan" name="alasan" maxlength="255" placeholder="mis. cuti tahunan">
                    </div>
                </div>
            </div>
            <div class="card-foot row" style="justify-content:flex-end">
                <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Delegasi</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="card card--flush">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?= icon('users', 22) ?></div>
            <h3>Belum ada delegasi</h3>
            <p>Delegasi diperlukan ketika pejabat berwenang tidak dapat memproses persetujuan secara langsung.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dari</th>
                        <th>Kepada</th>
                        <th style="width:150px">Periode</th>
                        <th style="width:150px">Modul</th>
                        <th>Alasan</th>
                        <th style="width:110px">Status</th>
                        <?php if ($canManage): ?><th style="width:90px">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $aktif = (int) $row['is_active'] === 1;
                        $berjalan = $aktif && $hariIni >= (string) $row['tanggal_mulai'] && $hariIni <= (string) $row['tanggal_selesai'];
                        $label = !$aktif ? 'Nonaktif' : ($berjalan ? 'Berjalan' : ($hariIni < (string) $row['tanggal_mulai'] ? 'Terjadwal' : 'Berakhir'));
                        ?>
                        <tr>
                            <td class="small strong"><?= e((string) ($row['from_nama'] ?? '—')) ?></td>
                            <td class="small"><?= e((string) ($row['to_nama'] ?? '—')) ?></td>
                            <td class="small"><?= e(tanggal($row['tanggal_mulai'], 'd M Y')) ?> – <?= e(tanggal($row['tanggal_selesai'], 'd M Y')) ?></td>
                            <td class="small"><?= !empty($row['module']) ? e((string) ($moduleList[(string) $row['module']] ?? $row['module'])) : '<span class="muted">Semua modul</span>' ?></td>
                            <td class="small"><?= e(str_limit((string) ($row['alasan'] ?? '—'), 60)) ?></td>
                            <td>
                                <span class="badge badge--<?= $berjalan ? 'sukses' : ($label === 'Terjadwal' ? 'info' : 'netral') ?>"><?= e($label) ?></span>
                            </td>
                            <?php if ($canManage): ?>
                                <td>
                                    <?php if ($aktif): ?>
                                        <form method="post" action="<?= e(url('approval/delegations/' . (int) $row['id'] . '/close')) ?>" data-confirm="Nonaktifkan delegasi ini?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--sm btn--ghost" type="submit"><?= icon('x', 13) ?> Tutup</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
