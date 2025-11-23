<?php

namespace App\Modules\GoiDichVu;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\GoiDichVuCategory;
use App\Models\GoiDichVuGroup;
use Exception;
use Illuminate\Support\Facades\DB;

class GoiDichVuCategoryService
{
    /**
     * Lấy danh sách NHÓM GÓI DỊCH VỤ (tầng 2)
     * - Dùng Eloquent quan hệ group() để lấy tên nhóm cha
     * - Sau đó map thêm field ten_nhom_group cho FE (không join SQL thủ công)
     */
    public function getAll(array $params = [])
    {
        try {
            // Base query: load luôn quan hệ group
            $query = GoiDichVuCategory::query()->with('group');

            // Dùng FilterWithPagination như các module khác
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['goi_dich_vu_categories.*']
            );

            // Map thêm field ten_nhom_group để FE dùng hiển thị / filter
            $rawCollection = $result['collection'];

            // Chuẩn hoá: có thể là Collection hoặc array
            $mapped = collect($rawCollection)->map(function ($item) {
                // $item có thể là model hoặc array tuỳ FilterWithPagination
                if ($item instanceof GoiDichVuCategory) {
                    $item->ten_nhom_group = $item->group->ten_nhom ?? null;
                    return $item;
                }

                // fallback: mảng stdClass/array
                $arr = (array) $item;
                $group = $arr['group'] ?? null;
                if (is_array($group) || $group instanceof \ArrayAccess) {
                    $arr['ten_nhom_group'] = $group['ten_nhom'] ?? null;
                } else {
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
            // Để log rõ lỗi gốc
            throw new Exception('Lỗi khi lấy danh sách nhóm gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Lấy chi tiết 1 nhóm gói dịch vụ theo ID
     */
    public function getById(int $id)
    {
        $data = GoiDichVuCategory::with('group')->find($id);

        if (! $data) {
            return CustomResponse::error('Nhóm gói dịch vụ không tồn tại');
        }

        return $data;
    }

    /**
     * Tạo mới nhóm gói dịch vụ
     *
     * - Nếu ma_nhom_goi để trống → tự sinh: GGC0001, GGC0002, ...
     */
    public function create(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                // Kiểm tra Group cha có tồn tại không
                $groupId = $data['group_id'] ?? null;
                if (! $groupId || ! GoiDichVuGroup::whereKey($groupId)->exists()) {
                    return CustomResponse::error('Nhóm danh mục gói dịch vụ (group_id) không hợp lệ');
                }

                // Nếu không truyền trạng thái, mặc định = 1
                if (! array_key_exists('trang_thai', $data)) {
                    $data['trang_thai'] = 1;
                }

                // Nếu không truyền / để trống ma_nhom_goi → tự sinh mã
                if (empty($data['ma_nhom_goi'])) {
                    $data['ma_nhom_goi'] = $this->generateMaNhomGoi();
                }

                $category = GoiDichVuCategory::create($data);

                return $category;
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi tạo nhóm gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật nhóm gói dịch vụ
     * - Không tự sinh lại mã nếu đã có (giữ ổn định ma_nhom_goi)
     */
    public function update(int $id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $category = GoiDichVuCategory::findOrFail($id);

                // Nếu có gửi group_id mới thì verify luôn
                if (array_key_exists('group_id', $data)) {
                    $groupId = $data['group_id'];
                    if ($groupId && ! GoiDichVuGroup::whereKey($groupId)->exists()) {
                        return CustomResponse::error('Nhóm danh mục gói dịch vụ (group_id) không hợp lệ');
                    }
                }

                // Handle ma_nhom_goi:
                // - Nếu không gửi field → giữ nguyên
                // - Nếu gửi rỗng mà DB đã có → giữ mã cũ
                if (! array_key_exists('ma_nhom_goi', $data)) {
                    unset($data['ma_nhom_goi']);
                } elseif (empty($data['ma_nhom_goi']) && $category->ma_nhom_goi) {
                    unset($data['ma_nhom_goi']);
                }

                $category->update($data);

                return $category->fresh();
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi cập nhật nhóm gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Xoá nhóm gói dịch vụ
     * - onDelete('cascade') sẽ xoá luôn các gói + item bên dưới
     */
    public function delete(int $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $category = GoiDichVuCategory::findOrFail($id);

                $category->delete();

                return true;
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi xoá nhóm gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Lấy danh sách nhóm gói dịch vụ dạng options cho combobox
     * - value: id
     * - label: ten_nhom_goi
     *
     * Có thể filter theo group_id nếu FE truyền.
     */
    public function getOptions(array $params = [])
    {
        $query = GoiDichVuCategory::query()
            ->where('trang_thai', 1);

        if (!empty($params['group_id'])) {
            $query->where('group_id', $params['group_id']);
        }

        return $query
            ->select('id as value', 'ten_nhom_goi as label')
            ->orderBy('ten_nhom_goi')
            ->get();
    }

    /**
     * Tự sinh mã nhóm gói (ma_nhom_goi) cho GoiDichVuCategory
     *
     * Pattern: GGC0001, GGC0002, ...
     */
    protected function generateMaNhomGoi(): string
    {
        $prefix = 'GGC';

        $lastCode = GoiDichVuCategory::where('ma_nhom_goi', 'like', $prefix . '%')
            ->orderBy('ma_nhom_goi', 'desc')
            ->value('ma_nhom_goi');

        $nextNumber = 1;

        if ($lastCode && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastCode, $m)) {
            $nextNumber = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
