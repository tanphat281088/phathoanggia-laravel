<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Services\Quote\QuoteBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class QuoteExcelController extends Controller
{
    /**
     * Export báo giá Excel cho 1 đơn hàng.
     *
     * Build Excel từ trắng, layout bám sát PDF (resources/views/bao-gia/template.blade.php).
     */
    public function exportDonHang(Request $request, int $id, QuoteBuilder $quoteBuilder): StreamedResponse
    {
        // 1) Load đơn hàng + chi tiết
        /** @var DonHang $donHang */
        $donHang = DonHang::with([
                'chiTietDonHangs.sanPham',
                'chiTietDonHangs.donViTinh',
                'nguoiTao',
                'khachHang',
            ])
            ->findOrFail($id);

        // 2) Build sections & totals từ QuoteBuilder (giống PDF)
        $payload   = $quoteBuilder->buildForDonHang($donHang);
        $sections  = $payload['sections'] ?? [];
        $totals    = $payload['totals']   ?? [];
        $tongThanhTien = (int)($totals['total_thanh_tien'] ?? 0);

        // 3) Meta giống PDF
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

        // 4) Tạo Spreadsheet mới
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Báo giá');

        // Setup trang (A4 portrait)
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $pageSetup->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);

        $margin = $sheet->getPageMargins();
        $margin->setTop(0.5);
        $margin->setBottom(0.5);
        $margin->setLeft(0.5);
        $margin->setRight(0.5);

        // ===== Định nghĩa style =====
        $borderAll = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FF000000'],
                ],
            ],
        ];
        $alignCenter = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ];
        $alignLeft = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ];
        $alignRight = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ];
        $bold = [
            'font' => [
                'bold' => true,
            ],
        ];
        // Header bảng màu cam nhạt
        $headerFill = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF9B000'],
            ],
        ];
        // Row section A/B/C/D màu xanh nhạt
        $sectionFill = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFDE9D9'],
            ],
        ];

        // ===== Độ rộng cột giống PDF =====
        $sheet->getColumnDimension('A')->setWidth(5);   // STT
        $sheet->getColumnDimension('B')->setWidth(18);  // HẠNG MỤC
        $sheet->getColumnDimension('C')->setWidth(55);  // CHI TIẾT
        $sheet->getColumnDimension('D')->setWidth(8);   // ĐVT
        $sheet->getColumnDimension('E')->setWidth(8);   // SL
        $sheet->getColumnDimension('F')->setWidth(14);  // ĐƠN GIÁ
        $sheet->getColumnDimension('G')->setWidth(14);  // THÀNH TIỀN

        $row = 1;
