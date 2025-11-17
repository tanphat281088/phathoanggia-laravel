<?php

namespace App\Modules\KhachHang\Validates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKhachHangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Danh sách kênh hợp lệ từ config
        $kenhOptions = (array) config('kenh_lien_he.options', []);

        // id hiện tại (dùng cho rule unique)
        $id = $this->id ?? $this->route('id');

        return [
            /**
             * 🔹 Loại khách chuyên ngành Sự kiện
             * Cho phép đổi loại: Event <-> Wedding <-> Agency
             */
            'customer_type' => [
                'sometimes',
                'required',
                'integer',
                Rule::in([0, 1, 2]),
            ],

            /**
             * 🔹 Level quan hệ: Hệ thống / Vãng lai
             */
            'is_system_customer' => [
                'sometimes',
                'boolean',
            ],

            /**
             * 🔹 Thông tin cơ bản
             */
            'ten_khach_hang' => 'sometimes|required|string|max:255',

            // EMAIL: KHÔNG BẮT BUỘC; nếu có thì unique trừ bản ghi hiện tại
            'email'          => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('khach_hangs', 'email')->ignore($id),
            ],

            'so_dien_thoai'  => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('khach_hangs', 'so_dien_thoai')->ignore($id),
            ],

            'dia_chi'        => 'sometimes|nullable|string|max:255',

            'ghi_chu'        => 'sometimes|nullable|string|max:255',

            /**
             * 🔹 Kênh liên hệ: vẫn yêu cầu có nếu FE gửi trường này
             */
            'kenh_lien_he'   => [
                'sometimes',
                'required',
                'string',
                'max:191',
                Rule::in($kenhOptions),
            ],

            'source_detail'  => 'sometimes|nullable|string|max:255',

            /**
             * 🔹 Nhóm B2B (Event / Agency)
             */
            'company_name' => 'sometimes|nullable|string|max:255',
            'tax_code'     => 'sometimes|nullable|string|max:50',
            'department'   => 'sometimes|nullable|string|max:191',
            'position'     => 'sometimes|nullable|string|max:191',
            'industry'     => 'sometimes|nullable|string|max:191',

            /**
             * 🔹 Nhóm Wedding
             */
            'bride_name'     => 'sometimes|nullable|string|max:191',
            'groom_name'     => 'sometimes|nullable|string|max:191',
            'wedding_date'   => 'sometimes|nullable|date',
            'wedding_venue'  => 'sometimes|nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            // customer_type
            'customer_type.required' => 'Loại khách hàng là bắt buộc (Event / Wedding / Agency).',
            'customer_type.integer'  => 'Loại khách hàng không hợp lệ.',
            'customer_type.in'       => 'Loại khách hàng không hợp lệ (0=Event,1=Wedding,2=Agency).',

            // ten_khach_hang
            'ten_khach_hang.required' => 'Tên khách hàng là bắt buộc.',
            'ten_khach_hang.max'      => 'Tên khách hàng không được vượt quá 255 ký tự.',

            // email
            'email.email'   => 'Email không hợp lệ.',
            'email.max'     => 'Email không được vượt quá 255 ký tự.',
            'email.unique'  => 'Email đã tồn tại trong hệ thống.',

            // so_dien_thoai
            'so_dien_thoai.required' => 'Số điện thoại là bắt buộc.',
            'so_dien_thoai.max'      => 'Số điện thoại không được vượt quá 255 ký tự.',
            'so_dien_thoai.unique'   => 'Số điện thoại đã tồn tại trong hệ thống.',

            // kenh_lien_he
            'kenh_lien_he.required' => 'Vui lòng chọn Kênh liên hệ.',
            'kenh_lien_he.max'      => 'Kênh liên hệ không được vượt quá 191 ký tự.',
            'kenh_lien_he.in'       => 'Kênh liên hệ không hợp lệ (phải chọn trong danh sách).',

            // wedding_date
            'wedding_date.date'     => 'Ngày cưới không hợp lệ.',
        ];
    }
}
