<?php
/**
 * Form tambah/ubah master data generik.
 *
 * @var array $config
 * @var array $fields
 * @var array $options
 * @var array|null $row
 * @var bool $isEdit
 * @var array $errors
 */

$action = $isEdit
    ? url((string) $config['route'] . '/' . (int) $row['id'])
    : url((string) $config['route']);

$valueOf = static function (array $field) use ($row) {
    $name = (string) $field['name'];
    if (old($name) !== null) {
        return old($name);
    }
    if ($row !== null && array_key_exists($name, $row)) {
        return $row[$name];
    }
    return $field['default'] ?? '';
};
?>
<form class="form-card" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>

    <div class="form-grid">
        <?php foreach ($fields as $field): ?>
            <?php
            $name = (string) $field['name'];
            $type = (string) ($field['type'] ?? 'text');
            $value = $valueOf($field);
            $isFull = ($field['width'] ?? 'half') === 'full' || in_array($type, ['textarea'], true);
            $error = $errors[$name] ?? null;
            ?>
            <div class="field <?= $isFull ? 'field--full' : '' ?> <?= $error ? 'has-error' : '' ?>">
                <?php if ($type !== 'checkbox'): ?>
                    <label class="field-label" for="f-<?= e($name) ?>">
                        <?= e((string) $field['label']) ?>
                        <?php if (!empty($field['required'])): ?><span class="req">*</span><?php endif; ?>
                    </label>
                <?php endif; ?>

                <?php if ($type === 'select'): ?>
                    <select class="select" id="f-<?= e($name) ?>" name="<?= e($name) ?>">
                        <option value="">— pilih —</option>
                        <?php foreach (($options[$name] ?? []) as $option): ?>
                            <option value="<?= e((string) $option['value']) ?>"
                                <?= ((string) $value === (string) $option['value']) ? 'selected' : '' ?>>
                                <?= e((string) $option['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($type === 'textarea'): ?>
                    <textarea class="textarea" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
                              maxlength="<?= (int) ($field['max'] ?? 255) ?>"><?= e((string) $value) ?></textarea>

                <?php elseif ($type === 'checkbox'): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="<?= e($name) ?>" value="1" <?= ((int) $value === 1) ? 'checked' : '' ?>>
                        <?= e((string) $field['label']) ?>
                    </label>

                <?php elseif ($type === 'money'): ?>
                    <div class="input-money">
                        <span class="prefix">Rp</span>
                        <input class="input" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
                               value="<?= e(($value === '' || $value === null) ? '' : angka((float) $value)) ?>"
                               data-money inputmode="numeric" autocomplete="off">
                    </div>

                <?php elseif ($type === 'date'): ?>
                    <input class="input" type="date" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
                           value="<?= e($value ? date('Y-m-d', strtotime((string) $value)) : '') ?>">

                <?php elseif ($type === 'number'): ?>
                    <input class="input" type="number" step="any" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
                           value="<?= e((string) $value) ?>" <?= isset($field['min']) ? 'min="' . (int) $field['min'] . '"' : '' ?>>

                <?php else: ?>
                    <input class="input" type="text" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
                           value="<?= e((string) $value) ?>"
                           maxlength="<?= (int) ($field['max'] ?? 255) ?>"
                           placeholder="<?= e((string) ($field['placeholder'] ?? '')) ?>">
                <?php endif; ?>

                <?php if ($error): ?>
                    <span class="field-error"><?= e($error) ?></span>
                <?php elseif (!empty($field['help'])): ?>
                    <span class="field-help"><?= e((string) $field['help']) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-actions">
        <button class="btn btn--primary" type="submit"><?= icon('save', 16) ?> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan' ?></button>
        <a class="btn" href="<?= e(url((string) $config['route'])) ?>">Batal</a>
        <span class="spacer"></span>
        <span class="tiny muted">Semua perubahan tercatat pada audit trail.</span>
    </div>
</form>
