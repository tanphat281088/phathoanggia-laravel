<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('goi_dich_vus', function (Blueprint $table) {
            // 🔹 0 = Trọn gói (bundle – 1 dòng), 1 = Thành phần (nổ từng dịch vụ)
            if (!Schema::hasColumn('goi_dich_vus', 'package_mode')) {
                $table->unsignedTinyInteger('package_mode')
                    ->default(0)
                    ->after('gia_khuyen_mai')
                    ->comment('0 = Trọn gói, 1 = Thành phần (nổ từng dịch vụ)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goi_dich_vus', function (Blueprint $table) {
            if (Schema::hasColumn('goi_dich_vus', 'package_mode')) {
                $table->dropColumn('package_mode');
            }
        });
    }
};
