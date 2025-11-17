<?php

namespace App\Modules\SanPham;

use App\Models\SanPham;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\KhoTong;

class SanPhamService
{
    /**
     * Lấy tất cả dữ liệu (đã fix tổng & phân trang an toàn, KHÔNG dùng GROUP BY trên san_phams.*)
     */
    public function getAll(array $params = [])
    {
        try {
            // ========== Subqueries tính tổng ==========
            // Tổng số lượng nhập theo sản phẩm
            $subNhap = DB::table('chi_tiet_phieu_nhap_khos')
                ->select('san_pham_id', DB::raw('COALESCE(SUM(so_luong_nhap), 0) AS tong_so_luong_nhap'))
                ->groupBy('san_pham_id');

            // Tổng tồn thực tế theo sản phẩm
            $subTon = DB::table('kho_tongs')
                ->select('san_pham_id', DB::raw('COALESCE(SUM(so_luong_ton), 0) AS tong_so_luong_thuc_te'))
                ->groupBy('san_pham_id');

            // ========== Base query KHÔNG join bảng chi tiết trực tiếp ==========
            $query = SanPham::query()
                ->withoutGlobalScopes(['withUserNames'])
                ->with('images', 'danhMuc:id,ten_danh_muc') // giữ quan hệ để FE dùng
                ->leftJoin('users as nguoi_tao', 'san_phams.nguoi_tao', '=', 'nguoi_tao.id')
                ->leftJoin('users as nguoi_cap_nhat', 'san_phams.nguoi_cap_nhat', '=', 'nguoi_cap_nhat.id')
                ->leftJoin('loai_san_pham_masters as lsp', 'lsp.code', '=', 'san_phams.loai_san_pham')
                ->leftJoinSub($subNhap, 'pnk', function ($join) {
                    $join->on('pnk.san_pham_id', '=', 'san_phams.id');
                })
                ->leftJoinSub($subTon, 'ton', function ($join) {
                    $join->on('ton.san_pham_id', '=', 'san_phams.id');
                });

            // ===== (Optional) áp dụng bộ lọc nếu FE gửi — dùng đúng kiểu value, KHÔNG 'NGUYEN_LIEU:1'
            if (!empty($params['f']) && is_array($params['f'])) {
                foreach ($params['f'] as $f) {
                    $field = $f['field'] ?? null;
                    $op    = $f['operator'] ?? null;
                    $val   = $f['value'] ?? null;

                    if ($field === 'loai_san_pham' && is_string($val)) {
                        // Chuẩn hóa: cắt phần thừa nếu lỡ gửi 'NGUYEN_LIEU:1'
                        $val = explode(':', $val)[0];
                    }

                    if ($field && $op && $val !== null) {
                        switch ($op) {
                            case 'equal':
                                $query->where("san_phams.$field", '=', $val);
                                break;
                            case 'not_equal':
                                $query->where("san_phams.$field", '!=', $val);
                                break;
                            case 'contain':
                                $query->where("san_phams.$field", 'like', "%$val%");
                                break;
                            // có thể bổ sung thêm các toán tử khác nếu FE dùng
                        }
                    }
                }
            }

            // ===== (Sort) =====
            $sortCol = $params['sort_column']    ?? 'id';
            $sortDir = $params['sort_direction'] ?? 'desc';
            if (!in_array(strtolower($sortDir), ['asc', 'desc'], true)) {
                $sortDir = 'desc';
            }
            $query->orderBy("san_phams.$sortCol", $sortDir);

            // ===== Pagination =====
            $page    = max(1, (int)($params['page']      ?? 1));
            $perPage = max(1, (int)($params['limit']     ?? ($params['per_page'] ?? 20)));

            // ===== (1) Đếm tổng bản ghi thật sự (KHÔNG ORDER BY / GROUP BY)
            $countQuery = (clone $query);
            $base = $countQuery->getQuery();
            $base->orders = null;   // ⬅️ XÓA ORDER BY để tránh 1140
            $base->groups = null;   // (phòng xa nếu có set groups đâu đó)

            $total = (int) $countQuery
                ->select(DB::raw('COUNT(DISTINCT san_phams.id) as agg_total'))
                ->value('agg_total') ?? 0;

            // ===== (2) Lấy dữ liệu trang hiện tại
            $collection = $query->select([
                'san_phams.*',
                'nguoi_tao.name as ten_nguoi_tao',
                'nguoi_cap_nhat.name as ten_nguoi_cap_nhat',
                DB::raw('COALESCE(pnk.tong_so_luong_nhap, 0)      as tong_so_luong_nhap'),
                DB::raw('COALESCE(ton.tong_so_luong_thuc_te, 0)    as tong_so_luong_thuc_te'),
                DB::raw('COALESCE(lsp.ten_hien_thi, san_phams.loai_san_pham) as ten_loai'),
            ])->forPage($page, $perPage)->get();

            // (3) Trả về cấu trúc cũ cho FE
            return [
                'data' => $collection,
                'total' => (int)$total,
                'pagination' => [
                    'current_page'  => $page,
                    'last_page'     => max(1, (int) ceil(($total ?: 0) / $perPage)),
                    'from'          => ($total == 0) ? 0 : (($page - 1) * $perPage + 1),
                    'to'            => min($page * $perPage, (int)$total),
                    'total_current' => $collection->count(),
                ],
            ];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lấy danh sách: ' . $e->getMessage());
        }
    }

