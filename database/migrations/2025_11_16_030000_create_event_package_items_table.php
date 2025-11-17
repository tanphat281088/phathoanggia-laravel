<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng event_package_items
     *
     * - Mỗi dòng = 1 thiết bị / dịch vụ con nằm trong 1 GÓI DỊCH VỤ (san_phams.loai_san_pham = GOI_DICH_VU)
     * - Ví dụ:
     *    + Gói Âm thanh 1A (san_pham_id = 10)
     *      -> Loa EV        (item_id = 101, item_type = 'SAN_PHAM', so_luong = 2)
     *      -> Mixer Midas   (item_id = 102, item_type = 'SAN_PHAM', so_luong = 1)
     */
    public function up(): void
    {
        Schema::create('event_package_items', function (Blueprint $table) {
            $table->id();

            // GÓI DỊCH VỤ cha
            $table->foreignId('san_pham_id')
                ->constrained('san_phams')
                ->onDelete('cascade')
                ->comment('Gói dịch vụ (san_phams.id, loai_san_pham = GOI_DICH_VU)');

            // Item con bên trong gói:
            //  - Có thể là SAN_PHAM (thiết bị/dv lẻ) hoặc VT_ITEM... tuỳ mình map sau
            $table->unsignedBigInteger('item_id')
                ->comment('ID của thiết bị / dịch vụ con (tuỳ theo item_type)');
            $table->string('item_type', 50)
                ->nullable()
                ->comment('Loại item: SAN_PHAM, VT_ITEM, NHAN_SU, MON_AN,...');

            // Số lượng + đơn vị tính trong GÓI
            $table->decimal('so_luong', 10, 2)
                ->default(0)
                ->comment('Số lượng cấu hình trong gói');
            $table->string('don_vi_tinh', 50)
                ->nullable()
                ->comment('Đơn vị tính hiển thị (VD: bộ, cái, suất...)');

            // Giá tham chiếu tại thời điểm cấu hình gói (không bắt buộc phải khớp tồn kho)
            $table->integer('don_gia')
                ->default(0)
                ->comment('Giá 1 đơn vị (VND) tại thời điểm cấu hình gói');
            $table->integer('thanh_tien')
                ->default(0)
                ->comment('Thành tiền = don_gia * so_luong (để tính cost / tham khảo)');

            $table->string('ghi_chu')
                ->nullable();

            // Theo style hiện tại của hệ thống: nguoi_tao / nguoi_cap_nhat dạng string
            $table->string('nguoi_tao')->nullable();
            $table->string('nguoi_cap_nhat')->nullable();

            $table->timestamps();

            // Index hỗ trợ query nhanh theo gói & item
            $table->index('san_pham_id', 'epi_san_pham_idx');
            $table->index(['san_pham_id', 'item_id'], 'epi_sanpham_item_idx');
        });
    }

    /**
     * Rollback
     */
    public function down(): void
    {
        Schema::dropIfExists('event_package_items');
    }
};
