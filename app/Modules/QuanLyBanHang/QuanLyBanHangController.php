<?php

namespace App\Modules\QuanLyBanHang;

use App\Http\Controllers\Controller;
use App\Modules\QuanLyBanHang\Validates\CreateQuanLyBanHangRequest;
use App\Modules\QuanLyBanHang\Validates\UpdateQuanLyBanHangRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\QuanLyBanHangImport;
use Illuminate\Support\Str;
use App\Services\Quote\QuoteBuilder;


// 🔽 BỔ SUNG: gọi service ghi nhận biến động điểm khi đơn đã thanh toán
use App\Services\MemberPointService;

class QuanLyBanHangController extends Controller
{
  protected $quanLyBanHangService;

  public function __construct(QuanLyBanHangService $quanLyBanHangService)
  {
    $this->quanLyBanHangService = $quanLyBanHangService;
  }

  /**
   * Lấy danh sách QuanLyBanHangs
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->quanLyBanHangService->getAll($params);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([
      'collection' => $result['data'],
      'total' => $result['total'],
      'pagination' => $result['pagination'] ?? null
    ]);
  }

  /**
   * Lấy giá bán sản phẩm theo lựa chọn
   * Body: san_pham_id, don_vi_tinh_id, loai_gia (1=Đặt ngay, 2=Đặt trước 3 ngày)
   */
  public function getGiaBanSanPham(Request $request)
  {
    // ✅ validate & nhận thêm loai_gia
    $validated = $request->validate([
      'san_pham_id'    => 'required|integer|exists:san_phams,id',
      'don_vi_tinh_id' => 'required|integer',
      'loai_gia'       => 'required|integer|in:1,2',
    ]);

    $result = $this->quanLyBanHangService->getGiaBanSanPham(
      (int) $validated['san_pham_id'],
      (int) $validated['don_vi_tinh_id'],
      (int) $validated['loai_gia']
    );

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Tạo mới QuanLyBanHang
   * - KHÔNG nhận ma_don_hang từ request (BE tự sinh theo id)
   */
  public function store(CreateQuanLyBanHangRequest $request)
  {
    // 🔒 Phòng thủ: loại bỏ ma_don_hang nếu FE gửi lên
    $payload = $request->validated();
    unset($payload['ma_don_hang']);

$result = $this->quanLyBanHangService->create($payload);

if ($result instanceof \Illuminate\Http\JsonResponse) {
  return $result;
}

// 🔽 Gọi ghi điểm (an toàn & idempotent)
$this->tryRecordPaidEvent((int) $result->id);

// Service đã return ->refresh() nên đảm bảo có ma_don_hang trong response
return CustomResponse::success($result, 'Tạo mới thành công');

  }

  /**
   * Lấy thông tin QuanLyBanHang
   */
  public function show($id)
  {
    $result = $this->quanLyBanHangService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật QuanLyBanHang
   * ➕ (BỔ SUNG HOOK AN TOÀN)
   * Sau khi update thành công, gọi MemberPointService để ghi nhận "biến động điểm"
   * nếu và chỉ nếu đơn đã ở trạng thái "đã thanh toán". Service sẽ tự kiểm tra:
   * - trạng thái thanh toán hiện tại của đơn (không phải ở FE),
   * - idempotency theo don_hang_id (1 đơn chỉ tạo 1 biến động),
   * - tính doanh thu, quy đổi điểm (1 điểm = 1.000 VND),
   * - không gửi ZNS ở đây (để anh chủ động gửi trong UI).
   */
  public function update(UpdateQuanLyBanHangRequest $request, $id)
  {
    // 🔒 phòng thủ tương tự (tránh sửa mã)
    $payload = $request->validated();
    unset($payload['ma_don_hang']);

    $result = $this->quanLyBanHangService->update($id, $payload);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    // 🔽 HOOK MỀM: an toàn, không phá flow cũ, không throw lỗi ra ngoài.
    $this->tryRecordPaidEvent((int) $id);

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa QuanLyBanHang
   */
  public function destroy($id)
  {
    $result = $this->quanLyBanHangService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách QuanLyBanHang dạng option
   */
  public function getOptions(Request $request)
  {
    $params = $request->all();

    $params = Helper::validateFilterParams($params);

    $result = $this->quanLyBanHangService->getOptions([
      ...$params,
      'limit' => -1,
    ]);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getSanPhamByDonHangId($donHangId)
  {
    $result = $this->quanLyBanHangService->getSanPhamByDonHangId($donHangId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getSoTienCanThanhToan($donHangId)
  {
    $result = $this->quanLyBanHangService->getSoTienCanThanhToan($donHangId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getDonHangByKhachHangId($khachHangId)
  {
    $result = $this->quanLyBanHangService->getDonHangByKhachHangId($khachHangId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/QuanLyBanHang.xlsx');

    if (!file_exists($path)) {
      return CustomResponse::error('File không tồn tại');
    }

    return response()->download($path);
  }

  public function importExcel(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:xlsx,xls,csv',
    ]);

    try {
      $data = $request->file('file');
      $filename = Str::random(10) . '.' . $data->getClientOriginalExtension();
      $path = $data->move(public_path('excel'), $filename);

      $import = new QuanLyBanHangImport();
      Excel::import($import, $path);

      $thanhCong = $import->getThanhCong();
      $thatBai = $import->getThatBai();

      if ($thatBai > 0) {
        return CustomResponse::error('Import không thành công. Có ' . $thatBai . ' bản ghi lỗi và ' . $thanhCong . ' bản ghi thành công');
      }

      return CustomResponse::success([
        'success' => $thanhCong,
        'fail' => $thatBai
      ], 'Import thành công ' . $thanhCong . ' bản ghi');
    } catch (\Exception $e) {
      return CustomResponse::error('Lỗi import: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Xem trước hóa đơn HTML
   */
  public function xemTruocHoaDon($id)
  {
    $result = $this->quanLyBanHangService->xemTruocHoaDon($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return $result; // Trả về view HTML
  }


      /**
     * Xem báo giá dịch vụ (layout sự kiện)
     * GET /api/quan-ly-ban-hang/xem-bao-gia/{id}
     *
     * - Dùng cho preview PDF/print, giống kiểu xem trước hóa đơn.
     * - Build dữ liệu sections từ đơn hàng bằng QuoteBuilder, rồi đổ vào view bao-gia.template.
     */
    public function xemBaoGia($id, Request $request, QuoteBuilder $quoteBuilder)
    {
        // Load đơn hàng + các quan hệ cần thiết
        /** @var \App\Models\DonHang $donHang */
        $donHang = \App\Models\DonHang::with([
                'chiTietDonHangs.sanPham',
                'chiTietDonHangs.donViTinh',
                'nguoiTao',
                'khachHang',
            ])
            ->findOrFail($id);

        // Build sections & totals cho báo giá
        $quotePayload = $quoteBuilder->buildForDonHang($donHang);
        $sections     = $quotePayload['sections'] ?? [];
        $totals       = $quotePayload['totals'] ?? [];

        // Meta báo giá: cho phép FE gửi override (query), nếu trống sẽ fallback DB/Blade
        $meta = $request->only([
            'nguoi_nhan',
            'dien_thoai',
            'phong_ban',
            'email',
            'cong_ty',
            'dia_chi',
            'du_an',
            'dia_chi_thuc_hien',
            'ngay_to_chuc',
            'so_luong_khach',
            'nguoi_bao_gia',
            'chuc_vu_bao_gia',
            'dien_thoai_bao_gia',
            'email_bao_gia',
            'ngay_bao_gia',
            'han_hieu_luc',
        ]);

    // ===== Lấy meta Step 8 lưu trong đơn hàng (nếu có) =====
    // 1. Hạng mục tuỳ biến theo nhóm gói
    $categoryTitlesRaw = $donHang->quote_category_titles ?? [];
    if (is_string($categoryTitlesRaw)) {
        $decoded = json_decode($categoryTitlesRaw, true);
        $categoryTitlesRaw = is_array($decoded) ? $decoded : [];
    }
    $meta['category_titles'] = is_array($categoryTitlesRaw) ? $categoryTitlesRaw : [];

    // 2. Ghi chú cuối báo giá
    $meta['footer_note'] = $donHang->quote_footer_note ?? null;

    // 3. Người báo giá / Xác nhận báo giá
    $meta['quote_signer_name']  = $donHang->quote_signer_name  ?? null;
    $meta['quote_signer_title'] = $donHang->quote_signer_title ?? null;
    $meta['quote_signer_phone'] = $donHang->quote_signer_phone ?? null;
    $meta['quote_signer_email'] = $donHang->quote_signer_email ?? null;
    $meta['quote_approver_note'] = $donHang->quote_approver_note ?? null;


        // Tuỳ biến tiêu đề Hạng mục & ghi chú báo giá theo đơn
        // Ưu tiên dữ liệu trong DB của đơn; nếu sau này muốn override qua query thì có thể merge thêm
               // Tuỳ biến tiêu đề Hạng mục theo nhóm gói & ghi chú báo giá theo đơn
        $meta['section_titles']   = $donHang->quote_section_titles ?? [];   // nếu sau này còn dùng
        $meta['footer_note']      = $donHang->quote_footer_note ?? null;

        // Cách 1: map Hạng mục gốc (hang_muc) => label tuỳ biến
        $meta['category_titles']  = $donHang->quote_category_titles ?? [];

        // Meta người báo giá / xác nhận báo giá
        $meta['signer'] = [
            'name'         => $donHang->quote_signer_name,
            'title'        => $donHang->quote_signer_title,
            'phone'        => $donHang->quote_signer_phone,
            'email'        => $donHang->quote_signer_email,
            'approver_note'=> $donHang->quote_approver_note,
        ];

        return view('bao-gia.template', [
            'donHang'  => $donHang,
            'sections' => $sections,
            'totals'   => $totals,
            'meta'     => $meta,
        ]);

    }

  // ==========================
  // 🔽 PRIVATE HELPER BỔ SUNG
  // ==========================
  /**
   * Gọi service ghi nhận "biến động điểm" khi đơn đã thanh toán.
   * - Service tự kiểm tra trạng thái hiện tại của đơn trong DB.
   * - Tự idempotent theo don_hang_id (1 đơn 1 biến động).
   * - Không ném lỗi ra ngoài để không ảnh hưởng flow cập nhật đơn.
   */
  private function tryRecordPaidEvent(int $donHangId): void
  {
    try {
      /** @var \App\Services\MemberPointService $svc */
      $svc = app(MemberPointService::class);
      $svc->recordPaidOrder($donHangId);
    } catch (\Throwable $e) {
      // log lỗi nội bộ, không phá vỡ response cho FE
      report($e);
    }
  }
}
