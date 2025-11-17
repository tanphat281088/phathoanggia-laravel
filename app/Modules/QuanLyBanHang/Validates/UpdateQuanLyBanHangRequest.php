<?php

namespace App\Modules\QuanLyBanHang\Validates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuanLyBanHangRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Lấy id đơn hàng từ route/input để áp dụng unique bỏ qua chính nó.
   */
  protected function currentId(): ?int
  {
    // Tuỳ controller: /don-hang/{id}
    return (int)($this->route('id') ?? $this->route('don_hang') ?? $this->input('id') ?? 0);
  }

  /**
   * Get the validation rules that apply to the request.
   */
  public function rules(): array
  {
    $id = $this->currentId();

    return [
      // ===== Thông tin đơn / báo giá =====
      'ma_don_hang'       => ['prohibited'], // không cho sửa mã sau khi tạo

      'ngay_tao_don_hang' => ['sometimes', 'required', 'date'],
      'dia_chi_giao_hang' => ['sometimes', 'required', 'string'],

      // ===== TRẠNG THÁI GIAO HÀNG (giữ cho tương thích) =====
      // 0 = Chưa giao, 1 = Đang giao, 2 = Đã giao, 3 = Đã hủy
      'trang_thai_don_hang' => ['sometimes','nullable','integer', Rule::in([0,1,2,3])],

      // ===== Thông tin người nhận (giao hoa cũ – giữ, nhưng dùng ít dần) =====
      'nguoi_nhan_ten'        => ['sometimes','nullable','string','max:191'],
      'nguoi_nhan_sdt'        => ['sometimes','nullable','string','max:20','regex:/^(0|\+84)\d{8,12}$/'],
      'nguoi_nhan_thoi_gian'  => ['sometimes','required','date'],


      // ===== THÔNG TIN KHÁCH HÀNG =====
      // 0 = KH hệ thống, 1 = KH tự do
      'loai_khach_hang'   => ['sometimes','required','integer', Rule::in([0,1])],
      'khach_hang_id'     => ['sometimes','nullable','integer','exists:khach_hangs,id'],
      'ten_khach_hang'    => ['sometimes','nullable','string','max:255'],
      'so_dien_thoai'     => ['sometimes','nullable','string','max:255'],

      // ===== THÔNG TIN SỰ KIỆN / BÁO GIÁ (MỚI) =====
      'project_name'       => ['sometimes', 'nullable', 'string', 'max:255'],
      'event_type'         => ['sometimes', 'nullable', 'string', 'max:100'],
      'event_start'        => ['sometimes', 'nullable', 'date'],
      'event_end'          => ['sometimes', 'nullable', 'date'],
      'guest_count'        => ['sometimes', 'nullable', 'integer', 'min:0'],
      'venue_name'         => ['sometimes', 'nullable', 'string', 'max:255'],
      'venue_address'      => ['sometimes', 'nullable', 'string', 'max:255'],
      'contact_name'       => ['sometimes', 'nullable', 'string', 'max:191'],
      'contact_phone'      => ['sometimes', 'nullable', 'string', 'max:50'],
      'contact_email'      => ['sometimes', 'nullable', 'email', 'max:191'],
      'contact_department' => ['sometimes', 'nullable', 'string', 'max:191'],
      'contact_position'   => ['sometimes', 'nullable', 'string', 'max:191'],

      'quote_status'       => ['sometimes', 'nullable', 'integer', Rule::in([0,1,2,3,4,5,6])],


      // ===== Chi phí – giảm trừ =====
      'giam_gia'          => ['sometimes','required','numeric','min:0'],
      'chi_phi'           => ['sometimes','required','numeric','min:0'],

      'giam_gia_thanh_vien' => ['sometimes','nullable','numeric','min:0','max:100'],


      // ===== Thanh toán =====
      // 0 = Chưa thanh toán, 1 = Thanh toán một phần, 2 = Thanh toán toàn bộ
      'loai_thanh_toan'       => ['sometimes','required','integer', Rule::in([0,1,2])],
      'so_tien_da_thanh_toan' => [
        'sometimes','nullable','numeric','min:0',
        Rule::requiredIf(function () {
          $val = $this->input('loai_thanh_toan');
          return isset($val) && (int)$val === 1;
        }),
      ],

      // ===== Thuế (VAT) =====
      'tax_mode' => ['sometimes', 'integer', Rule::in([0, 1])],
      'vat_rate' => [
        'sometimes', 'nullable', 'numeric', 'min:0', 'max:20',
        Rule::requiredIf(function () {
          $val = $this->input('tax_mode');
          return isset($val) && (int)$val === 1;
        }),
      ],


      // ===== Danh sách dịch vụ =====
      'danh_sach_san_pham'                   => ['sometimes','required','array','min:1'],
      'danh_sach_san_pham.*.san_pham_id'     => ['sometimes','required','integer','exists:san_phams,id'],
      'danh_sach_san_pham.*.don_vi_tinh_id'  => ['sometimes','required','integer','exists:don_vi_tinhs,id'],
      'danh_sach_san_pham.*.so_luong'        => ['sometimes','required','numeric','min:1'],

      // ❌ EVENT: loai_gia không còn trong DB, chỉ cho phép FE gửi nếu còn form cũ
      'danh_sach_san_pham.*.loai_gia'        => ['sometimes','nullable','integer', Rule::in([1,2])],

      'danh_sach_san_pham.*.don_gia'         => ['sometimes','nullable','numeric','min:0'],
      'danh_sach_san_pham.*.thanh_tien'      => ['sometimes','nullable','numeric','min:0'],

      // ===== Khác =====
      'ghi_chu'            => ['sometimes','nullable','string','max:255'],
      'images'             => ['sometimes','nullable','array'],
    ];
  }

  /**
   * Get the error messages for the defined validation rules.
   */
  public function messages(): array
  {
    return [
      // Đơn hàng
      'ma_don_hang.prohibited'     => 'Không được phép sửa mã đơn hàng',
      'ngay_tao_don_hang.required' => 'Ngày tạo đơn hàng là bắt buộc',
      'ngay_tao_don_hang.date'     => 'Ngày tạo đơn hàng không hợp lệ',
      'dia_chi_giao_hang.required' => 'Địa chỉ là bắt buộc',
      'dia_chi_giao_hang.string'   => 'Địa chỉ phải là chuỗi',

      // Trạng thái đơn hàng
      'trang_thai_don_hang.integer' => 'Trạng thái đơn hàng phải là số',
      'trang_thai_don_hang.in'      => 'Trạng thái đơn hàng phải là 0, 1, 2 hoặc 3',

      // Thông tin người nhận
      'nguoi_nhan_ten.string'      => 'Tên người nhận phải là chuỗi',
      'nguoi_nhan_ten.max'         => 'Tên người nhận không được vượt quá 191 ký tự',
      'nguoi_nhan_sdt.string'      => 'SĐT người nhận phải là chuỗi',
      'nguoi_nhan_sdt.max'         => 'SĐT người nhận không được vượt quá 20 ký tự',
      'nguoi_nhan_sdt.regex'       => 'SĐT người nhận không hợp lệ (hỗ trợ 0… hoặc +84…)',
      'nguoi_nhan_thoi_gian.date'  => 'Ngày giờ nhận không hợp lệ',

      // Khách hàng
      'loai_khach_hang.required'   => 'Loại khách hàng là bắt buộc',
      'loai_khach_hang.integer'    => 'Loại khách hàng phải là số',
      'loai_khach_hang.in'         => 'Loại khách hàng phải là 0 hoặc 1',
      'khach_hang_id.integer'      => 'Khách hàng phải là số',
      'khach_hang_id.exists'       => 'Khách hàng không tồn tại',
      'ten_khach_hang.string'      => 'Tên khách hàng phải là chuỗi',
      'ten_khach_hang.max'         => 'Tên khách hàng không được vượt quá 255 ký tự',
      'so_dien_thoai.string'       => 'Số điện thoại phải là chuỗi',
      'so_dien_thoai.max'          => 'Số điện thoại không được vượt quá 255 ký tự',

      // Event
      'project_name.max'           => 'Tên dự án / sự kiện không được vượt quá 255 ký tự',
      'event_type.max'             => 'Loại sự kiện không được vượt quá 100 ký tự',
      'event_start.date'           => 'Thời gian bắt đầu sự kiện không hợp lệ',
      'event_end.date'             => 'Thời gian kết thúc sự kiện không hợp lệ',
      'guest_count.integer'        => 'Số lượng khách phải là số nguyên',
      'guest_count.min'            => 'Số lượng khách không được âm',
      'venue_name.max'             => 'Tên địa điểm không được vượt quá 255 ký tự',
      'venue_address.max'          => 'Địa chỉ địa điểm không được vượt quá 255 ký tự',
      'contact_email.email'        => 'Email người liên hệ không hợp lệ',

      // Giảm trừ/Chi phí
      'giam_gia.required'          => 'Giảm giá là bắt buộc',
      'giam_gia.numeric'           => 'Giảm giá phải là số',
      'giam_gia.min'               => 'Giảm giá không được âm',
      'chi_phi.required'           => 'Chi phí là bắt buộc',
      'chi_phi.numeric'            => 'Chi phí phải là số',
      'chi_phi.min'                => 'Chi phí không được âm',
      'giam_gia_thanh_vien.numeric' => 'Giảm giá thành viên phải là số phần trăm',
      'giam_gia_thanh_vien.min'     => 'Giảm giá thành viên không được âm',
      'giam_gia_thanh_vien.max'     => 'Giảm giá thành viên không được lớn hơn 100%',

      // Thanh toán
      'loai_thanh_toan.required'   => 'Loại thanh toán là bắt buộc',
      'loai_thanh_toan.integer'    => 'Loại thanh toán phải là số',
      'loai_thanh_toan.in'         => 'Loại thanh toán phải là 0, 1 hoặc 2',
      'so_tien_da_thanh_toan.required_if' => 'Vui lòng nhập số tiền đã thanh toán khi chọn "Thanh toán một phần"',
      'so_tien_da_thanh_toan.numeric'     => 'Số tiền đã thanh toán phải là số',
      'so_tien_da_thanh_toan.min'         => 'Số tiền đã thanh toán không được âm',

      // Dịch vụ
      'danh_sach_san_pham.required' => 'Danh sách dịch vụ là bắt buộc',
      'danh_sach_san_pham.array'    => 'Danh sách dịch vụ phải là một mảng',
      'danh_sach_san_pham.min'      => 'Báo giá phải có ít nhất 1 dịch vụ',
      'danh_sach_san_pham.*.san_pham_id.required' => 'ID dịch vụ là bắt buộc',
      'danh_sach_san_pham.*.san_pham_id.integer'  => 'ID dịch vụ phải là số',
      'danh_sach_san_pham.*.san_pham_id.exists'   => 'Dịch vụ không tồn tại',
      'danh_sach_san_pham.*.don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
      'danh_sach_san_pham.*.don_vi_tinh_id.integer'  => 'Đơn vị tính phải là số',
      'danh_sach_san_pham.*.don_vi_tinh_id.exists'   => 'Đơn vị tính không tồn tại',
      'danh_sach_san_pham.*.so_luong.required'       => 'Số lượng là bắt buộc',
      'danh_sach_san_pham.*.so_luong.numeric'        => 'Số lượng phải là số',
      'danh_sach_san_pham.*.so_luong.min'            => 'Số lượng phải lớn hơn 0',

      // Thuế
      'tax_mode.integer'           => 'Chế độ thuế phải là số',
      'tax_mode.in'                => 'Chế độ thuế không hợp lệ',
      'vat_rate.required_if'       => 'Vui lòng nhập VAT (%) khi chọn Có thuế',
      'vat_rate.numeric'           => 'VAT (%) phải là số',
      'vat_rate.min'               => 'VAT (%) không được âm',
      'vat_rate.max'               => 'VAT (%) không vượt quá 20%',

      // Khác
      'ghi_chu.string'             => 'Ghi chú phải là chuỗi',
      'ghi_chu.max'                => 'Ghi chú không được vượt quá 255 ký tự',
    ];
  }
}
