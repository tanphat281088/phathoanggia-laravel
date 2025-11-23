<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng header chi phí cho từng báo giá.
     *
     * - Mỗi record = 1 bảng chi phí của 1 báo giá (don_hang_id) và 1 loại (type).
     * - type:
     *      1 = Chi phí đề xuất
     *      2 = Chi phí thực tế
     *
     * - Không đụng tới bảng don_hangs, chỉ liên kết qua FK.
     */
    public function up(): void
    {
        if (! Schema::hasTable('quote_costs')) {
            Schema::create('quote_costs', function (Blueprint $table) {
                $table->id();

                // Báo giá gốc
                $table->foreignId('don_hang_id')
                    ->constrained('don_hangs')
                    ->onDelete('cascade')
                    ->comment('FK -> don_hangs.id (báo giá gốc)');

                // Loại bảng chi phí: 1 = Đề xuất, 2 = Thực tế
                $table->unsignedTinyInteger('type')
                    ->comment('1 = Chi phí đề xuất, 2 = Chi phí thực tế');

                // Mã bảng chi phí (nếu muốn đánh số riêng, có thể để trống)
                $table->string('code', 50)
                    ->nullable()
                    ->comment('Mã bảng chi phí (VD: CPDX..., CPTT...), có thể để trống');

                // Trạng thái xử lý của bảng chi phí
                $table->unsignedTinyInteger('status')
                    ->default(0)
                    ->comment('0 = Nháp, 1 = Đang chỉnh, 2 = Khoá / Đã chốt');

                // Tổng doanh thu (snapshot từ báo giá)
                $table->integer('total_revenue')
                    ->default(0)
                    ->comment('Tổng doanh thu (VND) tại thời điểm tạo bảng chi phí');

                // Tổng chi phí (sum các dòng chi phí)
                $table->integer('total_cost')
                    ->default(0)
                    ->comment('Tổng chi phí (VND)');

                // Lợi nhuận tuyệt đối (revenue - cost)
                $table->integer('total_margin')
                    ->default(0)
                    ->comment('Lợi nhuận = total_revenue - total_cost (VND)');

                // % lợi nhuận trên doanh thu (có thể null nếu không tính được)
                $table->decimal('margin_percent', 5, 2)
                    ->nullable()
                    ->comment('Tỷ lệ lợi nhuận trên doanh thu (%)');

                // Ghi chú nội bộ cho bảng chi phí
                $table->text('note')
                    ->nullable()
                    ->comment('Ghi chú nội bộ cho bảng chi phí');

                // Theo style hệ thống hiện tại
                $table->string('nguoi_tao')->nullable();
                $table->string('nguoi_cap_nhat')->nullable();
                $table->timestamps();

                // 1 báo giá chỉ có 1 bảng Đề xuất & 1 bảng Thực tế
                $table->unique(['don_hang_id', 'type'], 'quote_costs_don_hang_type_unique');

                // Index hỗ trợ tra cứu
                $table->index('don_hang_id', 'quote_costs_don_hang_id_index');
                $table->index('type', 'quote_costs_type_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_costs');
    }
};
