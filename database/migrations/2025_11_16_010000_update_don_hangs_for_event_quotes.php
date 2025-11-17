<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nâng cấp:
     * - Bảng don_hangs = "BÁO GIÁ SỰ KIỆN"
     * - Bảng chi_tiet_don_hangs = chi tiết hạng mục A/B/C/D
     *
     * LƯU Ý:
     * - KHÔNG xoá các cột cũ (trang_thai_thanh_toan, so_tien_da_thanh_toan, ...),
     *   để các module khác (tài chính, điểm thưởng...) vẫn dùng được.
     * - Chỉ THÊM cột mới + BỎ cột loai_gia (giá đặt trước 3 ngày) vì EVENT không dùng.
     */
    public function up(): void
    {
        /**
         * ===== 1) BẢNG don_hangs: thêm metadata cho BÁO GIÁ SỰ KIỆN =====
         */
        Schema::table('don_hangs', function (Blueprint $table) {
            // Tên dự án / sự kiện
            if (! Schema::hasColumn('don_hangs', 'project_name')) {
                $table->string('project_name', 255)
                    ->nullable()
                    ->after('ma_don_hang')
                    ->comment('Tên dự án / Sự kiện');
            }

            // Loại sự kiện: khai trương, hội nghị, gala, wedding, internal...
            if (! Schema::hasColumn('don_hangs', 'event_type')) {
                $table->string('event_type', 100)
                    ->nullable()
                    ->after('project_name')
                    ->comment('Loại sự kiện (khai trương, hội nghị, gala, wedding, ...)');
            }

            // Thời gian sự kiện (từ - đến)
            if (! Schema::hasColumn('don_hangs', 'event_start')) {
                $table->dateTime('event_start')
                    ->nullable()
                    ->after('event_type')
                    ->comment('Thời gian bắt đầu sự kiện');
            }

            if (! Schema::hasColumn('don_hangs', 'event_end')) {
                $table->dateTime('event_end')
                    ->nullable()
                    ->after('event_start')
                    ->comment('Thời gian kết thúc sự kiện');
            }

            // Số lượng khách dự kiến
            if (! Schema::hasColumn('don_hangs', 'guest_count')) {
                $table->unsignedInteger('guest_count')
                    ->default(0)
                    ->after('event_end')
                    ->comment('Số lượng khách dự kiến');
            }

            // Địa điểm tổ chức (tách rõ venue_name + venue_address)
            if (! Schema::hasColumn('don_hangs', 'venue_name')) {
                $table->string('venue_name', 255)
                    ->nullable()
                    ->after('guest_count')
                    ->comment('Tên địa điểm / Nhà hàng / Khách sạn');
            }

            if (! Schema::hasColumn('don_hangs', 'venue_address')) {
                $table->string('venue_address', 255)
                    ->nullable()
                    ->after('venue_name')
                    ->comment('Địa chỉ chi tiết địa điểm tổ chức');
            }

            // Thông tin người liên hệ chính (snapshot tại thời điểm báo giá)
            if (! Schema::hasColumn('don_hangs', 'contact_name')) {
                $table->string('contact_name', 191)
                    ->nullable()
                    ->after('ten_khach_hang')
                    ->comment('Người liên hệ chính cho dự án');
            }

            if (! Schema::hasColumn('don_hangs', 'contact_phone')) {
                $table->string('contact_phone', 50)
                    ->nullable()
                    ->after('contact_name')
                    ->comment('SĐT người liên hệ');
            }

            if (! Schema::hasColumn('don_hangs', 'contact_email')) {
                $table->string('contact_email', 191)
                    ->nullable()
                    ->after('contact_phone')
                    ->comment('Email người liên hệ');
            }

            if (! Schema::hasColumn('don_hangs', 'contact_department')) {
                $table->string('contact_department', 191)
                    ->nullable()
                    ->after('contact_email')
                    ->comment('Phòng ban người liên hệ (MKT, HR, ...)');
            }

            if (! Schema::hasColumn('don_hangs', 'contact_position')) {
                $table->string('contact_position', 191)
                    ->nullable()
                    ->after('contact_department')
                    ->comment('Chức vụ người liên hệ (Manager, Trưởng phòng, ...)');
            }

            // Trạng thái BÁO GIÁ (tách riêng với trang_thai_don_hang cũ)
            if (! Schema::hasColumn('don_hangs', 'quote_status')) {
                $table->unsignedTinyInteger('quote_status')
                    ->default(0)
                    ->after('trang_thai_thanh_toan')
                    ->comment('0=Nháp,1=Đã gửi,2=Thương lượng,3=Khách duyệt,4=Đã thực hiện,5=Đã tất toán,6=Đã huỷ');
            }

            // Ghi chú hiển thị trên báo giá (riêng với ghi_chu nội bộ nếu cần sau này)
            if (! Schema::hasColumn('don_hangs', 'note_customer')) {
                $table->text('note_customer')
                    ->nullable()
                    ->after('ghi_chu')
                    ->comment('Ghi chú thể hiện trên báo giá gửi khách');
            }
        });

        /**
         * ===== 2) BẢNG chi_tiet_don_hangs: thêm cấu trúc hạng mục A/B/C/D =====
         */
        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            // Mã nhóm hạng mục (A/B/C/D...) theo mẫu báo giá
            if (! Schema::hasColumn('chi_tiet_don_hangs', 'section_code')) {
                $table->string('section_code', 5)
                    ->nullable()
                    ->after('don_hang_id')
                    ->comment('Nhóm hạng mục: A/B/C/D...');
            }

            // Tên hạng mục hiển thị (nếu muốn override tên dịch vụ)
            if (! Schema::hasColumn('chi_tiet_don_hangs', 'title')) {
                $table->string('title', 255)
                    ->nullable()
                    ->after('section_code')
                    ->comment('Tiêu đề hạng mục hiển thị trên báo giá');
            }

            // Mô tả chi tiết hạng mục
            if (! Schema::hasColumn('chi_tiet_don_hangs', 'description')) {
                $table->text('description')
                    ->nullable()
                    ->after('title')
                    ->comment('Mô tả chi tiết hạng mục / dịch vụ');
            }

            // Nhà cung cấp cho từng dòng (nếu khác nhau)
            if (! Schema::hasColumn('chi_tiet_don_hangs', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')
                    ->nullable()
                    ->after('don_vi_tinh_id')
                    ->comment('FK -> nha_cung_caps.id (nhà cung cấp cho hạng mục này)');
            }

            // Giá gốc (cost) & chi phí (sau khi nhân số lượng)
            if (! Schema::hasColumn('chi_tiet_don_hangs', 'base_cost')) {
                $table->integer('base_cost')
                    ->default(0)
                    ->after('don_gia')
                    ->comment('Giá vốn 1 đơn vị (VND)');
            }

            if (! Schema::hasColumn('chi_tiet_don_hangs', 'cost_amount')) {
                $table->integer('cost_amount')
                    ->default(0)
                    ->after('base_cost')
                    ->comment('Tổng chi phí (base_cost * so_luong), dùng cho tính lãi gộp');
            }

            // Dòng header "A. NHÂN SỰ", "B. CƠ SỞ VẬT CHẤT" ... (không có số lượng/tiền)
            if (! Schema::hasColumn('chi_tiet_don_hangs', 'is_section_header')) {
                $table->boolean('is_section_header')
                    ->default(false)
                    ->after('thanh_tien')
                    ->comment('1=dòng tiêu đề nhóm (A/B/C/D), 0=dòng chi tiết');
            }

            // EVENT không dùng loại giá (đặt trước 3 ngày...), bỏ nếu còn
            if (Schema::hasColumn('chi_tiet_don_hangs', 'loai_gia')) {
                $table->dropColumn('loai_gia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            $cols = [
                'project_name',
                'event_type',
                'event_start',
                'event_end',
                'guest_count',
                'venue_name',
                'venue_address',
                'contact_name',
                'contact_phone',
                'contact_email',
                'contact_department',
                'contact_position',
                'quote_status',
                'note_customer',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('don_hangs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            $cols = [
                'section_code',
                'title',
                'description',
                'supplier_id',
                'base_cost',
                'cost_amount',
                'is_section_header',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('chi_tiet_don_hangs', $col)) {
                    $table->dropColumn($col);
                }
            }

            // Nếu bạn THỰC SỰ muốn rollback hoàn toàn, có thể thêm lại loai_gia ở đây
            // (nhưng với ERP sự kiện thì không cần nữa).
        });
    }
};
