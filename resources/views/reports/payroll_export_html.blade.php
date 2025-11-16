<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>{{ $title ?? 'Bảng lương' }}</title>
  <style>
    /* DomPDF: CHỈ dùng CSS 2.1 cơ bản */
    * { font-family: "DejaVu Sans", Arial, sans-serif !important; }
    html, body { font-size: 12px; color: #2E3A63; margin: 18px; }

    /* Màu & style tái sử dụng từ báo cáo KQKD */
    /* brand-bg: #FCE7EF; brand-accent: #C83D5D; brand-text: #2E3A63; */
    /* border: #D7CAD1; muted: #6B7280; table-head: #FAF7F8; row-alt: #FFF9FB; */

    .report-header {
      border: 1px solid #C83D5D;
      background: #FCE7EF;
      padding: 10px 12px;
      border-radius: 6px;
      margin-bottom: 12px;
    }
    .brand-line {
      font-size: 16px;
      font-weight: 700;
      letter-spacing: .3px;
      color: #C83D5D;
      text-transform: uppercase;
    }
    .company-meta { margin-top: 4px; color: #2E3A63; }
    .company-meta span { display: inline-block; margin-right: 16px; }

    /* meta */
    .report-meta       { margin-top: 6px; color: #6B7280; }
    .report-meta-table { width: 100%; border-collapse: collapse; }
    .report-meta-table td { border: none; padding: 0; vertical-align: top; }
    .report-meta-left  { text-align: left;  width: 60%; }
    .report-meta-right { text-align: right; width: 40%; }

    .report-title  {
      font-size: 18px;
      font-weight: 800;
      margin: 10px 0 8px;
    
      letter-spacing: .4px;
      color: #C83D5D;
      text-align: center;
    }

    table  { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #D7CAD1; padding: 6px 7px; vertical-align: top; }
    th     { background: #FAF7F8; font-weight: 700; }
    .right { text-align: right; }
    .center{ text-align: center; }
    .muted { color: #6B7280; }

        /* Bảng công tóm tắt: thu nhỏ font, padding để vừa khổ A4 */
    .table-timesheet {
      table-layout: fixed;   /* dompdf sẽ chia đều cột theo width */
      width: 100%;
    }
    .table-timesheet th,
    .table-timesheet td {
      padding: 4px 4px;
      font-size: 10px;
    }
    .table-timesheet th {
      line-height: 1.2;
    }


    .section { margin-top: 12px; }
    .section h3 {
      margin: 10px 0 6px;
      color: #2E3A63;
      font-size: 14px;
      text-transform: uppercase;
      border-left: 4px solid #C83D5D;
      padding-left: 8px;
    }

    .zebra tr:nth-child(even) td { background: #FFF9FB; }
    .nowrap { white-space: nowrap; }
    .no-border { border: none !important; }

    .badge {
      display: inline-block;
      padding: 1px 6px;
      border-radius: 999px;
      font-size: 10px;
      background: #F5F5F5;
    }
    .badge-green { background:#E6FBF0; color:#157347; }
    .badge-red   { background:#FFE6EA; color:#A1173F; }

    /* bảng 2 cột giống KQKD: dùng table thay flex */
    .two-col-table { width:100%; border-collapse:collapse; }
    .two-col-table td { border:none; padding:0 4px 0 0; vertical-align:top; }

    .note { margin-top:8px; font-size:11px; color:#6B7280; line-height:1.45; }
  </style>
</head>
<body>

  @php
    // format tiền
    $fmt = function($v) {
      $n = (int) ($v ?? 0);
      return number_format($n, 0, ',', '.');
    };

    // Chuẩn hoá label tháng: "THÁNG 11/2025" từ "2025-11"
    $ym       = $payroll['thang'] ?? '';
    $thangTxt = $ym;
    if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
        [$y, $m] = explode('-', $ym);
        $thangTxt = 'THÁNG ' . (int)$m . '/' . $y;   // THÁNG 11/2025
    }
  @endphp


  <!-- HEADER -->
  <div class="report-header">
    <div class="brand-line">CÔNG TY CỔ PHẦN TRANG TRÍ PHÁT HOÀNG GIA</div>
    <div class="company-meta">
      <span><strong>MST:</strong> 0319141372</span>
      <span><strong>Đ/c:</strong> Số 100 Nguyễn Minh Hoàng, Phường Bảy Hiền, TP. Hồ Chí Minh</span>
    </div>

    <div class="report-meta">
      <table class="report-meta-table">
        <tr>
          <td class="report-meta-left no-border">
            <strong>Nhân viên:</strong> {{ $user->name ?? 'N/A' }}
          </td>
          <td class="report-meta-right no-border">
            <strong>Ngày in:</strong> {{ date('d/m/Y H:i') }}
          </td>
        </tr>
      </table>
    </div>
  </div>

  <div class="report-title">
    BẢNG LƯƠNG {{ $thangTxt }} - {{ $user->name ?? '' }} (ID: {{ $user->id }})
  </div>


  {{-- 1. Thông tin nhân viên --}}
  <div class="section">
    <h3>1. Thông tin nhân viên</h3>
    <table>
      <tbody>
      <tr>
        <th style="width:25%">Nhân viên</th>
        <td>{{ $user->name ?? 'N/A' }}</td>
      </tr>
      <tr>
        <th>Mã nhân viên</th>
        <td>#{{ $user->id }}</td>
      </tr>
      <tr>
        <th>Email</th>
        <td>{{ $user->email ?? '—' }}</td>
      </tr>
      <tr>
        <th>Kỳ lương</th>
        <td>{{ $payroll['thang'] ?? '' }}</td>
      </tr>
      </tbody>
    </table>
  </div>

  {{-- 2. Bảng công tóm tắt --}}
  <div class="section">
    <h3>2. Bảng công tóm tắt</h3>
    @if($timesheet)
        <table class="zebra table-timesheet">
        <thead>
          <tr>
            <th class="center">Kỳ<br>công</th>
            <th class="right">Số ngày<br>công</th>
            <th class="right">Số phút<br>công</th>
            <th class="right">Đi trễ<br>(phút)</th>
            <th class="right">Về sớm<br>(phút)</th>
            <th class="right">Nghỉ phép<br>(ngày / giờ)</th>
            <th class="right">Nghỉ không lương<br>(ngày / giờ)</th>
            <th class="right">Làm thêm<br>(phút)</th>
            <th class="center">Trạng<br>thái</th>
            <th class="center">Tổng hợp<br>lúc</th>
          </tr>
        </thead>

        <tbody>
          <tr>
            <td class="center">{{ $timesheet['thang'] ?? '' }}</td>
            <td class="right">{{ number_format($timesheet['so_ngay_cong'] ?? 0, 2) }}</td>
            <td class="right">{{ $timesheet['so_gio_cong'] ?? 0 }}</td>
            <td class="right">{{ $timesheet['di_tre_phut'] ?? 0 }}</td>
            <td class="right">{{ $timesheet['ve_som_phut'] ?? 0 }}</td>
            <td class="right">
              {{ $timesheet['nghi_phep_ngay'] ?? 0 }} ngày /
              {{ $timesheet['nghi_phep_gio'] ?? 0 }} giờ
            </td>
            <td class="right">
              {{ $timesheet['nghi_khong_luong_ngay'] ?? 0 }} ngày /
              {{ $timesheet['nghi_khong_luong_gio'] ?? 0 }} giờ
            </td>
            <td class="right">{{ $timesheet['lam_them_gio'] ?? 0 }}</td>
            <td class="center">
              @if(!empty($timesheet['locked']))
                <span class="badge badge-red">Đã khóa</span>
              @else
                <span class="badge badge-green">Chưa khóa</span>
              @endif
            </td>
                   <td class="center">{{ $timesheet['computed_at'] ?? '—' }}</td>

          </tr>
        </tbody>
      </table>
    @else
      <div class="muted">Không có dữ liệu bảng công cho kỳ này.</div>
    @endif
  </div>

  {{-- 2.1 Phút công dùng để tính lương --}}
  <div class="section">
    <h3>2.1. Phút công dùng để tính lương</h3>
    <table>
      <tbody>
      <tr>
        <th style="width:45%">Số phút công tiêu chuẩn (28 ngày x 8 giờ x 60 phút)</th>
        <td class="right">{{ $metrics['std_minutes'] ?? '—' }}</td>
      </tr>
      <tr>
        <th>Số phút công thực tế (tính lương)</th>
        <td class="right">{{ $metrics['actual_minutes'] ?? ($timesheet['so_gio_cong'] ?? '—') }}</td>
      </tr>
      <tr>
        <th>Số phút tính lương cơ bản</th>
        <td class="right">{{ $metrics['base_minutes'] ?? '—' }}</td>
      </tr>
      <tr>
        <th>Số phút tăng ca (tính lương)</th>
        <td class="right">{{ $metrics['ot_minutes'] ?? '—' }}</td>
      </tr>
      </tbody>
    </table>
  </div>

  {{-- 3. Bảng lương chi tiết --}}
  <div class="section">
    <h3>3. Bảng lương chi tiết</h3>

    <table class="two-col-table">
      <tr>
        <td style="width:55%;">

          {{-- 3.1 Cấu hình & Công --}}
          <table>
            <thead>
              <tr>
                <th colspan="2" class="center">3.1. Cấu hình & Công</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th style="width:55%">Lương cơ bản</th>
                <td class="right">{{ $fmt($payroll['luong_co_ban'] ?? 0) }} đ</td>
              </tr>
              <tr>
                <th>Công chuẩn (ngày)</th>
                <td class="right">{{ $payroll['cong_chuan'] ?? 0 }}</td>
              </tr>
              <tr>
                <th>Hệ số</th>
                <td class="right">{{ number_format($payroll['he_so'] ?? 0, 2, ',', '.') }}</td>
              </tr>
              <tr>
                <th>Ngày công</th>
                <td class="right">{{ number_format($payroll['so_ngay_cong'] ?? 0, 2, ',', '.') }}</td>
              </tr>
              <tr>
                <th>Số phút công (từ bảng công)</th>
                <td class="right">{{ $payroll['so_gio_cong'] ?? 0 }}</td>
              </tr>
              <tr>
                <th>Đơn giá lương cơ bản / phút</th>
                <td class="right">
                  @if(!empty($metrics['unit_base_min']))
                    {{ $fmt($metrics['unit_base_min']) }} đ/phút
                  @else
                    —
                  @endif
                </td>
              </tr>
              <tr>
                <th>Đơn giá tăng ca / phút</th>
                <td class="right">
                  @if(!empty($metrics['ot_rate_per_min']))
                    {{ $fmt($metrics['ot_rate_per_min']) }} đ/phút
                  @else
                    —
                  @endif
                </td>
              </tr>
              <tr>
                <th>Chế độ lương</th>
                <td class="right">
                  @if(($metrics['mode'] ?? null) === 'khoan')
                    Lương khoán
                  @else
                    Lương theo công (tính theo phút)
                  @endif
                </td>
              </tr>
            </tbody>
          </table>

        </td>
        <td style="width:45%;">

          {{-- 3.2 Cộng / Trừ --}}
          <table>
            <thead>
              <tr>
                <th colspan="2" class="center">3.2. Cộng / Trừ</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th style="width:55%">Lương theo công / khoán</th>
                <td class="right">{{ $fmt($payroll['luong_theo_cong'] ?? 0) }} đ</td>
              </tr>
              <tr>
                <th>Phụ cấp</th>
                <td class="right">{{ $fmt($payroll['phu_cap'] ?? 0) }} đ</td>
              </tr>
              <tr>
                <th>Thưởng</th>
                <td class="right">{{ $fmt($payroll['thuong'] ?? 0) }} đ</td>
              </tr>
              <tr>
                <th>Phạt</th>
                <td class="right">{{ $fmt($payroll['phat'] ?? 0) }} đ</td>
              </tr>
              <tr>
                <th>Khấu trừ khác (R)</th>
                <td class="right">{{ $fmt($payroll['R_deduct_other'] ?? 0) }} đ</td>
              </tr>
              <tr>
                <th>Tạm ứng (T)</th>
                <td class="right">{{ $fmt($payroll['T_advance'] ?? 0) }} đ</td>
              </tr>
            </tbody>
          </table>

          {{-- 3.3 Bảo hiểm --}}
          <table style="margin-top:6px;">
            <thead>
              <tr>
                <th colspan="2" class="center">3.3. Bảo hiểm</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th style="width:55%">Lương tính bảo hiểm</th>
                <td class="right">
                  @if(!empty($metrics['bh_base']))
                    {{ $fmt($metrics['bh_base']) }} đ
                  @else
                    —
                  @endif
                </td>
              </tr>
              <tr>
                <th>BHXH</th>
                <td class="right">{{ $fmt($payroll['bhxh'] ?? 0) }} đ</td>
              </tr>
              <tr>
                <th>BHYT</th>
                <td class="right">{{ $fmt($payroll['bhyt'] ?? 0) }} đ</td>
              </tr>
              <tr>
                <th>BHTN</th>
                <td class="right">{{ $fmt($payroll['bhtn'] ?? 0) }} đ</td>
              </tr>
              <tr>
                <th>Tổng bảo hiểm (Q)</th>
                <td class="right">{{ $fmt($payroll['Q_insurance'] ?? 0) }} đ</td>
              </tr>
            </tbody>
          </table>

        </td>
      </tr>
    </table>
  </div>

  {{-- 3.4 Lương tăng ca & tổng kết P/Q/R/T/U --}}
  <div class="section">
    <h3>3.4. Lương tăng ca & tổng kết</h3>
    <table>
      <tbody>
      <tr>
        <th style="width:45%">Lương tăng ca (từ phút tăng ca)</th>
        <td class="right">
          @if(!empty($metrics['ot_amount']))
            {{ $fmt($metrics['ot_amount']) }} đ
          @else
            —
          @endif
        </td>
      </tr>
      <tr>
        <th>P (Tổng thu nhập trước khấu trừ) = Lương công + Phụ cấp + Thưởng − Phạt</th>
        <td class="right">{{ $fmt($payroll['P_gross'] ?? 0) }} đ</td>
      </tr>
      <tr>
        <th>Q (Bảo hiểm) = BHXH + BHYT + BHTN</th>
        <td class="right">{{ $fmt($payroll['Q_insurance'] ?? 0) }} đ</td>
      </tr>
      <tr>
        <th>R (Khấu trừ khác)</th>
        <td class="right">{{ $fmt($payroll['R_deduct_other'] ?? 0) }} đ</td>
      </tr>
      <tr>
        <th>T (Tạm ứng)</th>
        <td class="right">{{ $fmt($payroll['T_advance'] ?? 0) }} đ</td>
      </tr>
      <tr>
        <th><strong>U (Thực nhận) = P − Q − R − T</strong></th>
        <td class="right"><strong>{{ $fmt($payroll['U_net'] ?? 0) }} đ</strong></td>
      </tr>
      <tr>
        <th>Trạng thái bảng lương</th>
        <td>
          @if(!empty($payroll['locked']))
            <span class="badge badge-red">Đã khóa</span>
          @else
            <span class="badge badge-green">Chưa khóa</span>
          @endif
          &nbsp;| Tính lúc: {{ $payroll['computed_at'] ?? '—' }}
        </td>
      </tr>
      </tbody>
    </table>
  </div>

  <div class="note">
    Bảng lương được sinh tự động từ hệ thống PHG Event ERP. Người lập (xem báo cáo): {{ auth()->user()->name ?? '---' }}.
  </div>

</body>
</html>
