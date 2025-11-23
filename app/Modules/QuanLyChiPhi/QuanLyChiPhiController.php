<?php

namespace App\Modules\QuanLyChiPhi;

use App\Class\CustomResponse;
use App\Class\Helper;
use App\Http\Controllers\Controller;
use App\Models\QuoteCost;
use Illuminate\Http\Request;
use App\Services\Quote\QuoteCostReportBuilder; // NEW: builder dữ liệu cho PDF chi phí

class QuanLyChiPhiController extends Controller
{
    protected QuanLyChiPhiService $service;

    public function __construct(QuanLyChiPhiService $service)
    {
        $this->service = $service;
    }

    // ======================================================================
    //  ĐỀ XUẤT (type = 1)
    // ======================================================================

    /**
     * GET /api/quan-ly-chi-phi/de-xuat
     * - Danh sách bảng chi phí ĐỀ XUẤT (paging + filter)
     */
    public function indexDeXuat(Request $request)
    {
        $params = $request->all();
        $params = Helper::validateFilterParams($params);

        $result = $this->service->getAll($params, QuoteCost::TYPE_DE_XUAT);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success([
            'collection' => $result['data'],
            'total'      => $result['total'],
            'pagination' => $result['pagination'] ?? null,
        ]);
    }

    /**
     * POST /api/quan-ly-chi-phi/de-xuat/from-quote/{donHangId}
     *
     * - Tạo (hoặc lấy lại) bảng chi phí ĐỀ XUẤT cho 1 báo giá
     * - Nếu đã có (don_hang_id, type=DE_XUAT) -> trả về record hiện có
     */
    public function createDeXuatFromDonHang(int $donHangId)
    {
        $result = $this->service->createFromDonHang($donHangId, QuoteCost::TYPE_DE_XUAT);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Khởi tạo bảng chi phí đề xuất thành công');
    }

