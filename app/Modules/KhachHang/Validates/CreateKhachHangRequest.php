<?php

namespace App\Modules\KhachHang\Validates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateKhachHangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Danh sách kênh hợp lệ từ config
        $kenhOptions = (array) config('kenh_lien_he.options', []);

        return [
            /**
             * 🔹 Loại khách chuyên ngành Sự kiện:
             * 0 = Event client   (doanh nghiệp / brand)
             * 1 = Wedding client (tiệc cưới)
             * 2 = Agency client  (agency / khách sỉ)
             */
            'customer_type' => [
                'required',
                'integer',
                Rule::in([0, 1, 2]),
            ],

            /**
             * 🔹 Level quan hệ: Khách hệ thống / vãng lai
             * - Nếu FE không gửi thì mặc định bên Model/DB sẽ là 1 (hệ thống).
             */
            'is_system_customer' => [
                'sometimes',
                'boolean',
            ],

            /**
             * 🔹 Thông tin cơ bản
             */
            'ten_khach_hang' => 'required|string|max:255',

            // EMAIL: KHÔNG BẮT BUỘC, nhưng nếu có thì unique
            'email'          => 'nullable|email|max:255|unique:khach_hangs,email',

            'so_dien_thoai'  => 'required|string|max:255|unique:khach_hangs,so_dien_thoai',

            // Địa chỉ liên hệ: tuỳ chọn
            'dia_chi'        => 'nullable|string|max:255',

            'ghi_chu'        => 'nullable|string|max:255',

            /**
             * 🔹 Kênh liên hệ: BẮT BUỘC + phải nằm trong danh sách config
             */
            'kenh_lien_he'   => [
                'required',
                'string',
                'max:191',
                Rule::in($kenhOptions),
            ],

            // Mô tả chi tiết nguồn (giới thiệu bởi ai, qua kênh nào…)
            'source_detail'  => 'nullable|string|max:255',

            /**
             * 🔹 Nhóm B2B (Event / Agency)
             * -> Không bắt buộc trong rules BE (để linh hoạt),
             *    UI có thể bắt buộc riêng cho loại Event/Agency nếu cần.
             */
            'company_name' => 'nullable|string|max:255',
            'tax_code'     => 'nullable|string|max:50',
            'department'   => 'nullable|string|max:191',
            'position'     => 'nullable|string|max:191',
            'industry'     => 'nullable|string|max:191',

            /**
             * 🔹 Nhóm Wedding (chỉ dùng khi customer_type = 1, nhưng để nullable cho linh hoạt)
             */
            'bride_name'     => 'nullable|string|max:191',
            'groom_name'     => 'nullable|string|max:191',
            'wedding_date'   => 'nullable|date',
            'wedding_venue'  => 'nullable|string|max:255',
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