// Lưu lại row cho phần intro để chỉnh chiều cao sau
$introRow1 = null; // "Kính gửi Quý khách hàng,"
$introRow2 = null; // câu intro dài

        // ===== HEADER CÔNG TY =====
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'CÔNG TY TNHH SỰ KIỆN PHÁT HOÀNG GIA');
        $sheet->getStyle("A{$row}")->applyFromArray($bold + $alignCenter);
        $row++;

        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'Văn phòng: 102 Nguyễn Minh Hoàng, Phường Bảy Hiền, Thành phố Hồ Chí Minh, Việt Nam');
        $sheet->getStyle("A{$row}")->applyFromArray($alignCenter);
        $row++;

        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'Kho 1: 100 Nguyễn Minh Hoàng, P. 12, Q. Tân Bình, TP. HCM');
        $sheet->getStyle("A{$row}")->applyFromArray($alignCenter);
        $row++;

        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'Kho 2: 111 Nguyễn Minh Hoàng, P. 12, Q. Tân Bình, TP. HCM');
        $sheet->getStyle("A{$row}")->applyFromArray($alignCenter);
        $row++;

        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'Điện thoại: 0949 40 43 44  |  Email: info@phathoanggia.com.vn  |  MST: 0311465079');
        $sheet->getStyle("A{$row}")->applyFromArray($alignCenter);
        $row += 2;

        // ===== TIÊU ĐỀ BÁO GIÁ =====
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'BÁO GIÁ CUNG CẤP DỊCH VỤ (SỰ KIỆN)');
        $sheet->getStyle("A{$row}")->applyFromArray($bold + $alignCenter);
        $row += 2;

        // ===== THÔNG TIN KHÁCH HÀNG =====
        // Người nhận / Điện thoại
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Người nhận:');
        $sheet->mergeCells("C{$row}:D{$row}");
        $sheet->setCellValue("C{$row}", $meta['nguoi_nhan']);
        $sheet->mergeCells("E{$row}:F{$row}");
        $sheet->setCellValue("E{$row}", 'Điện thoại:');
        $sheet->setCellValue("G{$row}", $meta['dien_thoai']);
        $row++;

        // Phòng ban / Email
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Phòng ban:');
        $sheet->mergeCells("C{$row}:D{$row}");
        $sheet->setCellValue("C{$row}", $meta['phong_ban']);
        $sheet->mergeCells("E{$row}:F{$row}");
        $sheet->setCellValue("E{$row}", 'Email:');
        $sheet->setCellValue("G{$row}", $meta['email']);
        $row++;

        // Công ty
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Công ty:');
        $sheet->mergeCells("C{$row}:G{$row}");
        $sheet->setCellValue("C{$row}", $meta['cong_ty']);
        $row++;

        // Địa chỉ
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Địa chỉ:');
        $sheet->mergeCells("C{$row}:G{$row}");
        $sheet->setCellValue("C{$row}", $meta['dia_chi']);
        $row++;

        // Địa chỉ thực hiện
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Địa chỉ thực hiện:');
        $sheet->mergeCells("C{$row}:G{$row}");
        $sheet->setCellValue("C{$row}", $meta['dia_chi_thuc_hien']);
        $row++;

        // Ngày tổ chức / Số lượng
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Ngày tổ chức:');
        $sheet->mergeCells("C{$row}:D{$row}");
        $sheet->setCellValue("C{$row}", $meta['ngay_to_chuc']);
        $sheet->mergeCells("E{$row}:F{$row}");
        $sheet->setCellValue("E{$row}", 'Số lượng:');
        $sheet->setCellValue("G{$row}", $meta['so_luong_khach']);
        $row += 2;

// ===== LỜI MỞ ĐẦU (giống PDF) =====
$introRow1 = $row;
$sheet->mergeCells("A{$row}:G{$row}");
$sheet->setCellValue("A{$row}", 'Kính gửi Quý khách hàng,');
$sheet->getStyle("A{$row}")->applyFromArray($alignLeft);
$row++;

