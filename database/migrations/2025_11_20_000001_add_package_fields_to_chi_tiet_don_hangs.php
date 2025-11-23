<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm các cột phục vụ gói dịch vụ vào bảng chi_tiet_don_hangs:
     * - is_package    : đánh dấu dòng này là gói (true) hay dịch vụ lẻ (false)
     * - ten_hien_thi  : tên hiển thị cho khách (vd: "Gói âm thanh sự kiện 1A dưới 50 khách")
     * - package_items : JSON mô tả chi tiết gói (danh sách thiết bị/dịch vụ con)
     */
    public function up(): void
    {
        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            // boolean, default = false (0)
            $table->boolean('is_package')
                ->default(false)
                ->after('don_gia');

            // tên hiển thị cho dòng báo giá (có thể null)
            $table->string('ten_hien_thi', 255)
                ->nullable()
                ->after('is_package');

            // JSON lưu danh sách thiết bị/dịch vụ con trong gói (có thể null)
            $table->json('package_items')
                ->nullable()
                ->after('ten_hien_thi');
        });
    }

    /**
     * Rollback: xoá các cột vừa thêm.
     */
    public function down(): void
    {
        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            // Nếu muốn an toàn hơn có thể check hasColumn, nhưng ở đây đơn giản xoá thẳng
            $table->dropColumn(['is_package', 'ten_hien_thi', 'package_items']);
        });
    }
};
