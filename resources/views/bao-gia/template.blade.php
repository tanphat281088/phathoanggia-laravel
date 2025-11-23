<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>BÁO GIÁ – Đơn #{{ $donHang->id ?? '' }}</title>
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

        // ===== Meta báo giá (tạm fallback từ đơn hàng) =====
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

         $sections = $sections ?? [];
        $totals   = $totals   ?? ['total_chi_phi' => 0, 'total_thanh_tien' => 0];
        $tongThanhTien = (int)($totals['total_thanh_tien'] ?? 0);

        // ===== CHUYỂN SỐ → CHỮ (TIỀN VIỆT) =====
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

                // Viết hoa chữ cái đầu + thêm "đồng chẵn./."
                $result = trim($result);
                $result = mb_convert_case($result, MB_CASE_LOWER, 'UTF-8');
                $result = mb_strtoupper(mb_substr($result, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($result, 1, null, 'UTF-8');

                return $result . ' đồng chẵn./.';
            }
        }
              // ===== Map Hạng mục tuỳ biến theo NHÓM GÓI (Step 8) =====
        $categoryTitlesRaw = $meta['category_titles'] ?? [];
        $categoryTitleMap  = [];

        if (is_array($categoryTitlesRaw)) {
            foreach ($categoryTitlesRaw as $row) {
                if (!is_array($row)) continue;
                $key   = $row['key']   ?? null; // Hạng mục gốc
                $label = $row['label'] ?? null; // Tên hiển thị trên báo giá

                if ($key !== null && $label !== null && $key !== '' && $label !== '') {
                    $categoryTitleMap[$key] = $label;
                }
            }
        }

        // Ghi chú / phần đuôi báo giá tuỳ biến (Step 8)
        $footerNote = $meta['footer_note'] ?? null;
