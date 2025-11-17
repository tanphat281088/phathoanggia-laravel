<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bước 1: Chuẩn hoá bảng khach_hangs cho ERP Sự kiện
     * - Bỏ customer_mode (di sản shop hoa)
     * - Thêm customer_type, is_system_customer
     * - Thêm nhóm field B2B (Event/Agency) & Wedding
     * - Thêm một số field CRM
     */
    public function up(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            /**
             * 🔹 1) BỎ HOÀN TOÀN customer_mode (không dùng cho ERP sự kiện)
             */
            if (Schema::hasColumn('khach_hangs', 'customer_mode')) {
                // Nếu về sau có index riêng cho customer_mode, Laravel sẽ drop cùng cột
                $table->dropColumn('customer_mode');
            }

            /**
             * 🔹 2) Loại khách chuyên ngành sự kiện
             * 0 = Event client
             * 1 = Wedding client
             * 2 = Agency client
             */
            if (! Schema::hasColumn('khach_hangs', 'customer_type')) {
                $table->unsignedTinyInteger('customer_type')
                    ->default(0)
                    ->after('loai_khach_hang_id')
                    ->comment('0=Event,1=Wedding,2=Agency');
            }

            /**
             * 🔹 3) Level quan hệ: Khách hệ thống / Khách vãng lai
             * 1 = Khách hệ thống (mặc định)
             * 0 = Khách vãng lai
             */
            if (! Schema::hasColumn('khach_hangs', 'is_system_customer')) {
                $table->boolean('is_system_customer')
                    ->default(true)
                    ->after('customer_type')
                    ->comment('1=Khách hệ thống,0=Vãng lai');
            }

            /**
             * 🔹 4) Nhóm thông tin B2B (Event / Agency)
             * Đặt gần ten_khach_hang cho dễ nhìn
             */
            if (! Schema::hasColumn('khach_hangs', 'company_name')) {
                $table->string('company_name', 255)
                    ->nullable()
                    ->after('ten_khach_hang')
                    ->comment('Tên công ty/tổ chức (Event/Agency)');
            }

            if (! Schema::hasColumn('khach_hangs', 'tax_code')) {
                $table->string('tax_code', 50)
                    ->nullable()
                    ->after('company_name')
                    ->comment('Mã số thuế (nếu có)');
            }

            if (! Schema::hasColumn('khach_hangs', 'department')) {
                $table->string('department', 191)
                    ->nullable()
                    ->after('tax_code')
                    ->comment('Phòng ban phụ trách (Marketing, HR, …)');
            }

            if (! Schema::hasColumn('khach_hangs', 'position')) {
                $table->string('position', 191)
                    ->nullable()
                    ->after('department')
                    ->comment('Chức vụ người liên hệ (Trưởng phòng, Manager, …)');
            }

            if (! Schema::hasColumn('khach_hangs', 'industry')) {
                $table->string('industry', 191)
                    ->nullable()
                    ->after('position')
                    ->comment('Ngành hàng: ngân hàng, FMCG, giáo dục, …');
            }

            /**
             * 🔹 5) Nhóm thông tin Wedding
             */
            if (! Schema::hasColumn('khach_hangs', 'bride_name')) {
                $table->string('bride_name', 191)
                    ->nullable()
                    ->after('industry')
                    ->comment('Tên cô dâu');
            }

            if (! Schema::hasColumn('khach_hangs', 'groom_name')) {
                $table->string('groom_name', 191)
                    ->nullable()
                    ->after('bride_name')
                    ->comment('Tên chú rể');
            }

            if (! Schema::hasColumn('khach_hangs', 'wedding_date')) {
                $table->date('wedding_date')
                    ->nullable()
                    ->after('groom_name')
                    ->comment('Ngày cưới (nếu là khách wedding)');
            }

            if (! Schema::hasColumn('khach_hangs', 'wedding_venue')) {
                $table->string('wedding_venue', 255)
                    ->nullable()
                    ->after('wedding_date')
                    ->comment('Địa điểm/Nhà hàng tổ chức tiệc cưới');
            }

            /**
             * 🔹 6) Nhóm field CRM linh hoạt
             */
            if (! Schema::hasColumn('khach_hangs', 'source_detail')) {
                $table->string('source_detail', 255)
                    ->nullable()
                    ->after('kenh_lien_he')
                    ->comment('Chi tiết nguồn: giới thiệu bởi ai, kênh nào, …');
            }

            if (! Schema::hasColumn('khach_hangs', 'note_internal')) {
                $table->text('note_internal')
                    ->nullable()
                    ->after('ghi_chu')
                    ->comment('Ghi chú nội bộ, không hiển thị cho khách');
            }
        });

        /**
         * 🔹 7) Index tối ưu cho truy vấn CRM
         */
        Schema::table('khach_hangs', function (Blueprint $table) {
            // index tên cố định để tránh trùng
            if (! $this->indexExists('khach_hangs', 'idx_khach_hangs_customer_type')) {
                $table->index('customer_type', 'idx_khach_hangs_customer_type');
            }

            if (! $this->indexExists('khach_hangs', 'idx_khach_hangs_is_system_customer')) {
                $table->index('is_system_customer', 'idx_khach_hangs_is_system_customer');
            }

            if (! $this->indexExists('khach_hangs', 'idx_khach_hangs_phone')) {
                $table->index('so_dien_thoai', 'idx_khach_hangs_phone');
            }

            if (! $this->indexExists('khach_hangs', 'idx_khach_hangs_email')) {
                $table->index('email', 'idx_khach_hangs_email');
            }
        });
    }

    /**
     * Hàm phụ kiểm tra index có tồn tại không (dùng INFORMATION_SCHEMA)
     */
    private function indexExists(string $table, string $index): bool
    {
        $conn = Schema::getConnection();
        $db   = $conn->getDatabaseName();

        $sql  = "SELECT COUNT(1) AS c
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME   = ?
                   AND INDEX_NAME   = ?";

        $row = $conn->selectOne($sql, [$db, $table, $index]);

        return ! empty($row) && (int)($row->c ?? 0) > 0;
    }

    /**
     * Rollback: bỏ các cột & index vừa thêm
     * (customer_mode KHÔNG được thêm lại nữa – coi như bỏ hẳn cho ERP sự kiện)
     */
    public function down(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            // Bỏ index (nếu tồn tại)
            try {
                $table->dropIndex('idx_khach_hangs_customer_type');
            } catch (\Throwable $e) {}

            try {
                $table->dropIndex('idx_khach_hangs_is_system_customer');
            } catch (\Throwable $e) {}

            try {
                $table->dropIndex('idx_khach_hangs_phone');
            } catch (\Throwable $e) {}

            try {
                $table->dropIndex('idx_khach_hangs_email');
            } catch (\Throwable $e) {}

            // Bỏ các cột mới
            $cols = [
                'customer_type',
                'is_system_customer',
                'company_name',
                'tax_code',
                'department',
                'position',
                'industry',
                'bride_name',
                'groom_name',
                'wedding_date',
                'wedding_venue',
                'source_detail',
                'note_internal',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('khach_hangs', $col)) {
                    $table->dropColumn($col);
                }
            }

            // ❗ KHÔNG thêm lại customer_mode trong down()
            // vì trên ERP sự kiện chúng ta bỏ hẳn khái niệm này.
        });
    }
};
