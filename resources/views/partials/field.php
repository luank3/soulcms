<?php

/**
 * A labelled form field with inline, accessible error reporting.
 *
 * @var string       $name
 * @var string       $label
 * @var array<mixed> $errors
 * @var string       $type
 * @var mixed        $value
 * @var string|null  $hint
 * @var bool         $required
 * @var bool         $autofocus
 * @var string|null  $placeholder
 * @var int          $rows
 */

$id = $name;
$errors = $errors ?? [];
$error = isset($errors[$name]) && is_string($errors[$name]) ? $errors[$name] : null;
$hint = $hint ?? null;
$type = $type ?? 'text';
$value = $value ?? '';
$required = $required ?? false;
$autofocus = $autofocus ?? false;
$placeholder = $placeholder ?? null;

$describedBy = [];

if ($error !== null) {
    $describedBy[] = $id . '-error';
}

if ($hint !== null) {
    $describedBy[] = $id . '-hint';
}

?>
<div class="field<?= $error !== null ? ' field--invalid' : '' ?>">
    <label for="<?= e($id) ?>">
        <?= e($label) ?><?= $required ? '<span class="field__required" aria-hidden="true">*</span>' : '' ?>
    </label>

    <input
        id="<?= e($id) ?>"
        name="<?= e($name) ?>"
        type="<?= e($type) ?>"
        value="<?= e((string) $value) ?>"
        <?= $required ? 'required' : '' ?>
        <?= $autofocus ? 'autofocus' : '' ?>
        <?= $placeholder !== null ? 'placeholder="' . e((string) $placeholder) . '"' : '' ?>
        <?= $error !== null ? 'aria-invalid="true"' : '' ?>
        <?= $describedBy !== [] ? 'aria-describedby="' . e(implode(' ', $describedBy)) . '"' : '' ?>
    >

    <?php if ($error !== null): ?>
        <span class="field__error" id="<?= e($id) ?>-error"><?= icon('alert', '', 14) ?> <?= e($error) ?></span>
    <?php endif; ?>

    <?php if ($hint !== null): ?>
        <span class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></span>
    <?php endif; ?>
</div>
