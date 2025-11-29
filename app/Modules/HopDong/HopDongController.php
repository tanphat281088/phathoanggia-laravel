<?php

namespace App\Modules\HopDong;

use App\Class\CustomResponse;
use App\Class\Helper;
use App\Class\FilterWithPagination;
use App\Http\Controllers\Controller;
use App\Models\HopDong;
use Illuminate\Http\Request;
use App\Services\HopDong\HopDongExportService;
use Illuminate\Validation\Rule; // <-- THÊM DÒNG NÀY

use Illuminate\Support\Facades\Response;

class HopDongController extends Controller
{
    protected HopDongService $service;

    public function __construct(HopDongService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/quan-ly-hop-dong
     *
     * - Danh sách Hợp đồng (paging + filter)
     * - Có thể filter theo:
     *    + so_hop_dong
     *    + ten_khach_hang (thông qua donHang.ten_khach_hang)
     *    + status
     *    + ngay_hop_dong (from/to)
     */
    public function index(Request $request)
    {
        $params = $request->all();
        $params = Helper::validateFilterParams($params);

        try {
            // Base query: load luôn Báo giá gốc để FE hiển thị
            $query = HopDong::query()->with('donHang');

            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['hop_dongs.*']
            );

            return CustomResponse::success([
                'collection' => $result['collection'],
                'total'      => $result['total'],
                'pagination' => [
                    'current_page'  => $result['current_page'],
                    'last_page'     => $result['last_page'],
                    'from'          => $result['from'],
                    'to'            => $result['to'],
                    'total_current' => $result['total_current'],
                ],
            ]);
        } catch (\Throwable $e) {
            return CustomResponse::error('Lỗi khi lấy danh sách Hợp đồng: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/quan-ly-hop-dong/from-quote/{donHangId}
     *
     * - Tạo (hoặc lấy lại) Hợp đồng từ 1 Báo giá (don_hangs.id)
     * - Trả về HopDong + items
     */
    public function createFromQuote(int $donHangId)
    {
        $result = $this->service->createFromDonHang($donHangId);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Khởi tạo Hợp đồng từ báo giá thành công');
    }

    /**
     * GET /api/quan-ly-hop-dong/{id}
     *
     * - Lấy chi tiết 1 Hợp đồng + báo giá + items
     */
    public function show(int $id)
    {
        $result = $this->service->getById($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result);
    }

    /**
     * PUT/PATCH /api/quan-ly-hop-dong/{id}
     *
     * - Cập nhật thông tin Hợp đồng (header)
     * - Không sửa items ở phase 1 (giữ khớp báo giá)
     */
    public function update(Request $request, int $id)
    {
        // Validate nhẹ các field cơ bản; phần còn lại giao cho Service chuẩn hoá
        $validated = $request->validate([
            'so_hop_dong'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'ngay_hop_dong' => ['sometimes', 'nullable', 'date'],

            // Bên A
            'ben_a_ten'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'ben_a_dia_chi'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'ben_a_mst'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'ben_a_dai_dien'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'ben_a_chuc_vu'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'ben_a_dien_thoai'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'ben_a_email'       => ['sometimes', 'nullable', 'email',  'max:191'],

            // Xưng hô Bên A: dropdown (Ông / Bà)
            'ben_a_xung_ho'     => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::in(['Ông', 'Bà']),
            ],

            // Bên B (Phát Hoàng Gia) – form mới KHÔNG cho sửa, nhưng nếu FE có gửi
            // cũng sẽ bị HopDongService::update() unset bỏ qua.

            // Sự kiện
            'su_kien_ten'                  => ['sometimes', 'nullable', 'string', 'max:500'],
            'su_kien_thoi_gian_text'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'su_kien_thoi_gian_setup_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'su_kien_dia_diem'             => ['sometimes', 'nullable', 'string', 'max:500'],

            // Giá trị HĐ
            'tong_truoc_vat'           => ['sometimes', 'nullable', 'integer', 'min:0'],
            'vat_rate'                 => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:20'],
            'vat_amount'               => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tong_sau_vat'             => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tong_sau_vat_bang_chu'    => ['sometimes', 'nullable', 'string', 'max:500'],

            // Đợt thanh toán
            'dot1_ty_le'          => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'dot1_so_tien'        => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dot1_thoi_diem_text' => ['sometimes', 'nullable', 'string', 'max:500'],

            'dot2_ty_le'          => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'dot2_so_tien'        => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dot2_thoi_diem_text' => ['sometimes', 'nullable', 'string', 'max:500'],

            // Điều khoản tuỳ chỉnh
            'dieukhoan_tuy_chinh' => ['sometimes', 'nullable', 'string'],

            // Trạng thái HĐ
            'status' => ['sometimes', 'nullable', 'integer', 'in:0,1,2,3,4'],
        ]);

        $result = $this->service->update($id, $validated);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Cập nhật Hợp đồng thành công');
    }

    /**
     * DELETE /api/quan-ly-hop-dong/{id}
     *
     * - Xoá Hợp đồng (và toàn bộ items)
     * - Tuỳ policy anh có thể khoá xoá khi status != Nháp
     */
    public function destroy(int $id)
    {
        /** @var HopDong|null $hopDong */
        $hopDong = HopDong::find($id);
        if (! $hopDong) {
            return CustomResponse::error('Hợp đồng không tồn tại');
        }

        // Policy mềm: chỉ cho xoá khi còn ở trạng thái Nháp
        if ((int) $hopDong->status !== HopDong::STATUS_DRAFT) {
            return CustomResponse::error('Chỉ được phép xoá Hợp đồng đang ở trạng thái Nháp');
        }

        $hopDong->items()->delete();
        $hopDong->delete();

        return CustomResponse::success([], 'Xoá Hợp đồng thành công');
    }

    /**
     * GET /api/quan-ly-hop-dong/{id}/export-docx
     *
     * - Xuất file DOCX Hợp đồng dựa trên template Word
     */
    public function exportDocx(int $id, HopDongExportService $exportService)
    {
        /** @var HopDong|null $hopDong */
        $hopDong = HopDong::find($id);
        if (! $hopDong) {
            return CustomResponse::error('Hợp đồng không tồn tại');
        }

        try {
            $docxPath = $exportService->exportDocx($hopDong);

            $fileName = basename($docxPath);

            return response()->download($docxPath, $fileName)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return CustomResponse::error('Lỗi khi export DOCX Hợp đồng: ' . $e->getMessage());
        }
    }
    /**
     * GET /api/quan-ly-hop-dong/{id}/export-docx-bilingual
     *
     * - Xuất file DOCX Hợp đồng SONG NGỮ (Việt – Anh)
     */
    public function exportDocxBilingual(int $id, HopDongExportService $exportService)
    {
        /** @var HopDong|null $hopDong */
        $hopDong = HopDong::find($id);
        if (! $hopDong) {
            return CustomResponse::error('Hợp đồng không tồn tại');
        }

        try {
            $docxPath = $exportService->exportDocxBilingual($hopDong);

            $fileName = basename($docxPath);

            return response()->download($docxPath, $fileName)
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return CustomResponse::error('Lỗi khi export DOCX SONG NGỮ Hợp đồng: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/quan-ly-hop-dong/{id}/export-pdf
     *
     * - Xuất PDF Hợp đồng (DomPDF + Blade hop-dong.export-pdf)
     */
    public function exportPdf(int $id, HopDongExportService $exportService)
    {
        /** @var HopDong|null $hopDong */
        $hopDong = HopDong::find($id);
        if (! $hopDong) {
            return CustomResponse::error('Hợp đồng không tồn tại');
        }

        try {
            // Service mới: render Blade + DomPDF → trả về đường dẫn file PDF
            $pdfPath  = $exportService->exportPdf($hopDong);
            $fileName = basename($pdfPath);

            return response()->download($pdfPath, $fileName)
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return CustomResponse::error('Lỗi khi export PDF Hợp đồng: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/quan-ly-hop-dong/{id}/preview
     *
     * - Preview Hợp đồng bằng đúng file PDF giống exportPdf (mở inline)
     */
    public function preview(int $id, HopDongExportService $exportService)
    {
        /** @var HopDong|null $hopDong */
        $hopDong = HopDong::find($id);
        if (! $hopDong) {
            return CustomResponse::error('Hợp đồng không tồn tại');
        }

        try {
            // Dùng chung service exportPdf: tạo PDF từ templates_token.docx
            $pdfPath  = $exportService->exportPdf($hopDong);
            $fileName = basename($pdfPath);

            // Trả PDF dạng inline để xem trực tiếp (không force download)
            return response()->file($pdfPath, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        } catch (\Throwable $e) {
            return CustomResponse::error('Lỗi khi preview PDF Hợp đồng: ' . $e->getMessage());
        }
    }

}
