<?php

/**
 * Registry V2 cho RBAC chi tiết (default-deny).
 * - Mỗi mục gồm: name (module), actions (tất cả quyền = false mặc định).
 * - 7 action chuẩn: showMenu, index, show, create, edit, delete, export
 * - Action đặc thù (đã chốt): send/assign/status, sendZns, notifyAndSetStatus, convert, post/unpost
 *
 * Lưu ý:
 * - Parent modules (cskh, utilities) chủ yếu dùng showMenu; các endpoint thực tế nằm ở module con.
 * - VT ledger dùng quyền của vt-stocks (index/export).
 * - Cashflow chia nhỏ thành 4 module con để phân quyền hạt mịn.
 */

return [

  // ===== HỆ THỐNG =====
  ['name' => 'dashboard',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'vai-tro',            'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'nguoi-dung',         'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'cau-hinh-chung',     'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'thoi-gian-lam-viec', 'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'lich-su-import',     'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

  // ===== KHÁCH HÀNG / DANH MỤC =====
  ['name' => 'loai-khach-hang',        'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'khach-hang',             'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  // Đặc thù: convert vãng lai → khách chuẩn
  ['name' => 'khach-hang-vang-lai',    'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'convert'=>false]],

  // Khách hàng Pass đơn & CTV
  ['name' => 'khach-hang-pass-ctv',    'actions' => [
      'showMenu' => false,   // nếu sau này muốn hiện menu riêng trong Sidebar thì bật true
      'index'    => false,   // GET /khach-hang-pass-ctv
      'show'     => false,
      'create'   => false,
      'edit'     => false,
      'delete'   => false,
      'export'   => false,
      // Đặc thù: chuyển đổi chế độ
      'convert'  => false,   // POST /khach-hang-pass-ctv/convert-to-pass|convert-to-normal
  ]],


  // ===== SẢN PHẨM / NCC =====
  ['name' => 'nha-cung-cap',       'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'danh-muc-san-pham',  'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'don-vi-tinh',        'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'san-pham',           'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

  // Cha: chỉ dùng để bật/tắt menu "Quản lý Gói dịch vụ" trên Sidebar
  ['name' => 'goi-dich-vu',            'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

   // ===== GÓI DỊCH VỤ =====
  ['name' => 'goi-dich-vu-groups',     'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'goi-dich-vu-categories', 'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'goi-dich-vu-packages',   'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  // loai-san-pham (master options) nếu có trang riêng thì thêm showMenu/index

  // ===== KHO & BÁN HÀNG =====
  ['name' => 'phieu-nhap-kho',     'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'phieu-xuat-kho',     'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'quan-ly-ton-kho',    'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'quan-ly-ban-hang',   'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

  // ===== GIAO HÀNG (tách module riêng) =====
  // Đặc thù: notifyAndSetStatus (gửi SMS + đặt trạng thái)
  ['name' => 'giao-hang',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'notifyAndSetStatus'=>false]],

  // ===== SẢN XUẤT =====
  ['name' => 'cong-thuc-san-xuat', 'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'san-xuat',           'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

  // ===== TÀI CHÍNH & BÁO CÁO =====
  ['name' => 'phieu-thu',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
['name' => 'phieu-chi', 'actions' => [
    'showMenu' => false,
    'index'    => false,
    'show'     => false,
    'create'   => false,
    'edit'     => false,
    'delete'   => false,
    // ✅ Action đặc thù:
    'post'     => false, // Ghi sổ phiếu chi
    'unpost'   => false, // Hủy ghi sổ phiếu chi
    'export'   => false,
]],

  ['name' => 'bao-cao-thu-chi',    'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'bao-cao-quan-tri',   'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

    
  ['name' => 'bao-cao-tai-chinh', 'actions' => [
    'showMenu' => false,
    'index'    => false,
    'show'     => false,
    'create'   => false,
    'edit'     => false,
    'delete'   => false,
    'export'   => false,
]],

   // ===== BÁO CÁO KHÁCH HÀNG (READ-ONLY) =====
  ['name' => 'bao-cao-khach-hang', 'actions' => [
    'showMenu' => false, // sẽ bật true nếu muốn hiện mục riêng trên Sidebar
    'index'    => false, // quyền xem Báo cáo khách hàng
    'show'     => false,
    'create'   => false,
    'edit'     => false,
    'delete'   => false,
    'export'   => false,
  ]],
  
  // ===== KIỂM TOÁN (Tra soát lệch phiếu ↔ sổ quỹ) =====
  ['name' => 'kiem-toan',          'actions' => [
      'showMenu' => false,   // hiện/ẩn tab/menu
      'index'    => false,   // quyền xem tra soát
      'show'     => false,
      'create'   => false,
      'edit'     => false,   // quyền áp dụng fix (bù/điều chỉnh)
      'delete'   => false,
      'export'   => false,   // nếu sau này có Export CSV
  ]],


  // ===== CSKH =====
  ['name' => 'cskh',               'actions' => ['showMenu'=>false,'index'=>false]], // cha (menu)
  // Đặc thù: sendZns
  ['name' => 'cskh-points',        'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'sendZns'=>false]],

  // CSKH → Đánh giá dịch vụ (ZNS Review)
  ['name' => 'cskh-review', 'actions' => [
      'showMenu' => false,  // chỉ bật true nếu muốn hiện riêng trên Sidebar (thường false)
      'index'    => false,  // GET /cskh/reviews/invites
      'show'     => false,  // không dùng riêng, middleware có fallback show ← index
      'create'   => false,  // POST /cskh/reviews/invites/from-order/{id}
      'edit'     => false,  // không dùng
      'delete'   => false,  // không dùng
      'export'   => false,  // chưa dùng
      // Đặc thù:
      'send'     => false,  // POST /cskh/reviews/invites/{id}/send
      'bulk'     => false,  // POST /cskh/reviews/bulk-send
      'cancel'   => false,  // PATCH /cskh/reviews/invites/{id}/cancel
  ]],


  // ===== UTILITIES =====
  ['name' => 'utilities',          'actions' => ['showMenu'=>false,'index'=>false]], // cha (menu)
  // Facebook: send/assign/status
  ['name' => 'utilities-fb',       'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'send'=>false,'assign'=>false,'status'=>false]],
  // Zalo: send/assign/status
  ['name' => 'utilities-zl',       'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'send'=>false,'assign'=>false,'status'=>false]],

  // ===== VT (VẬT TƯ) — hạt mịn =====
  ['name' => 'vt-items',           'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'vt-receipts',        'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'vt-issues',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  // ledger đi chung quyền với stocks (index/export)
  ['name' => 'vt-stocks',          'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],

// ===== CASHFLOW — umbrella (quyền tổng) =====
['name' => 'cashflow', 'actions' => [
    'showMenu' => false,  // hiện/ẩn mục trên Sidebar
    'index'    => false,  // cho phép xem các trang/endpoint đọc (balances/ledger/accounts/aliases)
    'show'     => false,
    'create'   => false,
    'edit'     => false,  // nếu muốn post/unpost chuyển nội bộ, bật thêm edit cho role
    'delete'   => false,
    'export'   => false,
]],



  // ===== CASHFLOW — hạt mịn =====
  ['name' => 'cash-accounts',            'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'cash-aliases',             'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  ['name' => 'cash-ledger',              'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false]],
  // Đặc thù: post/unpost
  ['name' => 'cash-internal-transfers',  'actions' => ['showMenu'=>false,'index'=>false,'show'=>false,'create'=>false,'edit'=>false,'delete'=>false,'export'=>false,'post'=>false,'unpost'=>false]],


// ===== QUẢN LÝ CÔNG NỢ =====
['name' => 'quan-ly-cong-no', 'actions' => [
    'showMenu' => false,  // hiện mục trên Sidebar
    'index'    => true,  // GET /cong-no/summary
    'show'     => true,  // GET /cong-no/customers/{id}
    'export'   => true,  // GET /cong-no/export
    // giữ read-only:
    'create'   => false,
    'edit'     => false,
    'delete'   => false,
]],

// ===== QUẢN LÝ CHI PHÍ (cha) =====
['name' => 'quan-ly-chi-phi', 'actions' => [
    // showMenu: bật true nếu anh muốn hiện 1 group "Quản lý chi phí" trên Sidebar
    'showMenu' => false,
    'index'    => false,
    'show'     => false,
    'create'   => false,
    'edit'     => false,
    'delete'   => false,
    'export'   => false,
]],

// ===== QUẢN LÝ CHI PHÍ → ĐỀ XUẤT =====
['name' => 'quan-ly-chi-phi-de-xuat', 'actions' => [
    'showMenu' => false,  // menu con sẽ xử lý ở FE bằng group "Quản lý chi phí"
    'index'    => false,  // list bảng chi phí đề xuất
    'show'     => false,  // xem 1 bảng
    'create'   => false,  // tạo mới (from-quote)
    'edit'     => false,  // sửa header + items
    'delete'   => false,  // xoá
    'export'   => false,  // nếu sau này có export Excel
]],

// ===== QUẢN LÝ CHI PHÍ → THỰC TẾ =====
['name' => 'quan-ly-chi-phi-thuc-te', 'actions' => [
    'showMenu' => false,
    'index'    => false,
    'show'     => false,
    'create'   => false,
    'edit'     => false,
    'delete'   => false,
    'export'   => false,
]],

// ===== QUẢN LÝ HỢP ĐỒNG =====
['name' => 'quan-ly-hop-dong', 'actions' => [
    'showMenu' => false,   // để hiện group "Quản lý HĐ + TL" / "Quản lý Hợp đồng" trên Sidebar
    'index'    => false,  // GET /quan-ly-hop-dong
    'show'     => false,  // GET /quan-ly-hop-dong/{id}
    'create'   => false,  // POST /quan-ly-hop-dong (sau này nếu có)
    'edit'     => false,  // PUT/PATCH /quan-ly-hop-dong/{id}
    'delete'   => false,  // DELETE /quan-ly-hop-dong/{id}
    'export'   => false,  // GET /.../export-docx|export-pdf (sau này)
]],
// ===== NHÂN SỰ → BẢNG LƯƠNG =====
['name' => 'payrollMe', 'actions' => [
    // 7 action chuẩn (dù không dùng hết, vẫn khai báo để form Vai trò không lỗi)
    'showMenu' => false,
    'index'    => false,
    'show'     => false,
    'create'   => false,
    'edit'     => false,
    'delete'   => false,
    'export'   => false,
]],

['name' => 'payroll', 'actions' => [
    // 7 action chuẩn — thêm đủ để UI “Tất cả” không văng lỗi
    'showMenu' => false,
    'index'    => false,   // GET /nhan-su/bang-luong/list
    'show'     => false,   // GET /nhan-su/bang-luong?user_id=&thang=
    'create'   => false,
    'edit'     => false,
    'delete'   => false,
    'export'   => false,
    // Action đặc thù của Payroll
    'recompute'=> false,   // POST /nhan-su/bang-luong/recompute
    'lock'     => false,   // PATCH /nhan-su/bang-luong/lock
    'unlock'   => false,   // PATCH /nhan-su/bang-luong/unlock
    'update'   => false,   // PATCH /nhan-su/bang-luong/update-manual
]],


// ===== NHÂN SỰ → THIẾT LẬP LƯƠNG (HỒ SƠ) =====
['name' => 'payroll-profile', 'actions' => [
    'showMenu' => false,   // bật true nếu muốn hiện 1 mục riêng trên Sidebar
    'index'    => false,   // quyền xem hồ sơ + preview + mở trang UI
    'show'     => false,
    'create'   => false,   // không dùng create riêng
    'edit'     => false,   // quyền upsert (lưu thay đổi)
    'delete'   => false,
    'export'   => false,
]],


  // ===== NHÂN SỰ =====
['name' => 'nhan-su', 'actions' => [
  'showMenu' => false,
  'index'    => false,
  'show'     => false,
  'create'   => false,
  'edit'     => false,
  'delete'   => false,
  'export'   => false,
]],




];
