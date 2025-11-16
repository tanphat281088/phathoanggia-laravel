<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Báo cáo KQKD</title>
  <style>
    /* DomPDF: CHỈ dùng CSS 2.1 cơ bản */
    * { font-family: "DejaVu Sans", Arial, sans-serif !important; }
    html, body { font-size: 12px; color: #2E3A63; margin: 18px; }

    /* Màu (thay var(--...)) */
    /* brand-bg: #FCE7EF;  brand-accent: #C83D5D;  brand-text: #2E3A63; */
    /* border: #D7CAD1;    muted: #6B7280;        table-head: #FAF7F8; row-alt: #FFF9FB; */

    .report-header { border:1px solid #C83D5D; background:#FCE7EF; padding:12px 14px; border-radius:6px; margin-bottom:14px; }
    .brand-line    { font-size:16px; font-weight:700; letter-spacing:.3px; color:#C83D5D; text-transform:uppercase; }
    .company-meta  { margin-top:4px; color:#2E3A63; }
    .company-meta span { display:inline-block; margin-right:16px; }

    /* KHÔNG dùng flex: thay bằng hàng 2 cột đơn giản */
    .report-meta { margin-top:8px; color:#6B7280; }
    .report-meta-table { width:100%; border-collapse:collapse; }
    .report-meta-table td { border:none; padding:0; vertical-align:top; }
    .report-meta-left  { text-align:left;  width:70%; }
    .report-meta-right { text-align:right; width:30%; }

    .report-title  { font-size:18px; font-weight:800; margin:10px 0 8px; text-transform:uppercase; letter-spacing:.4px; color:#C83D5D; text-align: center; }

    table{ width:100%; border-collapse:collapse; }
    th,td{ border:1px solid #D7CAD1; padding:7px 8px; vertical-align:top; }
    th    { background:#FAF7F8; font-weight:700; }
    .right{ text-align:right; }
    .center{ text-align:center; }
    .muted{ color:#6B7280; }
    .section{ margin-top:12px; }
    .section h3{ margin:10px 0 6px; color:#2E3A63; font-size:14px; text-transform:uppercase; border-left:4px solid #C83D5D; padding-left:8px; }
    .zebra tr:nth-child(even) td { background: #FFF9FB; }
    .note { margin-top:8px; font-size:11px; color:#6B7280; line-height:1.45; }
    .sub  { color:#6B7280; font-style:italic; }
    .nowrap { white-space: nowrap; }
    .no-border { border:none !important; }

    /* ====== BỔ SUNG: fix tràn bảng tháng ====== */
    .table-month { table-layout: fixed; width: 100%; }
    .table-month th, .table-month td { padding: 4px 5px; font-size: 10px; }
    .table-month th { word-wrap: break-word; line-height: 1.15; }
    .table-month td { word-wrap: break-word; }

    /* Phân bổ bề rộng 14 cột (cộng ≈100%) */
    .col-ym  { width: 10%; } /* Kỳ */
    .col01   { width: 7%;  }
    .col02   { width: 7%;  }
    .col03   { width: 7%;  }
    .col04   { width: 7%;  }
    .col05   { width: 6%;  }
    .col06   { width: 6%;  }
    .col07   { width: 6%;  }
    .col08   { width: 6%;  }
    .col09   { width: 7%;  }
    .col10   { width: 6%;  }
    .col11   { width: 6%;  }
    .col12   { width: 6%;  }
    .col13   { width: 6%;  }
  </style>
</head>
<body>

  <!-- HEADER -->
  <div class="report-header">
    <div class="brand-line">CÔNG TY CỔ PHẦN TRANG TRÍ PHÁT HOÀNG GIA</div>
    <div class="company-meta">
      <span><strong>MST:</strong> 0319141372</span>
      <span><strong>Đ/c:</strong> Số 100 Nguyễn Minh Hoàng, Phường Bảy Hiền, TP. Hồ Chí Minh</span>
    </div>

    <!-- Thay flex = bảng 2 cột -->
    <div class="report-meta">
      <table class="report-meta-table">
        <tr>
          <td class="report-meta-left no-border">
            <strong>Kỳ dữ liệu:</strong>
            @if(!empty($from) || !empty($to))
              {{ $from ?? '...' }} → {{ $to ?? '...' }}
            @else
              Toàn bộ dữ liệu
            @endif
          </td>
          <td class="report-meta-right no-border">
            <strong>Ngày xuất:</strong> {{ date('d/m/Y H:i') }}
          </td>
        </tr>
      </table>
    </div>
  </div>

  <div class="report-title">BÁO CÁO KẾT QUẢ KINH DOANH</div>

  @php
    $s  = $summary  ?? [];
    $by = $byCatMap ?? [2=>[],5=>[],6=>[],7=>[],8=>[],10=>[]];

    $doanhThu01 = max(0, (int)($s['01_doanh_thu_ban_hang'] ?? 0));

    $pct = function($val, $base) {
      if (!$base || $base == 0) return '0%';
      $p = ($val / $base) * 100;
      $txt = number_format($p, 1, '.', '');
      $txt = rtrim(rtrim($txt, '0'), '.');
      return $txt.'%';
    };

    $fmt = function($v) {
      $n = (int)$v;
      return number_format($n, 0, ',', '.');
    };
  @endphp

  <!-- TỔNG HỢP & TIỂU MỤC -->
  <div class="section">
    <h3>Tổng hợp & chi tiết theo danh mục</h3>
    <table>
      <thead>
        <tr>
          <th style="width:34%">Chỉ tiêu</th>
          <th style="width:36%">Diễn giải</th>
          <th class="right" style="width:15%">Số tiền (VNĐ)</th>
          <th class="right" style="width:15%">Tỷ lệ (%)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>01. Doanh thu bán hàng và cung cấp dịch vụ</strong></td>
    <td class="muted">Tổng giá trị các đơn hàng đã giao và đã hủy trong kỳ.</td>

          <td class="right">{{ $fmt($s['01_doanh_thu_ban_hang'] ?? 0) }}</td>
          <td class="right"><strong>100%</strong></td>
        </tr>

        <tr>
          <td><strong>02. Giá vốn hàng bán</strong></td>
          <td>
            <div class="muted">Tổng chi phí giá vốn (dưới đây là các mục chi tiết).</div>
            {!! (function() use ($by, $fmt, $pct, $doanhThu01) {
              $items = $by[2] ?? [];
              $html = '<table class="zebra" style="margin:6px 0 4px 0; width:100%; border-collapse:collapse;"><thead><tr>'.
                      '<th style="width:55%">- Mục chi tiết</th>'.
                      '<th class="right" style="width:22%">Số tiền (VNĐ)</th>'.
                      '<th class="right" style="width:23%">Tỷ lệ (%)</th>'.
                      '</tr></thead><tbody>';
              if (!$items) {
                $html .= '<tr><td class="sub">- (không có danh mục con)</td><td class="right">0</td><td class="right">0%</td></tr>';
              } else {
                foreach ($items as $it) {
                  $label = trim($it['category_name'] ?? 'Chưa phân loại');
                  $val = (int)($it['total'] ?? 0);
                  $html .= '<tr>'.
                           '<td class="sub">- '.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</td>'.
                           '<td class="right">'.$fmt($val).'</td>'.
                           '<td class="right">'.$pct($val, $doanhThu01).'</td>'.
                           '</tr>';
                }
              }
              $html .= '</tbody></table>';
              return $html;
            })() !!}
          </td>
          <td class="right">{{ $fmt($s['02_gia_von_hang_ban'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['02_gia_von_hang_ban'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>03. Lợi nhuận gộp (01 − 02)</strong></td>
          <td class="muted">Chênh lệch giữa doanh thu và giá vốn.</td>
          <td class="right">{{ $fmt($s['03_loi_nhuan_gop'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['03_loi_nhuan_gop'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>04. Doanh thu hoạt động tài chính</strong></td>
          <td class="muted">Doanh thu tài chính phát sinh (phiếu thu loại “tài chính”).</td>
          <td class="right">{{ $fmt($s['04_doanh_thu_hd_tai_chinh'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['04_doanh_thu_hd_tai_chinh'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>05. Chi phí tài chính</strong></td>
          <td>
            <div class="muted">Các khoản chi phí tài chính trong kỳ.</div>
            {!! (function() use ($by, $fmt, $pct, $doanhThu01) {
              $items = $by[5] ?? [];
              $html = '<table class="zebra" style="margin:6px 0 4px 0; width:100%; border-collapse:collapse;"><thead><tr>'.
                      '<th style="width:55%">- Mục chi tiết</th>'.
                      '<th class="right" style="width:22%">Số tiền (VNĐ)</th>'.
                      '<th class="right" style="width:23%">Tỷ lệ (%)</th>'.
                      '</tr></thead><tbody>';
              if (!$items) {
                $html .= '<tr><td class="sub">- (không có danh mục con)</td><td class="right">0</td><td class="right">0%</td></tr>';
              } else {
                foreach ($items as $it) {
                  $label = trim($it['category_name'] ?? 'Chưa phân loại');
                  $val = (int)($it['total'] ?? 0);
                  $html .= '<tr>'.
                           '<td class="sub">- '.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</td>'.
                           '<td class="right">'.$fmt($val).'</td>'.
                           '<td class="right">'.$pct($val, $doanhThu01).'</td>'.
                           '</tr>';
                }
              }
              $html .= '</tbody></table>';
              return $html;
            })() !!}
          </td>
          <td class="right">{{ $fmt($s['05_chi_phi_tai_chinh'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['05_chi_phi_tai_chinh'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>06. Chi phí bán hàng</strong></td>
          <td>
            <div class="muted">Các khoản chi phục vụ bán hàng (marketing, vận chuyển, khuyến mãi,…).</div>
            {!! (function() use ($by, $fmt, $pct, $doanhThu01) {
              $items = $by[6] ?? [];
              $html = '<table class="zebra" style="margin:6px 0 4px 0; width:100%; border-collapse:collapse;"><thead><tr>'.
                      '<th style="width:55%">- Mục chi tiết</th>'.
                      '<th class="right" style="width:22%">Số tiền (VNĐ)</th>'.
                      '<th class="right" style="width:23%">Tỷ lệ (%)</th>'.
                      '</tr></thead><tbody>';
              if (!$items) {
                $html .= '<tr><td class="sub">- (không có danh mục con)</td><td class="right">0</td><td class="right">0%</td></tr>';
              } else {
                foreach ($items as $it) {
                  $label = trim($it['category_name'] ?? 'Chưa phân loại');
                  $val = (int)($it['total'] ?? 0);
                  $html .= '<tr>'.
                           '<td class="sub">- '.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</td>'.
                           '<td class="right">'.$fmt($val).'</td>'.
                           '<td class="right">'.$pct($val, $doanhThu01).'</td>'.
                           '</tr>';
                }
              }
              $html .= '</tbody></table>';
              return $html;
            })() !!}
          </td>
          <td class="right">{{ $fmt($s['06_chi_phi_ban_hang'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['06_chi_phi_ban_hang'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>07. Chi phí quản lý doanh nghiệp</strong></td>
          <td>
            <div class="muted">Các khoản chi phục vụ quản trị vận hành (lương, BHXH, điện nước, VPP,…).</div>
            {!! (function() use ($by, $fmt, $pct, $doanhThu01) {
              $items = $by[7] ?? [];
              $html = '<table class="zebra" style="margin:6px 0 4px 0; width:100%; border-collapse:collapse;"><thead><tr>'.
                      '<th style="width:55%">- Mục chi tiết</th>'.
                      '<th class="right" style="width:22%">Số tiền (VNĐ)</th>'.
                      '<th class="right" style="width:23%">Tỷ lệ (%)</th>'.
                      '</tr></thead><tbody>';
              if (!$items) {
                $html .= '<tr><td class="sub">- (không có danh mục con)</td><td class="right">0</td><td class="right">0%</td></tr>';
              } else {
                foreach ($items as $it) {
                  $label = trim($it['category_name'] ?? 'Chưa phân loại');
                  $val = (int)($it['total'] ?? 0);
                  $html .= '<tr>'.
                           '<td class="sub">- '.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</td>'.
                           '<td class="right">'.$fmt($val).'</td>'.
                           '<td class="right">'.$pct($val, $doanhThu01).'</td>'.
                           '</tr>';
                }
              }
              $html .= '</tbody></table>';
              return $html;
            })() !!}
          </td>
          <td class="right">{{ $fmt($s['07_chi_phi_quan_ly_dn'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['07_chi_phi_quan_ly_dn'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>08. Chi phí đầu tư CCDC</strong></td>
          <td>
            <div class="muted">Các khoản chi đầu tư, mua sắm và phân bổ CCDC.</div>
            {!! (function() use ($by, $fmt, $pct, $doanhThu01) {
              $items = $by[8] ?? [];
              $html = '<table class="zebra" style="margin:6px 0 4px 0; width:100%; border-collapse:collapse;"><thead><tr>'.
                      '<th style="width:55%">- Mục chi tiết</th>'.
                      '<th class="right" style="width:22%">Số tiền (VNĐ)</th>'.
                      '<th class="right" style="width:23%">Tỷ lệ (%)</th>'.
                      '</tr></thead><tbody>';
              if (!$items) {
                $html .= '<tr><td class="sub">- (không có danh mục con)</td><td class="right">0</td><td class="right">0%</td></tr>';
              } else {
                foreach ($items as $it) {
                  $label = trim($it['category_name'] ?? 'Chưa phân loại');
                  $val = (int)($it['total'] ?? 0);
                  $html .= '<tr>'.
                           '<td class="sub">- '.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</td>'.
                           '<td class="right">'.$fmt($val).'</td>'.
                           '<td class="right">'.$pct($val, $doanhThu01).'</td>'.
                           '</tr>';
                }
              }
              $html .= '</tbody></table>';
              return $html;
            })() !!}
          </td>
          <td class="right">{{ $fmt($s['08_chi_phi_dau_tu_ccdc'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['08_chi_phi_dau_tu_ccdc'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>09. Lợi nhuận thuần từ HĐKD</strong></td>
          <td class="muted">= 03 + 04 − 05 − 06 − 07 − 08.</td>
          <td class="right">{{ $fmt($s['09_loi_nhuan_thuan_hd_kd'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['09_loi_nhuan_thuan_hd_kd'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>10. Chi phí khác</strong></td>
          <td>
            <div class="muted">Các khoản chi ngoài hoạt động thường xuyên.</div>
            {!! (function() use ($by, $fmt, $pct, $doanhThu01) {
              $items = $by[10] ?? [];
              $html = '<table class="zebra" style="margin:6px 0 4px 0; width:100%; border-collapse:collapse;"><thead><tr>'.
                      '<th style="width:55%">- Mục chi tiết</th>'.
                      '<th class="right" style="width:22%">Số tiền (VNĐ)</th>'.
                      '<th class="right" style="width:23%">Tỷ lệ (%)</th>'.
                      '</tr></thead><tbody>';
              if (!$items) {
                $html .= '<tr><td class="sub">- (không có danh mục con)</td><td class="right">0</td><td class="right">0%</td></tr>';
              } else {
                foreach ($items as $it) {
                  $label = trim($it['category_name'] ?? 'Chưa phân loại');
                  $val = (int)($it['total'] ?? 0);
                  $html .= '<tr>'.
                           '<td class="sub">- '.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</td>'.
                           '<td class="right">'.$fmt($val).'</td>'.
                           '<td class="right">'.$pct($val, $doanhThu01).'</td>'.
                           '</tr>';
                }
              }
              $html .= '</tbody></table>';
              return $html;
            })() !!}
          </td>
          <td class="right">{{ $fmt($s['10_chi_phi_khac'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['10_chi_phi_khac'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>11. Thu nhập khác</strong></td>
          <td class="muted">Thu nhập ngoài hoạt động kinh doanh chính.</td>
          <td class="right">{{ $fmt($s['11_thu_nhap_khac'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['11_thu_nhap_khac'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>12. Lợi nhuận khác (12 = 10 − 11)</strong></td>
          <td class="muted">= Chi phí khác − Thu nhập khác.</td>
          <td class="right">{{ $fmt($s['12_loi_nhuan_khac'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['12_loi_nhuan_khac'] ?? 0), $doanhThu01) }}</td>
        </tr>

        <tr>
          <td><strong>13. Lợi nhuận trước thuế (13 = 09 + 12)</strong></td>
          <td class="muted">= Lợi nhuận thuần từ HĐKD + Lợi nhuận khác.</td>
          <td class="right">{{ $fmt($s['13_loi_nhuan_truoc_thue'] ?? 0) }}</td>
          <td class="right">{{ $pct(($s['13_loi_nhuan_truoc_thue'] ?? 0), $doanhThu01) }}</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- CHI TIẾT THEO THÁNG -->
  <div class="section">
    <h3>Chi tiết theo tháng</h3>
    <table class="zebra table-month">
      <!-- Chia bề rộng từng cột để tránh tràn -->
      <colgroup>
        <col class="col-ym" />
        <col class="col01" /><col class="col02" /><col class="col03" />
        <col class="col04" /><col class="col05" /><col class="col06" />
        <col class="col07" /><col class="col08" /><col class="col09" />
        <col class="col10" /><col class="col11" /><col class="col12" /><col class="col13" />
      </colgroup>
      <thead>
        <tr>
          <th class="center">Kỳ (YYYY-MM)</th>
          <th class="right">01 Doanh thu</th>
          <th class="right">02 Giá vốn</th>
          <th class="right">03 Lợi nhuận gộp</th>
          <th class="right">04 DT tài chính</th>
          <th class="right">05 CP tài chính</th>
          <th class="right">06 CP bán hàng</th>
          <th class="right">07 CP QLDN</th>
          <th class="right">08 CP CCDC</th>
          <th class="right">09 LN thuần</th>
          <th class="right">10 CP khác</th>
          <th class="right">11 TN khác</th>
          <th class="right">12 LN khác</th>
          <th class="right">13 LNTT</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($series ?? []) as $r)
          <tr>
            <td class="center">{{ $r['ym'] ?? '' }}</td>
            <td class="right">{{ $fmt($r['01'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['02'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['03'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['04'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['05'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['06'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['07'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['08'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['09'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['10'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['11'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['12'] ?? 0) }}</td>
            <td class="right">{{ $fmt($r['13'] ?? 0) }}</td>
          </tr>
        @empty
          <tr><td colspan="14" class="center muted">Không có dữ liệu kỳ tháng để hiển thị.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="note">
    Báo cáo được sinh tự động từ hệ thống PHG Event ERP. Người lập: {{ auth()->user()->name ?? '---' }}.
  </div>

</body>
</html>
