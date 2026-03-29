<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('diem_lam_viecs')) {
            return;
        }

        Schema::table('diem_lam_viecs', function (Blueprint $table) {
            if (!Schema::hasColumn('diem_lam_viecs', 'ma_dia_diem')) {
                $table->string('ma_dia_diem', 20)
                    ->nullable()
                    ->after('id');
            }

            if (!Schema::hasColumn('diem_lam_viecs', 'loai_dia_diem')) {
                $table->enum('loai_dia_diem', ['fixed', 'event'])
                    ->default('fixed')
                    ->after('ten');
            }

            if (!Schema::hasColumn('diem_lam_viecs', 'nguon_tao')) {
                $table->enum('nguon_tao', ['system', 'manual', 'mobile'])
                    ->default('manual')
                    ->after('loai_dia_diem');
            }

            if (!Schema::hasColumn('diem_lam_viecs', 'created_by')) {
                $table->unsignedBigInteger('created_by')
                    ->nullable()
                    ->after('nguon_tao');
            }

            if (!Schema::hasColumn('diem_lam_viecs', 'hieu_luc_tu')) {
                $table->dateTime('hieu_luc_tu')
                    ->nullable()
                    ->after('created_by');
            }

            if (!Schema::hasColumn('diem_lam_viecs', 'hieu_luc_den')) {
                $table->dateTime('hieu_luc_den')
                    ->nullable()
                    ->after('hieu_luc_tu');
            }

            if (!Schema::hasColumn('diem_lam_viecs', 'ghi_chu')) {
                $table->text('ghi_chu')
                    ->nullable()
                    ->after('dia_chi');
            }
        });

        // FK created_by -> users.id (an toàn, chỉ tạo nếu bảng users tồn tại)
        if (
            Schema::hasTable('users') &&
            Schema::hasColumn('diem_lam_viecs', 'created_by')
        ) {
            try {
                Schema::table('diem_lam_viecs', function (Blueprint $table) {
                    $table->foreign('created_by', 'diem_lam_viecs_created_by_fk')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // bỏ qua nếu FK đã tồn tại
            }
        }

        // Index phục vụ filter fixed/event + active/expiry
        try {
            Schema::table('diem_lam_viecs', function (Blueprint $table) {
                $table->unique('ma_dia_diem', 'diem_lam_viecs_ma_dia_diem_unique');
            });
        } catch (\Throwable $e) {
            // bỏ qua nếu index đã tồn tại
        }

        try {
            Schema::table('diem_lam_viecs', function (Blueprint $table) {
                $table->index(['loai_dia_diem', 'trang_thai'], 'diem_lam_viecs_loai_trang_thai_idx');
            });
        } catch (\Throwable $e) {
            // bỏ qua nếu index đã tồn tại
        }

        try {
            Schema::table('diem_lam_viecs', function (Blueprint $table) {
                $table->index(['hieu_luc_den'], 'diem_lam_viecs_hieu_luc_den_idx');
            });
        } catch (\Throwable $e) {
            // bỏ qua nếu index đã tồn tại
        }

        $now = now();

        // Backfill 16 điểm cố định thành master data chuẩn
        $fixedRows = [
            ['code' => 'DD001', 'aliases' => ['Văn phòng PHG', 'Trụ sở PHG']],
            ['code' => 'DD002', 'aliases' => ['Melisa']],
            ['code' => 'DD003', 'aliases' => ['Grand Palace']],
            ['code' => 'DD004', 'aliases' => ['Queen Tân Bình']],
            ['code' => 'DD005', 'aliases' => ['Queen Kỳ Hòa']],
            ['code' => 'DD006', 'aliases' => ['Happy Gold']],
            ['code' => 'DD007', 'aliases' => ['Long Biên (Him Lam)', 'Long Biên', 'Him Lam']],
            ['code' => 'DD008', 'aliases' => ['Nhà hàng Phú Nhuận']],
            ['code' => 'DD009', 'aliases' => ['Đông Hồ']],
            ['code' => 'DD010', 'aliases' => ['Vườn Cau']],
            ['code' => 'DD011', 'aliases' => ['Vườn Cau 2']],
            ['code' => 'DD012', 'aliases' => ['Eros']],
            ['code' => 'DD013', 'aliases' => ['Dinh Độc Lập']],
            ['code' => 'DD014', 'aliases' => ['Đồng Xanh']],
            ['code' => 'DD015', 'aliases' => ['Viễn Đông']],
            ['code' => 'DD016', 'aliases' => ['Westen Palace', 'Western Palace']],
        ];

        foreach ($fixedRows as $row) {
            DB::table('diem_lam_viecs')
                ->whereIn('ten', $row['aliases'])
                ->update([
                    'ma_dia_diem'   => $row['code'],
                    'loai_dia_diem' => 'fixed',
                    'nguon_tao'     => 'system',
                    'hieu_luc_tu'   => null,
                    'hieu_luc_den'  => null,
                    'updated_at'    => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('diem_lam_viecs')) {
            return;
        }

        try {
            Schema::table('diem_lam_viecs', function (Blueprint $table) {
                $table->dropForeign('diem_lam_viecs_created_by_fk');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            Schema::table('diem_lam_viecs', function (Blueprint $table) {
                $table->dropUnique('diem_lam_viecs_ma_dia_diem_unique');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            Schema::table('diem_lam_viecs', function (Blueprint $table) {
                $table->dropIndex('diem_lam_viecs_loai_trang_thai_idx');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            Schema::table('diem_lam_viecs', function (Blueprint $table) {
                $table->dropIndex('diem_lam_viecs_hieu_luc_den_idx');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('diem_lam_viecs', function (Blueprint $table) {
            if (Schema::hasColumn('diem_lam_viecs', 'ghi_chu')) {
                $table->dropColumn('ghi_chu');
            }
            if (Schema::hasColumn('diem_lam_viecs', 'hieu_luc_den')) {
                $table->dropColumn('hieu_luc_den');
            }
            if (Schema::hasColumn('diem_lam_viecs', 'hieu_luc_tu')) {
                $table->dropColumn('hieu_luc_tu');
            }
            if (Schema::hasColumn('diem_lam_viecs', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('diem_lam_viecs', 'nguon_tao')) {
                $table->dropColumn('nguon_tao');
            }
            if (Schema::hasColumn('diem_lam_viecs', 'loai_dia_diem')) {
                $table->dropColumn('loai_dia_diem');
            }
            if (Schema::hasColumn('diem_lam_viecs', 'ma_dia_diem')) {
                $table->dropColumn('ma_dia_diem');
            }
        });
    }
};