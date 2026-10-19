<?php

/**
 * @var array $messages
 */

$canPost = auth()->check();
$lastId = 0;

foreach ($messages as $message) {
    $lastId = max($lastId, (int) $message['id']);
}

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('chat', '', 16) ?>
        <h1 class="panel__title">Trò chuyện</h1>
        <span class="panel__meta"><?= e((string) count($messages)) ?> tin nhắn gần đây</span>
    </div>

    <div class="panel__body panel__body--flush">
        <?php if ($messages === []): ?>
            <?= view('partials/empty', [
                'title' => 'Phòng trò chuyện còn trống',
                'message' => 'Hãy là người đầu tiên gửi một lời chào.',
                'icon' => 'chat',
            ]) ?>
        <?php else: ?>
            <div class="chatwall" data-chat-wall data-chat-endpoint="<?= e(url('/shoutbox/list')) ?>" data-chat-last="<?= e((string) $lastId) ?>">
                <?php foreach ($messages as $message): ?>
                    <div class="chatline">
                        <span class="chatline__who">@<?= e($message['name']) ?></span>
                        <span><?= e($message['comment']) ?></span>
                        <span class="chatline__time"><?= e(date('H:i', (int) $message['time'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canPost): ?>
        <div class="panel__body">
            <form class="form form--tight" method="post" action="<?= e(url('/shoutbox/send')) ?>">
                <?= csrf_field() ?>
                <div class="row" style="flex-wrap:nowrap">
                    <input name="message" maxlength="2000" required placeholder="Nói gì đó…">
                    <button class="btn" type="submit">Gửi</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="panel__body">
            <p class="muted" style="margin:0"><a href="<?= e(url('/login')) ?>">Đăng nhập</a> để tham gia trò chuyện.</p>
        </div>
    <?php endif; ?>
</section>
