<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tầng 2: NHÓM GÓI DỊCH VỤ
 *
 * - Thuộc về 1 NHÓM DANH MỤC GÓI DỊCH VỤ (goi_dich_vu_groups)
 * - Ví dụ:
 *    + Nhóm "Gói sự kiện âm thanh" (group)
 *       -> "Gói âm thanh tiệc cưới" (category)
 *       -> "Gói âm thanh hội nghị" (category)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('goi_dich_vu_categories')) {
            Schema::create('goi_dich_vu_categories', function (Blueprint $table) {
                $table->id();

                // FK tới bảng nhóm tầng 1
                $table->foreignId('group_id')
                    ->constrained('goi_dich_vu_groups')
                    ->onDelete('cascade')
                    ->comment('Thuộc nhóm danh mục gói dịch vụ (tầng 1)');

                // Mã nhóm gói (có thể dùng để code/tra cứu)
                $table->string('ma_nhom_goi', 100)
                    ->nullable()
                    ->unique()
                    ->comment('Mã nhóm gói dịch vụ, ví dụ: AM_THANH_TIEC_CUOI');

                // Tên nhóm gói hiển thị
                $table->string('ten_nhom_goi', 255)
                    ->comment('Tên nhóm gói dịch vụ, ví dụ: Gói âm thanh tiệc cưới');

                // Ghi chú thêm nếu cần
                $table->string('ghi_chu', 500)
                    ->nullable();

                // Trạng thái: 1=Hoạt động, 0=Ngưng
                $table->boolean('trang_thai')
                    ->default(1)
                    ->comment('1 = Hoạt động, 0 = Ngưng sử dụng');

                // Theo style hệ thống hiện tại
                $table->string('nguoi_tao')->nullable();
                $table->string('nguoi_cap_nhat')->nullable();

                $table->timestamps();

                $table->index('group_id', 'gdv_cat_group_idx');
                $table->index('trang_thai', 'gdv_cat_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goi_dich_vu_categories');
    }
};
