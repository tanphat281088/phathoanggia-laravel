<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nếu bảng users không tồn tại thì bỏ qua
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Chỉ thêm cột nếu chưa có
            if (!Schema::hasColumn('users', 'ma_nv')) {
                // Mã nhân viên: QL001, NV001...
                $table->string('ma_nv', 20)
                    ->nullable()
                    ->after('status'); // hoặc after('is_ngoai_gio') cũng được
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ma_nv')) {
                $table->dropColumn('ma_nv');
            }
        });
    }
};
