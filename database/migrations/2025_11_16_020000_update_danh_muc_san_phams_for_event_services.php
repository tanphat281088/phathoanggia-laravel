<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nâng cấp bảng danh_muc_san_phams để dùng làm DANH MỤC DỊCH VỤ:
     *
     * - Thêm parent_id: cho phép cấu trúc cha/con (nếu sau này cần nhiều tầng)
     * - Thêm group_code: Nhóm dịch vụ cao nhất, 5 giá trị chính:
     *      + NHAN_SU
     *      + CO_SO_VAT_CHAT
     *      + TIEC
     *      + THUE_DIA_DIEM
     *      + CHI_PHI_KHAC
     */
    public function up(): void
    {
        Schema::table('danh_muc_san_phams', function (Blueprint $table) {
            // 🔹 Tầng cha/con (null = nhóm dịch vụ cao nhất)
            if (! Schema::hasColumn('danh_muc_san_phams', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')
                    ->nullable()
                    ->after('id')
                    ->comment('Danh mục cha; null = Nhóm dịch vụ cấp cao nhất');

                $table->index('parent_id', 'dmsp_parent_idx');
            }

            // 🔹 Nhóm dịch vụ cao nhất (5 nhóm bạn yêu cầu)
            if (! Schema::hasColumn('danh_muc_san_phams', 'group_code')) {
                $table->string('group_code', 50)
                    ->nullable()
                    ->after('ma_danh_muc')
                    ->comment('Nhóm dịch vụ: NHAN_SU, CO_SO_VAT_CHAT, TIEC, THUE_DIA_DIEM, CHI_PHI_KHAC');

                $table->index('group_code', 'dmsp_group_code_idx');
            }
        });
    }

    /**
     * Rollback
     */
    public function down(): void
    {
        Schema::table('danh_muc_san_phams', function (Blueprint $table) {
            if (Schema::hasColumn('danh_muc_san_phams', 'parent_id')) {
                // dropColumn sẽ tự drop luôn index liên quan
                $table->dropColumn('parent_id');
            }

            if (Schema::hasColumn('danh_muc_san_phams', 'group_code')) {
                $table->dropColumn('group_code');
            }
        });
    }
};
