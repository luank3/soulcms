<?php

/**
 * The empty state. An empty screen is an invitation to act.
 *
 * @var string      $title
 * @var string|null $message
 * @var string|null $actionLabel
 * @var string|null $actionUrl
 * @var string      $icon
 */

?>
<div class="empty">
    <span class="empty__icon"><?= icon($icon ?? 'inbox', '', 22) ?></span>
    <p class="empty__title"><?= e($title) ?></p>
    <?php if (!empty($message)): ?>
        <p><?= e($message) ?></p>
    <?php endif; ?>
    <?php if (!empty($actionUrl)): ?>
        <a class="btn btn--ghost btn--sm mt-4" href="<?= e($actionUrl) ?>"><?= e($actionLabel ?? \SoulCMS\Support\Lang::get('common.continue')) ?></a>
    <?php endif; ?>
</div>
