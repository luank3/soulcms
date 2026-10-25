<?php

/** @var array{slug:string,title:string,content:string,description:string,keyword:string} $static_page */

?>
<article class="panel">
    <div class="panel__head">
        <?= icon('file', '', 16) ?>
        <h1 class="panel__title"><?= e($static_page['title']) ?></h1>
    </div>
    <div class="panel__body prose">
        <?= markdown_to_html($static_page['content']) ?>
    </div>
</article>
