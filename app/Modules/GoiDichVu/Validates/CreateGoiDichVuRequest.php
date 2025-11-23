<?php

namespace App\Modules\GoiDichVu\Validates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class CreateGoiDichVuRequest extends FormRequest
{
    /**
     * Quyền gọi request
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Luật validate cho TẠO MỚI gói dịch vụ
     *
     * Bảng chính: goi_dich_vus
     * Bảng con:   goi_dich_vu_items (thông qua field items)
     */
    public function rules(): array
    {
        return [
            // Thuộc nhóm gói dịch vụ nào (tầng 2)
            'category_id' => 'required|integer|exists:goi_dich_vu_categories,id',

            // Mã gói: cho phép rỗng, nếu truyền thì phải unique
            'ma_goi' => 'nullable|string|max:100|unique:goi_dich_vus,ma_goi',

            // Tên gói hiển thị
            'ten_goi' => 'required|string|max:255',

            // Mô tả ngắn / chi tiết: optional
            'mo_ta_ngan'     => 'nullable|string|max:500',
            'mo_ta_chi_tiet' => 'nullable|string',

            // Giá niêm yết & khuyến mãi
            'gia_niem_yet'   => 'required|numeric|min:0',
            'gia_khuyen_mai' => 'nullable|numeric|min:0',

       // 0 = gói trọn gói (1 dòng), 1 = gói thành phần (nổ từng dịch vụ)
'package_mode' => ['sometimes', 'nullable', 'integer', Rule::in([0, 1])],



            // Trạng thái: optional, nếu không gửi thì Service sẽ default = 1
            'trang_thai' => 'nullable|boolean',

            // ====== CHI TIẾT GÓI (items) ======
            // Có thể để trống (gói không bắt buộc phải có item ngay từ đầu),
            // nhưng nếu FE đã gửi items thì phải là array & từng phần tử phải hợp lệ.
            'items'                          => 'sometimes|array',
            'items.*.san_pham_id'           => 'required_with:items|integer|exists:san_phams,id',
            'items.*.so_luong'              => 'required_with:items|numeric|min:0',
            'items.*.don_gia'               => 'nullable|numeric|min:0',
            'items.*.thanh_tien'            => 'nullable|numeric|min:0',
            'items.*.ghi_chu'               => 'nullable|string|max:500',
            'items.*.thu_tu'                => 'nullable|integer|min:0',
        ];
    }

    /**
     * Thông điệp lỗi
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Nhóm gói dịch vụ là bắt buộc',
            'category_id.integer'  => 'Nhóm gói dịch vụ không hợp lệ',
            'category_id.exists'   => 'Nhóm gói dịch vụ không tồn tại',

            'ma_goi.max'      => 'Mã gói không được vượt quá 100 ký tự',
            'ma_goi.unique'   => 'Mã gói đã tồn tại',
            'package_mode.integer' => 'Loại gói dịch vụ phải là số.',
            'package_mode.in'      => 'Loại gói dịch vụ không hợp lệ.',

            'ten_goi.required' => 'Tên gói dịch vụ là bắt buộc',
            'ten_goi.max'      => 'Tên gói dịch vụ không được vượt quá 255 ký tự',

            'mo_ta_ngan.max' => 'Mô tả ngắn không được vượt quá 500 ký tự',

            'gia_niem_yet.required' => 'Giá niêm yết là bắt buộc',
            'gia_niem_yet.numeric'  => 'Giá niêm yết phải là số',
            'gia_niem_yet.min'      => 'Giá niêm yết phải lớn hơn hoặc bằng 0',

            'gia_khuyen_mai.numeric' => 'Giá khuyến mãi phải là số',
            'gia_khuyen_mai.min'     => 'Giá khuyến mãi phải lớn hơn hoặc bằng 0',

            'trang_thai.boolean' => 'Trạng thái phải là kiểu đúng/sai (boolean)',

            // items
            'items.array' => 'Danh sách chi tiết gói phải là mảng',

            'items.*.san_pham_id.required_with' => 'Chi tiết gói: chưa chọn dịch vụ / sản phẩm',
            'items.*.san_pham_id.integer'       => 'Chi tiết gói: ID dịch vụ / sản phẩm không hợp lệ',
            'items.*.san_pham_id.exists'        => 'Chi tiết gói: dịch vụ / sản phẩm không tồn tại',

            'items.*.so_luong.required_with' => 'Chi tiết gói: số lượng là bắt buộc',
            'items.*.so_luong.numeric'       => 'Chi tiết gói: số lượng phải là số',
            'items.*.so_luong.min'           => 'Chi tiết gói: số lượng phải ≥ 0',

            'items.*.don_gia.numeric' => 'Chi tiết gói: đơn giá phải là số',
            'items.*.don_gia.min'     => 'Chi tiết gói: đơn giá phải ≥ 0',

            'items.*.thanh_tien.numeric' => 'Chi tiết gói: thành tiền phải là số',
            'items.*.thanh_tien.min'     => 'Chi tiết gói: thành tiền phải ≥ 0',

            'items.*.ghi_chu.max' => 'Chi tiết gói: ghi chú không được vượt quá 500 ký tự',

            'items.*.thu_tu.integer' => 'Chi tiết gói: thứ tự hiển thị phải là số nguyên',
            'items.*.thu_tu.min'     => 'Chi tiết gói: thứ tự hiển thị phải ≥ 0',
        ];
    }
}
