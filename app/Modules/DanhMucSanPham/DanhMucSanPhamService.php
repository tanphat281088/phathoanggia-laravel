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
                        $data['group_code'] ?? null
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

    public function getOptions()
    {
        return DanhMucSanPham::select('id as value', 'ten_danh_muc as label')
            ->orderBy('ten_danh_muc')
            ->get();
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
     * Tự sinh mã danh mục theo group_code ngắn
     *
     * - NS   -> NS0001, NS0002, ...
     * - CSVC -> CSVC0001, ...
     * - TIEC -> TIEC0001, ...
     * - TD   -> TD0001, ...
     * - CPK  -> CPK0001, ...
     * - null -> DM0001, ...
     */
    protected function generateMaDanhMuc(?string $groupCode): string
    {
        $prefixMap = [
            'NS'   => 'NS',
            'CSVC' => 'CSVC',
            'TIEC' => 'TIEC',
            'TD'   => 'TD',
            'CPK'  => 'CPK',
        ];

        $prefix = $prefixMap[$groupCode] ?? 'DM';

        $lastCode = DanhMucSanPham::where('ma_danh_muc', 'like', $prefix . '%')
            ->orderBy('ma_danh_muc', 'desc')
            ->value('ma_danh_muc');

        $nextNumber = 1;
        if ($lastCode && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastCode, $m)) {
            $nextNumber = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