    /**
     * Lấy dữ liệu theo ID
     */
    public function getById($id)
    {
        $data = SanPham::with([
            'images',
            'donViTinhs' => function ($query) {
                $query->withoutGlobalScope('withUserNames')->select('don_vi_tinhs.id as value', 'ten_don_vi as label');
            },
            'nhaCungCaps' => function ($query) {
                $query->withoutGlobalScope('withUserNames')->select('nha_cung_caps.id as value', 'ten_nha_cung_cap as label');
            },
            'danhMuc',
            // ✅ MỚI: load luôn danh sách item bên trong gói (nếu là gói)
            'packageItems.item',
        ])->find($id);

        if (!$data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }

        return $data;
    }

    /**
     * Tạo mới dữ liệu
     * - Nếu ma_san_pham rỗng -> tự sinh mã
     * - Nếu loai_san_pham là gói + có package_items => lưu chi tiết gói vào event_package_items
     */
    public function create(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                // 🔹 Tự sinh mã nếu không có
                if (empty($data['ma_san_pham'])) {
                    $data['ma_san_pham'] = $this->generateMaSanPham($data['loai_san_pham'] ?? null);
                }

                $result = SanPham::create([
                    'ma_san_pham'        => $data['ma_san_pham'],
                    'ten_san_pham'       => $data['ten_san_pham'],
         'danh_muc_id'        => $data['danh_muc_id'] ?? null, // cho phép NULL nếu không chọn danh mục

                    'gia_nhap_mac_dinh'  => $data['gia_nhap_mac_dinh'],
                    'gia_dat_truoc_3n'   => $data['gia_dat_truoc_3n'] ?? 0,
                    'ty_le_chiet_khau'   => $data['ty_le_chiet_khau']   ?? 0,
                    'muc_loi_nhuan'      => $data['muc_loi_nhuan']      ?? 0,
                    'so_luong_canh_bao'  => $data['so_luong_canh_bao']  ?? 0,
                    'loai_san_pham'      => $data['loai_san_pham'],
                        'is_package'        => !empty($data['is_package']) ? 1 : 0,

                    'ghi_chu'            => $data['ghi_chu'] ?? null,
                    'trang_thai'         => $data['trang_thai'],
                ]);

                // Đơn vị tính
                if (isset($data['don_vi_tinh_id'])) {
                    $result->donViTinhs()->attach($data['don_vi_tinh_id'], [
                        'nguoi_tao'      => Auth::user()->id,
                        'nguoi_cap_nhat' => Auth::user()->id
                    ]);
                }

                // Nhà cung cấp
                if (isset($data['nha_cung_cap_id'])) {
                    $result->nhaCungCaps()->attach($data['nha_cung_cap_id'], [
                        'nguoi_tao'      => Auth::user()->id,
                        'nguoi_cap_nhat' => Auth::user()->id
                    ]);
                }

                // Thêm ảnh nếu có
                if (!empty($data['image'])) {
                    $result->images()->create(['path' => $data['image']]);
                }

// ✅ Nếu là GÓI DỊCH VỤ (is_package=1) + có package_items => lưu chi tiết vào event_package_items
if (!empty($data['package_items']) && is_array($data['package_items']) && !empty($data['is_package'])) {
    $this->syncPackageItems($result, $data['package_items'], false);
}


                return $result->fresh(['images', 'danhMuc', 'packageItems.item']);
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Cập nhật dữ liệu
     * - Nếu request có package_items => xoá hết cũ, ghi lại mới
     */
    public function update($id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $model = SanPham::findOrFail($id);

                $sanPhamData = $data;
                unset(
                    $sanPhamData['don_vi_tinh_id'],
                    $sanPhamData['nha_cung_cap_id'],
                    $sanPhamData['package_items']
                );

                $model->update($sanPhamData);

                // Cập nhật ảnh nếu có
                if (!empty($data['image'])) {
                    $model->images()->get()->each(function ($image) use ($data) {
                        $image->update(['path' => $data['image']]);
                    });
                }

                // Đơn vị tính
                if (isset($data['don_vi_tinh_id'])) {
                    $ids = is_array($data['don_vi_tinh_id']) ? $data['don_vi_tinh_id'] : [$data['don_vi_tinh_id']];
                    $ids = array_map('intval', $ids);

                    $pivot = [];
                    foreach ($ids as $idDvt) {
                        $pivot[$idDvt] = [
                            'nguoi_tao'      => Auth::id(),
                            'nguoi_cap_nhat' => Auth::id(),
                        ];
                    }

                    $model->donViTinhs()->sync($pivot);
                }

                // Nhà cung cấp
                if (isset($data['nha_cung_cap_id'])) {
                    $ids = is_array($data['nha_cung_cap_id']) ? $data['nha_cung_cap_id'] : [$data['nha_cung_cap_id']];
                    $ids = array_map('intval', $ids);

                    $pivot = [];
                    foreach ($ids as $idNcc) {
                        $pivot[$idNcc] = [
                            'nguoi_tao'      => Auth::id(),
                            'nguoi_cap_nhat' => Auth::id(),
                        ];
                    }

                    $model->nhaCungCaps()->sync($pivot);
                }

// Nếu request có key package_items VÀ is_package = 1 => sync lại chi tiết gói
if (array_key_exists('package_items', $data) && !empty($model->is_package)) {
    $items = $data['package_items'] ?? [];
    $this->syncPackageItems($model, $items, true);
}


                return $model->fresh(['images', 'danhMuc', 'packageItems.item']);
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $model = SanPham::findOrFail($id);

                // Xóa ảnh nếu có
                $model->images()->get()->each(function ($image) {
                    $image->delete();
                });

                $model->donViTinhs()->detach();
                $model->nhaCungCaps()->detach();

                // event_package_items đã có onDelete('cascade') theo migration
                return $model->delete();
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách SanPham dạng option cho combobox (tạo đơn hàng)
     */
    public function getOptions(array $params = [])
    {
        $qRaw = trim((string)($params['search'] ?? $params['q'] ?? $params['keyword'] ?? ''));
        $q    = mb_strtolower($qRaw, 'UTF-8');
        $digits = preg_replace('/\D+/', '', $qRaw);

        $query = SanPham::query()->withoutGlobalScopes(['withUserNames']);

        // Optional filter theo loại & danh mục
        if (!empty($params['loai_san_pham'])) {
            $query->where('san_phams.loai_san_pham', $params['loai_san_pham']);
        }
        if (!empty($params['danh_muc_id'])) {
            $query->where('san_phams.danh_muc_id', $params['danh_muc_id']);
        }

        if ($q !== '') {
            $isNumeric = ($digits !== '') && ctype_digit($digits);
            if ($isNumeric) {
                $query->where(function ($x) use ($digits) {
                    $x->where('san_phams.ma_san_pham', '=', $digits)
                        ->orWhere('san_phams.ma_san_pham', 'like', $digits . '%')
                        ->orWhere('san_phams.ma_san_pham', 'like', '%' . $digits . '%');
                })->orderByRaw('CASE 
              WHEN san_phams.ma_san_pham = ? THEN 0
              WHEN san_phams.ma_san_pham LIKE ? THEN 1
              ELSE 2 END', [$digits, $digits . '%'])
                    ->orderBy('san_phams.ma_san_pham');
            } else {
                $query->whereRaw('LOWER(san_phams.ten_san_pham) LIKE ?', ['%' . $q . '%'])
                    ->orderBy('san_phams.ten_san_pham');
            }
        } else {
            $query->orderBy('san_phams.ten_san_pham');
        }

        $rows = $query->select([
            'san_phams.id',
            'san_phams.ma_san_pham',
            'san_phams.ten_san_pham',
        ])
            ->limit(50)
            ->get();

        return $rows->map(function ($r) {
            $name = (string)$r->ten_san_pham;
            $code = (string)$r->ma_san_pham;
            $hasCodeInName = (bool)preg_match('/(^|[^0-9])' . preg_quote($code, '/') . '($|[^0-9])/u', $name);
            $label = $hasCodeInName ? $name : "{$name} - {$code}";
            return ['value' => (int)$r->id, 'label' => $label];
        });
    }

    /**
     * Lấy danh sách SanPham dạng option theo NhaCungCap
     */
    public function getOptionsByNhaCungCap($nhaCungCapId)
    {
        return SanPham::whereHas('nhaCungCaps', function ($query) use ($nhaCungCapId) {
            $query->withoutGlobalScope('withUserNames')
                ->where('nha_cung_caps.id', $nhaCungCapId)
                ->where('san_phams.loai_san_pham', '!=', 'SP_SAN_XUAT');
        })
            ->withoutGlobalScope('withUserNames')
            ->leftJoin('loai_san_pham_masters as lsp', 'lsp.code', '=', 'san_phams.loai_san_pham')
            ->select(
                'san_phams.id as value',
                DB::raw('CONCAT(san_phams.ten_san_pham, " (", COALESCE(lsp.ten_hien_thi, san_phams.loai_san_pham), ")") as label')
            )
            ->get();
    }

    /**
     * Lấy danh sách Lô Sản Phẩm dạng option theo Sản phẩm
     */
    public function getOptionsLoSanPhamBySanPhamIdAndDonViTinhId($sanPhamId, $donViTinhId)
    {
        $loSanPham = KhoTong::where('kho_tongs.san_pham_id', $sanPhamId)
            ->where('kho_tongs.don_vi_tinh_id', $donViTinhId)
            ->leftJoin('chi_tiet_phieu_nhap_khos', 'kho_tongs.ma_lo_san_pham', '=', 'chi_tiet_phieu_nhap_khos.ma_lo_san_pham')
            ->withoutGlobalScope('withUserNames')
            ->select(
                'kho_tongs.ma_lo_san_pham as value',
                DB::raw('CONCAT(kho_tongs.ma_lo_san_pham, " - NSX: ", DATE_FORMAT(chi_tiet_phieu_nhap_khos.ngay_san_xuat, "%d/%m/%Y"), " - HSD: ", DATE_FORMAT(chi_tiet_phieu_nhap_khos.ngay_het_han, "%d/%m/%Y"), " - SL Tồn: ", kho_tongs.so_luong_ton, " - HSD Còn lại: ", DATEDIFF(chi_tiet_phieu_nhap_khos.ngay_het_han, CURDATE()), " ngày") as label'),
                DB::raw('DATEDIFF(chi_tiet_phieu_nhap_khos.ngay_het_han, CURDATE()) as hsd_con_lai')
            )
            ->orderBy('hsd_con_lai', 'asc')
            ->get();

        return $loSanPham;
    }

    /**
     * ✅ Helper: sync danh sách item bên trong gói dịch vụ
     */
    protected function syncPackageItems(SanPham $package, array $items, bool $clearOld = true): void
    {
        if ($clearOld) {
            $package->packageItems()->delete();
        }

        foreach ($items as $row) {
            if (empty($row['item_id'])) {
                continue;
            }

            $soLuong   = isset($row['so_luong']) ? (float)$row['so_luong'] : 0;
            $donGia    = isset($row['don_gia']) ? (int)$row['don_gia'] : 0;
            $thanhTien = isset($row['thanh_tien'])
                ? (int)$row['thanh_tien']
                : (int)round($soLuong * $donGia);

            $package->packageItems()->create([
                'item_id'     => (int)$row['item_id'],
                'item_type'   => $row['item_type'] ?? 'SAN_PHAM',
                'so_luong'    => $soLuong,
                'don_vi_tinh' => $row['don_vi_tinh'] ?? null,
                'don_gia'     => $donGia,
                'thanh_tien'  => $thanhTien,
                'ghi_chu'     => $row['ghi_chu'] ?? null,
                'nguoi_tao'   => (string)Auth::id(),
                'nguoi_cap_nhat' => (string)Auth::id(),
            ]);
        }
    }

    /**
     * ✅ Helper: tự sinh mã chi tiết dịch vụ / thiết bị / gói
     * - DVNCC / DVSX / NL / GOI... tuỳ theo loai_san_pham
     */
    protected function generateMaSanPham(?string $loaiSanPham): string
    {
        // Prefix theo loại
        switch ($loaiSanPham) {
            case 'SP_NHA_CUNG_CAP':
                $prefix = 'DVNCC';
                break;
            case 'SP_SAN_XUAT':
                $prefix = 'DVSX';
                break;
            case 'NGUYEN_LIEU':
                $prefix = 'NL';
                break;
            case 'GOI_DICH_VU':
                $prefix = 'GOI';
                break;
            default:
                $prefix = 'DV';
        }

        $lastCode = SanPham::where('ma_san_pham', 'like', $prefix . '%')
            ->orderBy('ma_san_pham', 'desc')
            ->value('ma_san_pham');

        $nextNumber = 1;
        if ($lastCode && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastCode, $m)) {
            $nextNumber = (int)$m[1] + 1;
        }

        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
