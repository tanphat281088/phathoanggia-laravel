<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Services\Quote\QuoteBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuoteExcelController extends Controller
{
    /**
     * Export báo giá Excel cho 1 đơn hàng.
     *
     * Dùng template resources/excel/bao-gia-template.xls,
     * fill header + sections giống PDF.
     */
    public function exportDonHang(Request $request, int $id, QuoteBuilder $quoteBuilder): StreamedResponse
    {
        // 1) Load đơn hàng + chi tiết cần thiết
        /** @var DonHang $donHang */
        $donHang = DonHang::with([
                'chiTietDonHangs.sanPham',
                'chiTietDonHangs.donViTinh',
                'nguoiTao',
                'khachHang',
            ])
            ->findOrFail($id);

        // 2) Build sections & totals từ QuoteBuilder
        $payload   = $quoteBuilder->buildForDonHang($donHang);
        $sections  = $payload['sections'] ?? [];
        $totals    = $payload['totals']   ?? [];
        $tongTien  = (int)($totals['total_thanh_tien'] ?? 0);

        // 3) Meta (cho phép override, fallback từ đơn hàng)
        $metaReq = $request->only([
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
        ]);

        $meta = array_merge([
            'nguoi_nhan'        => $metaReq['nguoi_nhan']        ?? ($donHang->ten_khach_hang ?? 'Quý khách hàng'),
            'dien_thoai'        => $metaReq['dien_thoai']        ?? ($donHang->so_dien_thoai ?? ''),
            'phong_ban'         => $metaReq['phong_ban']         ?? '',
            'email'             => $metaReq['email']             ?? '',
            'cong_ty'           => $metaReq['cong_ty']           ?? ($donHang->ten_khach_hang ?? ''),
            'dia_chi'           => $metaReq['dia_chi']           ?? ($donHang->dia_chi_giao_hang ?? ''),
            'du_an'             => $metaReq['du_an']             ?? ($donHang->ghi_chu ?? ''),
            'dia_chi_thuc_hien' => $metaReq['dia_chi_thuc_hien'] ?? ($donHang->dia_chi_giao_hang ?? ''),
            'ngay_to_chuc'      => $metaReq['ngay_to_chuc']      ?? '',
            'so_luong_khach'    => $metaReq['so_luong_khach']    ?? '',
        ], $metaReq);

        // 4) Load template Excel (.xls)
        $templatePath = resource_path('excel/bao-gia-template.xls');
        if (!file_exists($templatePath)) {
            abort(404, 'Template Excel báo giá không tồn tại: ' . $templatePath);
        }

        // Dùng IOFactory để load (PhpSpreadsheet tự nhận định dạng)
        $spreadsheet = IOFactory::load($templatePath);

        /** @var Worksheet $sheet */
        $sheet = $spreadsheet->getSheet(0);


        // === XÓA DỮ LIỆU DEMO CŨ TRONG KHU VỰC BẢNG (A12:J200) ===
        for ($row = 12; $row <= 200; $row++) {
            foreach (range('A', 'J') as $col) {
                $sheet->setCellValue($col . $row, null);
            }
        }


        // 5) Fill header theo layout mẫu (cần chỉnh lại ô cho đúng template của anh)
        // Ở đây mình giữ nguyên vị trí giống mô tả trước: người nhận / ĐT ở B3/E3, ...
        $sheet->setCellValue('B3', $meta['nguoi_nhan'] ?? '');
        $sheet->setCellValue('E3', $meta['dien_thoai'] ?? '');

        $sheet->setCellValue('B4', $meta['phong_ban'] ?? '');
        $sheet->setCellValue('E4', $meta['email'] ?? '');

        $sheet->setCellValue('B5', $meta['cong_ty'] ?? '');
        $sheet->setCellValue('B6', $meta['dia_chi'] ?? '');
        $sheet->setCellValue('B7', $meta['du_an'] ?? '');
        $sheet->setCellValue('B8', $meta['dia_chi_thuc_hien'] ?? '');
        $sheet->setCellValue('B9', $meta['ngay_to_chuc'] ?? '');

        if (!empty($meta['so_luong_khach'])) {
            $sheet->setCellValue('C9', 'Số lượng: ' . $meta['so_luong_khach'] . ' khách');
        }

        // 6) Fill bảng báo giá từ row 12 như template gốc
        $currentRow = 12;

        foreach ($sections as $section) {
            $letter        = $section['letter'] ?? '';
            $sectionName   = $section['name']   ?? ($section['key'] ?? '');
            $totalThanhTien= (int)($section['total_thanh_tien'] ?? 0);

            // Dòng group: "A. NHÂN SỰ"
            $sheet->setCellValue("A{$currentRow}", "{$letter}. " . mb_strtoupper((string)$sectionName, 'UTF-8'));
            // Tổng tiền nhóm (theo template: cột J)
            $sheet->setCellValue("J{$currentRow}", $totalThanhTien);

            $currentRow++;

            // Dòng con: 1,2,3...
            $idx = 1;
            foreach ($section['items'] ?? [] as $line) {
                $sheet->setCellValue("A{$currentRow}", $idx);
                $sheet->setCellValue("B{$currentRow}", $line['hang_muc'] ?? '');
                // Chi tiết: gộp HTML thành text (nhiều dòng)
                $detailText = $line['chi_tiet'] ?? '';
                $sheet->setCellValue("C{$currentRow}", $detailText);
                $sheet->setCellValue("D{$currentRow}", $line['dvt'] ?? '');
                $sheet->setCellValue("E{$currentRow}", $line['so_luong'] ?? '');

                $sheet->setCellValue("I{$currentRow}", $line['don_gia'] ?? '');
                $sheet->setCellValue("J{$currentRow}", $line['thanh_tien'] ?? '');

                $idx++;
                $currentRow++;
            }
        }

        // 7) Tổng chi phí & Bằng chữ (row 31, 32 hoặc ngay dưới nếu currentRow > 31)
        $rowTong    = max($currentRow, 31);
        $rowBangChu = $rowTong + 1;

        $sheet->setCellValue("A{$rowTong}", 'TỔNG CHI PHÍ:');
        $sheet->setCellValue("J{$rowTong}", $tongTien);

        $sheet->setCellValue("A{$rowBangChu}", 'Bằng chữ:');
        $sheet->setCellValue("B{$rowBangChu}", number_format($tongTien, 0, ',', '.') . ' đồng');

        // 8) Trả file về client (dạng Xlsx chuẩn)
        $fileNameSafe = $donHang->ma_don_hang ?: ('DH-' . $donHang->id);
        $fileNameSafe = preg_replace('/[^A-Za-z0-9\-_]/', '_', $fileNameSafe);
        $downloadName = 'Bao-gia-' . $fileNameSafe . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            // Ghi ra Xlsx chuẩn
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            // Xoá output buffer nếu có
            if (ob_get_length()) {
                ob_end_clean();
            }
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }
}
