<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Báo cáo KQKD</title>
  <style>
    :root{
      --brand-bg: #FCE7EF;     /* pastel hồng */
      --brand-accent: #C83D5D; /* hồng đậm */
      --brand-text: #2E3A63;   /* xanh đen chữ */
      --border: #D7CAD1;
      --muted: #6B7280;
      --table-head: #FAF7F8;
      --row-alt: #FFF9FB;
    }
    html, body { font-family: "DejaVu Sans", Arial, sans-serif; }
    body { font-size: 12px; color: var(--brand-text); margin: 18px; }

    .report-header { border:1px solid var(--brand-accent); background:var(--brand-bg); padding:12px 14px; border-radius:6px; margin-bottom:14px; }
    .brand-line    { font-size:16px; font-weight:700; letter-spacing:.3px; color:var(--brand-accent); text-transform:uppercase; }
    .company-meta  { margin-top:4px; color:var(--brand-text); }
    .company-meta span { display:inline-block; margin-right:16px; }
    .report-meta   { margin-top:8px; color:var(--muted); display:flex; justify-content:space-between; flex-wrap:wrap; }
    .report-title  { font-size:18px; font-weight:800; margin:10px 0 8px; text-transform:uppercase; letter-spacing:.4px; color:var(--brand-accent); }

    table{ width:100%; border-collapse:collapse; }
    th,td{ border:1px solid var(--border); padding:7px 8px; vertical-align:top; }
    th    { background:var(--table-head); font-weight:700; }
    .right{ text-align:right; }
    .center{ text-align:center; }
    .muted{ color:var(--muted); }
    .section{ margin-top:12px; }
    .section h3{ margin:10px 0 6px; color:var(--brand-text); font-size:14px; text-transform:uppercase; border-left:4px solid var(--brand-accent); padding-left:8px; }
    .zebra tr:nth-child(even) td { background: var(--row-alt); }
    .note { margin-top:8px; font-size:11px; color:var(--muted); line-height:1.45; }
    .sub  { color:var(--muted); font-style:italic; }
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
    <div class="report-meta">
      <span><strong>Kỳ dữ liệu:</strong>
        @if(!empty($from) || !empty($to))
          {{ $from ?? '...' }} → {{ $to ?? '...' }}
        @else
          Toàn bộ dữ liệu
        @endif
      </span>
      <span><strong>Ngày xuất:</strong> {{ date('d/m/Y H:i') }}</span>
    </div>
  </div>

  <div class="report-title">BÁO CÁO KẾT QUẢ KINH DOANH</div>

  @php
    $s  = $summary  ?? [];
    $by = $byCatMap ?? []; // [2=>[...],5=>[...],6=>[...],7=>[...],10=>[...]]
    $renderBreakdown = function($items, $caption){
      // luôn hiển thị bảng tiểu mục (kể cả khi tất cả = 0)
      $html = '<table class="zebra" style="margin:6px 0 4px 0;">';
      $html .= '<thead><tr><th colspan="2" class="center" style="background:#fff;">'.$caption.'</th></tr>';
      $html .= '<tr><th style="width:65%">- Mục chi tiết</th><th class="right" style="width:35%">Số tiền (VNĐ)</th></tr></thead><tbody>';

      if (!$items || !is_array($items) || count($items)===0) {
        $html .= '<tr><td class="sub">- (không có danh mục con)</td><td class="right">0</td></tr>';
      } else {
        foreach ($items as $it){
          $label = trim(($it['category_code'] ? $it['category_code'].' - ' : '').($it['category_name'] ?? 'Chưa phân loại'));
          $html .= '<tr><td class="sub">- '.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</td>'.
                   '<td class="right">'.number_format($it['total'] ?? 0).'</td></tr>';
        }
      }

      $html .= '</tbody></table>';
      return $html;
    };
  @endphp

  <!-- TỔNG HỢP & TIỂU MỤC -->
  <div class="section">
    <h3>Tổng hợp & chi tiết theo danh mục</h3>
    <table>
      <thead>
        <tr>
          <th style="width:40%">Chỉ tiêu</th>
          <th style="width:40%">Diễn giải</th>
          <th class="right" style="width:20%">Số tiền (VNĐ)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>01. Doanh thu bán hàng và cung cấp dịch vụ</strong></td>
          <td class="muted">Tổng doanh thu ghi nhận theo phiếu thu trong kỳ.</td>
          <td class="right">{{ number_format($s['01_doanh_thu_ban_hang'] ?? 0) }}</td>
        </tr>

        <tr>
          <td><strong>02. Giá vốn hàng bán</strong></td>
          <td>
            <div class="muted">Tổng chi phí giá vốn (dưới đây là các mục chi tiết).</div>
            {!! $renderBreakdown($by[2] ?? [], 'Chi tiết giá vốn') !!}
          </td>
          <td class="right">{{ number_format($s['02_gia_von_hang_ban'] ?? 0) }}</td>
        </tr>

        <tr>
          <td><strong>03. Lợi nhuận gộp (01 − 02)</strong></td>
          <td class="muted">Chênh lệch giữa doanh thu và giá vốn.</td>
          <td class="right">{{ number_format($s['03_loi_nhuan_gop'] ?? 0) }}</td>
        </tr>

        <tr>
          <td><strong>05. Chi phí tài chính</strong></td>
          <td>
            <div class="muted">Các khoản chi phí tài chính trong kỳ.</div>
            {!! $renderBreakdown($by[5] ?? [], 'Chi tiết chi phí tài chính') !!}
          </td>
          <td class="right">{{ number_format($s['05_chi_phi_tai_chinh'] ?? 0) }}</td>
        </tr>

        <tr>
          <td><strong>06. Chi phí bán hàng</strong></td>
          <td>
            <div class="muted">Các khoản chi phục vụ bán hàng (marketing, vận chuyển, khuyến mãi,…).</div>
            {!! $renderBreakdown($by[6] ?? [], 'Chi tiết chi phí bán hàng') !!}
          </td>
          <td class="right">{{ number_format($s['06_chi_phi_ban_hang'] ?? 0) }}</td>
        </tr>

        <tr>
          <td><strong>07. Chi phí quản lý doanh nghiệp</strong></td>
          <td>
            <div class="muted">Các khoản chi phục vụ quản trị vận hành (lương, BHXH, điện nước, VPP,…).</div>
            {!! $renderBreakdown($by[7] ?? [], 'Chi tiết chi phí QLDN') !!}
          </td>
          <td class="right">{{ number_format($s['07_chi_phi_quan_ly_dn'] ?? 0) }}</td>
        </tr>

        <tr>
          <td><strong>10. Chi phí khác</strong></td>
          <td>
            <div class="muted">Các khoản chi ngoài hoạt động thường xuyên.</div>
            {!! $renderBreakdown($by[10] ?? [], 'Chi tiết chi phí khác') !!}
          </td>
          <td class="right">{{ number_format($s['10_chi_phi_khac'] ?? 0) }}</td>
        </tr>

        <tr>
          <td><strong>13. Lợi nhuận trước thuế</strong></td>
          <td class="muted">Kết quả sau khi cộng trừ các khoản doanh thu/chi phí trong kỳ.</td>
          <td class="right">{{ number_format($s['13_loi_nhuan_truoc_thue'] ?? 0) }}</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- CHI TIẾT THEO THÁNG -->
  <div class="section">
    <h3>Chi tiết theo tháng</h3>
    <table class="zebra">
      <thead>
        <tr>
          <th class="center">Kỳ (YYYY-MM)</th>
          <th class="right">01 Doanh thu</th>
          <th class="right">02 Giá vốn</th>
          <th class="right">03 Lợi nhuận gộp</th>
          <th class="right">05 CP tài chính</th>
          <th class="right">06 CP bán hàng</th>
          <th class="right">07 CP QLDN</th>
          <th class="right">10 CP khác</th>
          <th class="right">13 LNTT</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($series ?? []) as $r)
          <tr>
            <td class="center">{{ $r['ym'] ?? '' }}</td>
            <td class="right">{{ number_format($r['01'] ?? 0) }}</td>
            <td class="right">{{ number_format($r['02'] ?? 0) }}</td>
            <td class="right">{{ number_format($r['03'] ?? 0) }}</td>
            <td class="right">{{ number_format($r['05'] ?? 0) }}</td>
            <td class="right">{{ number_format($r['06'] ?? 0) }}</td>
            <td class="right">{{ number_format($r['07'] ?? 0) }}</td>
            <td class="right">{{ number_format($r['10'] ?? 0) }}</td>
            <td class="right">{{ number_format($r['13'] ?? 0) }}</td>
          </tr>
        @empty
          <tr><td colspan="9" class="center muted">Không có dữ liệu kỳ tháng để hiển thị.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="note">
    Báo cáo được sinh tự động từ hệ thống PHG Event ERP. Người lập: {{ auth()->user()->name ?? '---' }}.
  </div>

</body>
</html>