$introRow2 = $row;
$sheet->mergeCells("A{$row}:G{$row}");
$sheet->setCellValue("A{$row}", 'Trước hết, PHÁT HOÀNG GIA xin chân thành cảm ơn Quý khách đã tin tưởng và lựa chọn chúng tôi là đơn vị đồng hành trong chương trình sắp tới. Dưới đây là báo giá chi tiết cho các hạng mục dịch vụ sự kiện theo yêu cầu của Quý khách:');
$sheet->getStyle("A{$row}")->applyFromArray($alignLeft);
$row += 2;



        // ===== BẢNG BÁO GIÁ =====
        // Header bảng
        $sheet->setCellValue("A{$row}", 'STT');
        $sheet->setCellValue("B{$row}", 'HẠNG MỤC');
        $sheet->setCellValue("C{$row}", 'CHI TIẾT');
        $sheet->setCellValue("D{$row}", 'ĐVT');
        $sheet->setCellValue("E{$row}", 'SL');
        $sheet->setCellValue("F{$row}", 'ĐƠN GIÁ');
        $sheet->setCellValue("G{$row}", 'THÀNH TIỀN');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($bold + $borderAll + $alignCenter + $headerFill);
        $row++;

        $tableStartRow = $row;

        // In từng section giống Blade
        foreach ($sections as $section) {
            $letter        = $section['letter'] ?? '';
            $name          = $section['name']   ?? ($section['key'] ?? '');
            $totalThanhTien= (int)($section['total_thanh_tien'] ?? 0);

            // Dòng tiêu đề section: merge A..F, tiền ở G
            $label = trim(($letter ? $letter . '. ' : '') . mb_strtoupper($name, 'UTF-8'));
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("G{$row}", $totalThanhTien);
            $sheet->getStyle("A{$row}:G{$row}")
                  ->applyFromArray($borderAll + $bold + $sectionFill + $alignLeft);
            $row++;

            $items    = $section['items'] ?? [];
            $count    = count($items);
            $rowIndex = 1;
            $iIdx     = 0;

            while ($iIdx < $count) {
                $line = $items[$iIdx];

                $hmRaw    = $line['hang_muc']     ?? '';
                $hangMuc  = $hmRaw;

                $isPackageLine = !empty($line['is_package']);

                if ($isPackageLine) {
                    $groupCount = 1;
                } else {
                    $groupCount = 1;
                    for ($j = $iIdx + 1; $j < $count; $j++) {
                        $lineJ      = $items[$j];
                        $isPackageJ = !empty($lineJ['is_package']);
                        $hmRawJ     = $lineJ['hang_muc'] ?? '';
                        if (!$isPackageJ && $hmRawJ === $hmRaw) {
                            $groupCount++;
                        } else {
                            break;
                        }
                    }
                }

                $line0 = $line;

                // Dòng đầu nhóm
                $startRowGroup = $row;
                $endRowGroup   = $row + $groupCount - 1;

                // STT
                $sheet->setCellValue("A{$row}", $rowIndex);
                // Hạng mục: merge B cho cả group
                $sheet->mergeCells("B{$startRowGroup}:B{$endRowGroup}");
                $sheet->setCellValue("B{$startRowGroup}", $hangMuc);

    // Chi tiết – chuyển <br> và dấu • thành xuống dòng
$detail0 = (string)($line0['chi_tiet'] ?? '');
$detail0 = preg_replace('/<br\s*\/?>/i', "\n", $detail0);
$detail0 = str_replace('•', "\n•", $detail0);
// tránh xuống dòng ngay đầu
$detail0 = ltrim($detail0, "\n");

$sheet->setCellValue("C{$row}", $detail0);
$sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);

                // ĐVT
                $sheet->setCellValue("D{$row}", $line0['dvt'] ?? '');
                // SL
                $sheet->setCellValue("E{$row}", $line0['so_luong'] ?? '');
                // Đơn giá
                $sheet->setCellValue("F{$row}", $line0['don_gia'] ?? '');
                // Thành tiền
                $sheet->setCellValue("G{$row}", $line0['thanh_tien'] ?? '');
                $row++;

                // Các dòng con nếu có
                for ($k = 1; $k < $groupCount; $k++) {
                    $lineN = $items[$iIdx + $k];

                    $sheet->setCellValue("A{$row}", $rowIndex + $k);
                   $detailN = (string)($lineN['chi_tiet'] ?? '');
$detailN = preg_replace('/<br\s*\/?>/i', "\n", $detailN);
$detailN = str_replace('•', "\n•", $detailN);
$detailN = ltrim($detailN, "\n");

$sheet->setCellValue("C{$row}", $detailN);
$sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);

                    $sheet->setCellValue("D{$row}", $lineN['dvt'] ?? '');
                    $sheet->setCellValue("E{$row}", $lineN['so_luong'] ?? '');
                    $sheet->setCellValue("F{$row}", $lineN['don_gia'] ?? '');
                    $sheet->setCellValue("G{$row}", $lineN['thanh_tien'] ?? '');
                    $row++;
                }

                $rowIndex += $groupCount;
                $iIdx     += $groupCount;
            }
        }

        $tableEndRow = $row - 1;

        // Áp dụng border + alignment cho vùng bảng
        $sheet->getStyle("A{$tableStartRow}:G{$tableEndRow}")->applyFromArray($borderAll);

// Cột HẠNG MỤC + CHI TIẾT: căn trái + giữa theo chiều dọc
$sheet->getStyle("B{$tableStartRow}:C{$tableEndRow}")
      ->applyFromArray($alignLeft);

        $sheet->getStyle("A{$tableStartRow}:A{$tableEndRow}")->applyFromArray($alignCenter);
        $sheet->getStyle("D{$tableStartRow}:E{$tableEndRow}")->applyFromArray($alignCenter);
        $sheet->getStyle("F{$tableStartRow}:G{$tableEndRow}")->applyFromArray($alignRight);
        // Format số cho ĐƠN GIÁ & THÀNH TIỀN (có phân tách hàng nghìn)
$sheet->getStyle("F{$tableStartRow}:G{$tableEndRow}")
      ->getNumberFormat()->setFormatCode('#,##0');
