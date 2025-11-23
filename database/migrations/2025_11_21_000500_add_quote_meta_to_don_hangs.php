<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm metadata phục vụ tuỳ biến báo giá vào bảng don_hangs:
     *  - quote_section_titles: JSON map section_code => tên Hạng mục hiển thị
     *      VD: { "NS": "Nhân sự sự kiện 1", "CSVC": "Hệ thống âm thanh 1A" }
     *  - quote_footer_note   : Ghi chú / phần đuôi báo giá (theo từng đơn)
     */
    public function up(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            // JSON lưu tiêu đề Hạng mục cho từng nhóm NS/CSVC/TIEC/TD/CPK
            if (!Schema::hasColumn('don_hangs', 'quote_section_titles')) {
                $table->json('quote_section_titles')
                    ->nullable()
                    ->after('ghi_chu')
                    ->comment('Map section_code => tiêu đề Hạng mục cho báo giá (tuỳ biến theo đơn)');
            }

            // Ghi chú / phần đuôi báo giá (theo từng đơn)
            if (!Schema::hasColumn('don_hangs', 'quote_footer_note')) {
                $table->text('quote_footer_note')
                    ->nullable()
                    ->after('quote_section_titles')
                    ->comment('Ghi chú / phần đuôi báo giá (tuỳ biến theo đơn)');
            }
        });
    }

    /**
     * Rollback: xoá các cột vừa thêm.
     */
    public function down(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            if (Schema::hasColumn('don_hangs', 'quote_section_titles')) {
                $table->dropColumn('quote_section_titles');
            }
            if (Schema::hasColumn('don_hangs', 'quote_footer_note')) {
                $table->dropColumn('quote_footer_note');
            }
        });
    }
};
