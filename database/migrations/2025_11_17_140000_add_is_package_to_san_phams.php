<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột is_package vào bảng san_phams
     * - 0 = dịch vụ / thiết bị đơn lẻ
     * - 1 = gói dịch vụ (có chi tiết món / sản phẩm bên trong)
     */
    public function up(): void
    {
        Schema::table('san_phams', function (Blueprint $table) {
            if (!Schema::hasColumn('san_phams', 'is_package')) {
                $table->boolean('is_package')
                    ->default(0)
                    ->after('loai_san_pham')
                    ->comment('0 = dịch vụ/thiết bị đơn; 1 = gói dịch vụ');
            }
        });
    }

    /**
     * Rollback
     */
    public function down(): void
    {
        Schema::table('san_phams', function (Blueprint $table) {
            if (Schema::hasColumn('san_phams', 'is_package')) {
                $table->dropColumn('is_package');
            }
        });
    }
};
