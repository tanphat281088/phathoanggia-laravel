<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            if (!Schema::hasColumn('chi_tiet_don_hangs', 'hang_muc_goc')) {
                $table->string('hang_muc_goc', 255)
                    ->nullable()
                    ->after('ten_hien_thi')
                    ->comment('Tên Hạng mục gốc (Nhóm gói dịch vụ) dùng cho cột HẠNG MỤC trên báo giá');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            if (Schema::hasColumn('chi_tiet_don_hangs', 'hang_muc_goc')) {
                $table->dropColumn('hang_muc_goc');
            }
        });
    }
};
