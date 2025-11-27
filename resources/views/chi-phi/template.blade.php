<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>CHI PHÍ – Đơn #{{ $donHang->id ?? '' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

    @php
        // ===== Thông tin công ty =====
        $company = [
            'name'  => 'CÔNG TY TNHH SỰ KIỆN PHÁT HOÀNG GIA',
            'addr1' => 'Văn phòng: 102 Nguyễn Minh Hoàng, Phường Bảy Hiền, Thành phố Hồ Chí Minh, Việt Nam',
            'addr2' => 'Kho 1: 100 Nguyễn Minh Hoàng, P. 12, Q. Tân Bình, TP. HCM',
            'addr3' => 'Kho 2: 111 Nguyễn Minh Hoàng, P. 12, Q. Tân Bình, TP. HCM',
            'phone' => 'Điện thoại: 0949 40 43 44',
            'email' => 'Email: info@phathoanggia.com.vn',
            'tax'   => 'Mã số thuế: 0311465079',
            'logo'  => asset('storage/logo.png'),
        ];

        // ===== Meta dự án (giống báo giá) =====
        $meta = $meta ?? [];
        $meta = array_merge([
            'nguoi_nhan'        => $meta['nguoi_nhan']        ?? ($donHang->ten_khach_hang ?? 'Quý khách hàng'),
            'dien_thoai'        => $meta['dien_thoai']        ?? ($donHang->so_dien_thoai ?? ''),
            'phong_ban'         => $meta['phong_ban']         ?? '',
            'email'             => $meta['email']             ?? '',
            'cong_ty'           => $meta['cong_ty']           ?? ($donHang->ten_khach_hang ?? ''),
            'dia_chi'           => $meta['dia_chi']           ?? ($donHang->dia_chi_giao_hang ?? ''),
            'du_an'             => $meta['du_an']             ?? ($donHang->ghi_chu ?? ''),
            'dia_chi_thuc_hien' => $meta['dia_chi_thuc_hien'] ?? ($donHang->dia_chi_giao_hang ?? ''),
            'ngay_to_chuc'      => $meta['ngay_to_chuc']      ?? '',
            'so_luong_khach'    => $meta['so_luong_khach']    ?? '',
        ], $meta);

        // ===== Tổng hợp chi phí & doanh thu =====
        // $totals mong đợi: total_revenue, total_cost, total_margin, margin_percent
        $totals        = $totals ?? [];
        $totalRevenue  = (int)($totals['total_revenue']  ?? 0);
        $totalCost     = (int)($totals['total_cost']     ?? 0);
        $totalMargin   = (int)($totals['total_margin']   ?? ($totalRevenue - $totalCost));
        $marginPercent = $totals['margin_percent'] ?? null;

        // ===== Số → chữ (dùng cho Tổng chi phí) =====
        if (! function_exists('vnNumberToText')) {
            function vnNumberToText(int $number): string
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

        // Map Hạng mục tuỳ biến nếu cần (giống báo giá) – hiện tại để trống
        $categoryTitlesRaw = $meta['category_titles'] ?? [];
        $categoryTitleMap  = [];
        if (is_array($categoryTitlesRaw)) {
            foreach ($categoryTitlesRaw as $row) {
                if (!is_array($row)) continue;
                $key   = $row['key']   ?? null;
                $label = $row['label'] ?? null;
                if ($key !== null && $label !== null && $key !== '' && $label !== '') {
                    $categoryTitleMap[$key] = $label;
                }
            }
        }

        $footerNote = $meta['footer_note'] ?? null;

        // Người báo giá / xác nhận (tái sử dụng từ báo giá)
        $signer = $meta['signer'] ?? [
            'name'          => null,
            'title'         => null,
            'phone'         => null,
            'email'         => null,
            'approver_note' => null,
        ];

        // Loại chi phí: Đề xuất / Thực tế (nếu controller truyền vào)
        $costTypeText = $meta['cost_type_text'] ?? 'Chi phí';
        $tongChiPhiBangChu = vnNumberToText($totalCost);

        $sections = $sections ?? [];
    @endphp

    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:"DejaVu Sans",Arial,sans-serif;
            font-size:11px;
            line-height:1.4;
            color:#000;
        }
        .page{
            width:100%;
            max-width:800px;
            margin:0 auto;
            padding:10px 18px;
        }
        .page-frame{
            border:1px solid #000;
            padding:4px 6px;
        }
        @media print{
            .page-frame{
                border:1px solid #000;
                padding:4px 6px;
            }
        }

        .header{display:flex;margin-bottom:4px;}
        .logo-box{width:90px;}
        .logo-box img{max-width:80px;height:auto;}
        .company-box{flex:1;text-align:center;}
        .company-name{font-size:12px;font-weight:700;text-transform:uppercase;}
        .company-line{font-size:9px;margin-top:1px;}

        .title-bar{
            margin-top:4px;
            background:#f9b000;
            padding:4px;
            text-align:center;
            font-weight:700;
            text-transform:uppercase;
            font-size:12px;
            border:1px solid #000;
        }

        .subtitle{
            text-align:center;
            font-size:10px;
            margin-top:2px;
            font-style:italic;
        }

        .info-table{
            width:100%;
            border-collapse:collapse;
            margin-top:4px;
            margin-bottom:4px;
        }
        .info-table td{
            border:1px solid #000;
            padding:3px 4px;
            font-size:9px;
        }
        .info-label{
            width:80px;
            background:#fef4cf;
            font-weight:700;
        }

        .intro{
            margin:4px 0;
            font-size:9px;
            text-align:justify;
        }

        .cost-table{
            width:100%;
            border-collapse:collapse;
            font-size:9px;
        }
        .cost-table th,
        .cost-table td{
            border:1px solid #000;
            padding:2px 3px;
            vertical-align:middle;
        }
        .cost-table thead th{
            background:#f9b000;
            font-weight:700;
            text-align:center;
        }

        .col-stt{width:4%;}
        .col-hangmuc{width:14%;}
        .col-chitiet{width:30%;}
        .col-dvt{width:6%;}
        .col-sl{width:6%;}
        .col-sup{width:10%;}
        .col-dgcp{width:10%;}
        .col-ttcp{width:10%;}
        .col-dgban{width:10%;}
        .col-ttban{width:10%;}

        .section-row{
            background:#fde9d9;
            font-weight:700;
        }
        .text-center{text-align:center;}
        .text-right{text-align:right;}

        .summary-block{
            margin-top:5px;
            font-size:9px;
        }
        .summary-row{
            display:flex;
            justify-content:space-between;
            margin-bottom:2px;
        }
        .summary-label{font-weight:700;}

        .note-block{
            margin-top:5px;
            font-size:8.5px;
        }
        .note-block ul{margin-left:16px;margin-top:2px;}

        .sign-table{
            width:100%;
            border-collapse:collapse;
            margin-top:10px;
            font-size:9px;
        }
        .sign-table td{
            border:1px solid #000;
            padding:4px 3px;
            text-align:center;
            vertical-align:middle;
        }
        .sign-header{
            background:#f9b000;
            font-weight:700;
        }
        .sign-note{
            margin-top:28px;
            font-style:italic;
        }

        .footer-company{
            margin-top:8px;
            text-align:center;
            font-size:8px;
            line-height:1.3;
        }

        @media print{
            @page{
                size:A4 portrait;
                margin:0.5cm 0.5cm;
            }
            .page{padding:8px 12px;}
        }
    </style>
