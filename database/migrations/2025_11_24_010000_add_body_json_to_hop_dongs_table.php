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
        Schema::table('hop_dongs', function (Blueprint $table) {
            // Thêm cột JSON lưu toàn bộ nội dung HĐ (Level 2)
            if (! Schema::hasColumn('hop_dongs', 'body_json')) {
                $table->json('body_json')
                    ->nullable()
                    ->after('dieukhoan_tuy_chinh')
                    ->comment('Toàn bộ nội dung hợp đồng (Level 2) dạng JSON các block');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hop_dongs', function (Blueprint $table) {
            if (Schema::hasColumn('hop_dongs', 'body_json')) {
                $table->dropColumn('body_json');
            }
        });
    }
};
