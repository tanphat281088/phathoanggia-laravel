<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tầng 1: NHÓM DANH MỤC GÓI DỊCH VỤ
 *
 * Ví dụ:
 *  - "Gói sự kiện âm thanh"
 *  - "Gói sự kiện ánh sáng"
 *  - "Gói trọn gói khai trương"
 *
 * Tầng 2 (goi_dich_vu_categories) sẽ trỏ foreign key vào bảng này.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('goi_dich_vu_groups')) {
            Schema::create('goi_dich_vu_groups', function (Blueprint $table) {
                $table->id();

                // Mã nhóm (có thể dùng để code trong hệ thống, vd: GDV_AM_THANH)
                $table->string('ma_nhom', 100)
                    ->nullable()
                    ->unique()
                    ->comment('Mã nhóm danh mục gói dịch vụ (có thể để trống, hệ thống tự sinh sau)');

                // Tên nhóm hiển thị
                $table->string('ten_nhom', 255)
                    ->comment('Tên nhóm danh mục gói dịch vụ, ví dụ: Gói sự kiện âm thanh');

                // Ghi chú thêm nếu cần
                $table->string('ghi_chu', 500)
                    ->nullable();

                // Trạng thái: 1=Hoạt động, 0=Ngưng sử dụng
                $table->boolean('trang_thai')
                    ->default(1)
                    ->comment('1 = Hoạt động, 0 = Ngưng sử dụng');

                // Theo style hệ thống hiện tại
                $table->string('nguoi_tao')->nullable();
                $table->string('nguoi_cap_nhat')->nullable();

                $table->timestamps();

                $table->index('trang_thai', 'gdv_groups_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goi_dich_vu_groups');
    }
};
