<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nhà cung cấp dịch vụ nhận diện khuôn mặt
    |--------------------------------------------------------------------------
    |
    |  - aws-gateway  : Laravel gọi tới 1 HTTP endpoint (API Gateway + Lambda),
    |                   giống AWS_URL hiện tại anh đang dùng trong Google Sheet.
    |  - rekognition  : (tương lai) dùng trực tiếp AWS Rekognition SDK trong PHP.
    |
    */
    'provider' => env('FACE_PROVIDER', 'aws-gateway'),

    /*
    |--------------------------------------------------------------------------
    | Cấu hình cho provider = "aws-gateway"
    |--------------------------------------------------------------------------
    |
    |  - url       : URL API Gateway (ví dụ: https://xxxxx.execute-api.ap-southeast-1.amazonaws.com/verify)
    |  - timeout   : timeout tối đa (giây) cho 1 request tới gateway
    |  - threshold : ngưỡng điểm khớp (0–100), ví dụ 90
    |
    */
    'aws_gateway' => [
        'url'       => env('FACE_AWS_GATEWAY_URL', ''),   // <- anh copy AWS_URL cũ vào .env
        'timeout'   => (float) env('FACE_AWS_TIMEOUT', 3.0),
        'threshold' => (int) env('FACE_AWS_THRESHOLD', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cấu hình cho provider = "rekognition" (dùng trực tiếp AWS SDK)
    |--------------------------------------------------------------------------
    |
    | Chưa dùng ngay, nhưng config sẵn để sau này nâng cấp nếu anh muốn bỏ lambda.
    |
    */
    'rekognition' => [
        'region'         => env('AWS_REKOGNITION_REGION', 'ap-southeast-1'),
        'collection_id'  => env('AWS_REKOGNITION_COLLECTION', 'phg-employees'),
        'threshold'      => (int) env('AWS_REKOGNITION_THRESHOLD', 90),
        'max_faces'      => (int) env('AWS_REKOGNITION_MAX_FACES', 1),
        'timeout'        => (float) env('AWS_REKOGNITION_TIMEOUT', 3.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hạn chế & tối ưu ảnh selfie
    |--------------------------------------------------------------------------
    |
    |  - max_bytes           : kích thước ảnh tối đa cho 1 lần gửi (byte). 500 KB–1 MB là hợp lý.
    |  - max_width, max_height : (tương lai) nếu muốn resize server-side.
    |
    */
    'image' => [
        'max_bytes'  => (int) env('FACE_IMAGE_MAX_BYTES', 800 * 1024), // 800KB
        'max_width'  => (int) env('FACE_IMAGE_MAX_WIDTH', 1280),
        'max_height' => (int) env('FACE_IMAGE_MAX_HEIGHT', 1280),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-spam / Anti-reuse ảnh trong khoảng thời gian ngắn
    |--------------------------------------------------------------------------
    |
    |  - reuse_window_seconds : không cho dùng lại cùng một ảnh trong X giây
    |                           (ví dụ 300s = 5 phút).
    |
    */
    'security' => [
        'reuse_window_seconds' => (int) env('FACE_REUSE_WINDOW_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging & debug
    |--------------------------------------------------------------------------
    |
    |  - log_success : có log cả case OK không (thường chỉ cần log FAIL).
    |  - log_channel : channel log Laravel (stack, daily, v.v.)
    |
    */
    'log' => [
        'log_success' => (bool) env('FACE_LOG_SUCCESS', false),
        'log_fail'    => (bool) env('FACE_LOG_FAIL', true),
        'channel'     => env('FACE_LOG_CHANNEL', 'stack'),
    ],
];
