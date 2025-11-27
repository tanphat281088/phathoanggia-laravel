<?php

namespace App\Modules\DanhMucSanPham\Validates;

use Illuminate\Foundation\Http\FormRequest;

class CreateDanhMucSanPhamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Mã danh mục: cho phép rỗng, BE tự sinh nếu không có
            'ma_danh_muc' => 'nullable|string|max:255|unique:danh_muc_san_phams,ma_danh_muc',

            'ten_danh_muc' => 'required|string|max:255',

            /**
             * Nhóm dịch vụ:
             * - Cho phép cả code NGẮN:  NS, CSVC, TIEC, TD, CPK
             * - Và code DÀI cũ: NHAN_SU, CO_SO_VAT_CHAT, TIEC, THUE_DIA_DIEM, CHI_PHI_KHAC
             */
           'group_code' => 'nullable|string|in:NS,CSVC,TIEC,TD,CPK,CPQL,CPFT,CPFG,GG,NHAN_SU,CO_SO_VAT_CHAT,THUE_DIA_DIEM,CHI_PHI_KHAC,CHI_PHI_QUAN_LY,CHI_PHI_PHAT_SINH_TANG,CHI_PHI_PHAT_SINH_GIAM,GIAM_GIA',


            'parent_id' => 'nullable|integer|exists:danh_muc_san_phams,id',

            'ghi_chu' => 'nullable|string',
            'trang_thai' => 'required|boolean',
            'image' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'ma_danh_muc.max' => 'Mã danh mục không được vượt quá 255 ký tự',
            'ma_danh_muc.unique' => 'Mã danh mục đã tồn tại',

            'ten_danh_muc.required' => 'Tên danh mục là bắt buộc',
            'ten_danh_muc.max' => 'Tên danh mục không được vượt quá 255 ký tự',

            'group_code.in' => 'Nhóm dịch vụ không hợp lệ',

            'parent_id.integer' => 'Danh mục cha phải là số nguyên',
            'parent_id.exists'  => 'Danh mục cha không tồn tại',

            'trang_thai.required' => 'Trạng thái là bắt buộc',
        ];
    }
}