// Cho phép các dòng bảng tự tăng chiều cao theo nội dung
for ($r = $tableStartRow; $r <= $tableEndRow; $r++) {
    $sheet->getRowDimension($r)->setRowHeight(-1); // -1 = auto
}


        // ===== TÍNH TỔNG TRƯỚC / SAU VAT GIỐNG PDF =====
        $taxMode = (int)($donHang->tax_mode ?? 0);
        $vatRate = $donHang->vat_rate !== null ? (float)$donHang->vat_rate : null;

        $subtotal = $donHang->subtotal !== null ? (int)$donHang->subtotal : $tongThanhTien;
        $vatAmount = $donHang->vat_amount !== null ? (int)$donHang->vat_amount : 0;
        $grandTotal = $donHang->grand_total !== null
            ? (int)$donHang->grand_total
            : ($taxMode === 1 ? $subtotal + $vatAmount : $subtotal);

        if ($taxMode === 1 && $vatAmount === 0 && $vatRate !== null) {
            $vatAmount  = (int) round($subtotal * $vatRate / 100);
            $grandTotal = $subtotal + $vatAmount;
        }

        $rowSubtotal = $row + 1;
        $rowVat      = $rowSubtotal + 1;
        $rowGrand    = $rowSubtotal + 2;
        $rowBangChu  = $rowGrand + 1;

        // Tổng trước VAT
        $sheet->mergeCells("A{$rowSubtotal}:F{$rowSubtotal}");
        $sheet->setCellValue("A{$rowSubtotal}", 'TỔNG CHI PHÍ TRƯỚC VAT:');
        $sheet->setCellValue("G{$rowSubtotal}", $subtotal);

        // VAT
        if ($taxMode === 1) {
            $vatLabel = 'VAT';
            if ($vatRate !== null) {
                $vatPercentText = rtrim(rtrim(number_format($vatRate, 2, ',', ''), '0'), ',');
                $vatLabel .= ' (' . $vatPercentText . '%)';
            }
            $sheet->mergeCells("A{$rowVat}:F{$rowVat}");
            $sheet->setCellValue("A{$rowVat}", $vatLabel . ':');
            $sheet->setCellValue("G{$rowVat}", $vatAmount);
        }

        // Tổng sau VAT
        $sheet->mergeCells("A{$rowGrand}:F{$rowGrand}");
        $sheet->setCellValue("A{$rowGrand}", 'TỔNG CHI PHÍ SAU VAT:');
        $sheet->setCellValue("G{$rowGrand}", $grandTotal);

// Format số cho 3 dòng tổng
$sheet->getStyle("G{$rowSubtotal}:G{$rowGrand}")
      ->getNumberFormat()->setFormatCode('#,##0');


// Bằng chữ: dùng cùng logic như PDF
$tongThanhTienBangChu = $this->vnNumberToText($grandTotal);

// Label "Bằng chữ:" chiếm 2 cột A+B
$sheet->mergeCells("A{$rowBangChu}:B{$rowBangChu}");
$sheet->setCellValue("A{$rowBangChu}", 'Bằng chữ:');

// Nội dung bằng chữ chiếm từ C..G (5 cột)
$sheet->mergeCells("C{$rowBangChu}:G{$rowBangChu}");
$sheet->setCellValue("C{$rowBangChu}", $tongThanhTienBangChu);

