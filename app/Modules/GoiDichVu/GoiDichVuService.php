<?php

namespace App\Modules\GoiDichVu;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\GoiDichVu;
use App\Models\GoiDichVuCategory;
use App\Models\GoiDichVuItem;
use App\Models\SanPham;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\GoiDichVuGroup;
use Illuminate\Support\Str;

class GoiDichVuService
{
    /**
     * Lấy danh sách GÓI DỊCH VỤ (tầng 3)
     * - Không join SQL thủ công, dùng with('category.group')
     * - Map thêm ten_nhom_goi, ten_nhom_group cho FE
     */
    public function getAll(array $params = [])
    {
        try {
            // Base query: load luôn category + group
            $query = GoiDichVu::query()->with(['category.group']);

            // Dùng FilterWithPagination giống các module khác
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['goi_dich_vus.*']
            );

            $rawCollection = $result['collection'];

            // Map thêm field ten_nhom_goi, ten_nhom_group
            $mapped = collect($rawCollection)->map(function ($item) {
                if ($item instanceof GoiDichVu) {
                    $item->ten_nhom_goi   = $item->category->ten_nhom_goi ?? null;
                    $item->ten_nhom_group = optional($item->category->group)->ten_nhom;
                    return $item;
                }

                // fallback nếu FilterWithPagination trả array/stdClass
                $arr = (array) $item;
                $cat = $arr['category'] ?? null;
                if (is_array($cat) || $cat instanceof \ArrayAccess) {
                    $arr['ten_nhom_goi'] = $cat['ten_nhom_goi'] ?? null;
                    $grp = $cat['group'] ?? null;
                    if (is_array($grp) || $grp instanceof \ArrayAccess) {
                        $arr['ten_nhom_group'] = $grp['ten_nhom'] ?? null;
                    } else {
                        $arr['ten_nhom_group'] = null;
                    }
                } else {
                    $arr['ten_nhom_goi'] = null;
                    $arr['ten_nhom_group'] = null;
                }
                return $arr;
            });

            return [
                'data' => $mapped,
                'total' => $result['total'],
                'pagination' => [
                    'current_page'  => $result['current_page'],
                    'last_page'     => $result['last_page'],
                    'from'          => $result['from'],
                    'to'            => $result['to'],
                    'total_current' => $result['total_current'],
                ],
            ];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lấy danh sách gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Lấy chi tiết 1 gói dịch vụ theo ID
     * - Bao gồm: category.group + items.sanPham
     */
    public function getById(int $id)
    {
        $data = GoiDichVu::with([
            'category.group',
            'items.sanPham',
        ])->find($id);

        if (! $data) {
            return CustomResponse::error('Gói dịch vụ không tồn tại');
        }

        return $data;
    }

    /**
     * Tạo mới gói dịch vụ
     *
     * $data:
     *  - category_id
     *  - ma_goi, ten_goi, mo_ta_ngan, mo_ta_chi_tiet
     *  - gia_niem_yet, gia_khuyen_mai, trang_thai
     *  - items: [
     *      ['san_pham_id', 'so_luong', 'don_gia', 'thanh_tien', 'ghi_chu', 'thu_tu'],
     *    ]
     *
     * - Nếu ma_goi để trống → tự sinh mã theo pattern: GDV0001, GDV0002, ...
     */
    public function create(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $categoryId = $data['category_id'] ?? null;
                /** @var \App\Models\GoiDichVuCategory|null $category */
                $category = $categoryId
                    ? GoiDichVuCategory::with('group')->find($categoryId)
                    : null;

                if (! $category) {
                    return CustomResponse::error('Nhóm gói dịch vụ (category_id) không hợp lệ');
                }

                $items = $data['items'] ?? [];
                unset($data['items']);

if (! array_key_exists('trang_thai', $data)) {
    $data['trang_thai'] = 1;
}

// 🔹 Loại gói: 0 = Trọn gói, 1 = Thành phần
if (! array_key_exists('package_mode', $data)) {
    // Không gửi thì mặc định là TRỌN GÓI
    $data['package_mode'] = 0;
} else {
    // Có gửi thì ép về 0|1 cho chắc
    $data['package_mode'] = (int) $data['package_mode'] === 1 ? 1 : 0;
}

                          // 🔹 Nếu ma_goi để trống → tự sinh theo TÊN Nhóm gói dịch vụ (category.ten_nhom_goi)
                if (empty($data['ma_goi'])) {
                    $data['ma_goi'] = $this->generateMaGoiForCategory($category);
                }


                $package = GoiDichVu::create($data);


                if (! empty($items) && is_array($items)) {
                    $this->syncItems($package, $items, false);
                }

                return $package->load(['category.group', 'items.sanPham']);
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi tạo gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật gói dịch vụ
     * - Nếu có "items" => xoá hết chi tiết cũ, ghi lại mới
     * - Không tự động đổi ma_goi nếu đã có (tránh loạn mã)
     */
    public function update(int $id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $package = GoiDichVu::findOrFail($id);

                if (array_key_exists('category_id', $data)) {
                    $categoryId = $data['category_id'];
                    if (! $categoryId || ! GoiDichVuCategory::whereKey($categoryId)->exists()) {
                        return CustomResponse::error('Nhóm gói dịch vụ (category_id) không hợp lệ');
                    }
                }

                // Xử lý ma_goi khi update:
                // - Nếu không gửi field ma_goi: giữ nguyên
                // - Nếu gửi rỗng nhưng DB đã có mã: giữ nguyên mã cũ
                if (! array_key_exists('ma_goi', $data)) {
                    unset($data['ma_goi']);
                } elseif (empty($data['ma_goi']) && $package->ma_goi) {
                    unset($data['ma_goi']);
                }

                $items = $data['items'] ?? null;
                unset($data['items']);
// 🔹 Nếu FE gửi package_mode thì ép về 0|1
if (array_key_exists('package_mode', $data)) {
    $data['package_mode'] = (int) $data['package_mode'] === 1 ? 1 : 0;
}
                $package->update($data);

                if (is_array($items)) {
                    $this->syncItems($package, $items, true);
                }

                return $package->fresh(['category.group', 'items.sanPham']);
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi cập nhật gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Xoá gói dịch vụ
     */
    public function delete(int $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $package = GoiDichVu::findOrFail($id);
                $package->delete();

                return true;
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi xoá gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Lấy danh sách gói dịch vụ dạng options cho combobox
     */
    public function getOptions(array $params = [])
    {
        $query = GoiDichVu::query()
            ->where('trang_thai', 1);

        if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }

        if (!empty($params['group_id'])) {
            $groupId = (int) $params['group_id'];
            $query->whereHas('category', function ($q) use ($groupId) {
                $q->where('group_id', $groupId);
            });
        }

        return $query
            ->select('id as value', 'ten_goi as label')
            ->orderBy('ten_goi')
            ->get();
    }

    /**
     * Helper: sync danh sách chi tiết gói (items)
     */
    protected function syncItems(GoiDichVu $package, array $items, bool $clearOld = true): void
    {
        if ($clearOld) {
            GoiDichVuItem::where('goi_dich_vu_id', $package->id)->delete();
        }

        $order = 1;

        foreach ($items as $row) {
            $sanPhamId = $row['san_pham_id'] ?? null;
            if (! $sanPhamId) {
                continue;
            }

            $soLuong = isset($row['so_luong']) ? (float) $row['so_luong'] : 0;

            // Đơn giá: nếu không truyền, lấy giá từ san_phams
            $donGia = isset($row['don_gia']) ? (int) $row['don_gia'] : null;
            if ($donGia === null) {
                $giaRef = SanPham::whereKey($sanPhamId)->value('gia_nhap_mac_dinh');
                $donGia = $giaRef !== null ? (int) $giaRef : 0;
            }

            // Thành tiền: nếu không truyền, tính = so_luong * don_gia
            $thanhTien = isset($row['thanh_tien'])
                ? (int) $row['thanh_tien']
                : (int) round($soLuong * $donGia);

            $thuTu = isset($row['thu_tu']) ? (int) $row['thu_tu'] : $order++;

            GoiDichVuItem::create([
                'goi_dich_vu_id'  => $package->id,
                'san_pham_id'     => (int) $sanPhamId,
                'so_luong'        => $soLuong,
                'don_gia'         => $donGia,
                'thanh_tien'      => $thanhTien,
                'ghi_chu'         => $row['ghi_chu'] ?? null,
                'thu_tu'          => $thuTu,
                'nguoi_tao'       => (string) (Auth::id() ?? ''),
                'nguoi_cap_nhat'  => (string) (Auth::id() ?? ''),
            ]);
        }
    }


    /**
     * Tự sinh mã gói (ma_goi) theo TÊN Nhóm gói dịch vụ (tầng 2)
     *
     * Ví dụ:
     *  - Nhóm gói: "Hệ thống âm thanh"  -> GDVHTA0001
     *  - Nhóm gói: "Hệ thống ánh sáng" -> GDVHTS0001
     */
    protected function generateMaGoiForCategory(GoiDichVuCategory $category): string
    {
        $basePrefix = 'GDV';

        // Lấy tên Nhóm gói dịch vụ (ten_nhom_goi)
        $name = (string) $category->ten_nhom_goi;   // VD: "Hệ thống âm thanh"
        $slug = Str::slug($name, ' ');              // "he thong am thanh"
        $words = array_filter(explode(' ', $slug)); // ["he","thong","am","thanh"]

        $letters = '';
        foreach ($words as $w) {
            $letters .= strtoupper(substr($w, 0, 1)); // "HTAT"
        }

        // Lấy tối đa 3 ký tự: HTA, HTS, ...
        $catPrefix = substr($letters, 0, 3);

        // Prefix hoàn chỉnh: GDV + HTA -> GDVHTA
        $prefix = $basePrefix . $catPrefix;

        // Tìm mã cuối cùng với prefix này rồi +1
        $lastCode = GoiDichVu::where('ma_goi', 'like', $prefix . '%')
            ->orderBy('ma_goi', 'desc')
            ->value('ma_goi');

        $nextNumber = 1;
        if ($lastCode && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastCode, $m)) {
            $nextNumber = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }



/**
 * Tự sinh mã gói (ma_goi) theo Nhóm gói dịch vụ
 *
 * Ví dụ:
 *  - Nhóm gói: "Hệ thống âm thanh" → slug "he thong am thanh"
 *    → ký tự đầu mỗi từ: H T A T → lấy 3 chữ đầu "HTA"
 *    → Mã gói: GDVHTA0001, GDVHTA0002, ...
 *
 *  - Nhóm gói: "Hệ thống ánh sáng" → "he thong anh sang" → H T A S → "HTA"
 *    → Mã gói: GDVHTS0001, ...
 *
 * Nếu không tìm được nhóm → fallback về prefix "GDV".
 */
protected function generateMaGoi(?int $groupId = null): string
{
    // Base prefix
    $basePrefix = 'GDV';

    // Tạo prefix theo tên Nhóm gói
    $groupPrefix = '';

    if ($groupId) {
        /** @var \App\Models\GoiDichVuGroup|null $group */
        $group = GoiDichVuGroup::find($groupId);
        if ($group) {
            $name = (string) $group->ten_nhom;               // ví dụ: "Hệ thống âm thanh"
            $slug = Str::slug($name, ' ');                   // "he thong am thanh"
            $words = array_filter(explode(' ', $slug));      // ["he","thong","am","thanh"]

            $letters = '';
            foreach ($words as $w) {
                $letters .= strtoupper(substr($w, 0, 1));    // "HTAT"
            }

            // Lấy tối đa 3 chữ cái đầu cho gọn
            $groupPrefix = substr($letters, 0, 3);           // "HTA"
        }
    }

    // Ghép prefix: GDV + HTA → GDVHTA
    $prefix = $basePrefix . $groupPrefix;

    // Tìm mã cuối cùng với prefix này rồi +1
    $lastCode = GoiDichVu::where('ma_goi', 'like', $prefix . '%')
        ->orderBy('ma_goi', 'desc')
        ->value('ma_goi');

    $nextNumber = 1;
    if ($lastCode && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastCode, $m)) {
        $nextNumber = (int) $m[1] + 1;
    }

    // 4 chữ số cuối: 0001, 0002, ...
    return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
}

}
