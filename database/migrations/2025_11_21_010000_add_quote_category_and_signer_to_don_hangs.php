<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            // Map Hạng mục gốc (hang_muc) -> tên hiển thị
            if (!Schema::hasColumn('don_hangs', 'quote_category_titles')) {
                $table->json('quote_category_titles')
                    ->nullable()
                    ->after('quote_section_titles') // nếu cột này chưa có thì after('ghi_chu') cũng được
                    ->comment('Map hang_muc gốc => label Hạng mục trên báo giá (tuỳ biến theo đơn)');
            }

            // Meta người báo giá / xác nhận báo giá
            if (!Schema::hasColumn('don_hangs', 'quote_signer_name')) {
                $table->string('quote_signer_name', 191)->nullable()
                    ->after('quote_category_titles')
                    ->comment('Tên người báo giá (tuỳ chọn)');
            }

            if (!Schema::hasColumn('don_hangs', 'quote_signer_title')) {
                $table->string('quote_signer_title', 191)->nullable()
                    ->after('quote_signer_name')
                    ->comment('Chức danh người báo giá');
            }

            if (!Schema::hasColumn('don_hangs', 'quote_signer_phone')) {
                $table->string('quote_signer_phone', 50)->nullable()
                    ->after('quote_signer_title')
                    ->comment('Điện thoại người báo giá');
            }

            if (!Schema::hasColumn('don_hangs', 'quote_signer_email')) {
                $table->string('quote_signer_email', 191)->nullable()
                    ->after('quote_signer_phone')
                    ->comment('Email người báo giá');
            }

            if (!Schema::hasColumn('don_hangs', 'quote_approver_note')) {
                $table->string('quote_approver_note', 255)->nullable()
                    ->after('quote_signer_email')
                    ->comment('Ghi chú cột Xác nhận báo giá (VD: Đại diện khách hàng)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            if (Schema::hasColumn('don_hangs', 'quote_category_titles')) {
                $table->dropColumn('quote_category_titles');
            }
            if (Schema::hasColumn('don_hangs', 'quote_signer_name')) {
                $table->dropColumn('quote_signer_name');
            }
            if (Schema::hasColumn('don_hangs', 'quote_signer_title')) {
                $table->dropColumn('quote_signer_title');
            }
            if (Schema::hasColumn('don_hangs', 'quote_signer_phone')) {
                $table->dropColumn('quote_signer_phone');
            }
            if (Schema::hasColumn('don_hangs', 'quote_signer_email')) {
                $table->dropColumn('quote_signer_email');
            }
            if (Schema::hasColumn('don_hangs', 'quote_approver_note')) {
                $table->dropColumn('quote_approver_note');
            }
        });
    }
};
