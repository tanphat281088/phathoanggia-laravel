<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Hợp đồng dịch vụ</title>
    <style>
        /* ===== FONT & PAGE LAYOUT (MARGIN MODERATE) ===== */
        * {
            font-family: "Times New Roman", Times, serif;
            font-size: 13pt;
            box-sizing: border-box;
        }

        /* Moderate: Top 1", Bottom 1", Left 0.75", Right 0.75" */
        body {
            margin: 1in 0.75in;
            color: #000;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }

        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }
        .mb-0  { margin-bottom: 0; }
        .mb-5  { margin-bottom: 5px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-20 { margin-bottom: 20px; }
        .fw-bold { font-weight: bold; }

        h1, h2, h3, h4 {
            margin: 0;
            padding: 0;
            font-weight: bold;
        }
        h1 { font-size: 18pt; }
        h2 { font-size: 16pt; }

        /* ===== COVER PAGE ===== */
        .cover-wrapper {
            margin-top: 140px;
            text-align: center;
        }
        .cover-line {
            border-top: 1px solid #000;
            width: 60%;
            margin: 0 auto 30px auto;
        }
        .contract-title {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .contract-no {
            font-size: 12pt;
            margin-bottom: 40px;
        }

        /* ===== PARAGRAPHS GIỐNG WORD ===== */
        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0 5px 0;
        }

        /* Đoạn văn bình thường: justify, line 1.5, không treo chữ */
        .paragraph {
            margin-top: 0;
            margin-bottom: 0;
            text-align: justify;
            line-height: 1.5;
        }

        /* Giữ lại nếu sau này cần treo chữ riêng cho 1 đoạn nào đó */
        .paragraph-hanging {
            text-indent: -0.39in;
            margin-left: 0.39in;
        }

        /* ===== TABLE ===== */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 3pt 4pt;
            line-height: 1.3;
            vertical-align: top;
        }
        th {
            background: #f2f2f2;
            font-weight: bold;
        }
        .table-small th,
        .table-small td {
            font-size: 11pt;
        }

        /* ===== CHỮ KÝ ===== */
        .signature-wrapper {
            margin-top: 40px;
        }
        .signature-cell {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .signature-name {
            margin-top: 60px;
            font-weight: bold;
        }

        hr {
            border: none;
            border-top: 1px solid #000;
            margin: 40px 0;
        }
    </style>
</head>
<body>
@php
    $blockMap = [];
    foreach ($blocksResolved as $b) {
        if (!empty($b['key'])) {
            $blockMap[$b['key']] = $b;
        }
    }
    $getBlockText = function ($key) use ($blockMap) {
        return isset($blockMap[$key]['text']) ? $blockMap[$key]['text'] : '';
    };

    $sectionNames = [
        'NS'   => 'Nhân sự',
        'CSVC' => 'Cơ sở vật chất',
        'TIEC' => 'Tiệc',
        'TD'   => 'Thuê địa điểm',
        'CPK'  => 'Chi phí khác',

        // 🔹 Nhóm mới khớp với Báo giá
        'CPQL' => 'Chi phí quản lý',
        'CPFT' => 'Chi phí phát sinh tăng',
        'CPFG' => 'Chi phí phát sinh giảm',
        'GG'   => 'Giảm giá',

        'KHAC' => 'Khác',
    ];

    $grouped = [];
    foreach ($items as $it) {
        $code   = $it->section_code ?: 'KHAC';
        $letter = $it->section_letter ?: '';
        if (!isset($grouped[$code])) {
            $grouped[$code] = [
                'letter' => $letter,
                'name'   => isset($sectionNames[$code]) ? $sectionNames[$code] : $code,
                'items'  => [],
            ];
        }
        $grouped[$code]['items'][] = $it;
    }

    $formatMoney = function ($n) {
        return number_format((int)$n, 0, ',', '.');
    };
@endphp

{{-- Trang bìa --}}
<div class="cover-wrapper">
    <div class="cover-line"></div>

    <div class="contract-title">
        {!! nl2br(e($getBlockText('COVER_TITLE') ?: 'HỢP ĐỒNG CUNG CẤP DỊCH VỤ')) !!}
    </div>

    <div class="contract-no">
        {!! nl2br(e($getBlockText('COVER_CONTRACT_NO') ?: '')) !!}
    </div>

    <div style="margin-top: 40px;">
        {!! nl2br(e($getBlockText('COVER_PARTIES') ?: '')) !!}
    </div>

    <div style="margin-top: 160px;">
        {!! nl2br(e($getBlockText('COVER_DATE_LINE') ?: '')) !!}
    </div>
</div>

<hr />

{{-- Quốc hiệu + tiêu đề --}}
<div class="text-center mb-10">
    <div class="fw-bold">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
    <div>Độc lập - Tự do - Hạnh phúc</div>
    <div>----</div>
</div>

<div class="text-center mb-10">
    <h2 class="mb-0">
        {!! nl2br(e($getBlockText('COVER_TITLE') ?: 'HỢP ĐỒNG CUNG CẤP DỊCH VỤ')) !!}
    </h2>
    @if($hopDong->so_hop_dong)
        <div class="contract-no">
            (Số: {{ $hopDong->so_hop_dong }})
        </div>
    @endif
</div>

{{-- Lời mở đầu --}}
@if($getBlockText('PREAMBLE'))
    <div class="section-title">LỜI MỞ ĐẦU</div>
    <div class="paragraph">
        {!! nl2br(e($getBlockText('PREAMBLE'))) !!}
    </div>
@endif

{{-- Bên A / Bên B --}}
@if($getBlockText('PARTY_A_BLOCK'))
    <div class="section-title mt-10">BÊN A</div>
    <div class="paragraph">
        {!! nl2br(e($getBlockText('PARTY_A_BLOCK'))) !!}
    </div>
@endif

@if($getBlockText('PARTY_B_BLOCK'))
    <div class="section-title mt-10">BÊN B</div>
    <div class="paragraph">
        {!! nl2br(e($getBlockText('PARTY_B_BLOCK'))) !!}
    </div>
@endif

{{-- Điều 1 --}}
@if($getBlockText('ARTICLE1_BODY'))
    <div class="section-title mt-20">ĐIỀU 1: NỘI DUNG HỢP ĐỒNG</div>
    <div class="paragraph">
        {!! nl2br(e($getBlockText('ARTICLE1_BODY'))) !!}
    </div>
@endif

{{-- Bảng hạng mục --}}
@if(count($grouped))
    <div class="section-title mt-10">BẢNG HẠNG MỤC THỰC HIỆN</div>
    <table class="table-small">
        <thead>
        <tr>
            <th style="width:4%;">STT</th>
            <th style="width:16%;">Hạng mục</th>
            <th style="width:38%;">Chi tiết</th>
            <th style="width:8%;">ĐVT</th>
            <th style="width:8%;">SL</th>
            <th style="width:13%;">Đơn giá</th>
            <th style="width:13%;">Thành tiền</th>
        </tr>
        </thead>
        <tbody>
        @php
            $stt = 1;
            $tongTruocVat = 0;
        @endphp
        @foreach($grouped as $sec)
            <tr>
                <td colspan="7" style="font-weight:bold; background:#fde9d9;">
                    {{ $sec['letter'] ? $sec['letter'].'. ' : '' }}{{ mb_strtoupper($sec['name'], 'UTF-8') }}
                </td>
            </tr>
            @foreach($sec['items'] as $it)
                @php $tongTruocVat += (int)$it->thanh_tien; @endphp
                <tr>
                    <td class="text-center">{{ $stt++ }}</td>
                    <td>{{ $it->hang_muc }}</td>
                    <td>{!! nl2br(e($it->chi_tiet)) !!}</td>
                    <td class="text-center">{{ $it->dvt }}</td>
                    <td class="text-center">{{ (int)$it->so_luong }}</td>
                    <td class="text-right">{{ $formatMoney($it->don_gia) }}</td>
                    <td class="text-right">{{ $formatMoney($it->thanh_tien) }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
@endif

{{-- Điều 2 --}}
@if($getBlockText('ARTICLE2_BODY'))
    <div class="section-title mt-20">ĐIỀU 2: GIÁ TRỊ HỢP ĐỒNG VÀ ĐIỀU KHOẢN THANH TOÁN</div>
    <div class="paragraph">
        {!! nl2br(e($getBlockText('ARTICLE2_BODY'))) !!}
    </div>
@endif

{{-- Điều 3–9 --}}
@foreach([3,4,5,6,7,8,9] as $i)
    @php $key = 'ARTICLE'.$i.'_BODY'; @endphp
    @if($getBlockText($key))
        <div class="section-title mt-20">
            ĐIỀU {{ $i }}:
        </div>
        <div class="paragraph">
            {!! nl2br(e($getBlockText($key))) !!}
        </div>
    @endif
@endforeach
@php
    $xhA = $hopDong->ben_a_xung_ho ?? null;
    $repA = $xhA ? '(' . $xhA . ') ' . $hopDong->ben_a_dai_dien : $hopDong->ben_a_dai_dien;

    $xhB = $hopDong->ben_b_xung_ho ?? null;
    $repB = $xhB ? '(' . $xhB . ') ' . $hopDong->ben_b_dai_dien : $hopDong->ben_b_dai_dien;
@endphp

{{-- Chữ ký --}}
<div class="signature-wrapper">
    <table>
        <tr>
            <td class="signature-cell">
                <div class="fw-bold">ĐẠI DIỆN BÊN A</div>
                <div>(Ký, ghi rõ họ tên)</div>
                <div class="signature-name">
                  {{ $repA }}

                </div>
            </td>
            <td class="signature-cell">
                <div class="fw-bold">ĐẠI DIỆN BÊN B</div>
                <div>(Ký, ghi rõ họ tên)</div>
                <div class="signature-name">
              {{ $repB }}

                </div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
