<?php

/**
 * The validation summary.
 *
 * @var array $errors
 */

?>
<?php if (!empty($errors)): ?>
    <div class="alert alert--error" role="alert">
        <?= icon('alert', '', 18) ?>
        <div>
            <p class="alert__title">Có <?= e((string) count($errors)) ?> lỗi cần sửa</p>
            <ul>
                <?php foreach ($errors as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