// Căn trái cả dòng này
$sheet->getStyle("A{$rowBangChu}:G{$rowBangChu}")
      ->applyFromArray($alignLeft);


        // ===== KHỐI NGƯỜI BÁO GIÁ / XÁC NHẬN BÁO GIÁ =====
        $rowSignerTitle = $rowBangChu + 2;
        $rowSignerInfo  = $rowSignerTitle + 1;

        // Hàng tiêu đề
        $sheet->mergeCells("A{$rowSignerTitle}:D{$rowSignerTitle}");
        $sheet->mergeCells("E{$rowSignerTitle}:G{$rowSignerTitle}");
        $sheet->setCellValue("A{$rowSignerTitle}", 'NGƯỜI BÁO GIÁ');
        $sheet->setCellValue("E{$rowSignerTitle}", 'XÁC NHẬN BÁO GIÁ');
        $sheet->getStyle("A{$rowSignerTitle}:G{$rowSignerTitle}")
              ->applyFromArray($bold + $borderAll + $alignCenter);

        // Lấy thông tin signer từ DonHang
        $signerName  = $donHang->quote_signer_name  ?? '';
        $signerTitle = $donHang->quote_signer_title ?? '';
        $signerPhone = $donHang->quote_signer_phone ?? '';
        $signerEmail = $donHang->quote_signer_email ?? '';

        $approverNote = $donHang->quote_approver_note ?? '';

        // Hàng thông tin signer / approver
        $sheet->mergeCells("A{$rowSignerInfo}:D{$rowSignerInfo}");
        $sheet->mergeCells("E{$rowSignerInfo}:G{$rowSignerInfo}");

        // Text nhiều dòng cho người báo giá
        $leftLines = [];
        if ($signerName)  $leftLines[] = $signerName;
        if ($signerTitle) $leftLines[] = 'Chức vụ: ' . $signerTitle;
        if ($signerPhone) $leftLines[] = 'Điện thoại: ' . $signerPhone;
        if ($signerEmail) $leftLines[] = 'Email: ' . $signerEmail;
        $leftText = implode("\n", $leftLines);

        $sheet->setCellValue("A{$rowSignerInfo}", $leftText);
        $sheet->setCellValue("E{$rowSignerInfo}", $approverNote);
        $sheet->getStyle("A{$rowSignerInfo}:G{$rowSignerInfo}")
              ->applyFromArray($borderAll + $alignLeft);
              // Auto height cho hàng thông tin NGƯỜI BÁO GIÁ / XÁC NHẬN
