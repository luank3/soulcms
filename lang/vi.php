<?php

declare(strict_types=1);

/**
 * Message catalogue.
 */

return [
    'validation.required' => ':field không được để trống.',
    'validation.string' => ':field phải là văn bản.',
    'validation.int' => ':field phải là số nguyên.',
    'validation.numeric' => ':field phải là số.',
    'validation.boolean' => ':field phải là đúng hoặc sai.',
    'validation.email' => ':field phải là địa chỉ e-mail hợp lệ.',
    'validation.url' => ':field phải là một đường dẫn hợp lệ.',
    'validation.min' => ':field phải có ít nhất :min ký tự.',
    'validation.max' => ':field không được dài quá :max ký tự.',
    'validation.regex' => ':field không đúng định dạng.',
    'validation.in' => ':field phải là một trong: :allowed.',
    'validation.confirmed' => ':field không khớp.',
    'validation.same' => ':field không khớp.',
    'validation.different' => ':field phải khác với :other.',
    'validation.slug' => ':field chỉ được chứa chữ thường, số và dấu gạch ngang.',
    'validation.array' => ':field phải là một danh sách.',
    'auth.failed' => 'Thông tin đăng nhập không đúng.',
    'auth.locked' => 'Tài khoản đang bị tạm khoá do đăng nhập sai nhiều lần. Vui lòng thử lại sau.',
    'auth.rate_limited' => 'Bạn đang thao tác quá nhanh. Vui lòng chờ :seconds giây rồi thử lại.',
    'auth.csrf' => 'Phiên làm việc đã thay đổi. Vui lòng tải lại trang và thử lại.',
    'error.404.title' => 'Không tìm thấy trang',
    'error.404.body' => 'Đường dẫn này không tồn tại hoặc nội dung đã được chuyển sang nơi khác.',
    'error.500.title' => 'Có lỗi xảy ra',
    'error.500.body' => 'Máy chủ gặp sự cố khi xử lý yêu cầu. Vui lòng thử lại sau.',
    'error.403.title' => 'Không có quyền truy cập',
    'error.403.body' => 'Tài khoản của bạn không đủ quyền để xem trang này.',
];