    /**
     * GET /api/quan-ly-chi-phi/de-xuat/{id}
     * - Xem chi tiết 1 bảng chi phí ĐỀ XUẤT
     */
    public function showDeXuat(int $id)
    {
        $result = $this->service->getById($id, QuoteCost::TYPE_DE_XUAT);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result);
    }

    /**
     * PUT/PATCH /api/quan-ly-chi-phi/de-xuat/{id}
     * - Cập nhật header + items bảng chi phí ĐỀ XUẤT
     */
    public function updateDeXuat(Request $request, int $id)
    {
        $payload = $request->all();

        $result = $this->service->update($id, $payload);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Cập nhật bảng chi phí đề xuất thành công');
    }

    /**
     * DELETE /api/quan-ly-chi-phi/de-xuat/{id}
     * - Xoá bảng chi phí ĐỀ XUẤT (và toàn bộ dòng chi phí)
     */
    public function destroyDeXuat(int $id)
    {
        $result = $this->service->delete($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success([], 'Xoá bảng chi phí đề xuất thành công');
    }

    /**
     * POST /api/quan-ly-chi-phi/de-xuat/{id}/transfer-to-actual
     *
     * - Từ bảng ĐỀ XUẤT → tạo (hoặc mở lại) bảng THỰC TẾ tương ứng
     * - Nếu đã có thực tế: trả về record thực tế hiện tại, không clone lại
     */
    public function transferToActual(int $id)
    {
        $result = $this->service->transferToActual($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Đã chuyển sang bảng chi phí thực tế');
    }

    /**
     * GET /api/quan-ly-chi-phi/de-xuat/{id}/pdf
     *
     * - Xem / In PDF Chi phí ĐỀ XUẤT (layout giống báo giá + thêm cột SUP / Giá CP)
     * - Sử dụng view: resources/views/chi-phi/template.blade.php
     */
    public function exportDeXuatPdf(
        int $id,
        QuoteCostReportBuilder $builder
    ) {
        /** @var \App\Models\QuoteCost $cost */
        $cost = QuoteCost::with(['donHang', 'items'])
            ->where('type', QuoteCost::TYPE_DE_XUAT)
            ->findOrFail($id);

        $payload  = $builder->build($cost);
        $donHang  = $cost->donHang;
        $sections = $payload['sections'] ?? [];
        $totals   = $payload['totals'] ?? [];

        // Meta cho template chi phí
        $meta = [
            'cost_type_text'   => 'Chi phí đề xuất',
            'nguoi_nhan'       => $donHang->ten_khach_hang ?? 'Quý khách hàng',
            'dien_thoai'       => $donHang->so_dien_thoai ?? '',
            'phong_ban'        => '',
            'email'            => '',
            'cong_ty'          => $donHang->ten_khach_hang ?? '',
            'dia_chi'          => $donHang->dia_chi_giao_hang ?? '',
            'du_an'            => $donHang->project_name ?? ($donHang->ghi_chu ?? ''),
            'dia_chi_thuc_hien'=> $donHang->venue_address ?? ($donHang->dia_chi_giao_hang ?? ''),
            'ngay_to_chuc'     => $donHang->event_start
                ? \Illuminate\Support\Carbon::parse($donHang->event_start)->format('d/m/Y H:i')
                : '',
            'so_luong_khach'   => $donHang->guest_count ?? '',
            // Nếu muốn dùng tuỳ biến Step 8 thì truyền thêm:
            // 'category_titles'  => $donHang->quote_category_titles ?? [],
            // 'footer_note'      => $donHang->quote_footer_note ?? null,
            // 'signer'           => [
            //     'name'          => $donHang->quote_signer_name,
            //     'title'         => $donHang->quote_signer_title,
            //     'phone'         => $donHang->quote_signer_phone,
            //     'email'         => $donHang->quote_signer_email,
            //     'approver_note' => $donHang->quote_approver_note,
            // ],
        ];

        return view('chi-phi.template', [
            'donHang'  => $donHang,
            'sections' => $sections,
            'totals'   => $totals,
            'meta'     => $meta,
        ]);
    }

    // ======================================================================
    //  THỰC TẾ (type = 2)
    // ======================================================================

    /**
     * GET /api/quan-ly-chi-phi/thuc-te
     * - Danh sách bảng chi phí THỰC TẾ (paging + filter)
     */
    public function indexThucTe(Request $request)
    {
        $params = $request->all();
        $params = Helper::validateFilterParams($params);

        $result = $this->service->getAll($params, QuoteCost::TYPE_THUC_TE);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success([
            'collection' => $result['data'],
            'total'      => $result['total'],
            'pagination' => $result['pagination'] ?? null,
        ]);
    }

    /**
     * POST /api/quan-ly-chi-phi/thuc-te/from-quote/{donHangId}
     *
     * - Tạo (hoặc lấy lại) bảng chi phí THỰC TẾ cho 1 báo giá
     * - Dùng khi anh bấm nút "Chi phí thực tế" ngay từ màn Báo giá
     */
    public function createThucTeFromDonHang(int $donHangId)
    {
        $result = $this->service->createFromDonHang($donHangId, QuoteCost::TYPE_THUC_TE);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Khởi tạo bảng chi phí thực tế thành công');
    }

    /**
     * GET /api/quan-ly-chi-phi/thuc-te/{id}
     * - Xem chi tiết 1 bảng chi phí THỰC TẾ
     */
    public function showThucTe(int $id)
    {
        $result = $this->service->getById($id, QuoteCost::TYPE_THUC_TE);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result);
    }

    /**
     * PUT/PATCH /api/quan-ly-chi-phi/thuc-te/{id}
     * - Cập nhật header + items bảng chi phí THỰC TẾ
     */
    public function updateThucTe(Request $request, int $id)
    {
        $payload = $request->all();

        $result = $this->service->update($id, $payload);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Cập nhật bảng chi phí thực tế thành công');
    }

    /**
     * DELETE /api/quan-ly-chi-phi/thuc-te/{id}
     * - Xoá bảng chi phí THỰC TẾ
     */
    public function destroyThucTe(int $id)
    {
        $result = $this->service->delete($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success([], 'Xoá bảng chi phí thực tế thành công');
    }

    /**
     * GET /api/quan-ly-chi-phi/thuc-te/{id}/pdf
     *
     * - Xem / In PDF Chi phí THỰC TẾ
     */
    public function exportThucTePdf(
        int $id,
        QuoteCostReportBuilder $builder
    ) {
        /** @var \App\Models\QuoteCost $cost */
        $cost = QuoteCost::with(['donHang', 'items'])
            ->where('type', QuoteCost::TYPE_THUC_TE)
            ->findOrFail($id);

        $payload  = $builder->build($cost);
        $donHang  = $cost->donHang;
        $sections = $payload['sections'] ?? [];
        $totals   = $payload['totals'] ?? [];

        $meta = [
            'cost_type_text'   => 'Chi phí thực tế',
            'nguoi_nhan'       => $donHang->ten_khach_hang ?? 'Quý khách hàng',
            'dien_thoai'       => $donHang->so_dien_thoai ?? '',
            'phong_ban'        => '',
            'email'            => '',
            'cong_ty'          => $donHang->ten_khach_hang ?? '',
            'dia_chi'          => $donHang->dia_chi_giao_hang ?? '',
            'du_an'            => $donHang->project_name ?? ($donHang->ghi_chu ?? ''),
            'dia_chi_thuc_hien'=> $donHang->venue_address ?? ($donHang->dia_chi_giao_hang ?? ''),
            'ngay_to_chuc'     => $donHang->event_start
                ? \Illuminate\Support\Carbon::parse($donHang->event_start)->format('d/m/Y H:i')
                : '',
            'so_luong_khach'   => $donHang->guest_count ?? '',
        ];

        return view('chi-phi.template', [
            'donHang'  => $donHang,
            'sections' => $sections,
            'totals'   => $totals,
            'meta'     => $meta,
        ]);
    }
}