// Meta người báo giá / xác nhận báo giá
        $signer = $meta['signer'] ?? [
            'name'          => null,
            'title'         => null,
            'phone'         => null,
            'email'         => null,
            'approver_note' => null,
        ];
        $tongThanhTienBangChu = vnNumberToText($tongThanhTien);

      
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

        /* ===== HEADER GIỐNG EXCEL ===== */
        .header{
            display:flex;
            margin-bottom:4px;
        }
        .logo-box{
            width:90px;
        }
        .logo-box img{
            max-width:80px;
            height:auto;
        }
        .company-box{
            flex:1;
            text-align:center;
        }
        .company-name{
            font-size:12px;
            font-weight:700;
            text-transform:uppercase;
        }
        .company-line{
            font-size:9px;
            margin-top:1px;
        }

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

        /* ===== BẢNG THÔNG TIN KHÁCH/DỰ ÁN ===== */
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

        /* ===== BẢNG BÁO GIÁ ===== */
        .quote-table{
            width:100%;
            border-collapse:collapse;
            font-size:9px;
        }
        .quote-table th,
        .quote-table td{
            border:1px solid #000;
            padding:2px 3px;
            vertical-align:middle;
        }
        .quote-table thead th{
            background:#f9b000;
            font-weight:700;
            text-align:center;
        }
        .col-stt{width:5%;}
        .col-hangmuc{width:15%;}
        .col-chitiet{width:40%;}
        .col-dvt{width:7%;}
        .col-sl{width:7%;}
        .col-dongia{width:13%;}
        .col-thanhtien{width:13%;}

        .section-row{
            background:#fde9d9;
            font-weight:700;
        }

        .text-center{text-align:center;}
        .text-right{text-align:right;}

        /* ===== TỔNG & GHI CHÚ ===== */
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

        /* ===== CHỮ KÝ GIỐNG EXCEL ===== */
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

  <!-- Print Controls (giống hoá đơn) -->
  <div id="printControls" class="print-controls"
       style="position:fixed;top:10px;right:10px;z-index:1000;background:#fff;padding:8px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.15);display:none;">
    <button onclick="window.print()" style="background:#f9b000;color:#fff;border:none;padding:6px 10px;border-radius:3px;margin-right:4px;cursor:pointer;">🖨️ In báo giá</button>
    <button onclick="closePrint()" style="background:#6c757d;color:#fff;border:none;padding:6px 10px;border-radius:3px;cursor:pointer;">✖️ Đóng</button>
  </div>

  <script>
    window.addEventListener('load', function () {
      var box = document.getElementById('printControls');
      if (box) box.style.display = 'block';
      setTimeout(function () { window.print(); }, 500);
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

    {{-- ========== HEADER ========== --}}
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

    <div class="title-bar">BÁO GIÁ CUNG CẤP DỊCH VỤ (SỰ KIỆN)</div>

    {{-- ========== THÔNG TIN KHÁCH HÀNG / DỰ ÁN ========== --}}
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
        Kính gửi Quý khách hàng,
        <br/>
        Trước hết, <strong>PHÁT HOÀNG GIA</strong> xin chân thành cảm ơn Quý khách đã tin tưởng và lựa chọn
        chúng tôi là đơn vị đồng hành trong chương trình sắp tới. Dưới đây là báo giá chi tiết cho các hạng mục
        dịch vụ sự kiện theo yêu cầu của Quý khách:
    </div>

    {{-- ========== BẢNG BÁO GIÁ (GIỐNG CẤU TRÚC EXCEL) ========== --}}
    <table class="quote-table">
        <colgroup>
            <col class="col-stt">
            <col class="col-hangmuc">
            <col class="col-chitiet">
            <col class="col-dvt">
            <col class="col-sl">
            <col class="col-dongia">
            <col class="col-thanhtien">
        </colgroup>
        <thead>
        <tr>
            <th>STT</th>
            <th>HẠNG MỤC</th>
            <th>CHI TIẾT</th>
            <th>ĐVT</th>
            <th>SL</th>
            <th>ĐƠN GIÁ</th>
            <th>THÀNH TIỀN</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($sections as $section)
            {{-- Hàng tiêu đề Section (A. CSVC, B. Chi phí khác, ...) --}}
            <tr class="section-row">
                <td colspan="6">
                    {{ ($section['letter'] ?? '') . '. ' . mb_strtoupper($section['name'] ?? '', 'UTF-8') }}
                </td>
                <td class="text-right">
                    {{ isset($section['total_thanh_tien']) ? number_format($section['total_thanh_tien'], 0, ',', '.') : '' }}
                </td>
            </tr>

            @php
                $items    = $section['items'] ?? [];
                $count    = count($items);
                $rowIndex = 1; // STT
                $i        = 0;
            @endphp

            @while ($i < $count)
                @php
                    $line = $items[$i];

                    // ===== TÍNH HẠNG MỤC HIỂN THỊ CHO DÒNG/ NHÓM NÀY =====
                    $hmRaw    = $line['hang_muc']     ?? '';
                    $hmGoc    = $line['hang_muc_goc'] ?? $hmRaw;
                    $hangMuc  = $hmRaw;

                    // Override theo Step 8 nếu có
                    if (!empty($hmGoc) && !empty($categoryTitleMap[$hmGoc] ?? null)) {
                        $hangMuc = $categoryTitleMap[$hmGoc];
                    }

                    $isPackageLine = !empty($line['is_package']);

                    // ===== ĐẾM SỐ DÒNG LIÊN TIẾP CÙNG HẠNG MỤC (CHỈ CHO DÒNG THÀNH PHẦN) =====
                    if ($isPackageLine) {
                        // Dòng GÓI (Trọn gói) luôn là nhóm riêng, không merge với dòng khác
                        $groupCount = 1;
                    } else {
                        $groupCount = 1;
                        for ($j = $i + 1; $j < $count; $j++) {
                            $lineJ        = $items[$j];
                            $isPackageJ   = !empty($lineJ['is_package']);
                            $hmRawJ       = $lineJ['hang_muc']     ?? '';
                            $hmGocJ       = $lineJ['hang_muc_goc'] ?? $hmRawJ;
                            $hangMucJ     = $hmRawJ;

                            if (!empty($hmGocJ) && !empty($categoryTitleMap[$hmGocJ] ?? null)) {
                                $hangMucJ = $categoryTitleMap[$hmGocJ];
                            }

                            // Chỉ gộp nếu: cùng Hạng mục + đều là dòng THÀNH PHẦN (is_package = false)
                            if (!$isPackageJ && $hangMucJ === $hangMuc) {
                                $groupCount++;
                            } else {
                                break;
                            }
                        }
                    }

                    // Dòng đầu tiên trong nhóm
                    $line0 = $line;
                @endphp

                {{-- Dòng 1 của nhóm (có ô HẠNG MỤC với rowspan) --}}
                <tr>
                    <td class="text-center">{{ $rowIndex }}</td>
                    <td class="text-center" rowspan="{{ $groupCount }}">
                        {{ $hangMuc }}
                    </td>

                    <td>{!! $line0['chi_tiet_html'] ?? e($line0['chi_tiet'] ?? '') !!}</td>
                    <td class="text-center">{{ $line0['dvt'] ?? '' }}</td>
                    <td class="text-center">
                        @php $qty0 = $line0['so_luong'] ?? null; @endphp
                        {{ $qty0 !== null ? rtrim(rtrim(number_format($qty0, 2, ',', '.'), '0'), ',') : '' }}
                    </td>
                    <td class="text-right">
                        {{ isset($line0['don_gia']) ? number_format($line0['don_gia'], 0, ',', '.') : '' }}
                    </td>
                    <td class="text-right">
                        {{ isset($line0['thanh_tien']) ? number_format($line0['thanh_tien'], 0, ',', '.') : '' }}
                    </td>
                </tr>

                {{-- Các dòng tiếp theo trong cùng Hạng mục (nếu là THÀNH PHẦN) --}}
                @for ($k = 1; $k < $groupCount; $k++)
                    @php
                        $lineN = $items[$i + $k];
                    @endphp
                    <tr>
                        {{-- STT tăng theo từng dòng --}}
                        <td class="text-center">{{ $rowIndex + $k }}</td>

                        {{-- Không có cột HẠNG MỤC ở đây (vì đã rowspan ở dòng đầu nhóm) --}}
                        <td>{!! $lineN['chi_tiet_html'] ?? e($lineN['chi_tiet'] ?? '') !!}</td>
                        <td class="text-center">{{ $lineN['dvt'] ?? '' }}</td>
                        <td class="text-center">
                            @php $qtyN = $lineN['so_luong'] ?? null; @endphp
                            {{ $qtyN !== null ? rtrim(rtrim(number_format($qtyN, 2, ',', '.'), '0'), ',') : '' }}
                        </td>
                        <td class="text-right">
                            {{ isset($lineN['don_gia']) ? number_format($lineN['don_gia'], 0, ',', '.') : '' }}
                        </td>
                        <td class="text-right">
                            {{ isset($lineN['thanh_tien']) ? number_format($lineN['thanh_tien'], 0, ',', '.') : '' }}
                        </td>
                    </tr>
                @endfor

                @php
                    // Cập nhật STT & index
                    $rowIndex += $groupCount;
                    $i        += $groupCount;
                @endphp
            @endwhile

        @empty
            <tr>
                <td colspan="7" class="text-center">
                    (Chưa có dữ liệu báo giá – cần build sections từ đơn hàng)
                </td>
            </tr>
        @endforelse
        </tbody>



    </table>

    {{-- ========== TỔNG CHI PHÍ & GHI CHÚ ========== --}}
    <div class="summary-block">
        <div class="summary-row">
            <span class="summary-label">TỔNG CHI PHÍ:</span>
            <span class="summary-label">{{ number_format($tongThanhTien, 0, ',', '.') }} đ</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Bằng chữ:</span>
            <span>{{ $tongThanhTienBangChu }}</span>
        </div>
    </div>


    <div class="note-block">
        <div><strong>Ghi chú:</strong></div>

        @if (!empty($footerNote))
            {{-- Ghi chú tuỳ biến (Step 8) – giữ xuống dòng --}}
            <p>{!! nl2br(e($footerNote)) !!}</p>
        @else
            {{-- Ghi chú mặc định --}}
            <ul>
                <li>Giá trên đã bao gồm toàn bộ chi phí nhân sự và trang thiết bị theo mô tả trong bảng báo giá.</li>
                <li>Giá chưa bao gồm thuế VAT (nếu có thỏa thuận khác sẽ ghi rõ trong hợp đồng).</li>
                <li>Báo giá có hiệu lực đến ngày: {{ now()->addDays(7)->format('d/m/Y') }}.</li>
            </ul>
        @endif
    </div>


    {{-- ========== KHỐI CHỮ KÝ (2 CỘT: NGƯỜI BÁO GIÁ & XÁC NHẬN BÁO GIÁ) ========== --}}
    <table class="sign-table">
        <tr class="sign-header">
            <td>NGƯỜI BÁO GIÁ</td>
            <td>XÁC NHẬN BÁO GIÁ</td>
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
                @php
                    $approverNote = $signer['approver_note'] ?? null;
                @endphp
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

    </div> <!-- /.page-frame -->
  </div> <!-- /.page -->
</body>
</html>

