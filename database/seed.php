<?php

/**
 * Demo content for SoulCMS.
 *
 * Creates a small but realistic community: members with different roles, a
 * handful of categories, posts with chapters, comments and tags, and a live
 * chat room. Useful to see the interface with content in it.
 *
 * Usage: php bin/soulcms db:seed [--force]
 */

declare(strict_types=1);

use SoulCMS\Database\Connection;
use SoulCMS\Foundation\Str;

/**
 * @return array{0:int,1:int,2:int} the number of posts, comments and members
 */
return function (Connection $db, bool $force = false): array {
    $existing = (int) $db->scalar('SELECT COUNT(*) FROM `articles_post`');

    if ($existing > 0 && !$force) {
        return [0, 0, 0];
    }

    if ($force) {
        // Clear the demo content so the seeder can run again.
        $demoMembers = ['minhtoan', 'linhchi', 'hoanganh', 'thanhthuy', 'quangvinh', 'nhatlinh', 'baotran'];

        foreach (['articles_post_tags', 'articles_cmt', 'articles_chap', 'articles_post', 'articles_tags', 'articles_file', 'chat', 'articles_category'] as $table) {
            $db->query(sprintf('DELETE FROM `%s`', $table));
        }

        $placeholders = implode(', ', array_fill(0, count($demoMembers), '?'));

        $db->query(
            sprintf('DELETE FROM `users_waifu` WHERE `user_id` IN (SELECT `id` FROM `users` WHERE `nick` IN (%s))', $placeholders),
            $demoMembers
        );

        $db->query(sprintf('DELETE FROM `users` WHERE `nick` IN (%s)', $placeholders), $demoMembers);
    }

    $now = time();

    $categories = [
        ['Tin tức', 'tin-tuc', 'Tin tức chung về cộng đồng', 'tin tuc, thong bao'],
        ['Hướng dẫn', 'huong-dan', 'Hướng dẫn cài đặt và sử dụng', 'huong dan, cai dat'],
        ['Lập trình', 'lap-trinh', 'Thảo luận về lập trình', 'php, code, lap trinh'],
        ['Góc tâm sực', 'gom-tam-suc', 'Chia sẻ và trò chuyện', 'tam suc, chat'],
    ];

    foreach ($categories as [$name, $slug, $content, $keyword]) {
        $db->query(
            'INSERT INTO `articles_category` (`community`, `name`, `slug`, `content`, `keyword`) VALUES (1, ?, ?, ?, ?)',
            [$name, $slug, $content, $keyword]
        );
    }

    $members = [
        ['minhtoan', 'Minh Toan', 127, '2008-01-01', 860],
        ['linhchi', 'Linh Chi', 121, '2012-05-20', 540],
        ['hoanganh', 'Hoàng Anh', 120, '2015-03-11', 320],
        ['thanhthuy', 'Thanh Thủy', 60, '2018-08-02', 180],
        ['quangvinh', 'Quang Vinh', 30, '2021-01-19', 95],
        ['nhatlinh', 'Nhật Linh', 10, '2024-06-30', 40],
        ['baotran', 'Bảo Trân', 0, '2026-10-01', 3],
    ];

    foreach ($members as [$nick, $name, $level, $registered, $karma]) {
        $db->query(
            'INSERT INTO `users` (`nick`, `name`, `email`, `pass`, `level`, `reg`, `karma`, `xu`, `avatar`, `cover`)
             VALUES (?, ?, ?, ?, ?, ?, ?, 500, ?, ?)',
            [
                $nick,
                $name,
                $nick . '@example.com',
                password_hash('secret123', PASSWORD_BCRYPT),
                $level,
                strtotime($registered),
                $karma,
                '15',
                '8',
            ]
        );

        $db->query('INSERT INTO `users_waifu` (`user_id`) VALUES (?)', [$db->lastInsertId()]);
    }

    $posts = [
        ['Chuyển hệ thống sang SoulCMS: những điều cần biết', 'huong-dan', 'minhtoan', 2, true],
        ['Tối ưu truy vấn với chỉ mục phù hợp', 'lap-trinh', 'linhchi', 3, false],
        ['SQLite thực sự đủ dùng cho diễn đàn cỡ vừa?', 'lap-trinh', 'quangvinh', 3, false],
        ['Cập nhật giao diện mới cho phiên bản 1.0', 'tin-tuc', 'hoanganh', 1, false],
        ['Hướng dẫn viết bài viết hay trên diễn đàn', 'huong-dan', 'thanhthuy', 2, false],
        ['Chia sẻ kinh nghiệm sau 10 năm viết code', 'gom-tam-suc', 'nhatlinh', 4, false],
        ['Có nên dùng Docker cho một diễn đàn nhỏ?', 'lap-trinh', 'baotran', 3, false],
        ['Thông báo: bảo trì hệ thống cuối tuần', 'tin-tuc', 'minhtoan', 1, false],
        ['Về việc xếp hạng và điểm karma', 'gom-tam-suc', 'linhchi', 2, false],
        ['Cách dựng bản sao lưu tự động cho dữ liệu', 'huong-dan', 'quangvinh', 2, false],
    ];

    $tagNames = ['php', 'hiệu năng', 'sqlite', 'cơ sở dữ liệu', 'giao diện', 'hướng dẫn', 'docker', 'bảo mật'];
    $tagIds = [];

    foreach ($tagNames as $tag) {
        $tagIds[$tag] = $db->insert('articles_tags', [
            'name' => $tag,
            'slug' => Str::slug($tag),
            'count' => 0,
        ]);
    }

    $commentCount = 0;

    foreach ($posts as $index => [$title, $categorySlug, $author, $replies, $sticked]) {
        $category = $db->selectOne('SELECT `id` FROM `articles_category` WHERE `slug` = ?', [$categorySlug]);

        $postId = $db->insert('articles_post', [
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => 'Nội dung mẫu của bài viết "' . $title . '". Bài viết này được tạo bởi '
                . '`php bin/soulcms db:seed` để bạn có thể xem giao diện với nội dung thực tế. '
                . 'Bạn có thể sửa, xoá hoặc dùng làm mẫu cho bài của mình.',
            'time' => $now - ($index * 3600 * 7),
            'update_time' => $now - ($index * 3600 * 3),
            'category' => (int) ($category['id'] ?? 1),
            'author' => $author,
            'view' => 120 + ($index * 37),
            'sticked' => $sticked ? 1 : 0,
            'blocked' => 0,
            'buyfile' => 0,
        ]);

        foreach ([$tagNames[$index % count($tagNames)], $tagNames[($index + 3) % count($tagNames)]] as $tag) {
            $db->query('INSERT INTO `articles_post_tags` (`post_id`, `tag_id`) VALUES (?, ?)', [$postId, $tagIds[$tag]]);
            $db->query('UPDATE `articles_tags` SET `count` = `count` + 1 WHERE `id` = ?', [$tagIds[$tag]]);
        }

        for ($reply = 0; $reply < $replies; ++$reply) {
            $db->query(
                'INSERT INTO `articles_cmt` (`blogid`, `author`, `comment`, `time`) VALUES (?, ?, ?, ?)',
                [
                    (string) $postId,
                    $members[($index + $reply) % count($members)][0],
                    'Cảm ơn bài viết, phần giải thích rất rõ ràng. Mình đã áp dụng và thấy hiệu năng cải thiện rõ rệt.',
                    $now - ($index * 3600) + ($reply * 900),
                ]
            );

            ++$commentCount;
        }

        if ($index % 3 === 0) {
            $db->query(
                'INSERT INTO `articles_chap` (`title`, `slug`, `content`, `time`, `box`, `author`) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    'Chương ' . ($index + 1) . ' — Bắt đầu',
                    'chuong-' . ($index + 1),
                    'Nội dung chương đầu tiên của bài viết.',
                    $now - ($index * 3600),
                    $postId,
                    $author,
                ]
            );
        }
    }

    $chat = [
        'Chào mọi người, diễn đàn mới đã lên phiên bản mới.',
        'Có ai vừa nâng cấp server không? Mình thấy nhanh hơn hẳn.',
        'Cảm ơn anh/chị, giao diện đọc dễ chịu hơn nhiều.',
        'Hôm nay có ai rảnh không? Mình cần hỏi về SQLite.',
        'Mình vừa thử chạy 1000 request, vẫn giữ được độ trễ dưới 5ms.',
        'Cảm ơn bạn, mình sẽ thử đăng ký tài khoản mới.',
    ];

    foreach ($chat as $index => $message) {
        $db->query(
            'INSERT INTO `chat` (`name`, `time`, `comment`) VALUES (?, ?, ?)',
            [$members[$index % count($members)][0], $now - ($index * 420), $message]
        );
    }

    return [count($posts), $commentCount, count($members)];
};
