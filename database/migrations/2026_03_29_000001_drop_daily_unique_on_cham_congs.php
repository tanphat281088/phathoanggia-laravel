<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'cham_congs';
    private string $uniqueName = 'uniq_user_type_ngay';

    public function up(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        if ($this->indexExists($this->uniqueName)) {
            $uniqueName = $this->uniqueName;

            Schema::table($this->table, function (Blueprint $table) use ($uniqueName) {
                $table->dropUnique($uniqueName);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        // Chỉ rollback unique khi dữ liệu hiện tại KHÔNG có trùng.
        // Nếu đã phát sinh nhiều phiên trong ngày thì bỏ qua, tránh migrate:rollback bị fail.
        if ($this->indexExists($this->uniqueName)) {
            return;
        }

        if ($this->hasDailyDuplicates()) {
            return;
        }

        $uniqueName = $this->uniqueName;

        Schema::table($this->table, function (Blueprint $table) use ($uniqueName) {
            $table->unique(['user_id', 'type', 'ngay'], $uniqueName);
        });
    }

    private function indexExists(string $indexName): bool
    {
        $dbName = DB::getDatabaseName();

        $row = DB::selectOne(
            '
            SELECT COUNT(*) AS cnt
            FROM information_schema.statistics
            WHERE table_schema = ?
              AND table_name = ?
              AND index_name = ?
            ',
            [$dbName, $this->table, $indexName]
        );

        return (int) ($row->cnt ?? 0) > 0;
    }

    private function hasDailyDuplicates(): bool
    {
        $row = DB::selectOne(
            "
            SELECT 1 AS hit
            FROM {$this->table}
            GROUP BY user_id, type, DATE(checked_at)
            HAVING COUNT(*) > 1
            LIMIT 1
            "
        );

        return $row !== null;
    }
};