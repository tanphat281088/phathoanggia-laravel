<?php

namespace App\Modules\KhachHang;

use App\Models\KhachHang;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;

class KhachHangService
{
    /**
     * Lấy tất cả dữ liệu khách hàng (CRM Sự kiện)
     *
     * Hỗ trợ lọc:
     * - customer_type: 0=Event,1=Wedding,2=Agency
     * - is_system_customer: 1=Hệ thống,0=Vãng lai
     *
     * Hỗ trợ keyword (tùy vào FilterWithPagination và params FE gửi):
     * - keyword / q / search / term
     * - Tìm theo: ma_kh, ten_khach_hang, company_name, bride_name, groom_name, so_dien_thoai, email
     */
    public function getAll(array $params = [])
    {
        try {
            $query = KhachHang::query()
                ->with(
                    // Nếu model KhachHang có quan hệ images, vẫn giữ lại cho avatar / đính kèm
                    'images',
                    // Hạng khách hàng (Regular / VIP / ...)
                    'loaiKhachHang:id,ten_loai_khach_hang,gia_tri_uu_dai'
                );

            // ====== 1) Lọc theo loại khách: Event / Wedding / Agency ======
            if (isset($params['customer_type']) && $params['customer_type'] !== '') {
                $type = (int) $params['customer_type'];
                if (in_array($type, [KhachHang::TYPE_EVENT, KhachHang::TYPE_WEDDING, KhachHang::TYPE_AGENCY], true)) {
                    $query->where('customer_type', $type);
                }
            }

            // Cho phép alias khác từ FE (nếu sau này bạn dùng): type / segment
            if (isset($params['type']) && $params['type'] !== '') {
                $map = [
                    'event'   => KhachHang::TYPE_EVENT,
                    'wedding' => KhachHang::TYPE_WEDDING,
                    'agency'  => KhachHang::TYPE_AGENCY,
                ];
                $key = strtolower((string) $params['type']);
                if (isset($map[$key])) {
                    $query->where('customer_type', $map[$key]);
                }
            }

            // ====== 2) Lọc theo level: Khách hệ thống / Vãng lai ======
            if (isset($params['is_system_customer']) && $params['is_system_customer'] !== '') {
                // params có thể là "1"/"0" hoặc true/false
                $isSystem = filter_var($params['is_system_customer'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isSystem === true) {
                    $query->where('is_system_customer', 1);
                } elseif ($isSystem === false) {
                    $query->where('is_system_customer', 0);
                }
            }

            // Alias dễ nhớ cho FE:
            // - level = system / walkin
            if (isset($params['level']) && $params['level'] !== '') {
                $level = strtolower((string) $params['level']);
                if ($level === 'system') {
                    $query->where('is_system_customer', 1);
                } elseif ($level === 'walkin') {
                    $query->where('is_system_customer', 0);
                }
            }

            // ====== 3) Keyword search (nếu FilterWithPagination không tự lo phần này) ======
            $kw = trim($params['keyword'] ?? $params['q'] ?? $params['search'] ?? $params['term'] ?? '');
            if ($kw !== '') {
                $query->where(function ($q) use ($kw) {
                    $like = '%' . $kw . '%';

                    $q->where('ma_kh', 'like', $like)
                        ->orWhere('ten_khach_hang', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('so_dien_thoai', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('bride_name', 'like', $like)
                        ->orWhere('groom_name', 'like', $like);
                });
            }

            // ====== 4) Giao lại cho FilterWithPagination xử lý paging / sort / filter nâng cao ======
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['khach_hangs.*'] // gồm cả ma_kh, kenh_lien_he, customer_type, ...
            );

            return [
                'data'       => $result['collection'],
                'total'      => $result['total'],
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

    /**
     * Lấy dữ liệu theo ID
     */
    public function getById($id)
    {
        $data = KhachHang::with('images', 'loaiKhachHang')->find($id);

        if (! $data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }
        return $data;
    }

    /**
     * Tạo mới khách hàng
     * - Nếu request KHÔNG truyền ma_kh -> tự cấp theo rule KH + 5 số
     * - Dùng transaction + lock để tránh trùng khi 2 request chạy song song
     */
    public function create(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                /** @var \App\Models\KhachHang $model */
                $model = KhachHang::create($data);

                // chỉ cấp khi không truyền ma_kh
                if (empty($model->ma_kh)) {
                    $model->ma_kh = $this->nextIncrementalCode();
                    $model->save();
                }

                return $model;
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Cập nhật khách hàng
     * - Không đổi ma_kh khi update (đảm bảo tính nhất quán).
     * - Nếu vì lý do nào đó ma_kh còn trống -> tự backfill theo rule mới.
     */
    public function update($id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $model = KhachHang::findOrFail($id);
                $model->update($data);

                if (empty($model->ma_kh)) {
                    $model->ma_kh = $this->nextIncrementalCode();
                    $model->save();
                }

                return $model->fresh();
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa khách hàng
     */
    public function delete($id)
    {
        try {
            $model = KhachHang::findOrFail($id);
            return $model->delete();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách option hiển thị label thân thiện cho Event/Wedding/Agency:
     *
     * - Event / Agency:
     *   "KH00001 - [Company_Name hoặc Tên KH] - SĐT"
     *
     * - Wedding:
     *   "KH00002 - Cô dâu A - Chú rể B - SĐT"
     *
     * Hỗ trợ filter:
     * - keyword / q / search / term
     * - customer_type (0/1/2)
     * - is_system_customer / level (giống getAll)
     */
    public function getOptions(array $params = [])
    {
        $kw    = trim($params['keyword'] ?? $params['q'] ?? $params['search'] ?? $params['term'] ?? '');
        $limit = (int) ($params['limit'] ?? 30);

        $query = KhachHang::query()
            ->select([
                'id as value',
                // Dùng CASE trong SQL để chọn display_name phù hợp theo loại khách
                DB::raw("
                    CONCAT(
                        COALESCE(ma_kh, ''),
                        ' - ',
                        CASE
                            WHEN customer_type = " . KhachHang::TYPE_WEDDING . " 
                                 AND (bride_name IS NOT NULL OR groom_name IS NOT NULL) THEN
                                CONCAT(
                                    COALESCE(bride_name, ''),
                                    CASE 
                                        WHEN bride_name IS NOT NULL AND groom_name IS NOT NULL 
                                        THEN ' - ' 
                                        ELSE '' 
                                    END,
                                    COALESCE(groom_name, '')
                                )
                            WHEN (customer_type = " . KhachHang::TYPE_EVENT . " OR customer_type = " . KhachHang::TYPE_AGENCY . ")
                                 AND company_name IS NOT NULL 
                                 AND company_name <> '' THEN
                                company_name
                            ELSE
                                ten_khach_hang
                        END,
                        ' - ',
                        COALESCE(so_dien_thoai, '')
                    ) AS label
                "),
            ])
            ->orderBy('ma_kh');

        // Lọc theo loại khách nếu FE truyền
        if (isset($params['customer_type']) && $params['customer_type'] !== '') {
            $type = (int) $params['customer_type'];
            if (in_array($type, [KhachHang::TYPE_EVENT, KhachHang::TYPE_WEDDING, KhachHang::TYPE_AGENCY], true)) {
                $query->where('customer_type', $type);
            }
        }

        // Alias: type=event/wedding/agency
        if (isset($params['type']) && $params['type'] !== '') {
            $map = [
                'event'   => KhachHang::TYPE_EVENT,
                'wedding' => KhachHang::TYPE_WEDDING,
                'agency'  => KhachHang::TYPE_AGENCY,
            ];
            $key = strtolower((string) $params['type']);
            if (isset($map[$key])) {
                $query->where('customer_type', $map[$key]);
            }
        }

        // Lọc theo level: hệ thống / vãng lai
        if (isset($params['is_system_customer']) && $params['is_system_customer'] !== '') {
            $isSystem = filter_var($params['is_system_customer'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isSystem === true) {
                $query->where('is_system_customer', 1);
            } elseif ($isSystem === false) {
                $query->where('is_system_customer', 0);
            }
        }

        if (isset($params['level']) && $params['level'] !== '') {
            $level = strtolower((string) $params['level']);
            if ($level === 'system') {
                $query->where('is_system_customer', 1);
            } elseif ($level === 'walkin') {
                $query->where('is_system_customer', 0);
            }
        }

        // Keyword search cho dropdown
        if ($kw !== '') {
            $like = '%' . $kw . '%';
            $query->where(function ($q) use ($like) {
                $q->where('ma_kh', 'like', $like)
                    ->orWhere('ten_khach_hang', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('so_dien_thoai', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('bride_name', 'like', $like)
                    ->orWhere('groom_name', 'like', $like);
            });
        }

        return $query->limit($limit)->get();
    }

    /**
     * Sinh mã KH theo đúng pattern 'KH' + 5 số:
     * - Chỉ xét các mã hợp lệ (REGEXP '^KH[0-9]{5}$'), bỏ qua mã rác nếu có
     * - Lấy MAX rồi +1
     * - Dùng lockForUpdate để tránh race-condition
     */
    private function nextIncrementalCode(): string
    {
        $row = DB::table('khach_hangs')
            ->whereRaw("ma_kh REGEXP '^KH[0-9]{5}$'")
            ->selectRaw('MAX(CAST(SUBSTRING(ma_kh, 3) AS UNSIGNED)) AS max_num')
            ->lockForUpdate()
            ->first();

        $next = (int) ($row->max_num ?? 0) + 1;
        return 'KH' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
