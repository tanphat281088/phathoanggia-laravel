<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThongBaoCongTy extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'thong_bao_cong_ty';

    protected $fillable = [
        'tieu_de',
        'tom_tat',
        'noi_dung',
        'trang_thai',
        'ghim_dau',
        'publish_at',
        'expires_at',
        'attachment_original_name',
        'attachment_path',
        'attachment_mime',
        'attachment_size',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ghim_dau'   => 'boolean',
        'publish_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scopeVisible(Builder $q): Builder
    {
        $now = now();

        return $q->where('trang_thai', 'published')
            ->where(function (Builder $w) use ($now) {
                $w->whereNull('publish_at')
                  ->orWhere('publish_at', '<=', $now);
            })
            ->where(function (Builder $w) use ($now) {
                $w->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', $now);
            });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
