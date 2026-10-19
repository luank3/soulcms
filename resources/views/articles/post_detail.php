<?php

/**
 * @var array  $post
 * @var array  $chapters
 * @var array  $comments
 * @var array  $tags
 * @var array  $files
 * @var int    $comment_count
 * @var string $pagination
 */

$author = $post['author_detail'] ?? null;
$canComment = auth()->check();

?>
<div class="stack-lg">

    <article class="panel">
        <div class="panel__body">
            <header style="margin-bottom:var(--s-5)">
                <?php if (!empty($post['category_name'])): ?>
                    <span class="tagmark" <?= marker_style((string) ($post['category_slug'] ?? '')) ?>>
                        <?= e($post['category_name']) ?>
                    </span>
                <?php endif; ?>

                <h1 style="margin:var(--s-2) 0 var(--s-3)"><?= e($post['title']) ?></h1>

                <p class="feed__meta">
                    <a href="<?= e(url('/user/' . $post['author'])) ?>">@<?= e($post['author']) ?></a>
                    <?php if (is_array($author) && $author !== []): ?>
                        <?= member_chip($author) ?>
                    <?php endif; ?>
                    <span><?= e(date('d.m.Y · H:i', (int) $post['time'])) ?></span>
                    <span>cập nhật <?= e(time_ago((int) $post['update_time'])) ?></span>
                    <span><?= e((string) (int) $post['view']) ?> lượt xem</span>
                </p>
            </header>

            <div class="prose"><?= markdown_to_html((string) $post['content']) ?></div>

            <?php if ($tags !== []): ?>
                <p class="row mt-5" style="margin-bottom:0">
                    <?php foreach ($tags as $tag): ?>
                        <a class="chip chip--tag" href="<?= e(url('/tag/' . $tag['slug'])) ?>">
                            #<?= e($tag['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>
    </article>

    <?php if ($chapters !== []): ?>
        <section class="panel">
            <div class="panel__head">
                <?= icon('layers', '', 16) ?>
                <h2 class="panel__title">Danh sách chương</h2>
                <span class="panel__meta"><?= e((string) count($chapters)) ?></span>
            </div>
            <div class="panel__body panel__body--flush">
                <div class="feed">
                    <?php foreach ($chapters as $chapter): ?>
                        <div class="feed__row" style="grid-template-columns:3px minmax(0,1fr) auto">
                            <span class="feed__gutter" <?= marker_style((string) $chapter['slug']) ?>></span>
                            <div>
                                <h3 class="feed__title" style="font-size:var(--step-0)">
                                    <a href="<?= e(url('/view-chap/' . (int) $chapter['id'] . '-' . $chapter['slug'] . '.html')) ?>">
                                        <?= e($chapter['title']) ?>
                                    </a>
                                </h3>
                                <p class="feed__meta">
                                    <span>@<?= e($chapter['author']) ?></span>
                                    <span><?= e(time_ago((int) $chapter['time'])) ?></span>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($files !== []): ?>
        <section class="panel">
            <div class="panel__head">
                <?= icon('download', '', 16) ?>
                <h2 class="panel__title">Tệp đính kèm</h2>
                <span class="panel__meta"><?= e((string) count($files)) ?></span>
            </div>
            <div class="panel__body panel__body--flush">
                <div class="feed">
                    <?php foreach ($files as $file): ?>
                        <div class="feed__row" style="grid-template-columns:3px minmax(0,1fr) auto">
                            <span class="feed__gutter" style="background:var(--ink-3);min-height:20px"></span>
                            <div>
                                <h3 class="feed__title" style="font-size:var(--step-0)">
                                    <a href="<?= e(url('/view-file/' . (int) $file['id'])) ?>"><?= e($file['filename']) ?></a>
                                </h3>
                                <p class="feed__meta">
                                    <span><?= e((string) $file['filecate']) ?></span>
                                    <span><?= e(format_bytes((int) $file['filesize'])) ?></span>
                                    <span>@<?= e($file['author']) ?></span>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel__head">
            <?= icon('forum', '', 16) ?>
            <h2 class="panel__title">Bình luận</h2>
            <span class="panel__meta"><?= e((string) $comment_count) ?></span>
        </div>

        <div class="panel__body panel__body--flush">
            <?php if ($comments === []): ?>
                <p class="muted" style="padding:var(--s-4)">Chưa có bình luận nào. Hãy là người đầu tiên.</p>
            <?php else: ?>
                <div class="thread" style="padding:0 var(--s-4)">
                    <?php foreach ($comments as $comment): ?>
                        <article class="comment">
                            <span class="avatar"><?= e(mb_substr((string) $comment['author'], 0, 2)) ?></span>
                            <div>
                                <div class="comment__head">
                                    <a class="comment__author" href="<?= e(url('/user/' . $comment['author'])) ?>">
                                        @<?= e($comment['author']) ?>
                                    </a>
                                    <span class="comment__time"><?= e(date('d.m.Y H:i', (int) $comment['time'])) ?></span>
                                </div>
                                <div class="comment__body"><?= nl2br(e($comment['comment'])) ?></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($canComment): ?>
            <div class="panel__body">
                <form class="form" method="post" action="<?= e(url('/articles/' . (int) $post['id'] . '-' . $post['slug'] . '.html')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="comment">Viết bình luận</label>
                        <textarea id="comment" name="comment" rows="4" required placeholder="Chia sẻ ý kiến của bạn…"></textarea>
                    </div>
                    <div class="form__actions">
                        <button class="btn" type="submit"><?= icon('reply', '', 15) ?> Gửi bình luận</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="panel__body">
                <p class="muted" style="margin:0">
                    <a href="<?= e(url('/login')) ?>">Đăng nhập</a> để tham gia thảo luận.
                </p>
            </div>
        <?php endif; ?>

        <?php if ($pagination !== ''): ?>
            <div class="panel__body"><?= $pagination ?></div>
        <?php endif; ?>
    </section>
</div>
