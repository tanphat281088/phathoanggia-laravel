<?php

namespace App\Modules\DanhMucSanPham;

use App\Models\DanhMucSanPham;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;

class DanhMucSanPhamService
{
    public function getAll(array $params = [])
    {
        try {
            $query = DanhMucSanPham::query()->with('images');

            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['danh_muc_san_phams.*']
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
            throw new Exception('Lỗi khi lấy danh sách: ' . $e->getMessage());
        }
    }

    public function getById($id)
    {
        $data = DanhMucSanPham::with('images')->find($id);
        if (!$data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }
        return $data;
    }

    public function create(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                // Chuẩn hoá group_code về code NGẮN (NS, CSVC, TIEC, TD, CPK)
                $data['group_code'] = $this->normalizeGroupCode($data['group_code'] ?? null);

                // Tự sinh mã nếu không có
                if (empty($data['ma_danh_muc'])) {
                    $data['ma_danh_muc'] = $this->generateMaDanhMuc(
                        $data['group_code'] ?? null,
                        $data['parent_id'] ?? null
                    );
                }

                $result = DanhMucSanPham::create($data);

                if (!empty($data['image'])) {
                    $result->images()->create([
                        'path' => $data['image'],
                    ]);
                }

                return $result;
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $model = DanhMucSanPham::findOrFail($id);

                if (array_key_exists('group_code', $data)) {
                    $data['group_code'] = $this->normalizeGroupCode($data['group_code']);
                }

                $model->update($data);

                if (!empty($data['image'])) {
                    $model->images()->get()->each(function ($image) use ($data) {
                        $image->update([
                            'path' => $data['image'],
                        ]);
                    });
                }

                return $model->fresh();
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $model = DanhMucSanPham::findOrFail($id);

                $model->images()->get()->each(function ($image) {
                    $image->delete();
                });

                return $model->delete();
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách DanhMucSanPham dạng options cho combobox
     *
     * Hỗ trợ:
     *  - level=1: chỉ lấy tầng 1 (parent_id NULL hoặc 0)
     *  - level=2: chỉ lấy tầng 2 (parent_id > 0)
     *  - group_code:
     *      + Cho phép cả code NGẮN: NS, CSVC, TIEC, TD, CPK
     *      + Và code DÀI cũ: NHAN_SU, CO_SO_VAT_CHAT, THUE_DIA_DIEM, CHI_PHI_KHAC
     *    → sẽ chuẩn hoá về code ngắn rồi lọc theo cột group_code trong DB
     */
    public function getOptions(array $params = [])
    {
        $query = DanhMucSanPham::query()
            ->select('id as value', 'ten_danh_muc as label')
            ->orderBy('ten_danh_muc');

        $level = $params['level'] ?? null;

        // 🔹 Lọc theo tầng 1 / tầng 2
        if ($level == 1) {
            // Tầng 1: không có parent_id
            $query->where(function ($q) {
                $q->whereNull('parent_id')
                  ->orWhere('parent_id', 0);
            });
        } elseif ($level == 2) {
            // Tầng 2: có parent_id
            $query->where(function ($q) {
                $q->whereNotNull('parent_id')
                  ->where('parent_id', '>', 0);
            });
        }
        // 🔹 Nếu FE gửi parent_id (VD: id của MC, Âm thanh, ...) → chỉ lấy con trực tiếp của nó
        if (!empty($params['parent_id'])) {
            $query->where('parent_id', (int) $params['parent_id']);
        }

        // 🔹 Lọc thêm theo group_code nếu FE truyền (NS/CSVC/TIEC/TD/CPK hoặc mã dài)
        if (!empty($params['group_code'])) {
            $rawGroupCode = (string) $params['group_code'];

            // Dùng lại logic chuẩn hoá: code dài → code ngắn
            $normalized = $this->normalizeGroupCode($rawGroupCode);

            // Nếu normalizeGroupCode trả null (code lạ) thì fallback dùng raw
            $groupCodeToUse = $normalized ?: $rawGroupCode;

            $query->where('group_code', $groupCodeToUse);
        }

        return $query->get();
    }



    /**
     * Chuẩn hóa group_code:
     * - Nhận cả code dài & ngắn, trả về code ngắn (NS, CSVC, TIEC, TD, CPK)
     */
    protected function normalizeGroupCode(?string $groupCode): ?string
    {
        if (!$groupCode) {
            return null;
        }

        $map = [
            'NS'             => 'NS',
            'NHAN_SU'        => 'NS',
            'CSVC'           => 'CSVC',
            'CO_SO_VAT_CHAT' => 'CSVC',
            'TIEC'           => 'TIEC',
            'TD'             => 'TD',
            'THUE_DIA_DIEM'  => 'TD',
            'CPK'            => 'CPK',
            'CHI_PHI_KHAC'   => 'CPK',
        ];

        return $map[$groupCode] ?? null;
    }

    /**
     * Lấy prefix theo group_code ngắn
     */
    protected function getGroupPrefix(?string $groupCode): string
    {
        $prefixMap = [
            'NS'   => 'NS',
            'CSVC' => 'CSVC',
            'TIEC' => 'TIEC',
            'TD'   => 'TD',
            'CPK'  => 'CPK',
        ];

        return $prefixMap[$groupCode] ?? 'DM';
    }

    /**
     * Tự sinh mã danh mục
     *
     * - Nếu KHÔNG có parent_id (tầng 1):
     *      + NS   -> NS0001, NS0002, ...
     *      + CSVC -> CSVC0001, ...
     * - Nếu CÓ parent_id (tầng 2):
     *      + Lấy mã danh mục cha, ví dụ: CSVC0001
     *      + Con sẽ là: CSVC0001-01, CSVC0001-02, ...
     */
    protected function generateMaDanhMuc(?string $groupCode, ?int $parentId = null): string
    {
        // 🔹 Tầng 2: có danh mục cha
        if ($parentId) {
            $parent = DanhMucSanPham::find($parentId);
            $basePrefix = $parent?->ma_danh_muc;

            // Nếu không tìm thấy cha, fallback về prefix theo group_code
            if (!$basePrefix) {
                $basePrefix = $this->getGroupPrefix($groupCode);
            }

            $prefix = $basePrefix . '-';

            $lastCode = DanhMucSanPham::where('ma_danh_muc', 'like', $prefix . '%')
                ->orderBy('ma_danh_muc', 'desc')
                ->value('ma_danh_muc');

            $nextNumber = 1;
            if ($lastCode && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastCode, $m)) {
                $nextNumber = (int) $m[1] + 1;
            }

            // 2 chữ số cho con: 01, 02, 03,...
            return $prefix . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
        }

        // 🔹 Tầng 1: không có danh mục cha → dùng prefix theo group_code
        // 🔹 Tầng 1: không có danh mục cha → dùng prefix theo group_code
        $prefix = $this->getGroupPrefix($groupCode);

        // Đếm số danh mục TẦNG 1 hiện có cùng group_code
        // (kể cả các bản ghi cũ đang bị trùng mã)
        $count = DanhMucSanPham::where('group_code', $groupCode)
            ->where(function ($q) {
                $q->whereNull('parent_id')
                  ->orWhere('parent_id', 0);
            })
            ->count();

        $nextNumber = $count + 1;

        // 4 chữ số cho tầng 1: 0001, 0002,...
        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

    }
}