</head>
<body>

{{-- Nút in/đóng --}}
<div id="printControls" style="position:fixed;top:10px;right:10px;z-index:1000;background:#fff;padding:8px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.15);display:none;">
  <button onclick="window.print()" style="background:#f9b000;color:#fff;border:none;padding:6px 10px;border-radius:3px;margin-right:4px;cursor:pointer;">🖨️ In chi phí</button>
  <button onclick="closePrint()" style="background:#6c757d;color:#fff;border:none;padding:6px 10px;border-radius:3px;cursor:pointer;">✖️ Đóng</button>
</div>
<script>
  window.addEventListener('load', function () {
    var box = document.getElementById('printControls');
    if (box) box.style.display = 'block';

    // ⬇️ ẨN BOX TRƯỚC KHI IN → PDF không bị ô trắng
    setTimeout(function () {
      if (box) box.style.display = 'none';
      window.print();
    }, 500);
  });
  function closePrint() {
    if (window.opener) {
      window.close();
    } else {
      window.history.back();
    }
  }
</script>


<div class="page">
  <div class="page-frame">

    {{-- HEADER --}}
    <div class="header">
      <div class="logo-box">
        @if(!empty($company['logo']))
          <img src="{{ $company['logo'] }}" alt="Logo">
        @endif
      </div>
      <div class="company-box">
        <div class="company-name">{{ $company['name'] }}</div>
        <div class="company-line">{{ $company['addr1'] }}</div>
        <div class="company-line">{{ $company['addr2'] }}</div>
        <div class="company-line">{{ $company['addr3'] }}</div>
        <div class="company-line">{{ $company['phone'] }} | {{ $company['email'] }}</div>
        <div class="company-line">{{ $company['tax'] }}</div>
      </div>
    </div>

    <div class="title-bar">CHI PHÍ CUNG CẤP DỊCH VỤ (SỰ KIỆN)</div>
    <div class="subtitle">
      Loại bảng chi phí: {{ $costTypeText }}
    </div>

    {{-- THÔNG TIN KHÁCH/DỰ ÁN --}}
    <table class="info-table">
      <tr>
        <td class="info-label">Người nhận:</td>
        <td>{{ $meta['nguoi_nhan'] }}</td>
        <td class="info-label">Điện thoại:</td>
        <td>{{ $meta['dien_thoai'] }}</td>
      </tr>
      <tr>
        <td class="info-label">Phòng ban:</td>
        <td>{{ $meta['phong_ban'] }}</td>
        <td class="info-label">Email:</td>
        <td>{{ $meta['email'] }}</td>
      </tr>
      <tr>
        <td class="info-label">Công ty:</td>
        <td colspan="3">{{ $meta['cong_ty'] }}</td>
      </tr>
      <tr>
        <td class="info-label">Địa chỉ:</td>
        <td colspan="3">{{ $meta['dia_chi'] }}</td>
      </tr>
      <tr>
        <td class="info-label">Dự án:</td>
        <td colspan="3">{{ $meta['du_an'] }}</td>
      </tr>
      <tr>
        <td class="info-label">Địa chỉ thực hiện:</td>
        <td colspan="3">{{ $meta['dia_chi_thuc_hien'] }}</td>
      </tr>
      <tr>
        <td class="info-label">Ngày tổ chức:</td>
        <td>{{ $meta['ngay_to_chuc'] }}</td>
        <td class="info-label">Số lượng:</td>
        <td>{{ $meta['so_luong_khach'] }}</td>
      </tr>
    </table>

    <div class="intro">
      Đây là bảng <strong>{{ mb_strtoupper($costTypeText, 'UTF-8') }}</strong> cho chương trình nêu trên,
      thể hiện chi phí SUP và giá bán tương ứng cho từng hạng mục dịch vụ.
    </div>

    {{-- BẢNG CHI PHÍ (THÊM 3 CỘT: SUP / GIÁ CP / THÀNH TIỀN CP) --}}
    <table class="cost-table">
      <colgroup>
        <col class="col-stt">
        <col class="col-hangmuc">
        <col class="col-chitiet">
        <col class="col-dvt">
        <col class="col-sl">
        <col class="col-sup">
        <col class="col-dgcp">
        <col class="col-ttcp">
        <col class="col-dgban">
        <col class="col-ttban">
      </colgroup>
      <thead>
        <tr>
          <th rowspan="2">STT</th>
          <th rowspan="2">HẠNG MỤC</th>
          <th rowspan="2">CHI TIẾT</th>
          <th rowspan="2">ĐVT</th>
          <th rowspan="2">SL</th>
          <th rowspan="2">SUP</th>
          <th colspan="2">CHI PHÍ (SUP)</th>
          <th colspan="2">GIÁ BÁN</th>
        </tr>
        <tr>
          <th>ĐƠN GIÁ</th>
          <th>THÀNH TIỀN</th>
          <th>ĐƠN GIÁ</th>
          <th>THÀNH TIỀN</th>
        </tr>
      </thead>
      <tbody>
      @forelse($sections as $section)
        {{-- Hàng tiêu đề Section: A. NHÂN SỰ, B. CƠ SỞ VẬT CHẤT, ... --}}
        <tr class="section-row">
          <td colspan="7">
            {{ ($section['letter'] ?? '') . '. ' . mb_strtoupper($section['name'] ?? '', 'UTF-8') }}
          </td>
          <td class="text-right">
            {{-- Tổng chi phí của section --}}
            @if(isset($section['total_cost']))
              {{ number_format($section['total_cost'], 0, ',', '.') }}
            @endif
          </td>
          <td></td>
          <td class="text-right">
            {{-- Tổng doanh thu của section --}}
            @if(isset($section['total_revenue']))
              {{ number_format($section['total_revenue'], 0, ',', '.') }}
            @endif
          </td>
        </tr>

        @php
          $items    = $section['items'] ?? [];
          $count    = count($items);
          $rowIndex = 1;
          $i        = 0;
        @endphp

        @while($i < $count)
          @php
            $line = $items[$i];

            // Hạng mục gốc
            $hmRaw    = $line['hang_muc']     ?? '';
            $hmGoc    = $line['hang_muc_goc'] ?? $hmRaw;
            $hangMuc  = $hmRaw;
            if (!empty($hmGoc) && !empty($categoryTitleMap[$hmGoc] ?? null)) {
                $hangMuc = $categoryTitleMap[$hmGoc];
            }

            $isPackageLine = !empty($line['is_package']);

            // Gom nhóm theo Hạng mục (chỉ cho dòng thành phần)
            if ($isPackageLine) {
                $groupCount = 1;
            } else {
                $groupCount = 1;
                for ($j = $i + 1; $j < $count; $j++) {
                    $lineJ      = $items[$j];
                    $isPackageJ = !empty($lineJ['is_package']);
                    $hmRawJ     = $lineJ['hang_muc']     ?? '';
                    $hmGocJ     = $lineJ['hang_muc_goc'] ?? $hmRawJ;
                    $hangMucJ   = $hmRawJ;
                    if (!empty($hmGocJ) && !empty($categoryTitleMap[$hmGocJ] ?? null)) {
                        $hangMucJ = $categoryTitleMap[$hmGocJ];
                    }
                    if (!$isPackageJ && $hangMucJ === $hangMuc) {
                        $groupCount++;
                    } else {
                        break;
                    }
                }
            }

            $line0 = $line;
          @endphp

          {{-- Dòng đầu của nhóm --}}
          <tr>
            <td class="text-center">{{ $rowIndex }}</td>
            <td class="text-center" rowspan="{{ $groupCount }}">{{ $hangMuc }}</td>

            <td>{!! $line0['chi_tiet_html'] ?? e($line0['chi_tiet'] ?? '') !!}</td>
            <td class="text-center">{{ $line0['dvt'] ?? '' }}</td>
            <td class="text-center">
              @php $qty0 = $line0['so_luong'] ?? null; @endphp
              {{ $qty0 !== null ? rtrim(rtrim(number_format($qty0, 2, ',', '.'), '0'), ',') : '' }}
            </td>
            <td class="text-center">
              {{ $line0['supplier_name'] ?? '' }}
            </td>
            <td class="text-right">
              {{ isset($line0['cost_unit_price']) ? number_format($line0['cost_unit_price'], 0, ',', '.') : '' }}
            </td>
            <td class="text-right">
              {{ isset($line0['cost_total_amount']) ? number_format($line0['cost_total_amount'], 0, ',', '.') : '' }}
            </td>
            <td class="text-right">
              {{ isset($line0['sell_unit_price']) ? number_format($line0['sell_unit_price'], 0, ',', '.') : '' }}
            </td>
            <td class="text-right">
              {{ isset($line0['sell_total_amount']) ? number_format($line0['sell_total_amount'], 0, ',', '.') : '' }}
            </td>
          </tr>

          {{-- Các dòng tiếp theo trong cùng Hạng mục --}}
          @for($k = 1; $k < $groupCount; $k++)
            @php $lineN = $items[$i + $k]; @endphp
            <tr>
              <td class="text-center">{{ $rowIndex + $k }}</td>

              <td>{!! $lineN['chi_tiet_html'] ?? e($lineN['chi_tiet'] ?? '') !!}</td>
              <td class="text-center">{{ $lineN['dvt'] ?? '' }}</td>
              <td class="text-center">
                @php $qtyN = $lineN['so_luong'] ?? null; @endphp
                {{ $qtyN !== null ? rtrim(rtrim(number_format($qtyN, 2, ',', '.'), '0'), ',') : '' }}
              </td>
              <td class="text-center">
                {{ $lineN['supplier_name'] ?? '' }}
              </td>
              <td class="text-right">
                {{ isset($lineN['cost_unit_price']) ? number_format($lineN['cost_unit_price'], 0, ',', '.') : '' }}
              </td>
              <td class="text-right">
                {{ isset($lineN['cost_total_amount']) ? number_format($lineN['cost_total_amount'], 0, ',', '.') : '' }}
              </td>
              <td class="text-right">
                {{ isset($lineN['sell_unit_price']) ? number_format($lineN['sell_unit_price'], 0, ',', '.') : '' }}
              </td>
              <td class="text-right">
                {{ isset($lineN['sell_total_amount']) ? number_format($lineN['sell_total_amount'], 0, ',', '.') : '' }}
              </td>
            </tr>
          @endfor

          @php
            $rowIndex += $groupCount;
            $i        += $groupCount;
          @endphp
        @endwhile
      @empty
        <tr>
          <td colspan="10" class="text-center">
            (Chưa có dữ liệu chi phí – cần build sections từ đơn hàng & quote_cost_items)
          </td>
        </tr>
      @endforelse
      </tbody>
    </table>

    {{-- TỔNG HỢP CHI PHÍ / DOANH THU / LỢI NHUẬN --}}
    <div class="summary-block">
      <div class="summary-row">
        <span class="summary-label">TỔNG DOANH THU:</span>
        <span class="summary-label">{{ number_format($totalRevenue, 0, ',', '.') }} đ</span>
      </div>
      <div class="summary-row">
        <span class="summary-label">TỔNG CHI PHÍ:</span>
        <span class="summary-label">{{ number_format($totalCost, 0, ',', '.') }} đ</span>
      </div>
      <div class="summary-row">
        <span class="summary-label">LỢI NHUẬN:</span>
        <span class="summary-label">{{ number_format($totalMargin, 0, ',', '.') }} đ</span>
      </div>
      <div class="summary-row">
        <span class="summary-label">% LỢI NHUẬN:</span>
        <span>{{ $marginPercent !== null ? number_format($marginPercent, 2, ',', '.') . ' %' : '-' }}</span>
      </div>
      <div class="summary-row">
        <span class="summary-label">Bằng chữ (Tổng chi phí):</span>
        <span>{{ $tongChiPhiBangChu }}</span>
      </div>
    </div>

    {{-- GHI CHÚ --}}
    <div class="note-block">
      <div><strong>Ghi chú:</strong></div>
      @if (!empty($footerNote))
        <p>{!! nl2br(e($footerNote)) !!}</p>
      @else
        <ul>
          <li>Chi phí trên bao gồm toàn bộ chi phí nhân sự và trang thiết bị theo mô tả trong bảng chi phí.</li>
          <li>Chi phí chưa bao gồm thuế VAT (nếu có thoả thuận khác sẽ ghi rõ trong hợp đồng).</li>
          <li>Bảng chi phí dùng nội bộ để tham khảo và kiểm soát lãi/lỗ.</li>
        </ul>
      @endif
    </div>

    {{-- CHỮ KÝ --}}
    <table class="sign-table">
      <tr class="sign-header">
        <td>NGƯỜI PHỤ TRÁCH BÁO GIÁ</td>
        <td>XÁC NHẬN CHI PHÍ</td>
      </tr>
      <tr>
        <td>
          @php
            $signerTitle = $signer['title'] ?? 'Phụ trách kinh doanh';
            $signerName  = $signer['name'] ?? null;
            $signerPhone = $signer['phone'] ?? $company['phone'];
            $signerEmail = $signer['email'] ?? $company['email'];
          @endphp
          @if($signerName)
            <div>{{ $signerName }}</div>
          @endif
          <div>{{ $signerTitle }}</div>
          <div>Điện thoại: {{ $signerPhone }}</div>
          <div>Email: {{ $signerEmail }}</div>
          <div class="sign-note">(Ký, ghi rõ họ tên)</div>
        </td>
        <td>
          @php $approverNote = $signer['approver_note'] ?? null; @endphp
          @if($approverNote)
            <div>{{ $approverNote }}</div>
          @endif
          <div class="sign-note">(Ký, ghi rõ họ tên)</div>
        </td>
      </tr>
    </table>

    <div class="footer-company">
      <div>Mọi chi tiết xin liên hệ</div>
      <div>{{ $company['name'] }}</div>
      <div>{{ $company['addr1'] }}</div>
      <div>{{ $company['addr2'] }}</div>
      <div>{{ $company['addr3'] }}</div>
      <div>{{ $company['phone'] }} | {{ $company['email'] }}</div>
    </div>

  </div>
</div>
</body>
</html>
