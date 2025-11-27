<?php

namespace App\Http\Middleware;

use App\Class\CustomResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionV2
{
    /** Module alias: prefix URL -> module canonical (khớp registry V2) */
    protected array $alias = [
        // Hệ thống phụ
        'attendance'           => 'nhan-su', // alias cũ (nếu còn dùng)
        'nhan-su'              => 'nhan-su',

                // Nhân sự → Bảng lương
        'nhan-su/bang-luong/my' => 'payrollMe', // Bảng lương (của tôi)
        'nhan-su/bang-luong'    => 'payroll',   // Bảng lương (Quản lý) + list

        // Nhân sự → Thiết lập lương (hồ sơ)
        'nhan-su/luong-profile'     => 'payroll-profile',   // GET get()
        'nhan-su/luong-profile/upsert' => 'payroll-profile',// POST upsert()
        'nhan-su/luong/preview'     => 'payroll-profile',   // GET preview()

        // CSKH (parent/child)
        'cskh/points'          => 'cskh-points',
        'cskh-points'          => 'cskh-points',
        'cskh'                 => 'cskh',         // menu cha
        // CSKH → Đánh giá dịch vụ (ZNS Review)
'cskh/reviews'         => 'cskh-review',


        // Utilities
        'utilities/fb'         => 'utilities-fb',
        'utilities/zl'         => 'utilities-zl',
        'utilities'            => 'utilities',    // menu cha

        // VT
        'vt/items'             => 'vt-items',
        'vt/receipts'          => 'vt-receipts',
        'vt/issues'            => 'vt-issues',
        'vt/stocks'            => 'vt-stocks',
        'vt/ledger'            => 'vt-stocks',    // ledger dùng quyền stocks

        // Cashflow
        'cash/accounts'        => 'cash-accounts',
        'cash/aliases'         => 'cash-aliases',
        'cash/ledger'          => 'cash-ledger',
        'cash/balances'        => 'cash-ledger',
        'cash/balances/summary'=> 'cash-ledger',
        'cash/internal-transfers' => 'cash-internal-transfers',

        // Kiểm toán (Tra soát lệch phiếu ↔ sổ quỹ)
'cash/audit-delta'      => 'kiem-toan',
'cash/audit-delta/fix'  => 'kiem-toan',



                // Công nợ KH (read-only)
        'cong-no'               => 'quan-ly-cong-no',
        'cong-no/summary'       => 'quan-ly-cong-no',
        'cong-no/customers'     => 'quan-ly-cong-no',
        'cong-no/export'        => 'quan-ly-cong-no',

 // Quản lý chi phí (mới)
        'quan-ly-chi-phi'           => 'quan-ly-chi-phi',
        'quan-ly-chi-phi/de-xuat'   => 'quan-ly-chi-phi-de-xuat',
        'quan-ly-chi-phi/thuc-te'   => 'quan-ly-chi-phi-thuc-te',
        
        // Các module chuẩn 1:1
        'dashboard'            => 'dashboard',
        'vai-tro'              => 'vai-tro',
        'nguoi-dung'           => 'nguoi-dung',
        'cau-hinh-chung'       => 'cau-hinh-chung',
        'thoi-gian-lam-viec'   => 'thoi-gian-lam-viec',
        'lich-su-import'       => 'lich-su-import',

        'loai-khach-hang'      => 'loai-khach-hang',
        'khach-hang'           => 'khach-hang',
        'khach-hang-vang-lai'  => 'khach-hang-vang-lai',
        'khach-hang-pass-ctv'  => 'khach-hang-pass-ctv',


        'nha-cung-cap'         => 'nha-cung-cap',
        'danh-muc-san-pham'    => 'danh-muc-san-pham',
        'don-vi-tinh'          => 'don-vi-tinh',
        'san-pham'             => 'san-pham',

         // Gói dịch vụ (module mới)
        'goi-dich-vu/groups'     => 'goi-dich-vu-groups',
        'goi-dich-vu/categories' => 'goi-dich-vu-categories',
        'goi-dich-vu/packages'   => 'goi-dich-vu-packages',

        'phieu-nhap-kho'       => 'phieu-nhap-kho',
        'phieu-xuat-kho'       => 'phieu-xuat-kho',
        'quan-ly-ton-kho'      => 'quan-ly-ton-kho',
        'quan-ly-ban-hang'     => 'quan-ly-ban-hang',

           // 🔹 Hợp đồng (mới)
        'quan-ly-hop-dong'     => 'quan-ly-hop-dong',

        // Giao hàng: tách module riêng (đã chốt)
        'giao-hang'            => 'giao-hang',

        'cong-thuc-san-xuat'   => 'cong-thuc-san-xuat',
        'san-xuat'             => 'san-xuat',

        'phieu-thu'            => 'phieu-thu',
        'phieu-chi'            => 'phieu-chi',
        'thu-chi/bao-cao'      => 'bao-cao-thu-chi',
        'bao-cao-quan-tri'     => 'bao-cao-quan-tri',
        'bao-cao-quan-tri/tai-chinh' => 'bao-cao-tai-chinh',
        'bao-cao-quan-tri/khach-hang' => 'bao-cao-khach-hang',


    ];

    /** Public patterns (webhook/OAuth/options/template…) — tối thiểu & an toàn */
    /** Public patterns (webhook/OAuth/options/template…) — tối thiểu & an toàn */
    protected array $publicPrefixes = [
        // Webhook / OAuth (không qua jwt, public)
        'fb/webhook',                 // Webhook Facebook
        'zl/webhook',                 // Webhook Zalo
        'zl/oauth',                   // OAuth redirect/callback

        // AUTH: cho phép đọc thông tin phiên sau khi login (bỏ kiểm tra permission, vẫn đi qua jwt)
        'auth/me',
        'auth/logout',
        'auth/profile',
        'auth/change-password', 

        // Các endpoint tra cứu/khởi tạo cần mở (nếu chúng đi trong group có permission)
        'danh-sach-phan-quyen',
        'vt/references',
        'expense-categories',         // bao phủ .../parents, .../options, .../tree
    ];

    // === umbrella helper: kiểm quyền 1 module + action ===
    protected function hasPerm(array $permissions, string $name, string $action): bool
    {
        foreach ($permissions as $p) {
            if (!isset($p['name']) || $p['name'] !== $name) continue;
            $acts = $p['actions'] ?? [];
            if (!empty($acts[$action])) return true;
            if ($action === 'show' && !empty($acts['index'])) return true; // show được coi như index
        }
        return false;
    }



    public function handle(Request $request, Closure $next): Response
    {
        // 0) Public tối thiểu
        if ($this->isPublic($request)) {
            return $next($request);
        }

        $user = Auth::user();

        // 1) Xác thực & bypass admin tuyệt đối
        if (!$user) {
            return CustomResponse::error('Chưa đăng nhập', Response::HTTP_UNAUTHORIZED);
        }
        if (strcasecmp($user->email, 'admin@gmail.com') === 0) {
            return $next($request); // bypass tuyệt đối
        }

        // 2) Lấy role & quyền
        $role = $user->vaiTro;
        if (!$role || $role->trang_thai != 1) {
            return CustomResponse::error('Vai trò không hợp lệ', Response::HTTP_UNAUTHORIZED);
        }
        $permissions = json_decode($role->phan_quyen ?? '[]', true) ?: [];

        // 3) Chuẩn hoá path & resolve module
        $path = ltrim(preg_replace('#^api/#', '', $request->path()), '/'); // bỏ "api/"
        $module = $this->resolveModule($path);

        if (!$module) {
            // Không resolve được module → từ chối (default-deny)
            return CustomResponse::error('Bạn không có quyền truy cập (module)', Response::HTTP_FORBIDDEN);
        }

        // 4) Resolve action (chuẩn + đặc thù)
        $action = $this->resolveAction($path, $request->method());
        if (!$action) {
            return CustomResponse::error('Bạn không có quyền truy cập (action)', Response::HTTP_FORBIDDEN);
        }

        // 5) So khớp với quyền trong vai trò (so sánh name == module, không contains)
$granted = false;
foreach ($permissions as $p) {
    if (!isset($p['name']) || $p['name'] !== $module) continue;
    $acts = $p['actions'] ?? [];

    if (!empty($acts[$action])) { $granted = true; break; }

    // Fallback: cho phép show khi có index
    if ($action === 'show' && !empty($acts['index'])) { $granted = true; break; }
}

// === Fallback umbrella cho nhóm CASHFLOW ===
// Nếu module là 1 trong các 'cash-*' mà chưa được cấp thẳng,
// thì cho phép nếu có 'cashflow.index' (đọc) hoặc 'cashflow.edit' (ghi).
if (!$granted && str_starts_with($module, 'cash-')) {
    $isRead  = in_array($action, ['index','show','export'], true);
    $isWrite = in_array($action, ['create','edit','delete','post','unpost','update'], true);

    if ($isRead && $this->hasPerm($permissions, 'cashflow', 'index')) {
        $granted = true;
    } elseif ($isWrite && $this->hasPerm($permissions, 'cashflow', 'edit')) {
        $granted = true;
    }
}


        if (!$granted) {
            return CustomResponse::error('Bạn không có quyền thực hiện thao tác này', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /** Kiểm tra public tối thiểu */
    protected function isPublic(Request $request): bool
    {
        $p = ltrim(preg_replace('#^api/#', '', $request->path()), '/');
        foreach ($this->publicPrefixes as $pre) {
            if ($p === $pre || str_starts_with($p, $pre . '/')) {
                return true;
            }
        }
        return false;
    }

    /** Lấy segment đầu/đầu+1 rồi map alias → module canonical */
    protected function resolveModule(string $path): ?string
    {
        $seg = explode('/', $path);
        $first  = $seg[0] ?? '';
        $first2 = isset($seg[1]) ? $first . '/' . $seg[1] : $first;
        $first3 = isset($seg[2]) ? $first . '/' . $seg[1] . '/' . $seg[2] : $first2;

        // Ưu tiên match dài hơn trước
        foreach ([$first3, $first2, $first] as $key) {
            if (isset($this->alias[$key])) {
                return $this->alias[$key];
            }
        }
        return null;
    }

    /** Map HTTP + URL pattern -> action (chuẩn + đặc thù + export) */
    protected function resolveAction(string $path, string $method): ?string
    {
        // 1) Export / template
        if (preg_match('#(download-template-excel|kqkd-export|export)(\?|/|$)#', $path) === 1) {
            return 'export';
        }

        // 2) Đặc thù — Facebook
        if (preg_match('#^utilities/fb/conversations/\d+/reply$#', $path) === 1 && $method === 'POST') {
            return 'send';
        }
        if (preg_match('#^utilities/fb/conversations/\d+/assign$#', $path) === 1 && $method === 'POST') {
            return 'assign';
        }
        if (preg_match('#^utilities/fb/conversations/\d+/status$#', $path) === 1 && $method === 'PATCH') {
            return 'status';
        }

        // 3) Đặc thù — Zalo
        if (preg_match('#^utilities/zl/conversations/\d+/reply$#', $path) === 1 && $method === 'POST') {
            return 'send';
        }
        if (preg_match('#^utilities/zl/conversations/\d+/assign$#', $path) === 1 && $method === 'POST') {
            return 'assign';
        }
        if (preg_match('#^utilities/zl/conversations/\d+/status$#', $path) === 1 && $method === 'PATCH') {
            return 'status';
        }

        // 4) Đặc thù — CSKH Points
        if (preg_match('#^cskh(-points)?/events/\d+/send-zns$#', $path) === 1 && $method === 'POST') {
            return 'sendZns';
        }

                // 4b) Đặc thù — CSKH Review (Đánh giá dịch vụ)
        if (preg_match('#^cskh/reviews/invites/\d+/send$#', $path) === 1 && $method === 'POST') {
            return 'send';          // gửi 1 lời mời
        }
        if (preg_match('#^cskh/reviews/bulk-send$#', $path) === 1 && $method === 'POST') {
            return 'bulk';          // gửi hàng loạt
        }
        if (preg_match('#^cskh/reviews/invites/\d+/cancel$#', $path) === 1 && $method === 'PATCH') {
            return 'cancel';        // huỷ lời mời pending
        }
        // POST /cskh/reviews/invites/from-order/{id} → semantic là tạo mới từ đơn (có {id})
        if (preg_match('#^cskh/reviews/invites/from-order/\d+$#', $path) === 1 && $method === 'POST') {
            return 'create';        // tạo invite từ đơn
        }

        // /cskh/reviews/backfill -> dùng action 'bulk'
        if (preg_match('#^cskh/reviews/backfill$#', $path) === 1 && $method === 'POST') {
            return 'bulk';
        }


        // 5) Đặc thù — Giao hàng: notify-and-set-status
        if (preg_match('#^giao-hang/\d+/notify-and-set-status$#', $path) === 1 && $method === 'POST') {
            return 'notifyAndSetStatus';
        }

        // 6) Đặc thù — KH vãng lai: convert
        if (preg_match('#^khach-hang-vang-lai/convert$#', $path) === 1 && $method === 'POST') {
            return 'convert';
        }

        // 6b) Đặc thù — Khách hàng Pass đơn & CTV: convert
        if (preg_match('#^khach-hang-pass-ctv/convert-to-pass/\d+$#', $path) === 1 && $method === 'POST') {
            return 'convert';
        }
        if (preg_match('#^khach-hang-pass-ctv/convert-to-normal/\d+$#', $path) === 1 && $method === 'POST') {
            return 'convert';
        }


        // 7) Đặc thù — Cash internal transfers: post/unpost
        if (preg_match('#^cash/internal-transfers/\d+/post$#', $path) === 1 && $method === 'POST') {
            return 'post';
        }
        if (preg_match('#^cash/internal-transfers/\d+/unpost$#', $path) === 1 && $method === 'POST') {
            return 'unpost';
        }

                // 8) Đặc thù — Công nợ KH: xem chi tiết 1 khách
        if (preg_match('#^cong-no/customers/\d+$#', $path) === 1 && $method === 'GET') {
            return 'show';
        }

        // Đặc thù — Phiếu chi: post/unpost
        if (preg_match('#^phieu-chi/\d+/post$#', $path) === 1 && $method === 'POST') {
            return 'post';
        }
        if (preg_match('#^phieu-chi/\d+/unpost$#', $path) === 1 && $method === 'POST') {
            return 'unpost';
        }

// Đặc thù — Kiểm toán (audit delta): GET audit = index; POST fix = edit
if (preg_match('#^cash/audit-delta$#', $path) === 1 && $method === 'GET') {
    return 'index';
}
if (preg_match('#^cash/audit-delta/fix$#', $path) === 1 && $method === 'POST') {
    return 'edit'; // KHÔNG phải create
}

        // Đặc thù — Payroll (Bảng lương)
        // POST /nhan-su/bang-luong/recompute -> recompute
        if (preg_match('#^nhan-su/bang-luong/recompute$#', $path) === 1 && $method === 'POST') {
            return 'recompute';
        }
        // PATCH /nhan-su/bang-luong/lock -> lock
        if (preg_match('#^nhan-su/bang-luong/lock$#', $path) === 1 && $method === 'PATCH') {
            return 'lock';
        }
        // PATCH /nhan-su/bang-luong/unlock -> unlock
        if (preg_match('#^nhan-su/bang-luong/unlock$#', $path) === 1 && $method === 'PATCH') {
            return 'unlock';
        }
        // PATCH /nhan-su/bang-luong/update-manual -> update (đặc thù)
        if (preg_match('#^nhan-su/bang-luong/update-manual$#', $path) === 1 && $method === 'PATCH') {
            return 'update';
        }
        // GET /nhan-su/bang-luong/list -> index (để CRUD mặc định xử lý GET no-id = index)
        // GET /nhan-su/bang-luong (adminShow với ?user_id=) sẽ rơi về index (không check query ở đây).

// Đặc thù — Payroll Profile (Thiết lập lương)
// GET /nhan-su/luong-profile -> index
if (preg_match('#^nhan-su/luong-profile$#', $path) === 1 && $method === 'GET') {
    return 'index';
}
// POST /nhan-su/luong-profile/upsert -> edit (không coi là create)
if (preg_match('#^nhan-su/luong-profile/upsert$#', $path) === 1 && $method === 'POST') {
    return 'edit';
}
// GET /nhan-su/luong/preview -> index
if (preg_match('#^nhan-su/luong/preview$#', $path) === 1 && $method === 'GET') {
    return 'index';
}


// Đặc thù — Nhân sự: Đơn từ (duyệt/từ chối/hủy)
// PATCH /nhan-su/don-tu/{id}/approve -> edit
if (preg_match('#^nhan-su/don-tu/\d+/approve$#', $path) === 1 && $method === 'PATCH') {
    return 'edit';
}
// PATCH /nhan-su/don-tu/\d+/reject -> edit
if (preg_match('#^nhan-su/don-tu/\d+/reject$#', $path) === 1 && $method === 'PATCH') {
    return 'edit';
}
// PATCH /nhan-su/don-tu/\d+/cancel -> (tùy chọn) cho phép coi như edit; 
// nếu muốn user tự hủy không cần quyền edit, bạn có thể return 'index' thay vì 'edit'
if (preg_match('#^nhan-su/don-tu/\d+/cancel$#', $path) === 1 && $method === 'PATCH') {
    return 'edit';
}





        // 8) Chuẩn CRUD
       // 8) Chuẩn CRUD — nhận diện {id} theo segment CUỐI để hỗ trợ nested prefix (vd: vt/receipts/{id})
$parts = explode('/', trim($path, '/'));
$last  = $parts[count($parts) - 1] ?? null;
$hasId = is_numeric($last);

if ($hasId) {
    // /.../{id}
    return match ($method) {
        'GET'           => 'show',
        'PUT', 'PATCH'  => 'edit',
        'DELETE'        => 'delete',
        default         => null,
    };
} else {
    // /... (không có id)
    return match ($method) {
        'GET'  => 'index',
        'POST' => 'create',
        default => null,
    };
}

    }
}
