<?php
/**
 * Form kontrak proyek beserta termin penagihan.
 *
 * @var array $row
 * @var int|null $id
 * @var array $errors
 * @var array $partners
 * @var array $projects
 * @var array $statusList
 */
$hasError = static fn (string $key): bool => isset($errors[$key]);
$errorText = static fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e((string) $errors[$key]) . '</div>' : '';
$terms = (array) ($row['terms'] ?? []);
if ($terms === []) {
    $terms = [['termin_ke' => 1, 'deskripsi' => 'Termin 1', 'persentase' => 100, 'nilai' => (float) ($row['nilai_kontrak'] ?? 0)]];
}
?>
<?php if (isset($errors['general'])): ?>
    <div class="flash flash--error mb-16">
        <?= icon('alert', 18) ?>
        <div>
            <div class="strong">Kontrak belum dapat disimpan</div>
            <div class="small"><?= e((string) $errors['general']) ?></div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $id === null ? e(url('project/contract')) : e(url('project/contract/' . $id)) ?>" id="contract-form">
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Identitas Kontrak</h2>
                <div class="card-sub">Nomor kontrak harus unik dan dipakai pada dokumen penagihan.</div>
            </div>
            <?php if (!empty($row['nomor'])): ?>
                <span class="pill mono"><?= e((string) $row['nomor']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field<?= $hasError('nomor') ? ' has-error' : '' ?>">
                    <label class="field-label" for="c-nomor">Nomor Kontrak</label>
                    <input class="input mono" type="text" id="c-nomor" name="nomor" maxlength="50" required
                           value="<?= e((string) ($row['nomor'] ?? '')) ?>" placeholder="KTR/2026/001">
                    <?= $errorText('nomor') ?>
                </div>

                <div class="field<?= $hasError('judul') ? ' has-error' : '' ?>">
                    <label class="field-label" for="c-judul">Judul Kontrak</label>
                    <input class="input" type="text" id="c-judul" name="judul" maxlength="150" required
                           value="<?= e((string) ($row['judul'] ?? '')) ?>" placeholder="Pekerjaan …">
                    <?= $errorText('judul') ?>
                </div>

                <div class="field<?= $hasError('project_id') ? ' has-error' : '' ?>">
                    <label class="field-label" for="c-project">Proyek</label>
                    <select class="select" id="c-project" name="project_id" data-contract-project required>
                        <option value="">— pilih proyek —</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>"
                                    data-nilai="<?= (float) $project['nilai_kontrak'] ?>"
                                    <?= (int) ($row['project_id'] ?? 0) === (int) $project['id'] ? 'selected' : '' ?>>
                                <?= e($project['kode'] . ' — ' . $project['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorText('project_id') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="c-partner">Pemberi Kerja</label>
                    <select class="select" id="c-partner" name="partner_id">
                        <option value="">— ikuti proyek —</option>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= (int) $partner['id'] ?>" <?= (int) ($row['partner_id'] ?? 0) === (int) $partner['id'] ? 'selected' : '' ?>><?= e((string) $partner['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="tiny muted">Bila dikosongkan, pemberi kerja mengikuti data proyek.</div>
                </div>

                <div class="field<?= $hasError('nilai_kontrak') ? ' has-error' : '' ?>">
                    <label class="field-label" for="c-nilai">Nilai Kontrak (Rp)</label>
                    <input class="input mono" type="text" id="c-nilai" name="nilai_kontrak" inputmode="decimal"
                           value="<?= e(number_format((float) ($row['nilai_kontrak'] ?? 0), 2, ',', '.')) ?>">
                    <?= $errorText('nilai_kontrak') ?>
                </div>

                <div class="field<?= $hasError('ppn_persen') ? ' has-error' : '' ?>">
                    <label class="field-label" for="c-ppn">PPN (%)</label>
                    <input class="input" type="number" step="0.01" min="0" max="100" id="c-ppn" name="ppn_persen"
                           value="<?= e(number_format((float) ($row['ppn_persen'] ?? 11), 2, '.', '')) ?>">
                    <div class="tiny muted">Dipakai saat termin ditagihkan menjadi invoice AR.</div>
                    <?= $errorText('ppn_persen') ?>
                </div>

                <div class="field<?= $hasError('tanggal') ? ' has-error' : '' ?>">
                    <label class="field-label" for="c-tanggal">Tanggal Kontrak</label>
                    <input class="input" type="date" id="c-tanggal" name="tanggal" value="<?= e((string) ($row['tanggal'] ?? today())) ?>">
                    <?= $errorText('tanggal') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="c-mulai">Mulai</label>
                    <input class="input" type="date" id="c-mulai" name="tanggal_mulai" value="<?= e((string) ($row['tanggal_mulai'] ?? '')) ?>">
                </div>

                <div class="field<?= $hasError('tanggal_akhir') ? ' has-error' : '' ?>">
                    <label class="field-label" for="c-akhir">Berakhir</label>
                    <input class="input" type="date" id="c-akhir" name="tanggal_akhir" value="<?= e((string) ($row['tanggal_akhir'] ?? '')) ?>">
                    <?= $errorText('tanggal_akhir') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="c-status">Status</label>
                    <select class="select" id="c-status" name="status">
                        <?php foreach ($statusList as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($row['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="tiny muted">Termin hanya dapat ditagih pada kontrak aktif.</div>
                </div>

                <div class="field field--full">
                    <label class="field-label" for="c-ket">Keterangan</label>
                    <textarea class="textarea" id="c-ket" name="keterangan" rows="2" maxlength="255"><?= e((string) ($row['keterangan'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-16">
        <div class="card-head">
            <div>
                <h2 class="card-title">Termin Penagihan</h2>
                <div class="card-sub">Nilai termin boleh diisi otomatis dari persentase kontrak.</div>
            </div>
            <div class="row" style="gap:8px">
                <span class="pill" id="termin-total">Total 0%</span>
                <button class="btn btn--sm" type="button" id="add-termin"><?= icon('plus', 14) ?> Tambah Termin</button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($hasError('terms')): ?>
                <div class="field-error mb-8"><?= e((string) $errors['terms']) ?></div>
            <?php endif; ?>
            <div class="table-wrap">
                <table class="table" id="termin-table">
                    <thead>
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Deskripsi Termin</th>
                            <th class="num" style="width:120px">Persentase (%)</th>
                            <th class="num" style="width:180px">Nilai (Rp)</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody id="termin-body">
                        <?php foreach (array_values($terms) as $index => $term): ?>
                            <tr>
                                <td class="termin-no small strong"><?= $index + 1 ?></td>
                                <td><input class="input" type="text" name="terms[<?= $index ?>][deskripsi]" maxlength="150"
                                           value="<?= e((string) ($term['deskripsi'] ?? '')) ?>" placeholder="Termin <?= $index + 1 ?>"></td>
                                <td><input class="input mono num" type="number" step="0.01" min="0" max="100" name="terms[<?= $index ?>][persentase]"
                                           value="<?= e(number_format((float) ($term['persentase'] ?? 0), 2, '.', '')) ?>" data-termin-persen></td>
                                <td><input class="input mono num" type="text" inputmode="decimal" name="terms[<?= $index ?>][nilai]"
                                           value="<?= e(number_format((float) ($term['nilai'] ?? 0), 2, ',', '.')) ?>" data-termin-nilai></td>
                                <td><button class="btn btn--sm btn--ghost" type="button" data-termin-hapus title="Hapus termin"><?= icon('x', 14) ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="notice notice--info mt-12">
                <?= icon('info', 15) ?>
                <div class="tiny">
                    Total nilai termin sebaiknya sama dengan nilai kontrak. Termin yang sudah ditagih tidak akan
                    berubah nilainya karena invoice AR menyimpan nilai tersendiri.
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="justify-content:flex-end;gap:8px">
        <a class="btn" href="<?= e(url($id === null ? 'project/contracts' : 'project/contract/' . $id)) ?>">Batal</a>
        <button class="btn btn--primary" type="submit"><?= icon('save', 15) ?> Simpan Kontrak</button>
    </div>
</form>

<script>
(function () {
    var body = document.getElementById('termin-body');
    var table = document.getElementById('contract-form');
    if (!body) { return; }

    function uang(nilai) {
        return (Number(nilai) || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function nilaiKontrak() {
        var el = document.getElementById('c-nilai');
        if (!el) { return 0; }
        var bersih = String(el.value).replace(/\./g, '').replace(',', '.');
        return parseFloat(bersih) || 0;
    }

    function hitungTotal() {
        var total = 0;
        body.querySelectorAll('[data-termin-persen]').forEach(function (el) {
            total += parseFloat(el.value) || 0;
        });
        var pill = document.getElementById('termin-total');
        if (pill) {
            pill.textContent = 'Total ' + total.toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '%';
            pill.className = 'pill' + (Math.abs(total - 100) < 0.01 ? ' badge--sukses' : '');
        }
        var no = 1;
        body.querySelectorAll('.termin-no').forEach(function (el) { el.textContent = no++; });
    }

    function tambah() {
        var index = body.querySelectorAll('tr').length;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td class="termin-no small strong">' + (index + 1) + '</td>'
            + '<td><input class="input" type="text" name="terms[' + index + '][deskripsi]" maxlength="150" value="Termin ' + (index + 1) + '"></td>'
            + '<td><input class="input mono num" type="number" step="0.01" min="0" max="100" name="terms[' + index + '][persentase]" value="0" data-termin-persen></td>'
            + '<td><input class="input mono num" type="text" inputmode="decimal" name="terms[' + index + '][nilai]" value="0,00" data-termin-nilai></td>'
            + '<td><button class="btn btn--sm btn--ghost" type="button" data-termin-hapus title="Hapus termin">×</button></td>';
        body.appendChild(tr);
        hitungTotal();
    }

    body.addEventListener('input', function (event) {
        var el = event.target;
        if (el.matches('[data-termin-persen]')) {
            var kontrak = nilaiKontrak();
            var nilai = kontrak * (parseFloat(el.value) || 0) / 100;
            var baris = el.closest('tr');
            var inputNilai = baris ? baris.querySelector('[data-termin-nilai]') : null;
            if (inputNilai && kontrak > 0) { inputNilai.value = uang(nilai); }
        }
        if (el.id === 'c-nilai') {
            body.querySelectorAll('[data-termin-persen]').forEach(function (persen) {
                if ((parseFloat(persen.value) || 0) <= 0) { return; }
                var baris = persen.closest('tr');
                var inputNilai = baris ? baris.querySelector('[data-termin-nilai]') : null;
                if (inputNilai) { inputNilai.value = uang(nilaiKontrak() * (parseFloat(persen.value) || 0) / 100); }
            });
        }
        hitungTotal();
    });

    body.addEventListener('click', function (event) {
        var tombol = event.target.closest('[data-termin-hapus]');
        if (!tombol) { return; }
        var baris = tombol.closest('tr');
        if (baris) { baris.remove(); }
        hitungTotal();
    });

    var add = document.getElementById('add-termin');
    if (add) { add.addEventListener('click', tambah); }

    var project = document.querySelector('[data-contract-project]');
    if (project) {
        project.addEventListener('change', function () {
            var opsi = project.options[project.selectedIndex];
            var nilai = opsi ? parseFloat(opsi.getAttribute('data-nilai')) : 0;
            var el = document.getElementById('c-nilai');
            if (el && (!nilaiKontrak()) && nilai > 0) { el.value = uang(nilai); hitungTotal(); }
        });
    }

    hitungTotal();
})();
</script>
