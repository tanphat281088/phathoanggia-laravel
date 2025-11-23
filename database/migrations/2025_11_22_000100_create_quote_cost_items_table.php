<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng chi tiết chi phí cho từng báo giá.
     *
     * - Mỗi dòng = 1 hạng mục/chi tiết trong bảng chi phí (Đề xuất hoặc Thực tế).
     * - Liên kết:
     *      + quote_cost_id         -> quote_costs.id
     *      + chi_tiet_don_hang_id  -> chi_tiet_don_hangs.id (nếu có)
     */
    public function up(): void
    {
        if (! Schema::hasTable('quote_cost_items')) {
            Schema::create('quote_cost_items', function (Blueprint $table) {
                $table->id();

                // Header chi phí
                $table->foreignId('quote_cost_id')
                    ->constrained('quote_costs')
                    ->onDelete('cascade')
                    ->comment('FK -> quote_costs.id (bảng chi phí Đề xuất/Thực tế)');

                // Link ngược về dòng báo giá (optional)
                $table->unsignedBigInteger('chi_tiet_don_hang_id')
                    ->nullable()
                    ->comment('FK -> chi_tiet_don_hangs.id (nếu dòng chi phí gắn với 1 dòng báo giá)');

                // Nhóm / Hạng mục
                $table->string('hang_muc_goc', 255)
                    ->nullable()
                    ->comment('Hạng mục gốc: Âm thanh, Ánh sáng, Nhân sự...');

                $table->string('section_code', 10)
                    ->nullable()
                    ->comment('Nhóm section: NS / CSVC / TIEC / TD / CPK... (nếu map với báo giá)');

                $table->unsignedInteger('line_no')
                    ->default(0)
                    ->comment('STT hiển thị trong bảng chi phí');

                // Thông tin hiển thị
                $table->text('description')
                    ->nullable()
                    ->comment('Chi tiết hạng mục / thiết bị / dịch vụ');

                $table->string('dvt', 50)
                    ->nullable()
                    ->comment('Đơn vị tính hiển thị');

                $table->decimal('qty', 10, 2)
                    ->default(0)
                    ->comment('Số lượng');

                // Nhà cung cấp
                $table->unsignedBigInteger('supplier_id')
                    ->nullable()
                    ->comment('FK -> nha_cung_caps.id (nhà cung cấp nếu chọn từ master)');

                $table->string('supplier_name', 255)
                    ->nullable()
                    ->comment('Tên nhà cung cấp (snapshot hiển thị)');

                // GIÁ CHI PHÍ (SUP)
                $table->integer('cost_unit_price')
                    ->default(0)
                    ->comment('Đơn giá chi phí (SUP), VND');

                $table->integer('cost_total_amount')
                    ->default(0)
                    ->comment('Thành tiền chi phí = qty * cost_unit_price, VND');

                // GIÁ BÁN (DOANH THU) - snapshot từ báo giá
                $table->integer('sell_unit_price')
                    ->default(0)
                    ->comment('Đơn giá bán cho khách (snapshot), VND');

                $table->integer('sell_total_amount')
                    ->default(0)
                    ->comment('Thành tiền bán = qty * sell_unit_price, VND');

                // Ghi chú thêm cho dòng này
                $table->string('note', 500)
                    ->nullable()
                    ->comment('Ghi chú nội bộ cho dòng chi phí');

                // Theo style hệ thống hiện tại
                $table->string('nguoi_tao')->nullable();
                $table->string('nguoi_cap_nhat')->nullable();
                $table->timestamps();

                // Index hỗ trợ tra cứu
                $table->index('quote_cost_id', 'quote_cost_items_quote_cost_id_index');
                $table->index('chi_tiet_don_hang_id', 'quote_cost_items_chi_tiet_don_hang_id_index');
                $table->index('section_code', 'quote_cost_items_section_code_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_cost_items');
    }
};