$sheet->getRowDimension($rowSignerInfo)->setRowHeight(-1);

        // ===== GHI CHÚ & CHÂN TRANG GIỐNG PDF =====
        $rowNoteTitle = $rowSignerInfo + 2;
        $sheet->mergeCells("A{$rowNoteTitle}:G{$rowNoteTitle}");
        $sheet->setCellValue("A{$rowNoteTitle}", 'Ghi chú:');
        $sheet->getStyle("A{$rowNoteTitle}")->applyFromArray($bold + $alignLeft);
        $rowNoteTitle++;

        $notes = [
            '- Lần 1: Thanh toán 50% ngay sau khi Hợp đồng hoặc Xác nhận dịch vụ được ký kết.',
            '- Lần 2: Thanh toán chi phí còn lại sau 2 ngày sau khi nhận được hóa đơn tài chính.',
            '- Chi phí bao gồm: Phí nhân công thi công; kỹ thuật lắp đặt; nhân sự chạy xuyên suốt chương trình.',
            '- Thời gian lắp đặt: Trong vòng 1 ngày.',
            '- Thuế phí: Giá trên đã bao gồm thuế VAT.',
        ];

        foreach ($notes as $note) {
            $sheet->mergeCells("A{$rowNoteTitle}:G{$rowNoteTitle}");
            $sheet->setCellValue("A{$rowNoteTitle}", $note);
            $sheet->getStyle("A{$rowNoteTitle}")->applyFromArray($alignLeft);
            $rowNoteTitle++;
        }

        // Chân trang "Mọi chi tiết xin liên hệ..."
        $rowFooter = $rowNoteTitle + 1;
        $companyFooter = [
            'Mọi chi tiết xin liên hệ',
            'CÔNG TY TNHH SỰ KIỆN PHÁT HOÀNG GIA',
            'Văn phòng: 102 Nguyễn Minh Hoàng, Phường Bảy Hiền, Thành phố Hồ Chí Minh, Việt Nam',
            'Kho 1: 100 Nguyễn Minh Hoàng, P. 12, Q. Tân Bình, TP. HCM',
            'Kho 2: 111 Nguyễn Minh Hoàng, P. 12, Q. Tân Bình, TP. HCM',
            'Điện thoại: 0949 40 43 44  |  Email: info@phathoanggia.com.vn',
        ];
        foreach ($companyFooter as $line) {
            $sheet->mergeCells("A{$rowFooter}:G{$rowFooter}");
            $sheet->setCellValue("A{$rowFooter}", $line);
            $sheet->getStyle("A{$rowFooter}")->applyFromArray($alignCenter);
            $rowFooter++;
        }
        // Đảm bảo các hàng intro / người báo giá auto-height
        // (phòng trường hợp bị ghi đè bởi các style vùng lớn)
        $sheet->getRowDimension(16)->setRowHeight(-1); // "Kính gửi Quý khách hàng,"
        $sheet->getRowDimension(17)->setRowHeight(-1); // câu Intro dài
        $sheet->getRowDimension($rowSignerInfo)->setRowHeight(-1); // hàng NGƯỜI BÁO GIÁ / XÁC NHẬN

        // ===== TRẢ FILE =====
        $fileNameSafe = $donHang->ma_don_hang ?: ('DH-' . $donHang->id);
        $fileNameSafe = preg_replace('/[^A-Za-z0-9\-_]/', '_', $fileNameSafe);
        $downloadName = 'Bao-gia-' . $fileNameSafe . '.xlsx';
        // ===== CHỈNH CHIỀU CAO CHO CÁC HÀNG DÀI =====
        $defaultRowHeight = $sheet->getDefaultRowDimension()->getRowHeight();
        if (!$defaultRowHeight || $defaultRowHeight <= 0) {
            // fallback: Excel thường dùng 15pt
            $defaultRowHeight = 15;
        }

        // Intro dòng 1: hơi cao hơn bình thường (x1.5)
        if ($introRow1 !== null) {
            $sheet->getRowDimension($introRow1)
                  ->setRowHeight($defaultRowHeight * 1.5);
        }

        // Intro dài: cần cao hơn (~3 dòng)
        if ($introRow2 !== null) {
            $sheet->getRowDimension($introRow2)
                  ->setRowHeight($defaultRowHeight * 3);
        }

        // Hàng "Võ Văn An / Chức vụ / Điện thoại / Email ..."
        $sheet->getRowDimension($rowSignerInfo)
              ->setRowHeight($defaultRowHeight * 5);

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
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

    /**
     * Hàm đổi số → chữ (copy từ template PDF).
     */
    private function vnNumberToText(int $number): string
    {
        if ($number === 0) {
            return 'Không đồng';
        }

        $units  = ['',' nghìn',' triệu',' tỷ',' nghìn tỷ',' triệu tỷ'];
        $numbers = ['không','một','hai','ba','bốn','năm','sáu','bảy','tám','chín'];

        $result = '';
        $unit   = 0;

        while ($number > 0 && $unit < count($units)) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                $chunkText = '';

                $tram = intdiv($chunk, 100);
                $du   = $chunk % 100;
                $chuc = intdiv($du, 10);
                $donv = $du % 10;

                if ($tram > 0) {
                    $chunkText .= $numbers[$tram] . ' trăm';
                    if ($du > 0 && $du < 10) {
                        $chunkText .= ' lẻ';
                    }
                }

                if ($chuc > 1) {
                    $chunkText .= ' ' . $numbers[$chuc] . ' mươi';
                    if ($donv === 1) {
                        $chunkText .= ' mốt';
                    } elseif ($donv === 5) {
                        $chunkText .= ' lăm';
                    } elseif ($donv > 0) {
                        $chunkText .= ' ' . $numbers[$donv];
                    }
                } elseif ($chuc === 1) {
                    $chunkText .= ' mười';
                    if ($donv === 1) {
                        $chunkText .= ' một';
                    } elseif ($donv === 5) {
                        $chunkText .= ' lăm';
                    } elseif ($donv > 0) {
                        $chunkText .= ' ' . $numbers[$donv];
                    }
                } elseif ($chuc === 0 && $donv > 0) {
                    if ($tram > 0) {
                        $chunkText .= ' lẻ';
                    }
                    if ($donv === 5 && $tram > 0) {
                        $chunkText .= ' lăm';
                    } else {
                        $chunkText .= ' ' . $numbers[$donv];
                    }
                }

                $chunkText = trim($chunkText) . $units[$unit];
                $result = $chunkText . ($result ? ' ' . $result : '');
            }

            $number = intdiv($number, 1000);
            $unit++;
        }

        $result = trim($result);
        $result = mb_convert_case($result, MB_CASE_LOWER, 'UTF-8');
        $result = mb_strtoupper(mb_substr($result, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($result, 1, null, 'UTF-8');

        return $result . ' đồng chẵn./.';
    }
}
