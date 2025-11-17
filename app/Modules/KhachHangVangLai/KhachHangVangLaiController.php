<?php

namespace App\Modules\KhachHangVangLai;

use App\Class\CustomResponse;
use App\Models\DonHang;
use App\Models\KhachHang;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class KhachHangVangLaiController extends BaseController
{
    /**
     * GET /api/khach-hang-vang-lai
     * Trả về danh sách KH vãng lai gom theo SĐT (ưu tiên); nếu thiếu SĐT thì gom theo tên.
     * Fields: ten, sdt, dia_chi_gan_nhat, so_don, last_order_at
     */
    public function index(Request $request)
    {
        $rows = DonHang::query()
            ->where('loai_khach_hang', 1) // 1 = vãng lai (logic cũ vẫn dùng được cho ERP Sự kiện)
            ->where(function ($q) {
                $q->whereNotNull('so_dien_thoai')
                  ->orWhereNotNull('ten_khach_hang');
            })
            ->select([
                'ten_khach_hang',
                'so_dien_thoai',
                'dia_chi_giao_hang',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->get();

        if ($rows->isEmpty()) {
            return CustomResponse::success([
                'collection' => [],
                'total'      => 0,
            ]);
        }

        $grouped = [];
        foreach ($rows as $r) {
            $key = $r->so_dien_thoai
                ? 'PHONE:' . trim((string) $r->so_dien_thoai)
                : 'NAME:' . trim((string) $r->ten_khach_hang);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'ten'               => (string) ($r->ten_khach_hang ?? ''),
                    'sdt'               => (string) ($r->so_dien_thoai ?? ''),
                    'dia_chi_gan_nhat'  => (string) ($r->dia_chi_giao_hang ?? ''),
                    'so_don'            => 1,
                    'last_order_at'     => $r->created_at,
                ];
            } else {
                $grouped[$key]['so_don']++;
            }
        }

        $collection = array_values($grouped);

        return CustomResponse::success([
            'collection' => $collection,
            'total'      => count($collection),
        ]);
    }

    /**
     * POST /api/khach-hang-vang-lai/convert
     *
     * Body:
     *  - ten_khach_hang (required)
     *  - so_dien_thoai (nullable)
     *  - email (nullable)
     *  - dia_chi (nullable)
     *  - kenh_lien_he (nullable)
     *  - customer_type (nullable, 0=Event,1=Wedding,2=Agency; default=0 nếu không gửi)
     *  - don_hang_ids (nullable array id đơn cần gắn)
     *
     * Nghiệp vụ:
     *  - Tạo hoặc lấy KH hệ thống tương ứng
     *  - Luôn set is_system_customer = 1
     *  - Nếu KH mới -> gán customer_type (mặc định Event nếu không truyền)
     *  - Gắn các đơn vãng lai (loai_khach_hang=1) về KH hệ thống (loai_khach_hang=0)
     */
    public function convert(Request $request)
    {
        $validated = $request->validate([
            'ten_khach_hang'     => ['required', 'string', 'max:255'],
            'so_dien_thoai'      => ['nullable', 'string', 'max:50'],
            'email'              => ['nullable', 'string', 'max:255'],
            'dia_chi'            => ['nullable', 'string'],
            'kenh_lien_he'       => ['nullable', 'string', 'max:191'],
            'customer_type'      => [
                'nullable',
                'integer',
                Rule::in([
                    KhachHang::TYPE_EVENT,
                    KhachHang::TYPE_WEDDING,
                    KhachHang::TYPE_AGENCY,
                ]),
            ],
            'don_hang_ids'       => ['nullable', 'array'],
            'don_hang_ids.*'     => ['integer', 'exists:don_hangs,id'],
        ]);

        $ten   = trim($validated['ten_khach_hang']);
        $sdt   = isset($validated['so_dien_thoai']) ? $this->normalizeVNPhone($validated['so_dien_thoai']) : '';
        $mail  = $validated['email']   ?? null;
        $addr  = $validated['dia_chi'] ?? null;
        $kenh  = $validated['kenh_lien_he'] ?? null;
        $limitIds = $validated['don_hang_ids'] ?? null;

        // Nếu không truyền customer_type thì mặc định là Event client
        $customerType = $validated['customer_type'] ?? KhachHang::TYPE_EVENT;

        // Tự sinh email nếu thiếu & có SĐT
        if (! $mail && $sdt !== '') {
            $mail = $sdt . '@phgfloral.com';
        }

        return DB::transaction(function () use ($ten, $sdt, $mail, $addr, $kenh, $limitIds, $customerType) {
            // 1) Tạo hoặc lấy Khách hàng hệ thống
            if ($sdt !== '') {
                $kh = KhachHang::where('so_dien_thoai', $sdt)->lockForUpdate()->first();
            } else {
                $kh = KhachHang::where('ten_khach_hang', $ten)->lockForUpdate()->first();
            }

            if (! $kh) {
                // KH mới: luôn là KH hệ thống
                $dataCreate = [
                    'ma_kh'              => $this->nextMaKh(),
                    'ten_khach_hang'     => $ten,
                    'so_dien_thoai'      => $sdt ?: null,
                    'email'              => $mail ?: '',
                    'dia_chi'            => $addr ?: '',
                    'customer_type'      => $customerType,
                    'is_system_customer' => true,
                ];

                // Nếu có cột kenh_lien_he -> lưu trực tiếp
                if (Schema::hasColumn('khach_hangs', 'kenh_lien_he')) {
                    $dataCreate['kenh_lien_he'] = $kenh;
                } else {
                    // fallback vào ghi_chu để không mất dữ liệu
                    if ($kenh) {
                        $dataCreate['ghi_chu'] = '[Kênh liên hệ: ' . $kenh . ']';
                    }
                }

                $kh = KhachHang::create($dataCreate);
            } else {
                // KH đã tồn tại: cập nhật mềm thông tin còn thiếu
                $dirty = [
                    'ten_khach_hang' => $kh->ten_khach_hang ?: $ten,
                    'email'          => $kh->email ?: ($mail ?: ''),
                    'dia_chi'        => $kh->dia_chi ?: ($addr ?: ''),
                ];

                // Nếu có cột kenh_lien_he -> ưu tiên giữ giá trị cũ, nếu trống thì gán mới
                if (Schema::hasColumn('khach_hangs', 'kenh_lien_he')) {
                    $dirty['kenh_lien_he'] = $kh->kenh_lien_he ?: $kenh;
                } elseif ($kenh && empty($kh->ghi_chu)) {
                    $dirty['ghi_chu'] = '[Kênh liên hệ: ' . $kenh . ']';
                }

                // Nếu KH cũ chưa có ma_kh -> sinh bổ sung
                if (Schema::hasColumn('khach_hangs', 'ma_kh') && empty($kh->ma_kh)) {
                    $dirty['ma_kh'] = $this->nextMaKh();
                }

                // Đảm bảo KH này trở thành KH hệ thống
                if (Schema::hasColumn('khach_hangs', 'is_system_customer') && ! $kh->is_system_customer) {
                    $dirty['is_system_customer'] = true;
                }

                // Nếu chưa set customer_type thì gán theo yêu cầu convert lần này
                if (Schema::hasColumn('khach_hangs', 'customer_type') && $kh->customer_type === null) {
                    $dirty['customer_type'] = $customerType;
                }

                $kh->fill($dirty)->save();
            }

            // 2) Chọn các đơn vãng lai cần gắn
            $query = DonHang::query()
                ->where('loai_khach_hang', 1); // vãng lai

            if ($limitIds && count($limitIds)) {
                $query->whereIn('id', $limitIds);
            } elseif ($sdt !== '') {
                $query->where('so_dien_thoai', $sdt);
            } else {
                $query->where('ten_khach_hang', $ten);
            }

            // 3) Cập nhật đơn → gắn về KH hệ thống (loai_khach_hang = 0)
            $count = $query->update([
                'khach_hang_id'   => $kh->id,
                'loai_khach_hang' => 0, // 0 = Khách hệ thống
                'ten_khach_hang'  => $ten,
            ]);

            return CustomResponse::success([
                'khach_hang' => $kh,
                'so_don_gan' => $count,
            ], 'Đã chuyển thành khách hàng hệ thống');
        });
    }

    /** Chuẩn hoá số điện thoại VN: +84xxxx -> 0xxxx, loại space */
    private function normalizeVNPhone(string $raw): string
    {
        $s = preg_replace('/\s+/', '', (string) $raw);
        $s = preg_replace('/^\+?84/', '0', $s ?? '');
        if ($s !== null && ! preg_match('/^0/', $s) && preg_match('/^\d{9,10}$/', $s)) {
            $s = '0' . $s;
        }
        return $s ?? '';
    }

    /**
     * Sinh mã KH tiếp theo theo format 'KH' + 5 số (VD: KH00137).
     * Chạy trong transaction + lockForUpdate để tránh đụng độ khi nhiều tiến trình.
     */
    private function nextMaKh(): string
    {
        $lastMa = KhachHang::where('ma_kh', 'like', 'KH%')
            ->orderByDesc('ma_kh')
            ->lockForUpdate()
            ->value('ma_kh');

        $lastNum = 0;
        if ($lastMa && preg_match('/^KH(\d{1,})$/', $lastMa, $m)) {
            $lastNum = (int) $m[1];
        }

        $next = $lastNum + 1;
        return 'KH' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
