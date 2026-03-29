<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bảng cham_congs đã tồn tại từ migration trước
        if (!Schema::hasTable('cham_congs')) {
            return;
        }

        Schema::table('cham_congs', function (Blueprint $table) {
            // ===== Liên kết tới địa điểm làm việc (nếu có) =====
            // Nullable để không ảnh hưởng dữ liệu cũ
            if (!Schema::hasColumn('cham_congs', 'workpoint_id')) {
                $table->unsignedBigInteger('workpoint_id')
                      ->nullable()
                      ->after('user_id');

                // FK an toàn: chỉ tạo nếu bảng diem_lam_viecs tồn tại
                if (Schema::hasTable('diem_lam_viecs')) {
                    $table->foreign('workpoint_id')
                          ->references('id')
                          ->on('diem_lam_viecs')
                          ->onDelete('set null');
                }
            }

            // ===== Thông tin ảnh selfie & kết quả nhận diện =====
            if (!Schema::hasColumn('cham_congs', 'selfie_path')) {
                $table->string('selfie_path', 255)
                      ->nullable()
                      ->after('ghi_chu');
            }

            if (!Schema::hasColumn('cham_congs', 'face_match')) {
                // true = khớp, false = không khớp, null = chưa kiểm tra
                $table->boolean('face_match')
                      ->nullable()
                      ->after('selfie_path');
            }

            if (!Schema::hasColumn('cham_congs', 'face_score')) {
                // Điểm khớp 0-100
                $table->unsignedSmallInteger('face_score')
                      ->default(0)
                      ->after('face_match');
            }

            if (!Schema::hasColumn('cham_congs', 'face_provider')) {
                $table->string('face_provider', 50)
                      ->nullable()
                      ->after('face_score');
            }

            if (!Schema::hasColumn('cham_congs', 'face_checked_at')) {
                $table->dateTime('face_checked_at')
                      ->nullable()
                      ->after('face_provider');
            }

            if (!Schema::hasColumn('cham_congs', 'reason')) {
                // Lý do fail (sai vị trí, ảnh trùng, không khớp khuôn mặt...)
                $table->text('reason')
                      ->nullable()
                      ->after('face_checked_at');
            }

            if (!Schema::hasColumn('cham_congs', 'cancelled')) {
                // true = log bị hủy, không tính công (tương đương CANCEL bên Google Sheet)
                $table->boolean('cancelled')
                      ->default(false)
                      ->after('reason');
            }

            if (!Schema::hasColumn('cham_congs', 'cancelled_at')) {
                $table->dateTime('cancelled_at')
                      ->nullable()
                      ->after('cancelled');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cham_congs')) {
            return;
        }

        Schema::table('cham_congs', function (Blueprint $table) {
            // Xoá lần lượt các cột thêm mới (có kiểm tra để tránh lỗi)
            if (Schema::hasColumn('cham_congs', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('cham_congs', 'cancelled')) {
                $table->dropColumn('cancelled');
            }
            if (Schema::hasColumn('cham_congs', 'reason')) {
                $table->dropColumn('reason');
            }
            if (Schema::hasColumn('cham_congs', 'face_checked_at')) {
                $table->dropColumn('face_checked_at');
            }
            if (Schema::hasColumn('cham_congs', 'face_provider')) {
                $table->dropColumn('face_provider');
            }
            if (Schema::hasColumn('cham_congs', 'face_score')) {
                $table->dropColumn('face_score');
            }
            if (Schema::hasColumn('cham_congs', 'face_match')) {
                $table->dropColumn('face_match');
            }
            if (Schema::hasColumn('cham_congs', 'selfie_path')) {
                $table->dropColumn('selfie_path');
            }

            // FK workpoint_id (nếu có)
            if (Schema::hasColumn('cham_congs', 'workpoint_id')) {
                // tên FK có thể khác nhau tuỳ DB, nên dùng try-catch nhiều nơi sẽ chọn dropForeign nếu biết tên.
                // Để an toàn, chỉ dropColumn – nếu cần dropForeign chính xác, sau này mình tune thêm.
                $table->dropColumn('workpoint_id');
            }
        });
    }
};
