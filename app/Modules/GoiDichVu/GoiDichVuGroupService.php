<?php

namespace App\Modules\GoiDichVu;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\GoiDichVuGroup;
use Exception;
use Illuminate\Support\Facades\DB;

class GoiDichVuGroupService
{
    /**
     * Lấy danh sách nhóm danh mục gói dịch vụ (tầng 1)
     * - Hỗ trợ filter + phân trang theo chuẩn FilterWithPagination
     */
    public function getAll(array $params = [])
    {
        try {
            $query = GoiDichVuGroup::query();

            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['goi_dich_vu_groups.*']
            );

            return [
                'data' => $result['collection'],
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
            throw new Exception('Lỗi khi lấy danh sách nhóm gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Lấy chi tiết 1 nhóm theo ID
     */
    public function getById(int $id)
    {
        $data = GoiDichVuGroup::find($id);

        if (! $data) {
            return CustomResponse::error('Nhóm danh mục gói dịch vụ không tồn tại');
        }

        return $data;
    }

    /**
     * Tạo mới nhóm danh mục gói dịch vụ
     *
     * - Nếu ma_nhom để trống → tự sinh theo pattern: GGV0001, GGV0002, ...
     * - Nếu ma_nhom đã nhập tay → giữ nguyên, không đụng vào.
     */
    public function create(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                // Nếu không truyền trang_thai, default = 1
                if (! array_key_exists('trang_thai', $data)) {
                    $data['trang_thai'] = 1;
                }

                // Nếu không truyền/để trống ma_nhom -> tự sinh mã
                if (empty($data['ma_nhom'])) {
                    $data['ma_nhom'] = $this->generateMaNhom();
                }

                $group = GoiDichVuGroup::create($data);

                return $group;
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi tạo nhóm danh mục gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật nhóm danh mục gói dịch vụ
     * - Không tự động đổi ma_nhom nếu đã có (tránh làm loạn mã cũ).
     */
    public function update(int $id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $group = GoiDichVuGroup::findOrFail($id);

                // Nếu người dùng không gửi ma_nhom trong update thì giữ giá trị cũ
                if (! array_key_exists('ma_nhom', $data)) {
                    unset($data['ma_nhom']);
                } elseif (empty($data['ma_nhom']) && $group->ma_nhom) {
                    // Nếu cố tình gửi rỗng nhưng DB đã có mã -> giữ mã cũ
                    unset($data['ma_nhom']);
                }

                $group->update($data);

                return $group->fresh();
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi cập nhật nhóm danh mục gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Xoá nhóm danh mục gói dịch vụ
     * - onDelete('cascade') sẽ xoá luôn categories/packages/items bên dưới
     */
    public function delete(int $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $group = GoiDichVuGroup::findOrFail($id);

                $group->delete();

                return true;
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi xoá nhóm danh mục gói dịch vụ: ' . $e->getMessage());
        }
    }

    /**
     * Lấy danh sách nhóm dạng options cho combobox
     * - value: id
     * - label: ten_nhom
     */
    public function getOptions()
    {
        return GoiDichVuGroup::select('id as value', 'ten_nhom as label')
            ->where('trang_thai', 1)
            ->orderBy('ten_nhom')
            ->get();
    }

    /**
     * Tự sinh mã nhóm (ma_nhom) cho GoiDichVuGroup
     *
     * Pattern: GGV0001, GGV0002, ...
     */
    protected function generateMaNhom(): string
    {
        $prefix = 'GGV';

        // Lấy mã lớn nhất hiện tại bắt đầu bằng GGV
        $lastCode = GoiDichVuGroup::where('ma_nhom', 'like', $prefix . '%')
            ->orderBy('ma_nhom', 'desc')
            ->value('ma_nhom');

        $nextNumber = 1;

        if ($lastCode && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastCode, $m)) {
            $nextNumber = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
