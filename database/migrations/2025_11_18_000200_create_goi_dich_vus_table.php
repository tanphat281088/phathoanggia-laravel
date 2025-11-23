<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tầng 3: GÓI DỊCH VỤ CỤ THỂ
 *
 * - Thuộc về 1 NHÓM GÓI DỊCH VỤ (goi_dich_vu_categories)
 * - Ví dụ:
 *    + Nhóm: "Gói âm thanh tiệc cưới"
 *       -> "Gói âm thanh 100 khách - Basic"
 *       -> "Gói âm thanh 100 khách - Premium"
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('goi_dich_vus')) {
            Schema::create('goi_dich_vus', function (Blueprint $table) {
                $table->id();

                // FK tới bảng nhóm gói dịch vụ (tầng 2)
                $table->foreignId('category_id')
                    ->constrained('goi_dich_vu_categories')
                    ->onDelete('cascade')
                    ->comment('Thuộc nhóm gói dịch vụ (tầng 2)');

                // Mã gói (code nội bộ / dùng cho tìm kiếm, in ấn)
                $table->string('ma_goi', 100)
                    ->nullable()
                    ->unique()
                    ->comment('Mã gói dịch vụ, ví dụ: GDV_AMTHANH_100K_BASIC');

                // Tên gói hiển thị
                $table->string('ten_goi', 255)
                    ->comment('Tên gói dịch vụ, ví dụ: Gói âm thanh 100 khách - Basic');

                // Mô tả ngắn hiển thị chung (optional)
                $table->string('mo_ta_ngan', 500)
                    ->nullable()
                    ->comment('Mô tả ngắn về gói (hiển thị trên UI / báo giá)');

                // Mô tả chi tiết hơn (có thể dùng cho landing page / ghi chú)
                $table->text('mo_ta_chi_tiet')
                    ->nullable();

                // Giá niêm yết của gói (VND)
                $table->integer('gia_niem_yet')
                    ->default(0)
                    ->comment('Giá gói dịch vụ (VND)');

                // Giá khuyến mại (nếu có) - có thể để trống
                $table->integer('gia_khuyen_mai')
                    ->nullable()
                    ->comment('Giá khuyến mại (nếu có), VND');

                // Trạng thái gói: 1=Hoạt động, 0=Ngưng
                $table->boolean('trang_thai')
                    ->default(1)
                    ->comment('1 = Hoạt động, 0 = Ngưng sử dụng');

                // Theo style hệ thống hiện tại
                $table->string('nguoi_tao')->nullable();
                $table->string('nguoi_cap_nhat')->nullable();

                $table->timestamps();

                $table->index('category_id', 'gdv_pkg_cat_idx');
                $table->index('trang_thai', 'gdv_pkg_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goi_dich_vus');
    }
};
