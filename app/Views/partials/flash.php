<?php
/**
 * Pesan flash.
 *
 * @var array $flashes
 */
if (empty($flashes)) {
    return;
}
$icons = ['success' => 'check-circle', 'error' => 'alert', 'warning' => 'alert', 'info' => 'info'];
?>
<div class="flash-stack">
    <?php foreach ($flashes as $flash): ?>
        <?php $type = in_array($flash['type'], ['success', 'error', 'warning', 'info'], true) ? $flash['type'] : 'info'; ?>
        <div class="flash flash--<?= e($type) ?>">
            <?= icon($icons[$type], 17) ?>
            <div><?= e((string) $flash['message']) ?></div>
            <button type="button" class="flash-close" data-dismiss="flash" aria-label="Tutup">×</button>
        </div>
    <?php endforeach; ?>
</div>
