<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\BangCongThang;
use App\Models\LuongThang;
use App\Models\User;
use App\Services\Payroll\BangLuongService;
use App\Services\Timesheet\BangCongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;



class PayrollExportController extends Controller
{
    /**
     * Export chi tiết Bảng công + Bảng lương cho 1 nhân viên / tháng.
     *
     * Query:
     *  - user_id: int (bắt buộc)
     *  - thang: YYYY-MM (bắt buộc)
     *  - format: csv|html (mặc định: csv)
     */
    public function exportDetail(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'min:1'],
            'thang'   => ['required', 'regex:/^\d{4}\-\d{2}$/'],
        'format'  => ['nullable', 'in:csv,html,pdf'],

        ]);

        if ($v->fails()) {
            return $this->fail($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $userId = (int) $request->input('user_id');
        $thang  = (string) $request->input('thang');
        $format = $request->input('format', 'csv');

        // Lấy user
        $user = User::query()->find($userId);
        if (!$user) {
            return $this->fail(['message' => 'Không tìm thấy người dùng.'], 'USER_NOT_FOUND', 404);
        }

        // Lấy snapshot lương tháng
        $lt = LuongThang::query()
            ->with(['user:id,name,email'])
            ->ofUser($userId)
            ->month($thang)
            ->first();

        // Nếu chưa có -> lazy compute giống adminShow
        if (!$lt) {
            try {
                /** @var BangCongService $ts */
                $ts = app(BangCongService::class);
                $ts->computeMonth($thang, $userId);

                /** @var BangLuongService $svc */
                $svc = app(BangLuongService::class);
                $svc->computeMonth($thang, $userId);

                $lt = LuongThang::query()
                    ->with(['user:id,name,email'])
                    ->ofUser($userId)
                    ->month($thang)
                    ->first();
            } catch (\Throwable $e) {
                \Log::error('PayrollExport lazy compute failed', [
                    'uid' => $userId,
                    'thang' => $thang,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        if (!$lt) {
            return $this->fail(['message' => 'Chưa có dữ liệu bảng lương cho kỳ này.'], 'NO_PAYROLL', 404);
        }

        // Lấy bảng công (nếu có) để bổ sung
        $bc = BangCongThang::query()
            ->ofUser($userId)
            ->month($thang)
            ->first();

        // Giải mã ghi_chu (metrics) từ LuongThang
        $note = null;
        if (!empty($lt->ghi_chu)) {
            if (is_string($lt->ghi_chu)) {
                try {
                    $note = json_decode($lt->ghi_chu, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    $note = null;
                }
            } elseif (is_array($lt->ghi_chu) || $lt->ghi_chu instanceof \JsonSerializable) {
                $note = (array) $lt->ghi_chu;
            }
        }

        $metrics = [
            'mode'           => $note['mode']        ?? null,
            'base'           => isset($note['base']) ? (int)$note['base'] : null,
            'daily_rate'     => isset($note['daily_rate']) ? (int)$note['daily_rate'] : null,
            'bh_base'        => isset($note['bh_base']) ? (int)$note['bh_base'] : null,
            'std_minutes'    => isset($note['std_minutes'])    ? (int)$note['std_minutes']    : null,
            'actual_minutes' => isset($note['actual_minutes']) ? (int)$note['actual_minutes'] : null,
            'base_minutes'   => isset($note['base_minutes'])   ? (int)$note['base_minutes']   : null,
            'ot_minutes'     => isset($note['ot_minutes'])     ? (int)$note['ot_minutes']     : null,
            'unit_base_min'  => isset($note['unit_base_min'])  ? (int)$note['unit_base_min']  : null,
            'ot_rate_per_min'=> isset($note['ot_rate_per_min'])? (int)$note['ot_rate_per_min']: null,
            'ot_amount'      => isset($note['ot_amount'])      ? (int)$note['ot_amount']      : null,
        ];

        // Chuẩn bị payload chung cho cả CSV & HTML
        $tongBH = (int)$lt->bhxh + (int)$lt->bhyt + (int)$lt->bhtn;
        $P_gross =
            (int)$lt->luong_theo_cong
            + (int)$lt->phu_cap
            + (int)$lt->thuong
            - (int)$lt->phat;

        $Q_ins = $tongBH;
        $R_ded = (int)$lt->khau_tru_khac;
        $T_adv = (int)$lt->tam_ung;
        $U_net = (int)$lt->thuc_nhan;

        $timesheet = $bc ? [
            'thang'                     => $bc->thang,
            'so_ngay_cong'              => (int)$bc->so_ngay_cong,
            'so_gio_cong'               => (int)$bc->so_gio_cong, // phút
            'di_tre_phut'               => (int)$bc->di_tre_phut,
            've_som_phut'               => (int)$bc->ve_som_phut,
            'nghi_phep_ngay'            => (int)$bc->nghi_phep_ngay,
            'nghi_phep_gio'             => (int)$bc->nghi_phep_gio,
            'nghi_khong_luong_ngay'     => (int)$bc->nghi_khong_luong_ngay,
            'nghi_khong_luong_gio'      => (int)$bc->nghi_khong_luong_gio,
            'lam_them_gio'              => (int)$bc->lam_them_gio, // phút OT
            'locked'                    => (bool)$bc->locked,
            'computed_at'               => $bc->computed_at?->toDateTimeString(),
        ] : null;

        $payroll = [
            'thang'           => (string)$lt->thang,
            'luong_co_ban'    => (int)$lt->luong_co_ban,
            'cong_chuan'      => (int)$lt->cong_chuan,
            'he_so'           => (float)$lt->he_so,
            'so_ngay_cong'    => (float)$lt->so_ngay_cong,
            'so_gio_cong'     => (int)$lt->so_gio_cong, // phút
            'luong_theo_cong' => (int)$lt->luong_theo_cong,
            'phu_cap'         => (int)$lt->phu_cap,
            'thuong'          => (int)$lt->thuong,
            'phat'            => (int)$lt->phat,
            'bhxh'            => (int)$lt->bhxh,
            'bhyt'            => (int)$lt->bhyt,
            'bhtn'            => (int)$lt->bhtn,
            'khau_tru_khac'   => (int)$lt->khau_tru_khac,
            'tam_ung'         => (int)$lt->tam_ung,
            'thuc_nhan'       => (int)$lt->thuc_nhan,
            'locked'          => (bool)$lt->locked,
            'computed_at'     => $lt->computed_at?->toDateTimeString(),
            'P_gross'         => $P_gross,
            'Q_insurance'     => $Q_ins,
            'R_deduct_other'  => $R_ded,
            'T_advance'       => $T_adv,
            'U_net'           => $U_net,
        ];

        if ($format === 'html') {
    return $this->exportHtml($user, $timesheet, $payroll, $metrics);
}

if ($format === 'pdf') {
    return $this->exportPdf($user, $timesheet, $payroll, $metrics);
}

// Mặc định: CSV (Excel mở được)
return $this->exportCsv($user, $timesheet, $payroll, $metrics);

    }

    /**
     * Export dạng CSV (Excel đọc được).
     */
    protected function exportCsv(User $user, ?array $timesheet, array $payroll, array $metrics): StreamedResponse
    {
        $filename = $this->makeFilename($user, $payroll, 'csv');

        $callback = function () use ($user, $timesheet, $payroll, $metrics) {
            $out = fopen('php://output', 'w');

            // Đảm bảo Excel hiểu UTF-8
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // --- Block 1: Thông tin nhân viên ---
            fputcsv($out, ['Thông tin nhân viên']);
            fputcsv($out, ['User ID', $user->id]);
            fputcsv($out, ['Họ tên', $user->name ?? '']);
            fputcsv($out, ['Email', $user->email ?? '']);
            fputcsv($out, ['Kỳ lương', $payroll['thang']]);
            fputcsv($out, []); // dòng trống

            // --- Block 2: Bảng công tóm tắt ---
            fputcsv($out, ['Bảng công tóm tắt']);
            if ($timesheet) {
                fputcsv($out, [
                    'Tháng kỳ công',
                    'Số ngày công',
                    'Số phút công',
                    'Đi trễ (phút)',
                    'Về sớm (phút)',
                    'Nghỉ phép (ngày)',
                    'Nghỉ phép (giờ)',
                    'Nghỉ không lương (ngày)',
                    'Nghỉ không lương (giờ)',
                    'Làm thêm (phút)',
                    'Đã khóa',
                    'Tổng hợp lúc',
                ]);
                fputcsv($out, [
                    $timesheet['thang'],
                    $timesheet['so_ngay_cong'],
                    $timesheet['so_gio_cong'],
                    $timesheet['di_tre_phut'],
                    $timesheet['ve_som_phut'],
                    $timesheet['nghi_phep_ngay'],
                    $timesheet['nghi_phep_gio'],
                    $timesheet['nghi_khong_luong_ngay'],
                    $timesheet['nghi_khong_luong_gio'],
                    $timesheet['lam_them_gio'],
                    $timesheet['locked'] ? 1 : 0,
                    $timesheet['computed_at'],
                ]);
            } else {
                fputcsv($out, ['(Không có dữ liệu bảng công cho kỳ này)']);
            }

            // Thêm thông tin phút công theo lương
            fputcsv($out, []);
            fputcsv($out, ['Thông tin phút công (theo lương)']);
            fputcsv($out, ['Số phút công tiêu chuẩn', $metrics['std_minutes'] ?? '']);
            fputcsv($out, ['Số phút công thực tế (tính lương)', $metrics['actual_minutes'] ?? '']);
            fputcsv($out, ['Số phút tính lương cơ bản', $metrics['base_minutes'] ?? '']);
            fputcsv($out, ['Số phút tăng ca (tính lương)', $metrics['ot_minutes'] ?? '']);

            // --- Block 3: Bảng lương chi tiết ---
            fputcsv($out, []);
            fputcsv($out, ['Bảng lương chi tiết']);
            fputcsv($out, ['Chỉ tiêu', 'Giá trị']);

            fputcsv($out, ['Lương cơ bản', $payroll['luong_co_ban']]);
            fputcsv($out, ['Công chuẩn (ngày)', $payroll['cong_chuan']]);
            fputcsv($out, ['Hệ số', $payroll['he_so']]);
            fputcsv($out, ['Ngày công', $payroll['so_ngay_cong']]);
            fputcsv($out, ['Số phút công (raw từ bảng công)', $payroll['so_gio_cong']]);

            fputcsv($out, ['Đơn giá lương cơ bản / phút', $metrics['unit_base_min'] ?? '']);
            fputcsv($out, ['Đơn giá tăng ca / phút', $metrics['ot_rate_per_min'] ?? '']);
            fputcsv($out, ['Lương tăng ca (từ phút tăng ca)', $metrics['ot_amount'] ?? '']);

            fputcsv($out, ['Lương theo công/khoán', $payroll['luong_theo_cong']]);
            fputcsv($out, ['Phụ cấp', $payroll['phu_cap']]);
            fputcsv($out, ['Thưởng', $payroll['thuong']]);
            fputcsv($out, ['Phạt', $payroll['phat']]);

            fputcsv($out, ['BHXH', $payroll['bhxh']]);
            fputcsv($out, ['BHYT', $payroll['bhyt']]);
            fputcsv($out, ['BHTN', $payroll['bhtn']]);
            fputcsv($out, ['Tổng bảo hiểm (Q)', $payroll['Q_insurance']]);

            fputcsv($out, ['Khấu trừ khác (R)', $payroll['R_deduct_other']]);
            fputcsv($out, ['Tạm ứng (T)', $payroll['T_advance']]);

            fputcsv($out, ['P (Gross) = Lương công + phụ cấp + thưởng - phạt', $payroll['P_gross']]);
            fputcsv($out, ['U (Thực nhận) = P - Q - R - T', $payroll['U_net']]);

            fputcsv($out, ['Đã khóa bảng lương?', $payroll['locked'] ? 1 : 0]);
            fputcsv($out, ['Thời điểm tính (computed_at)', $payroll['computed_at']]);

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Tạo tên file export dạng:
     * "Bảng lương 2025-11 — Vũ Thị Thanh Huyền (#116).ext"
     */
    private function makeFilename(User $user, array $payroll, string $ext): string
    {
        $tenHienThi = $user->name ?: ($user->email ?: 'NV');
        $title = sprintf(
            'Bảng lương %s — %s (#%d)',
            $payroll['thang'] ?? '',
            $tenHienThi,
            $user->id
        );

        // Gom khoảng trắng thừa (cho chắc)
        $title = preg_replace('/\s+/', ' ', $title);

        return $title . '.' . $ext;
    }



    /**
     * Export dạng HTML (in ra PDF từ trình duyệt).
     */
    protected function exportHtml(User $user, ?array $timesheet, array $payroll, array $metrics)
    {
        $title = sprintf(
            'Bảng lương %s — %s (#%d)',
            $payroll['thang'],
            $user->name ?? $user->email ?? '',
            $user->id
        );

        $html = view('reports.payroll_export_html', [
            'title'     => $title,
            'user'      => $user,
            'timesheet' => $timesheet,
            'payroll'   => $payroll,
            'metrics'   => $metrics,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
    /**
     * Export dạng PDF (download file .pdf) bằng dompdf.
     */
    protected function exportPdf(User $user, ?array $timesheet, array $payroll, array $metrics)
    {
        $title = sprintf(
            'Bảng lương %s — %s (#%d)',
            $payroll['thang'],
            $user->name ?? $user->email ?? '',
            $user->id
        );

        $viewName = 'reports.payroll_export_html'; // hoặc 'report.payroll_export_html' nếu bạn đang dùng thư mục report

        // Render blade sang PDF
        $pdf = Pdf::loadView($viewName, [
            'title'     => $title,
            'user'      => $user,
            'timesheet' => $timesheet,
            'payroll'   => $payroll,
            'metrics'   => $metrics,
        ])->setPaper('a4', 'portrait');

         $filename = $this->makeFilename($user, $payroll, 'pdf');

        // Tải file PDF
        return $pdf->download($filename);
        // Nếu muốn xem inline thay vì tải về:
        // return $pdf->stream($filename);
    }

    // Nếu bạn chưa dùng blade view, có thể thay exportHtml() ở trên
    // bằng 1 string HTML inline. Ở đây mình dùng view để dễ chỉnh sửa.

    // ===== Helper JSON response (khi lỗi) =====
    private function ok($data = [], string $code = 'OK', int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::success($data, $code, $status);
        }
        return response()->json(['success' => true, 'data' => $data, 'code' => $code], $status);
    }

    private function fail($data = [], string $code = 'ERROR', int $status = 400)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::failed($data, $code, $status);
        }
        return response()->json(['success' => false, 'data' => $data, 'code' => $code], $status);
    }
}
