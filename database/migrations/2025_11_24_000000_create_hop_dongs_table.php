<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng HỢP ĐỒNG (header)
 *
 * - Mỗi record = 1 Hợp đồng gắn với 1 Báo giá (don_hangs)
 * - type / trạng thái thanh lý sẽ xử lý ở module khác (Thanh lý)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('hop_dongs')) {
            Schema::create('hop_dongs', function (Blueprint $table) {
                $table->id();

                // FK -> don_hangs.id (Báo giá gốc)
                $table->foreignId('don_hang_id')
                    ->constrained('don_hangs')
                    ->onDelete('cascade')
                    ->comment('FK -> don_hangs.id (Báo giá gốc)');

                // Số & ngày Hợp đồng
                $table->string('so_hop_dong', 100)
                    ->nullable()
                    ->comment('Số Hợp đồng, ví dụ: TC393/2025/HĐDV/PHG');
                $table->date('ngay_hop_dong')
                    ->nullable()
                    ->comment('Ngày ký Hợp đồng');

                // Trạng thái: 0 = Nháp, 1 = Đã ký, 2 = Đã thanh lý, 3 = Đã hủy
                $table->unsignedTinyInteger('status')
                    ->default(0)
                    ->comment('0 = Nháp, 1 = Đã ký, 2 = Đã thanh lý, 3 = Đã hủy');

                // ===== BÊN A (KHÁCH HÀNG) – Snapshot tại thời điểm ký =====
                $table->string('ben_a_ten', 255)->nullable()
                    ->comment('Tên Bên A (khách hàng)');
                $table->string('ben_a_dia_chi', 500)->nullable()
                    ->comment('Địa chỉ Bên A');
                $table->string('ben_a_mst', 100)->nullable()
                    ->comment('Mã số thuế Bên A');
                $table->string('ben_a_dai_dien', 255)->nullable()
                    ->comment('Người đại diện Bên A');
                $table->string('ben_a_chuc_vu', 255)->nullable()
                    ->comment('Chức vụ người đại diện Bên A');
                $table->string('ben_a_dien_thoai', 100)->nullable()
                    ->comment('Điện thoại Bên A');
                $table->string('ben_a_email', 191)->nullable()
                    ->comment('Email Bên A');

                // ===== BÊN B (PHÁT HOÀNG GIA) – Có thể sửa nhưng thường giữ cố định =====
                $table->string('ben_b_ten', 255)->nullable()
                    ->comment('Tên Bên B (mặc định: CÔNG TY TNHH SỰ KIỆN PHÁT HOÀNG GIA)');
                $table->string('ben_b_dia_chi', 500)->nullable()
                    ->comment('Địa chỉ Bên B');
                $table->string('ben_b_mst', 100)->nullable()
                    ->comment('Mã số thuế Bên B');
                $table->string('ben_b_tai_khoan', 255)->nullable()
                    ->comment('Số tài khoản ngân hàng Bên B');
                $table->string('ben_b_ngan_hang', 255)->nullable()
                    ->comment('Ngân hàng Bên B');
                $table->string('ben_b_dai_dien', 255)->nullable()
                    ->comment('Người đại diện Bên B');
                $table->string('ben_b_chuc_vu', 255)->nullable()
                    ->comment('Chức vụ người đại diện Bên B');

                // ===== THÔNG TIN SỰ KIỆN =====
                $table->string('su_kien_ten', 500)->nullable()
                    ->comment('Tên chương trình / sự kiện');
                $table->string('su_kien_thoi_gian_text', 500)->nullable()
                    ->comment('Thời gian tổ chức (dạng text: Ngày ..., từ ... đến ...)');
                $table->string('su_kien_thoi_gian_setup_text', 500)->nullable()
                    ->comment('Thời gian set up / tháo dỡ (dạng text, nếu có)');
                $table->string('su_kien_dia_diem', 500)->nullable()
                    ->comment('Địa điểm tổ chức');

                // ===== GIÁ TRỊ HỢP ĐỒNG =====
                $table->integer('tong_truoc_vat')
                    ->default(0)
                    ->comment('Giá trị HĐ trước VAT (VND)');
                $table->decimal('vat_rate', 5, 2)
                    ->nullable()
                    ->comment('Tỷ lệ VAT (%) – ví dụ 8, 10; null = không VAT');
                $table->integer('vat_amount')
                    ->default(0)
                    ->comment('Tiền VAT (VND)');
                $table->integer('tong_sau_vat')
                    ->default(0)
                    ->comment('Tổng giá trị HĐ sau VAT (VND)');
                $table->string('tong_sau_vat_bang_chu', 500)
                    ->nullable()
                    ->comment('Tổng giá trị HĐ sau VAT bằng chữ (snapshot)');

                // ===== CẤU TRÚC THANH TOÁN (ĐỢT 1, ĐỢT 2) =====
                $table->unsignedTinyInteger('dot1_ty_le')
                    ->default(0)
                    ->comment('Đợt 1: tỷ lệ % trên giá trị HĐ');
                $table->integer('dot1_so_tien')
                    ->default(0)
                    ->comment('Đợt 1: số tiền phải thanh toán (VND)');
                $table->string('dot1_thoi_diem_text', 500)
                    ->nullable()
                    ->comment('Đợt 1: thời điểm thanh toán (dạng text)');

                $table->unsignedTinyInteger('dot2_ty_le')
                    ->default(0)
                    ->comment('Đợt 2: tỷ lệ % trên giá trị HĐ');
                $table->integer('dot2_so_tien')
                    ->default(0)
                    ->comment('Đợt 2: số tiền phải thanh toán (VND)');
                $table->string('dot2_thoi_diem_text', 500)
                    ->nullable()
                    ->comment('Đợt 2: thời điểm thanh toán (dạng text)');

                // ===== ĐIỀU KHOẢN TUỲ CHỈNH (FREE-TEXT / JSON) =====
                $table->text('dieukhoan_tuy_chinh')
                    ->nullable()
                    ->comment('Các điều khoản/ghi chú tuỳ chỉnh thêm cho Hợp đồng (nếu có)');

                // Theo style hệ thống hiện tại
                $table->string('nguoi_tao')->nullable();
                $table->string('nguoi_cap_nhat')->nullable();

                $table->timestamps();

                // Index hỗ trợ tra cứu
                $table->index('don_hang_id', 'hop_dongs_don_hang_id_idx');
                $table->index('status', 'hop_dongs_status_idx');
                $table->index('so_hop_dong', 'hop_dongs_so_hd_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hop_dongs');
    }
};
