<?php

/**
 * The forum home.
 *
 * @var array      $posts
 * @var array      $categories
 * @var array      $stats
 * @var array      $sticked
 * @var array      $online_users
 * @var string     $pagination
 * @var int        $current_page
 * @var array|null $category
 * @var string     $order_by
 * @var string     $sort
 */

$canPost = auth()->check();

?>
<div class="stack-lg">

    <?php if ($category !== null): ?>
        <div class="panel">
            <div class="panel__body">
                <h1 style="margin-bottom:var(--s-2)"><?= e($category['name']) ?></h1>
                <p class="muted" style="max-width:64ch;margin:0"><?= e((string) ($category['content'] ?? '')) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($sticked)): ?>
        <section class="panel">
            <div class="panel__head">
                <?= icon('anchor', '', 16) ?>
                <h2 class="panel__title">Bài ghim</h2>
                <span class="panel__meta"><?= e((string) count($sticked)) ?> bài</span>
            </div>
            <div class="panel__body panel__body--flush">
                <div class="feed">
                    <?php foreach ($sticked as $post): ?>
                        <?= view('partials/feed-row', ['post' => $post]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Bài viết mới nhất</h2>
            <span class="panel__meta"><?= e((string) ($stats['count_post'] ?? 0)) ?> bài · <?= e((string) ($stats['count_user'] ?? 0)) ?> thành viên</span>
        </div>

        <form class="feed-tools" action="<?= e(url('/')) ?>" method="get" aria-label="Tùy chọn danh sách bài viết">
            <?php if ($category !== null): ?>
                <input type="hidden" name="category" value="<?= e((string) (int) $category['id']) ?>">
            <?php endif; ?>

            <label class="field feed-tools__field">
                <span>Sắp xếp theo</span>
                <select name="order_by">
                    <?php
                    $orderLabels = [
                        'id' => 'Bài gần đây',
                        'time' => 'Mới đăng',
                        'update_time' => 'Hoạt động gần đây',
                        'view' => 'Lượt xem',
                    ];
                    foreach ((array) config('app.content.post_list_order_by', ['update_time']) as $field):
                        $field = (string) $field;
                    ?>
                        <option value="<?= e($field) ?>" <?= $order_by === $field ? 'selected' : '' ?>>
                            <?= e($orderLabels[$field] ?? ucfirst(str_replace('_', ' ', $field))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field feed-tools__field feed-tools__direction">
                <span>Thứ tự</span>
                <select name="sort">
                    <?php foreach ((array) config('app.content.post_list_sort', ['desc']) as $direction): ?>
                        <?php $direction = (string) $direction; ?>
                        <option value="<?= e($direction) ?>" <?= $sort === $direction ? 'selected' : '' ?>>
                            <?= e($direction === 'asc' ? 'Tăng dần' : 'Giảm dần') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button class="btn btn--ghost btn--sm feed-tools__submit" type="submit">Áp dụng</button>
        </form>

        <?php if ($canPost): ?>
            <div class="panel__body" style="padding-bottom:0">
                <a class="btn btn--sm" href="<?= e(url('/articles/post')) ?>"><?= icon('plus', '', 15) ?> Viết bài mới</a>
            </div>
        <?php endif; ?>

        <div class="panel__body panel__body--flush">
            <?php if ($posts === []): ?>
                <?= view('partials/empty', [
                    'title' => 'Chưa có bài viết nào',
                    'message' => $canPost ? 'Hãy là người mở đầu chuyên mục này.' : 'Quay lại sau để xem bài viết mới nhất.',
                    'icon' => 'file',
                    'actionLabel' => $canPost ? 'Viết bài đầu tiên' : 'Về trang chủ',
                    'actionUrl' => $canPost ? url('/articles/post') : url('/'),
                ]) ?>
            <?php else: ?>
                <div class="feed">
                    <?php foreach ($posts as $post): ?>
                        <?= view('partials/feed-row', ['post' => $post]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($pagination !== ''): ?>
            <div class="panel__body"><?= $pagination ?></div>
        <?php endif; ?>
    </section>

    <div class="grid grid--2">
        <section class="panel">
            <div class="panel__head">
                <?= icon('folder', '', 16) ?>
                <h2 class="panel__title">Chuyên mục</h2>
            </div>
            <div class="panel__body panel__body--flush">
                <?php if ($categories === []): ?>
                    <p class="muted" style="padding:var(--s-4)">Chưa có chuyên mục nào.</p>
                <?php else: ?>
                    <div class="feed">
                        <?php foreach ($categories as $item): ?>
                            <div class="feed__row">
                                <span class="feed__gutter" <?= marker_style((string) $item['slug']) ?>></span>
                                <div>
                                    <h3 class="feed__title" style="font-size:var(--step-0)">
                                        <a href="<?= e(url('/?category=' . (int) $item['id'])) ?>"><?= e($item['name']) ?></a>
                                    </h3>
                                    <p class="feed__meta"><?= e((string) $item['slug']) ?></p>
                                </div>
                                <div class="feed__stats">
                                    <span class="feed__stat"><b><?= e((string) (int) ($item['count_post'] ?? 0)) ?></b>bài</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head">
                <?= icon('users', '', 16) ?>
                <h2 class="panel__title">Đang trực tuyến</h2>
                <span class="panel__meta"><?= e((string) count($online_users)) ?></span>
            </div>
            <div class="panel__body panel__body--flush">
                <?php if ($online_users === []): ?>
                    <p class="muted" style="padding:var(--s-4)">Chưa có ai đang trực tuyến.</p>
                <?php else: ?>
                    <div class="feed">
                        <?php foreach ($online_users as $member): ?>
                            <div class="feed__row" style="grid-template-columns:3px minmax(0,1fr) auto;padding-block:var(--s-2)">
                                <span class="feed__gutter" style="background:var(--live);min-height:20px"></span>
                                <div>
                                    <a href="<?= e(url('/user/' . $member['nick'])) ?>">@<?= e($member['nick']) ?></a>
                                </div>
                                <div class="feed__stats">
                                    <span class="feed__stat"><b style="color:var(--live)">●</b></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
