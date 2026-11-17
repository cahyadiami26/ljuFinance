<?php
/**
 * Pengaturan aplikasi.
 *
 * @var array $groups
 * @var array $labels
 */
?>
<form method="post" action="<?= e(url('system/settings')) ?>">
    <?= csrf_field() ?>

    <div class="grid-half">
        <?php foreach ($groups as $group => $settings): ?>
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title"><?= e($labels[$group] ?? ucfirst((string) $group)) ?></h2>
                </div>
                <div class="card-body">
                    <div class="stack-8">
                        <?php foreach ($settings as $setting): ?>
                            <?php $type = (string) $setting['input_type']; ?>
                            <div class="field">
                                <label class="field-label" for="s-<?= e((string) $setting['setting_key']) ?>">
                                    <?= e((string) ($setting['label'] ?? $setting['setting_key'])) ?>
                                </label>

                                <?php if ($type === 'textarea'): ?>
                                    <textarea class="textarea" id="s-<?= e((string) $setting['setting_key']) ?>"
                                              name="<?= e((string) $setting['setting_key']) ?>"><?= e((string) $setting['setting_value']) ?></textarea>
                                <?php elseif ($type === 'boolean'): ?>
                                    <select class="select" name="<?= e((string) $setting['setting_key']) ?>">
                                        <option value="1" <?= (string) $setting['setting_value'] === '1' ? 'selected' : '' ?>>Ya</option>
                                        <option value="0" <?= (string) $setting['setting_value'] !== '1' ? 'selected' : '' ?>>Tidak</option>
                                    </select>
                                <?php elseif ($type === 'select'): ?>
                                    <select class="select" name="<?= e((string) $setting['setting_key']) ?>">
                                        <?php foreach (['A4' => 'A4', 'A5' => 'A5', 'Letter' => 'Letter'] as $value => $label): ?>
                                            <option value="<?= e($value) ?>" <?= (string) $setting['setting_value'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($type === 'number'): ?>
                                    <input class="input" type="number" id="s-<?= e((string) $setting['setting_key']) ?>"
                                           name="<?= e((string) $setting['setting_key']) ?>" value="<?= e((string) $setting['setting_value']) ?>">
                                <?php else: ?>
                                    <input class="input" type="text" id="s-<?= e((string) $setting['setting_key']) ?>"
                                           name="<?= e((string) $setting['setting_key']) ?>" value="<?= e((string) $setting['setting_value']) ?>">
                                <?php endif; ?>

                                <span class="field-help">key: <?= e((string) $setting['setting_key']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-actions" style="max-width:980px">
        <button class="btn btn--primary" type="submit"><?= icon('save', 16) ?> Simpan Pengaturan</button>
        <span class="tiny muted">Beberapa parameter (mis. wajib lampiran) baru berlaku pada tahap modul terkait.</span>
    </div>
</form>
