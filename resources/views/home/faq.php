<?php

/**
 * @var string $faq_page
 */

$pages = [
    'terms' => [
        'title' => 'Nội quy & Điều khoản sử dụng',
        'intro' => 'Một vài nguyên tắc để diễn đàn luôn dễ đọc và hữu ích.',
        'items' => [
            ['Tôn trọng người khác', 'Không công kích, phân biệt đối tượng hay spam. Tranh luận với nội dung, không với con người.'],
            ['Đề tài thuộc đúng chuyên mục', 'Đặt bài vào chuyên mục phù hợp để người đọc tìm thấy dễ hơn.'],
            ['Nội dung phải hữu ích', 'Bài viết cần có thông tin hoặc kinh nghiệm để người khác học được điều gì đó.'],
            ['Bảo vệ thông tin cá nhân', 'Không đăng lại thông tin liên hệ hoặc dữ liệu cá nhân của người khác.'],
        ],
    ],
    'about' => [
        'title' => 'Về diễn đàn',
        'intro' => 'SoulCMS là một hệ quản trị nội dung mã nguồn mở, chạy trên SQLite, MySQL, MariaDB hoặc PostgreSQL.',
        'items' => [
            ['Cùng một cơ sở dữ liệu', 'Hệ thống dùng chung lược đồ với SketchCMS, nên bạn có thể chuyển sang mà không phải viết lại dữ liệu.'],
            ['Nhanh và nhẹ', 'Không phụ thuộc framework nặng: router, view engine và tầng dữ liệu đều do SoulCMS tự viết.'],
            ['Mở cho mọi người', 'Bạn có thể tự chạy, tự chủ dữ liệu, tuỳ biến giao diện theo ý mình.'],
        ],
    ],
    'help' => [
        'title' => 'Trung tâm trợ giúp',
        'intro' => 'Những thao tác thường gặp khi dùng diễn đàn.',
        'items' => [
            ['Viết bài mới', 'Nhấn "Viết bài mới" ở trang chủ, chọn chuyên mục, viết tiêu đề và nội dung rồi đăng.'],
            ['Gắn thẻ', 'Ngăn cách các thẻ bằng dấu phẩy. Thẻ giúp người đọc tìm bài cũ hơn.'],
            ['Trả lời bình luận', 'Cuộn xuống cuối bài viết, viết bình luận và nhấn "Gửi bình luận".'],
            ['Đổi mật khẩu quên', 'Dùng liên kết "Quên mật khẩu?" ở trang đăng nhập để nhận hướng dẫn đặt lại.'],
        ],
    ],
];

$page = $pages[$faq_page] ?? $pages['terms'];

?>
<section class="panel">
    <div class="panel__head">
        <?= icon('file', '', 16) ?>
        <h1 class="panel__title"><?= e($page['title']) ?></h1>
    </div>

    <div class="panel__body">
        <p class="muted" style="max-width:64ch"><?= e($page['intro']) ?></p>

        <div class="stack mt-5">
            <?php foreach ($page['items'] as [$heading, $text]): ?>
                <div>
                    <h3 style="font-size:var(--step-1);margin-bottom:var(--s-1)"><?= e($heading) ?></h3>
                    <p class="muted" style="max-width:64ch;margin:0"><?= e($text) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
