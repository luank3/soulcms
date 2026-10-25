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
            <p class="alert__title"><?= e(\SoulCMS\Support\Lang::get(count($errors) === 1 ? 'validation.summary.one' : 'validation.summary.many', ['count' => (string) count($errors)])) ?></p>
            <ul>
                <?php foreach ($errors as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
