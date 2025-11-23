<?php

namespace App\Modules\GoiDichVu\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoiDichVuGroupRequest extends FormRequest
{
    /**
     * Xác định user có quyền gọi request này không.
     * Tạm cho phép, middleware/permission sẽ chặn ở ngoài.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Luật validate cho cập nhật NHÓM DANH MỤC GÓI DỊCH VỤ.
     *
     * Bảng: goi_dich_vu_groups
     */
    public function rules(): array
    {
        // id của record đang update, đọc từ route (vd: /groups/{id})
        $id = $this->route('id') ?? $this->id;

        return [
            // Mã nhóm: chỉ validate nếu có gửi lên (sometimes),
            // unique trừ bản ghi hiện tại
            'ma_nhom' => 'sometimes|nullable|string|max:100|unique:goi_dich_vu_groups,ma_nhom,' . $id,

            // Tên nhóm: nếu gửi lên thì phải đúng kiểu & không rỗng
            'ten_nhom' => 'sometimes|required|string|max:255',

            // Ghi chú: optional
            'ghi_chu' => 'sometimes|nullable|string|max:500',

            // Trạng thái: optional, nếu có thì phải boolean
            'trang_thai' => 'sometimes|nullable|boolean',
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
