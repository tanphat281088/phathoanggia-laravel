<?php

namespace App\Modules\SanPham\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSanPhamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Thêm các quy tắc validation cho cập nhật SanPham ở đây
            'ma_san_pham'        => 'sometimes|required|string|max:255|unique:san_phams,ma_san_pham,' . $this->id,
            'ten_san_pham'       => 'sometimes|required|string|max:255',
            'image'              => 'nullable|string',

            // ✅ CHỈNH: cho phép nullable + kiểm tra exists nếu có giá trị
            'danh_muc_id'        => 'sometimes|nullable|integer|exists:danh_muc_san_phams,id',
            'don_vi_tinh_id'     => 'sometimes|required|array|min:1',
            'nha_cung_cap_id'    => 'sometimes|nullable|array',

            // Giá
            'gia_nhap_mac_dinh'  => 'sometimes|required|numeric',
            'gia_dat_truoc_3n'   => 'sometimes|nullable|numeric|min:0', // ✅ MỚI

            // Tỷ lệ & cảnh báo
            'ty_le_chiet_khau'   => 'sometimes|nullable|numeric|min:0|max:100',
            'muc_loi_nhuan'      => 'sometimes|nullable|numeric|min:0|max:100',
            'so_luong_canh_bao'  => 'sometimes|required|numeric|min:0',

            'ghi_chu'            => 'nullable|string',
            'trang_thai'         => 'sometimes|required|integer|in:0,1',

            // ✅ Thêm GOI_DICH_VU để có thể UPDATE gói dịch vụ sự kiện
            'loai_san_pham'      => 'sometimes|required|string|in:SP_NHA_CUNG_CAP,SP_SAN_XUAT,NGUYEN_LIEU,GOI_DICH_VU',

            // ✅ MỚI: danh sách item bên trong GÓI DỊCH VỤ (event_package_items)
            // - Với UPDATE chỉ validate nếu FE có gửi lên (sometimes)
            'package_items'                      => 'sometimes|array',
            'package_items.*.item_id'            => 'required_with:package_items|integer',
            'package_items.*.so_luong'           => 'required_with:package_items|numeric|min:0',
            'package_items.*.don_gia'            => 'nullable|numeric|min:0',
            'package_items.*.thanh_tien'         => 'nullable|numeric|min:0',
            'package_items.*.don_vi_tinh'        => 'nullable|string|max:50',
            'package_items.*.ghi_chu'            => 'nullable|string',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ma_san_pham.required' => 'Mã sản phẩm là bắt buộc',
            'ma_san_pham.max' => 'Mã sản phẩm không được vượt quá 255 ký tự',
            'ma_san_pham.unique' => 'Mã sản phẩm đã tồn tại',
            'ten_san_pham.required' => 'Tên sản phẩm là bắt buộc',
            'ten_san_pham.max' => 'Tên sản phẩm không được vượt quá 255 ký tự',

            'danh_muc_id.required' => 'Danh mục sản phẩm là bắt buộc',
            'danh_muc_id.integer' => 'Danh mục sản phẩm phải là số nguyên',

            'don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
            'don_vi_tinh_id.array' => 'Đơn vị tính phải là mảng',
            'don_vi_tinh_id.min' => 'Đơn vị tính phải có ít nhất 1 phần tử',

            'nha_cung_cap_id.array' => 'Nhà cung cấp phải là mảng',

            'gia_nhap_mac_dinh.required' => 'Giá nhập mặc định là bắt buộc',
            'gia_nhap_mac_dinh.numeric' => 'Giá nhập mặc định phải là số',

            // ✅ MỚI:
            'gia_dat_truoc_3n.numeric' => 'Giá đặt trước 3 ngày phải là số',
            'gia_dat_truoc_3n.min'     => 'Giá đặt trước 3 ngày phải ≥ 0',

            'ty_le_chiet_khau.required' => 'Tỷ lệ chiết khấu là bắt buộc',
            'ty_le_chiet_khau.numeric' => 'Tỷ lệ chiết khấu phải là số',
            'ty_le_chiet_khau.min' => 'Tỷ lệ chiết khấu phải lớn hơn 0',
            'ty_le_chiet_khau.max' => 'Tỷ lệ chiết khấu phải nhỏ hơn 100',

            'muc_loi_nhuan.required' => 'Mức lợi nhuận là bắt buộc',
            'muc_loi_nhuan.numeric' => 'Mức lợi nhuận phải là số',
            'muc_loi_nhuan.min' => 'Mức lợi nhuận phải lớn hơn 0',
            'muc_loi_nhuan.max' => 'Mức lợi nhuận phải nhỏ hơn 100',

            'so_luong_canh_bao.required' => 'Số lượng cảnh báo là bắt buộc',
            'so_luong_canh_bao.numeric' => 'Số lượng cảnh báo phải là số',
            'so_luong_canh_bao.min' => 'Số lượng cảnh báo phải lớn hơn 0',

            'trang_thai.required' => 'Trạng thái là bắt buộc',
            'trang_thai.integer' => 'Trạng thái phải là số nguyên',
            'trang_thai.in' => 'Trạng thái phải là 0 hoặc 1',

            'loai_san_pham.required' => 'Loại sản phẩm là bắt buộc',
            'loai_san_pham.string' => 'Loại sản phẩm phải là chuỗi',
            'loai_san_pham.in' => 'Loại sản phẩm phải là SP_NHA_CUNG_CAP, SP_SAN_XUAT, NGUYEN_LIEU hoặc GOI_DICH_VU',

            // package_items
            'package_items.array' => 'Danh sách thiết bị/dịch vụ trong gói phải là mảng.',
            'package_items.*.item_id.required_with' => 'Thiết bị/dịch vụ trong gói phải được chọn.',
            'package_items.*.item_id.integer' => 'ID thiết bị/dịch vụ trong gói phải là số nguyên.',
            'package_items.*.so_luong.required_with' => 'Số lượng trong gói là bắt buộc.',
            'package_items.*.so_luong.numeric' => 'Số lượng trong gói phải là số.',
            'package_items.*.so_luong.min' => 'Số lượng trong gói phải ≥ 0.',
            'package_items.*.don_gia.numeric' => 'Đơn giá trong gói phải là số.',
            'package_items.*.don_gia.min' => 'Đơn giá trong gói phải ≥ 0.',
            'package_items.*.thanh_tien.numeric' => 'Thành tiền trong gói phải là số.',
            'package_items.*.thanh_tien.min' => 'Thành tiền trong gói phải ≥ 0.',
            'package_items.*.don_vi_tinh.max' => 'Đơn vị tính trong gói không được vượt quá 50 ký tự.',
        ];
    }
}
