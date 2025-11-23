<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BẢNG CHI TIẾT GÓI DỊCH VỤ
 *
 * - Nối GÓI DỊCH VỤ (goi_dich_vus) với CHI TIẾT DỊCH VỤ (san_phams)
 * - Mỗi dòng = 1 chi tiết dịch vụ nằm trong 1 gói
 *
 * Ví dụ:
 *   Gói âm thanh 100 khách - Basic (goi_dich_vu_id = 1)
 *      -> Loa EV 50       (san_pham_id = 10, so_luong = 2)
 *      -> Mixer MG16XU    (san_pham_id = 11, so_luong = 1)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('goi_dich_vu_items')) {
            Schema::create('goi_dich_vu_items', function (Blueprint $table) {
                $table->id();

                // Gói dịch vụ cha
                $table->foreignId('goi_dich_vu_id')
                    ->constrained('goi_dich_vus')
                    ->onDelete('cascade')
                    ->comment('Gói dịch vụ (tầng 3)');

                // Chi tiết dịch vụ / sản phẩm con (từ module Sản phẩm)
                $table->foreignId('san_pham_id')
                    ->constrained('san_phams')
                    ->onDelete('cascade')
                    ->comment('Chi tiết dịch vụ / sản phẩm nằm trong gói');

                // Số lượng cấu hình trong gói
                $table->decimal('so_luong', 10, 2)
                    ->default(0)
                    ->comment('Số lượng cấu hình trong gói');

                // Giá tham chiếu tại thời điểm cấu hình gói (VND)
                $table->integer('don_gia')
                    ->default(0)
                    ->comment('Giá 1 đơn vị (VND) tại thời điểm cấu hình gói');

                // Thành tiền = don_gia * so_luong (để tham khảo / tính cost)
                $table->integer('thanh_tien')
                    ->default(0)
                    ->comment('Thành tiền = don_gia * so_luong (VND)');

                // Ghi chú thêm cho dòng này (nếu cần)
                $table->string('ghi_chu', 500)
                    ->nullable();

                // Thứ tự hiển thị trong gói (nếu muốn sắp xếp đẹp)
                $table->unsignedInteger('thu_tu')
                    ->default(0)
                    ->comment('Thứ tự hiển thị trong gói');

                // Theo style hệ thống hiện tại
                $table->string('nguoi_tao')->nullable();
                $table->string('nguoi_cap_nhat')->nullable();

                $table->timestamps();

                $table->index('goi_dich_vu_id', 'gdv_items_pkg_idx');
                $table->index('san_pham_id', 'gdv_items_sp_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goi_dich_vu_items');
    }
};
