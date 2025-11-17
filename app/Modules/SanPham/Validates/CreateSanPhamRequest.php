<?php

namespace App\Modules\SanPham\Validates;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\DonViTinh;
use App\Models\NhaCungCap;
use Illuminate\Validation\Validator;

class CreateSanPhamRequest extends FormRequest
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
            // 🔹 Mã chi tiết dịch vụ / thiết bị: CHO PHÉP RỖNG, Service sẽ tự sinh nếu không truyền
            'ma_san_pham'        => 'nullable|string|max:255|unique:san_phams,ma_san_pham',

            // 🔹 Tên chi tiết dịch vụ / thiết bị (hoặc tên gói nếu là gói)
            'ten_san_pham'       => 'required|string|max:255',

            'image'              => 'nullable|string',

            // 🔹 Danh mục dịch vụ: KHÔNG BẮT BUỘC (chi tiết thiết bị có thể thuộc nhiều gói)
            'danh_muc_id'        => 'nullable|integer|exists:danh_muc_san_phams,id',

            'don_vi_tinh_id'     => 'required|array',
            'nha_cung_cap_id'    => 'nullable|array',

            // Giá (giá đơn, hoặc giá gói nếu là gói)
            'gia_nhap_mac_dinh'  => 'required|numeric',
            'gia_dat_truoc_3n'   => 'nullable|numeric|min:0',

            // Tỷ lệ & cảnh báo: KHÔNG BẮT BUỘC nữa
            'ty_le_chiet_khau'   => 'nullable|numeric|min:0|max:100',
            'muc_loi_nhuan'      => 'nullable|numeric|min:0|max:100',
            'so_luong_canh_bao'  => 'nullable|numeric|min:0',

            'ghi_chu'            => 'nullable|string',
            'trang_thai'         => 'required|integer|in:0,1',

            // Loại “sản phẩm” (dịch vụ/thiết bị/gói) – giữ các code hiện có + GOI_DICH_VU
            'loai_san_pham'      => 'required|string|in:SP_NHA_CUNG_CAP,SP_SAN_XUAT,NGUYEN_LIEU,GOI_DICH_VU',
                    'is_package'        => 'sometimes|boolean',


            // ✅ Danh sách item bên trong GÓI DỊCH VỤ (event_package_items)
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
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            // ✅ Validate đơn vị tính
            $donViTinhId = $this->don_vi_tinh_id;
            if (!empty($donViTinhId)) {
                $donViTinhIds = array_map('intval', $donViTinhId);
                $existingDonViTinhIds = DonViTinh::whereIn('id', $donViTinhIds)->pluck('id')->toArray();
                $existingDonViTinhIds = array_map('intval', $existingDonViTinhIds);
                $missingDonViTinhIds = array_diff($donViTinhIds, $existingDonViTinhIds);

                if (!empty($missingDonViTinhIds)) {
                    $validator->errors()->add('don_vi_tinh_id', 'Một số id đơn vị tính không tồn tại: ' . implode(', ', $missingDonViTinhIds));
                }
            }

            // ✅ Validate nhà cung cấp (chỉ khi loại = SP_NHA_CUNG_CAP hoặc NGUYEN_LIEU)
            $loaiSanPham = $this->loai_san_pham;
            $nhaCungCapId = $this->nha_cung_cap_id;
            if (in_array($loaiSanPham, ['SP_NHA_CUNG_CAP', 'NGUYEN_LIEU']) && !empty($nhaCungCapId)) {
                $nhaCungCapIds = array_map('intval', $nhaCungCapId);
                $existingNhaCungCapIds = NhaCungCap::whereIn('id', $nhaCungCapIds)->pluck('id')->toArray();
                $existingNhaCungCapIds = array_map('intval', $existingNhaCungCapIds);
                $missingNhaCungCapIds = array_diff($nhaCungCapIds, $existingNhaCungCapIds);

                if (!empty($missingNhaCungCapIds)) {
                    $validator->errors()->add('nha_cung_cap_id', 'Một số id nhà cung cấp không tồn tại: ' . implode(', ', $missingNhaCungCapIds));
                }
            }

            // ✅ Nếu là GÓI DỊCH VỤ thì phải có ít nhất 1 package_item
            if ($loaiSanPham === 'GOI_DICH_VU') {
                $packageItems = $this->input('package_items');

                if (empty($packageItems) || !is_array($packageItems)) {
                    $validator->errors()->add('package_items', 'Gói dịch vụ phải có ít nhất 1 dòng thiết bị/dịch vụ bên trong.');
                } else {
                    foreach ($packageItems as $idx => $row) {
                        $itemId   = $row['item_id']   ?? null;
                        $soLuong  = $row['so_luong']  ?? null;

                        if (empty($itemId)) {
                            $validator->errors()->add(
                                "package_items.$idx.item_id",
                                'Thiết bị/dịch vụ ở dòng ' . ($idx + 1) . ' chưa được chọn.'
                            );
                        }

                        if ($soLuong === null || $soLuong === '' || (float)$soLuong < 0) {
                            $validator->errors()->add(
                                "package_items.$idx.so_luong",
                                'Số lượng ở dòng ' . ($idx + 1) . ' phải ≥ 0.'
                            );
                        }
                    }
                }
            }
        });
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // ma_san_pham: KHÔNG required nữa
            'ma_san_pham.max' => 'Mã chi tiết dịch vụ/thiết bị không được vượt quá 255 ký tự',
            'ma_san_pham.unique' => 'Mã chi tiết dịch vụ/thiết bị đã tồn tại',

            'ten_san_pham.required' => 'Tên chi tiết dịch vụ/thiết bị là bắt buộc',
            'ten_san_pham.max' => 'Tên chi tiết dịch vụ/thiết bị không được vượt quá 255 ký tự',

            'danh_muc_id.integer' => 'Danh mục dịch vụ phải là số nguyên',
            'danh_muc_id.exists' => 'Danh mục dịch vụ không tồn tại',

            'don_vi_tinh_id.required' => 'Đơn vị tính là bắt buộc',
            'don_vi_tinh_id.array' => 'Đơn vị tính phải là mảng',
            'don_vi_tinh_id.min' => 'Đơn vị tính phải có ít nhất 1 phần tử',

            'nha_cung_cap_id.array' => 'Nhà cung cấp phải là mảng',

            'gia_nhap_mac_dinh.required' => 'Giá dịch vụ là bắt buộc',
            'gia_nhap_mac_dinh.numeric' => 'Giá dịch vụ phải là số',

            'gia_dat_truoc_3n.numeric' => 'Giá đặt trước 3 ngày phải là số',
            'gia_dat_truoc_3n.min'     => 'Giá đặt trước 3 ngày phải ≥ 0',

            'ty_le_chiet_khau.numeric' => 'Tỷ lệ chiết khấu phải là số',
            'ty_le_chiet_khau.min' => 'Tỷ lệ chiết khấu phải ≥ 0',
            'ty_le_chiet_khau.max' => 'Tỷ lệ chiết khấu phải ≤ 100',

            'muc_loi_nhuan.numeric' => 'Mức lợi nhuận phải là số',
            'muc_loi_nhuan.min' => 'Mức lợi nhuận phải ≥ 0',
            'muc_loi_nhuan.max' => 'Mức lợi nhuận phải ≤ 100',

            'so_luong_canh_bao.numeric' => 'Số lượng cảnh báo phải là số',
            'so_luong_canh_bao.min' => 'Số lượng cảnh báo phải ≥ 0',

            'trang_thai.required' => 'Trạng thái là bắt buộc',
            'trang_thai.integer' => 'Trạng thái phải là số nguyên',
            'trang_thai.in' => 'Trạng thái phải là 0 hoặc 1',

            'loai_san_pham.required' => 'Loại dịch vụ là bắt buộc',
            'loai_san_pham.string' => 'Loại dịch vụ phải là chuỗi',
            'loai_san_pham.in' => 'Loại dịch vụ không hợp lệ',

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
