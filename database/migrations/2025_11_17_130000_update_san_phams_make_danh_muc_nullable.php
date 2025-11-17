<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cho phép cột danh_muc_id trong san_phams được NULL
     * để chi tiết dịch vụ / thiết bị có thể không thuộc danh mục nào.
     */
    public function up(): void
    {
        // Nếu project của bạn chưa cài doctrine/dbal, dùng raw SQL cho chắc
        // Giả định san_phams.id và danh_muc_san_phams.id đều BIGINT UNSIGNED
        DB::statement('ALTER TABLE san_phams MODIFY danh_muc_id BIGINT UNSIGNED NULL');
    }

    /**
     * Rollback: đưa danh_muc_id về NOT NULL (nếu cần)
     */
    public function down(): void
    {
        // Cẩn thận: rollback yêu cầu các record hiện tại không được có danh_muc_id = NULL
        DB::statement('ALTER TABLE san_phams MODIFY danh_muc_id BIGINT UNSIGNED NOT NULL');
    }
};
