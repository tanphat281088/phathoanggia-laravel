<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hop_dongs', function (Blueprint $table) {
            // Xưng hô Bên A: "Ông", "Bà"...
            $table->string('ben_a_xung_ho', 20)
                ->nullable()
                ->after('ben_a_dai_dien');

            // Xưng hô Bên B: "Ông", "Bà"... (mặc định sẽ là "Ông" ở service)
            $table->string('ben_b_xung_ho', 20)
                ->nullable()
                ->after('ben_b_dai_dien');

            // LƯU Ý: không tạo unique index cho so_hop_dong ở đây
            // để tránh lỗi migrate nếu dữ liệu cũ đang bị trùng.
            // Nếu sau này anh muốn siết unique thì tạo migration riêng,
            // sau khi đã dọn dữ liệu trùng.
        });
    }

    public function down(): void
    {
        Schema::table('hop_dongs', function (Blueprint $table) {
            $table->dropColumn(['ben_a_xung_ho', 'ben_b_xung_ho']);
        });
    }
};
