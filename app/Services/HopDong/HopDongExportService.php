<?php

namespace App\Services\HopDong;

use App\Models\HopDong;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Process\Process;
use Exception;

/**
 * Export HỢP ĐỒNG:
 * - DOCX: dùng template .docx cố định, chỉ thay các biến pháp lý & bảng hạng mục
 * - PDF: convert từ DOCX bằng LibreOffice (cho layout y chang Word)
 *
 * Lưu ý:
 *  - Template đặt tại: storage/app/contracts/templates/
 *  - Ưu tiên templates_token.docx, nếu không có thì dùng templates.docx
 */
class HopDongExportService
{
    /**
     * Lấy đường dẫn file template DOCX.
     */
    protected function getTemplatePath(): string
    {
        $baseDir = storage_path('app/contracts/templates');

        $candidates = [
            $baseDir . '/templates_token.docx', // nếu mày dùng tên này
            $baseDir . '/templates.docx',       // fallback
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new Exception('Không tìm thấy file template Hợp đồng trong thư mục: ' . $baseDir);
    }
    /**
     * Lấy đường dẫn file template DOCX SONG NGỮ.
     * Ưu tiên: templates_token_bilingual.docx → templates_token.docx → templates.docx
     */
    protected function getTemplatePathBilingual(): string
    {
        $baseDir = storage_path('app/contracts/templates');

        $candidates = [
            $baseDir . '/templates_token_bilingual.docx', // bản Việt–Anh
            $baseDir . '/templates_token.docx',           // fallback: bản Việt
            $baseDir . '/templates.docx',                 // fallback cuối cùng
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new Exception('Không tìm thấy file template Hợp đồng SONG NGỮ trong thư mục: ' . $baseDir);
    }

    /**
     * Tạo file DOCX Hợp đồng từ template và trả về đường dẫn tuyệt đối.
     *
     * - Đọc hop_dongs + don_hangs + items
     * - Map các token pháp lý + bảng hạng mục vào template
     */
    public function exportDocx(HopDong $hopDong): string
    {
        // Load liên kết cần thiết
        $hopDong->loadMissing(['donHang', 'items']);

        $templatePath = $this->getTemplatePath();
        if (! file_exists($templatePath)) {
            throw new Exception('Không tìm thấy file template Hợp đồng: ' . $templatePath);
        }

        // Build token map
        $tokenData = $this->buildTokenData($hopDong);

        // Khởi tạo TemplateProcessor
        $processor = new TemplateProcessor($templatePath);

        // Đổ từng token text vào
        foreach ($tokenData as $token => $value) {
            // Trong DOCX, token dạng ${TOKEN}, còn ở đây chỉ truyền "TOKEN"
            $processor->setValue($token, $value ?? '');
        }

        // Đổ bảng HẠNG MỤC (cloneRow trên ITEM_STT)
        $this->fillItemsTable($processor, $hopDong);

        // Lưu DOCX
        $exportDir = storage_path('app/contracts/exports');
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0775, true);
        }

        $fileName   = 'hop_dong_' . ($hopDong->id ?? 'tmp') . '_' . time() . '.docx';
        $outputPath = $exportDir . DIRECTORY_SEPARATOR . $fileName;

        $processor->saveAs($outputPath);

        return $outputPath;
    }
    /**
     * Tạo file DOCX Hợp đồng SONG NGỮ (Việt – Anh) từ template bilingual.
     *
     * - Template: templates_token_bilingual.docx
     * - Vẫn dùng chung token & bảng hạng mục như bản tiếng Việt
     */
    public function exportDocxBilingual(HopDong $hopDong): string
    {
        // Load liên kết cần thiết
        $hopDong->loadMissing(['donHang', 'items']);

        $templatePath = $this->getTemplatePathBilingual();
        if (! file_exists($templatePath)) {
            throw new Exception('Không tìm thấy file template Hợp đồng SONG NGỮ: ' . $templatePath);
        }

        // Build token map (dùng lại logic như bản thường)
        $tokenData = $this->buildTokenData($hopDong);

        // Khởi tạo TemplateProcessor với template song ngữ
        $processor = new TemplateProcessor($templatePath);

        // Đổ từng token text vào
        foreach ($tokenData as $token => $value) {
            $processor->setValue($token, $value ?? '');
        }

        // Đổ bảng HẠNG MỤC
        $this->fillItemsTable($processor, $hopDong);

        // Lưu DOCX
        $exportDir = storage_path('app/contracts/exports');
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0775, true);
        }

        $fileName   = 'hop_dong_bilingual_' . ($hopDong->id ?? 'tmp') . '_' . time() . '.docx';
        $outputPath = $exportDir . DIRECTORY_SEPARATOR . $fileName;

        $processor->saveAs($outputPath);

        return $outputPath;
    }

    /**
     * Tạo file PDF từ DOCX Hợp đồng bằng LibreOffice headless, trả về đường dẫn tuyệt đối.
     */
    /**
     * Tạo file PDF Hợp đồng từ DOCX bằng LibreOffice headless
     * - PDF giống y hệt Word
     */
    public function exportPdf(HopDong $hopDong): string
    {
        // 1) Tạo DOCX trước
        $docxPath = $this->exportDocx($hopDong);

        $exportDir = dirname($docxPath);
        $pdfName   = pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';
        $pdfPath   = $exportDir . DIRECTORY_SEPARATOR . $pdfName;

        // 2) Dùng absolute path LibreOffice
        $libreofficeBin = '/usr/bin/libreoffice';
        if (! file_exists($libreofficeBin)) {
            throw new Exception(
                'Không tìm thấy LibreOffice tại ' . $libreofficeBin .
                ' – kiểm tra lại cài đặt libreoffice.'
            );
        }

        // 3) Chạy LibreOffice trong thư mục export, dùng tên file ngắn
        $command = [
            $libreofficeBin,
            '--headless',
            '--convert-to',
            'pdf',
            basename($docxPath), // templates_xxx.docx
            '--outdir',
            $exportDir,
        ];

        $env = [
            // nhiều khi LibreOffice cần HOME hợp lệ khi chạy dưới user www-data
            'HOME' => sys_get_temp_dir(),
        ];

        $process = new Process(
            $command,
            $exportDir, // cwd: chạy trong thư mục chứa DOCX
            $env
        );

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            $err = $process->getErrorOutput() ?: $process->getOutput();
            Log::error('[HopDongExport] LibreOffice error: ' . $err);

            throw new Exception(
                'Lỗi khi chuyển DOCX sang PDF (LibreOffice).'
            );
        }

        if (! file_exists($pdfPath)) {
            Log::error('[HopDongExport] LibreOffice convert xong nhưng không thấy file PDF: ' . $pdfPath);
            throw new Exception('Không tìm thấy file PDF sau khi convert: ' . $pdfPath);
        }

        return $pdfPath;
    }


    /**
     * Build toàn bộ token text cho template DOCX (không dùng body_json).
     *
     * Token dùng:
     *  - SO_HOP_DONG
     *  - TEN_BEN_A, DIA_CHI_BEN_A, MST_BEN_A, DAI_DIEN_BEN_A, CHUC_VU_BEN_A
     *  - NGAY_HD_TEXT
     *  - TEN_SU_KIEN, THOI_GIAN_SU_KIEN_TEXT, THOI_GIAN_SETUP_TEXT, DIA_DIEM_SU_KIEN
     *  - TONG_TRUOC_VAT, VAT_RATE, VAT_AMOUNT, TONG_SAU_VAT, TONG_SAU_VAT_TEXT
     *  - DOT1_PERCENT, DOT1_AMOUNT, DOT1_AMOUNT_TEXT
     *  - DOT2_PERCENT, DOT2_AMOUNT, DOT2_AMOUNT_TEXT
     *  - SIGNATURE_A, SIGNATURE_B
     */
    protected function buildTokenData(HopDong $hopDong): array
    {
        $donHang = $hopDong->donHang;
        if (! $donHang) {
            throw new Exception('Hợp đồng không có Báo giá gốc (don_hang_id).');
        }

        $formatMoney = function (int $n): string {
            return number_format($n, 0, ',', '.');
        };

// ===== Ngày HĐ dạng text =====
// Ưu tiên ngày_hop_dong; nếu chưa có thì fallback created_at; nếu vẫn null thì lấy hôm nay
$dateSource = $hopDong->ngay_hop_dong
    ?? $hopDong->created_at
    ?? Carbon::now();

$ngayHdText = $this->formatNgayHopDongText($dateSource);


        // ===== BÊN A (khách hàng) =====
        $tenBenA     = $hopDong->ben_a_ten     ?: ($donHang->ten_khach_hang ?? '');
        $diaChiBenA  = $hopDong->ben_a_dia_chi ?: ($donHang->dia_chi_giao_hang ?? '');
        $mstBenA     = $hopDong->ben_a_mst     ?? '';
        $daiDienBenA = $hopDong->ben_a_dai_dien ?? '';
        $chucVuBenA  = $hopDong->ben_a_chuc_vu  ?? '';

        // ===== Sự kiện =====
        $tenSuKien = $hopDong->su_kien_ten
            ?? $donHang->project_name
            ?? $donHang->event_type
            ?? '';

        $thoiGianSuKienText = $hopDong->su_kien_thoi_gian_text ?? '';
        $thoiGianSetupText  = $hopDong->su_kien_thoi_gian_setup_text ?? '';
        $diaDiemSuKien      = $hopDong->su_kien_dia_diem
            ?? ($donHang->venue_name
                ? trim($donHang->venue_name . ' - ' . ($donHang->venue_address ?? ''))
                : ($donHang->venue_address ?? $donHang->dia_chi_giao_hang ?? ''));

        // ===== Tiền & VAT =====
        $tongTruocVat = (int) ($hopDong->tong_truoc_vat ?? 0);
        $vatRate      = $hopDong->vat_rate !== null ? (float) $hopDong->vat_rate : 0.0;
        $vatAmount    = (int) ($hopDong->vat_amount ?? 0);
        $tongSauVat   = (int) ($hopDong->tong_sau_vat ?? 0);
        $tongSauVatText = (string) ($hopDong->tong_sau_vat_bang_chu ?? '');

        // ===== Thanh toán đợt 1 / đợt 2 =====
        $dot1Percent = (int) ($hopDong->dot1_ty_le ?? 0);
        $dot1Amount  = (int) ($hopDong->dot1_so_tien ?? 0);
        if ($dot1Amount === 0 && $dot1Percent > 0 && $tongSauVat > 0) {
            $dot1Amount = (int) round($tongSauVat * $dot1Percent / 100);
        }
        $dot1AmountText = $dot1Amount > 0
            ? $this->vnNumberToText($dot1Amount)
            : '';

        $dot2Percent = (int) ($hopDong->dot2_ty_le ?? 0);
        $dot2Amount  = (int) ($hopDong->dot2_so_tien ?? 0);
        if ($dot2Amount === 0 && $dot2Percent > 0 && $tongSauVat > 0) {
            $dot2Amount = (int) round($tongSauVat * $dot2Percent / 100);
        }
        $dot2AmountText = $dot2Amount > 0
            ? $this->vnNumberToText($dot2Amount)
            : '';

        // ===== Chữ ký =====
        // BÊN A: lấy xưng hô (Ông/Bà) từ cột ben_a_xung_ho, nếu có
        $signatureA = '';
        if (!empty($hopDong->ben_a_dai_dien)) {
            $xhA = $hopDong->ben_a_xung_ho ?? null; // "Ông" | "Bà" | null
            $signatureA = ($xhA ? '(' . $xhA . ') ' : '') . $hopDong->ben_a_dai_dien;
        }

        // BÊN B: lấy xưng hô từ ben_b_xung_ho (mặc định đã set "Ông" trong Service)
        $signatureB = '';
        if (!empty($hopDong->ben_b_dai_dien)) {
            $xhB = $hopDong->ben_b_xung_ho ?? null;
            $signatureB = ($xhB ? '(' . $xhB . ') ' : '') . $hopDong->ben_b_dai_dien;
        }


        // ===== Build map =====
        $data = [
            // Header / trang bìa
            'SO_HOP_DONG'  => (string) ($hopDong->so_hop_dong ?? ''),
            'TEN_BEN_A'    => (string) $tenBenA,
            'NGAY_HD_TEXT' => $ngayHdText,

            // Bên A
            'DIA_CHI_BEN_A'   => (string) $diaChiBenA,
            'MST_BEN_A'       => (string) $mstBenA,
            'DAI_DIEN_BEN_A'  => (string) $daiDienBenA,
            'CHUC_VU_BEN_A'   => (string) $chucVuBenA,

            // Sự kiện
            'TEN_SU_KIEN'             => (string) $tenSuKien,
            'THOI_GIAN_SU_KIEN_TEXT'  => (string) $thoiGianSuKienText,
            'THOI_GIAN_SETUP_TEXT'    => (string) $thoiGianSetupText,
            'DIA_DIEM_SU_KIEN'        => (string) $diaDiemSuKien,

            // Tiền & VAT
            'TONG_TRUOC_VAT'   => $formatMoney($tongTruocVat),
            'VAT_RATE'         => $vatRate > 0 ? rtrim(rtrim(number_format($vatRate, 2, ',', ''), '0'), ',') . '%' : '',
            'VAT_AMOUNT'       => $formatMoney($vatAmount),
            'TONG_SAU_VAT'     => $formatMoney($tongSauVat),
            'TONG_SAU_VAT_TEXT'=> $tongSauVatText,

            // Thanh toán
            'DOT1_PERCENT'     => (string) $dot1Percent,
            'DOT1_AMOUNT'      => $formatMoney($dot1Amount),
            'DOT1_AMOUNT_TEXT' => $dot1AmountText,

            'DOT2_PERCENT'     => (string) $dot2Percent,
            'DOT2_AMOUNT'      => $formatMoney($dot2Amount),
            'DOT2_AMOUNT_TEXT' => $dot2AmountText,

            // Chữ ký
            'SIGNATURE_A'      => $signatureA,
            'SIGNATURE_B'      => $signatureB,
        ];

        return $data;
    }

    /**
     * Đổ bảng Hạng mục Hợp đồng vào template DOCX
     * - Template phải có 1 dòng chứa các token:
     *   ${ITEM_STT}, ${ITEM_HANGMUC}, ${ITEM_CHITIET}, ${ITEM_DVT},
     *   ${ITEM_SL}, ${ITEM_DONGIA}, ${ITEM_THANHTIEN}
     * - Hàm này sẽ cloneRow('ITEM_STT', n) và fill từng dòng
     */
    protected function fillItemsTable(TemplateProcessor $processor, HopDong $hopDong): void
    {
        $items = $hopDong->items;

        if (! $items || $items->isEmpty()) {
            return;
        }

        // Kiểm tra template có biến ITEM_STT hay không
        $variables = $processor->getVariables();
        if (! in_array('ITEM_STT', $variables, true)) {
            // Template hiện tại chưa hỗ trợ cloneRow cho bảng
            return;
        }

        $count = $items->count();

        // cloneRow: trong DOCX token phải là ${ITEM_STT}
        $processor->cloneRow('ITEM_STT', $count);

        $formatMoney = function (int $n): string {
            return number_format($n, 0, ',', '.');
        };

        foreach ($items as $index => $it) {
            $i = $index + 1;

            $processor->setValue("ITEM_STT#{$i}", $i);
            $processor->setValue("ITEM_HANGMUC#{$i}", (string) ($it->hang_muc ?? ''));
            $processor->setValue("ITEM_CHITIET#{$i}", (string) ($it->chi_tiet ?? ''));
            $processor->setValue("ITEM_DVT#{$i}", (string) ($it->dvt ?? ''));
            $processor->setValue("ITEM_SL#{$i}", (string) ($it->so_luong ?? ''));
            $processor->setValue("ITEM_DONGIA#{$i}", $formatMoney((int) ($it->don_gia ?? 0)));
            $processor->setValue("ITEM_THANHTIEN#{$i}", $formatMoney((int) ($it->thanh_tien ?? 0)));
        }
    }

    /**
     * Định dạng ngày HĐ: "Ngày 13 Tháng 10 Năm 2025"
     */
    protected function formatNgayHopDongText($ngay): string
    {
        if (! $ngay) {
            return '';
        }

        $d = $ngay instanceof Carbon ? $ngay : Carbon::parse($ngay);

        return sprintf(
            'Ngày %d Tháng %d Năm %d',
            (int) $d->format('d'),
            (int) $d->format('m'),
            (int) $d->format('Y')
        );
    }

    /**
     * Chuyển số -> chữ (VNĐ) dùng cho DOT1_AMOUNT_TEXT, DOT2_AMOUNT_TEXT
     * - Copy từ logic HopDongService cũ, đơn giản hoá
     */
    protected function vnNumberToText(int $number): string
    {
        if ($number === 0) {
            return 'Không đồng';
        }

        $units = ['', ' nghìn', ' triệu', ' tỷ', ' nghìn tỷ', ' triệu tỷ'];
        $nums  = ['không','một','hai','ba','bốn','năm','sáu','bảy','tám','chín'];

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
                    $chunkText .= $nums[$tram] . ' trăm';
                    if ($du > 0 && $du < 10) {
                        $chunkText .= ' lẻ';
                    }
                }

                if ($chuc > 1) {
                    $chunkText .= ' ' . $nums[$chuc] . ' mươi';
                    if ($donv === 1) {
                        $chunkText .= ' mốt';
                    } elseif ($donv === 5) {
                        $chunkText .= ' lăm';
                    } elseif ($donv > 0) {
                        $chunkText .= ' ' . $nums[$donv];
                    }
                } elseif ($chuc === 1) {
                    $chunkText .= ' mười';
                    if ($donv === 1) {
                        $chunkText .= ' một';
                    } elseif ($donv === 5) {
                        $chunkText .= ' lăm';
                    } elseif ($donv > 0) {
                        $chunkText .= ' ' . $nums[$donv];
                    }
                } elseif ($chuc === 0 && $donv > 0) {
                    if ($tram > 0) {
                        $chunkText .= ' lẻ';
                    }
                    if ($donv === 5 && $tram > 0) {
                        $chunkText .= ' lăm';
                    } else {
                        $chunkText .= ' ' . $nums[$donv];
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

    /**
     * Build token map dùng cho PREVIEW (body_json / Blade)
     * - Đây là map với dạng {TEN_BEN_A}, {TONG_SAU_VAT}..., KHÁC với ${...} trong DOCX
     */
    protected function buildTokenMap(HopDong $hopDong, $donHang = null): array
    {
        $donHang = $donHang ?: $hopDong->donHang;

        $tongSauVat = (int) ($hopDong->tong_sau_vat ?? 0);
        $dot1TyLe   = (int) ($hopDong->dot1_ty_le ?? 0);
        $dot2TyLe   = (int) ($hopDong->dot2_ty_le ?? 0);
        $dot1Amount = (int) ($hopDong->dot1_so_tien ?? 0);
        $dot2Amount = (int) ($hopDong->dot2_so_tien ?? 0);

        if ($tongSauVat > 0) {
            if ($dot1Amount <= 0 && $dot1TyLe > 0) {
                $dot1Amount = (int) round($tongSauVat * $dot1TyLe / 100);
            }
            if ($dot2Amount <= 0 && $dot2TyLe > 0) {
                $dot2Amount = (int) round($tongSauVat * $dot2TyLe / 100);
            }
        }

$dateSource = $hopDong->ngay_hop_dong
    ?? $hopDong->created_at
    ?? Carbon::now();

$ngayHdText = $this->formatNgayHopDongText($dateSource);


        $suKienTen = $hopDong->su_kien_ten
            ?? $donHang->project_name
            ?? $donHang->event_type
            ?? '';

        $suKienThoiGianText = $hopDong->su_kien_thoi_gian_text ?? '';
        $suKienSetupText    = $hopDong->su_kien_thoi_gian_setup_text ?? '';

        $suKienDiaDiem = $hopDong->su_kien_dia_diem
            ?? ($donHang->venue_name
                ? trim($donHang->venue_name . ' - ' . ($donHang->venue_address ?? ''))
                : ($donHang->venue_address ?? $donHang->dia_chi_giao_hang ?? ''));

        $formatMoney = function (int $n): string {
            return number_format($n, 0, ',', '.');
        };
        // ===== Đại diện (xưng hô + tên) cho Bên A/B =====
        $repA = '';
        if (!empty($hopDong->ben_a_dai_dien)) {
            $xhA = $hopDong->ben_a_xung_ho ?? null; // "Ông" | "Bà" | null
            $repA = ($xhA ? '(' . $xhA . ') ' : '') . $hopDong->ben_a_dai_dien;
        }

        $repB = '';
        if (!empty($hopDong->ben_b_dai_dien)) {
            $xhB = $hopDong->ben_b_xung_ho ?? null;
            $repB = ($xhB ? '(' . $xhB . ') ' : '') . $hopDong->ben_b_dai_dien;
        }

        return [
            '{SO_HOP_DONG}'  => (string) ($hopDong->so_hop_dong ?? ''),
            '{NGAY_HD_TEXT}' => $ngayHdText,

            '{TEN_BEN_A}'        => (string) ($hopDong->ben_a_ten ?? $donHang->ten_khach_hang ?? ''),
            '{DIA_CHI_BEN_A}'    => (string) ($hopDong->ben_a_dia_chi ?? $donHang->dia_chi_giao_hang ?? ''),
            '{MST_BEN_A}'        => (string) ($hopDong->ben_a_mst ?? ''),
              '{DAI_DIEN_BEN_A}'   => $repA,
            '{CHUC_VU_BEN_A}'    => (string) ($hopDong->ben_a_chuc_vu ?? ''),
            '{DIEN_THOAI_BEN_A}' => (string) ($hopDong->ben_a_dien_thoai ?? $donHang->so_dien_thoai ?? ''),
            '{EMAIL_BEN_A}'      => (string) ($hopDong->ben_a_email ?? ''),

            '{TEN_BEN_B}'        => (string) ($hopDong->ben_b_ten ?? 'CÔNG TY TNHH SỰ KIỆN PHÁT HOÀNG GIA'),
            '{DIA_CHI_BEN_B}'    => (string) ($hopDong->ben_b_dia_chi ?? ''),
            '{MST_BEN_B}'        => (string) ($hopDong->ben_b_mst ?? ''),
            '{TAI_KHOAN_BEN_B}'  => (string) ($hopDong->ben_b_tai_khoan ?? ''),
            '{NGAN_HANG_BEN_B}'  => (string) ($hopDong->ben_b_ngan_hang ?? ''),
     '{DAI_DIEN_BEN_B}'   => $repB,
            '{CHUC_VU_BEN_B}'    => (string) ($hopDong->ben_b_chuc_vu ?? ''),

            '{TEN_SU_KIEN}'            => (string) $suKienTen,
            '{THOI_GIAN_SU_KIEN_TEXT}' => (string) $suKienThoiGianText,
            '{THOI_GIAN_SETUP_TEXT}'   => (string) $suKienSetupText,
            '{DIA_DIEM_SU_KIEN}'       => (string) $suKienDiaDiem,

            '{TONG_SAU_VAT}'      => $formatMoney($tongSauVat),
            '{TONG_SAU_VAT_TEXT}' => (string) ($hopDong->tong_sau_vat_bang_chu ?? ''),

            '{DOT1_PERCENT}'     => (string) $dot1TyLe,
            '{DOT1_AMOUNT}'      => $formatMoney($dot1Amount),
            '{DOT1_AMOUNT_TEXT}' => '',
            '{DOT1_TIME_TEXT}'   => (string) ($hopDong->dot1_thoi_diem_text ?? ''),

            '{DOT2_PERCENT}'     => (string) $dot2TyLe,
            '{DOT2_AMOUNT}'      => $formatMoney($dot2Amount),
            '{DOT2_AMOUNT_TEXT}' => '',
            '{DOT2_TIME_TEXT}'   => (string) ($hopDong->dot2_thoi_diem_text ?? ''),
        ];
    }

    /**
     * Build danh sách block đã thay token dựa trên hop_dongs.body_json
     * - Dùng cho PREVIEW HTML (view hop-dong.export-pdf)
     * - Không ảnh hưởng tới export DOCX/PDF template
     */
    public function buildResolvedBlocks(HopDong $hopDong): array
    {
        $blocks = $hopDong->body_json;
        if (! is_array($blocks)) {
            return [];
        }

        $donHang  = $hopDong->donHang;
        $tokenMap = $this->buildTokenMap($hopDong, $donHang);

        $resolved = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $key   = (string) ($block['key']   ?? '');
            $label = (string) ($block['label'] ?? $key);
            $text  = (string) ($block['text']  ?? '');

            if ($key === '' || $text === '') {
                continue;
            }

            $final = strtr($text, $tokenMap);

            $resolved[] = [
                'key'   => $key,
                'label' => $label,
                'text'  => $final,
            ];
        }

        return $resolved;
    }


}
