<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thong_bao_cong_ty', function (Blueprint $table) {
            $table->id();
            $table->string('tieu_de', 255);
            $table->string('tom_tat', 500)->nullable();
            $table->longText('noi_dung')->nullable();

            $table->string('trang_thai', 20)->default('draft')->index(); // draft|published|archived
            $table->boolean('ghim_dau')->default(false)->index();

            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();

            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime', 150)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thong_bao_cong_ty');
    }
};
