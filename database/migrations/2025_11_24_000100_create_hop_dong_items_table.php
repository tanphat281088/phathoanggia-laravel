<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng CHI TIẾT HỢP ĐỒNG
 *
 * - Mỗi dòng = 1 hạng mục / dịch vụ trong Hợp đồng.
 * - Được convert 1–1 từ chi_tiet_don_hangs (báo giá) thông qua QuoteBuilder.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('hop_dong_items')) {
            Schema::create('hop_dong_items', function (Blueprint $table) {
                $table->id();

                // Header Hợp đồng
                $table->foreignId('hop_dong_id')
                    ->constrained('hop_dongs')
                    ->onDelete('cascade')
                    ->comment('FK -> hop_dongs.id');

                // Link ngược về dòng Báo giá (nếu còn tồn tại)
                $table->foreignId('chi_tiet_don_hang_id')
                    ->nullable()
                    ->constrained('chi_tiet_don_hangs')
                    ->nullOnDelete()
                    ->comment('FK -> chi_tiet_don_hangs.id (có thể null nếu bị xoá)');

                // Nhóm section: NS / CSVC / TIEC / TD / CPK / OTHER
                $table->string('section_code', 10)
                    ->nullable()
                    ->comment('Mã nhóm hạng mục: NS / CSVC / TIEC / TD / CPK / OTHER');
                $table->string('section_letter', 5)
                    ->nullable()
                    ->comment('Ký tự section: A / B / C... (snapshot để in)');

                // Hạng mục & chi tiết
                $table->string('hang_muc', 255)
                    ->nullable()
                    ->comment('Tên Hạng mục hiển thị trong HĐ');
                $table->string('hang_muc_goc', 255)
                    ->nullable()
                    ->comment('Hạng mục gốc (nếu cần map ngược)');
                $table->text('chi_tiet')
                    ->nullable()
                    ->comment('Chi tiết dịch vụ / hạng mục (có thể nhiều dòng)');
                $table->string('dvt', 100)
                    ->nullable()
                    ->comment('Đơn vị tính');

                // Số lượng
                $table->decimal('so_luong', 10, 2)
                    ->default(0)
                    ->comment('Số lượng');

                // Đơn giá & thành tiền (VND)
                $table->integer('don_gia')
                    ->default(0)
                    ->comment('Đơn giá bán (VND)');
                $table->integer('thanh_tien')
                    ->default(0)
                    ->comment('Thành tiền = so_luong * don_gia (VND)');

                // Dòng này là gói hay dịch vụ lẻ (snapshot)
                $table->boolean('is_package')
                    ->default(false)
                    ->comment('true = dòng gói dịch vụ; false = dịch vụ lẻ');

                // Theo style hệ thống hiện tại
                $table->string('nguoi_tao')->nullable();
                $table->string('nguoi_cap_nhat')->nullable();

                $table->timestamps();

                // Index hỗ trợ tra cứu
                $table->index('hop_dong_id', 'hop_dong_items_hd_idx');
                $table->index('chi_tiet_don_hang_id', 'hop_dong_items_ctdh_idx');
                $table->index('section_code', 'hop_dong_items_section_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hop_dong_items');
    }
};
