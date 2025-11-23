<?php

namespace App\Modules\GoiDichVu\Validates;

use Illuminate\Foundation\Http\FormRequest;

class CreateGoiDichVuGroupRequest extends FormRequest
{
    /**
     * Xác định user có quyền gọi request này không.
     * Tạm thời cho phép, middleware/permission sẽ chặn ở ngoài.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Luật validate cho tạo mới NHÓM DANH MỤC GÓI DỊCH VỤ.
     *
     * Bảng: goi_dich_vu_groups
     */
    public function rules(): array
    {
        return [
            // Mã nhóm: cho phép rỗng, nếu truyền thì phải unique
            'ma_nhom' => 'nullable|string|max:100|unique:goi_dich_vu_groups,ma_nhom',

            // Tên nhóm: bắt buộc
            'ten_nhom' => 'required|string|max:255',

            // Ghi chú: optional
            'ghi_chu' => 'nullable|string|max:500',

            // Trạng thái: optional, nếu có thì phải boolean
            'trang_thai' => 'nullable|boolean',
        ];
    }

    /**
     * Thông điệp lỗi.
     */
    public function messages(): array
    {
        return [
            'ma_nhom.max'      => 'Mã nhóm không được vượt quá 100 ký tự',
            'ma_nhom.unique'   => 'Mã nhóm đã tồn tại',

            'ten_nhom.required' => 'Tên nhóm là bắt buộc',
            'ten_nhom.max'      => 'Tên nhóm không được vượt quá 255 ký tự',

            'ghi_chu.max'       => 'Ghi chú không được vượt quá 500 ký tự',

            'trang_thai.boolean' => 'Trạng thái phải là kiểu đúng/sai (boolean)',
        ];
    }
}
