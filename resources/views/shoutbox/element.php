<?php

/**
 * @var array $message
 */

?>
<div class="chatline">
    <span class="chatline__who">@<?= e($message['name']) ?></span>
    <span><?= e($message['comment']) ?></span>
    <span class="chatline__time"><?= e(date('H:i', (int) $message['time'])) ?></span>
</div>
