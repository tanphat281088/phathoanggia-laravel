<?php

namespace App\Modules\KhachHangPassCtv;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Models\KhachHang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Khách hàng Agency (khách sỉ / pass job / planner)
 *
 * 🔹 ERP Sự kiện:
 * - KHÔNG dùng customer_mode nữa.
 * - Dùng trường customer_type trong bảng khach_hangs:
 *      0 = Event client
 *      1 = Wedding client
 *      2 = Agency client  (khách sỉ / pass đơn / CTV cũ)
 *
 * Module này tương đương "Khách hàng Pass đơn & CTV" cũ,
 * nhưng giờ hiểu là KH Agency (customer_type = TYPE_AGENCY).
 */
class KhachHangPassCtvController extends Controller
{
    /**
     * GET /api/khach-hang-pass-ctv
     *
     * Liệt kê danh sách khách hàng AGENCY:
     *  - customer_type = TYPE_AGENCY (2)
     *
     * Query:
     *  - q: tìm theo mã KH / tên / sđt / email
     *  - per_page: phân trang (mặc định 20)
     */
    public function index(Request $request)
    {
        $q       = trim((string) $request->input('q', ''));
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage <= 0 || $perPage > 200) {
            $perPage = 20;
        }

        $query = KhachHang::query()
            ->where('customer_type', KhachHang::TYPE_AGENCY)
            ->orderByDesc('id');

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('ma_kh', 'like', $like)
                  ->orWhere('ten_khach_hang', 'like', $like)
                  ->orWhere('company_name', 'like', $like)
                  ->orWhere('so_dien_thoai', 'like', $like)
                  ->orWhere('email', 'like', $like);
            });
        }

        $rows = $query->paginate($perPage);

        return CustomResponse::success($rows);
    }

    /**
     * GET /api/khach-hang-pass-ctv/options
     *
     * Trả về danh sách options KH Agency (customer_type = TYPE_AGENCY)
     * để dùng cho dropdown trên FE.
     *
     * Query:
     *  - q|keyword|search|term: tìm theo mã KH / tên / SĐT
     *  - limit: số record (mặc định 30)
     */
    public function options(Request $request)
    {
        $kw = trim((string) (
            $request->input('keyword') ??
            $request->input('q') ??
            $request->input('search') ??
            $request->input('term') ??
            ''
        ));

        $limit = (int) $request->input('limit', 30);
        if ($limit <= 0 || $limit > 200) {
            $limit = 30;
        }

        $query = KhachHang::query()
            ->where('customer_type', KhachHang::TYPE_AGENCY)
            ->selectRaw("
                id AS value,
                CONCAT(
                    COALESCE(ma_kh, ''),
                    ' - ',
                    CASE
                        WHEN company_name IS NOT NULL AND company_name <> '' THEN company_name
                        ELSE COALESCE(ten_khach_hang, '')
                    END,
                    ' - ',
                    COALESCE(so_dien_thoai, '')
                ) AS label
            ")
            ->orderBy('ma_kh');

        if ($kw !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $kw) . '%';
            $query->where(function ($q2) use ($like) {
                $q2->where('ma_kh', 'like', $like)
                   ->orWhere('ten_khach_hang', 'like', $like)
                   ->orWhere('company_name', 'like', $like)
                   ->orWhere('so_dien_thoai', 'like', $like);
            });
        }

        // Trả về COLLECTION thô giống /khach-hang/options
        return $query->limit($limit)->get();
    }

    /**
     * POST /api/khach-hang-pass-ctv/convert-to-pass/{id}
     *
     * Chuyển 1 khách hàng hệ thống thường → KH AGENCY.
     * - Đặt customer_type = TYPE_AGENCY.
     * - Đảm bảo is_system_customer = 1.
     * - Không chỉnh sửa các field khác.
     */
    public function convertToPass(int $id)
    {
        return DB::transaction(function () use ($id) {
            /** @var KhachHang|null $kh */
            $kh = KhachHang::lockForUpdate()->find($id);
            if (! $kh) {
                return CustomResponse::error('Không tìm thấy khách hàng.', 404);
            }

            // Nếu đã là Agency rồi thì coi như idempotent
            if ((int) $kh->customer_type === KhachHang::TYPE_AGENCY) {
                // Đảm bảo là khách hệ thống
                if (isset($kh->is_system_customer) && ! $kh->is_system_customer) {
                    $kh->is_system_customer = true;
                    $kh->save();
                }

                return CustomResponse::success([
                    'id'               => $kh->id,
                    'customer_type'    => $kh->customer_type,
                    'is_system_customer' => (bool) $kh->is_system_customer,
                    'idempotent'       => true,
                ], 'Khách hàng đã ở trạng thái Agency.');
            }

            $kh->customer_type = KhachHang::TYPE_AGENCY;
            if (isset($kh->is_system_customer)) {
                $kh->is_system_customer = true;
            }
            $kh->save();

            return CustomResponse::success([
                'id'                 => $kh->id,
                'customer_type'      => $kh->customer_type,
                'is_system_customer' => (bool) $kh->is_system_customer,
                'idempotent'         => false,
            ], 'Đã chuyển sang Khách hàng Agency.');
        });
    }

    /**
     * POST /api/khach-hang-pass-ctv/convert-to-normal/{id}
     *
     * Chuyển 1 khách hàng Agency → Khách hàng hệ thống loại thường (Event client).
     * - Đặt customer_type = TYPE_EVENT (0).
     * - Giữ is_system_customer = 1.
     */
    public function convertToNormal(int $id)
    {
        return DB::transaction(function () use ($id) {
            /** @var KhachHang|null $kh */
            $kh = KhachHang::lockForUpdate()->find($id);
            if (! $kh) {
                return CustomResponse::error('Không tìm thấy khách hàng.', 404);
            }

            // Nếu đã không phải Agency (tức đã là normal) thì idempotent
            if ((int) $kh->customer_type !== KhachHang::TYPE_AGENCY) {
                if (isset($kh->is_system_customer) && ! $kh->is_system_customer) {
                    $kh->is_system_customer = true;
                    $kh->save();
                }

                return CustomResponse::success([
                    'id'                 => $kh->id,
                    'customer_type'      => $kh->customer_type,
                    'is_system_customer' => (bool) $kh->is_system_customer,
                    'idempotent'         => true,
                ], 'Khách hàng đã ở trạng thái hệ thống thường.');
            }

            $kh->customer_type = KhachHang::TYPE_EVENT; // mặc định chuyển về khách Event
            if (isset($kh->is_system_customer)) {
                $kh->is_system_customer = true;
            }
            $kh->save();

            return CustomResponse::success([
                'id'                 => $kh->id,
                'customer_type'      => $kh->customer_type,
                'is_system_customer' => (bool) $kh->is_system_customer,
                'idempotent'         => false,
            ], 'Đã chuyển sang Khách hàng hệ thống thường (Event client).');
        });
    }
}
