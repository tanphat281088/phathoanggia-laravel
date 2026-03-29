<?php

use Illuminate\Support\Facades\Route;
use App\Modules\QuanLyBanHang\QuanLyBanHangController;
use App\Http\Controllers\ThuChi\AutoThuController; // <- chỉ import 1 lần

Route::get('/', function () {
    return view('welcome');
});

// === Xem trước hóa đơn (WEB ROUTE, trả về Blade view) ===
// URL: http://127.0.0.1:8000/quan-ly-ban-hang/xem-truoc-hoa-don/{id}
Route::prefix('quan-ly-ban-hang')->group(function () {
    Route::get('xem-truoc-hoa-don/{id}', [QuanLyBanHangController::class, 'xemTruocHoaDon'])
        ->name('quan-ly-ban-hang.xem-truoc-hoa-don');
});

// Re-sync phiếu thu theo ID số
Route::get('/admin/thu-chi/re-sync/{donHangId}', [AutoThuController::class, 'reSync'])
     ->name('thu-chi.reSync');

// Re-sync phiếu thu theo mã đơn (ma_don_hang), ví dụ: DH-20251019-160623
Route::get('/admin/thu-chi/re-sync-by-code/{maDonHang}', [AutoThuController::class, 'reSyncByCode'])
     ->name('thu-chi.reSyncByCode');


// ===== PUBLIC WEB: file selfie chấm công (raw bytes) =====
Route::get('/attendance-files/{path}', function (string $path) {
    $path = ltrim($path, '/');

    if (!str_starts_with($path, 'attendance/')) {
        abort(404);
    }

    $full = storage_path('app/public/' . $path);

    if (!is_file($full)) {
        abort(404);
    }

    $bin = @file_get_contents($full);
    if ($bin === false || $bin === '') {
        abort(404);
    }

    $mime = @mime_content_type($full) ?: 'image/jpeg';

    return response($bin, 200, [
        'Content-Type' => $mime,
        'Content-Length' => (string) strlen($bin),
        'Cache-Control' => 'public, max-age=604800',
        'X-Content-Type-Options' => 'nosniff',
        'Access-Control-Allow-Origin' => '*',
    ]);
})->where('path', '.*')->name('attendance.files.web');
