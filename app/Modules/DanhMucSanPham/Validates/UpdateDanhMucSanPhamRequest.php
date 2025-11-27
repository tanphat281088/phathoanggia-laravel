<?php

namespace App\Modules\DanhMucSanPham\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDanhMucSanPhamRequest extends FormRequest
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
      // Thêm các quy tắc validation cho cập nhật DanhMucSanPham ở đây
      'ma_danh_muc' => 'sometimes|required|string|max:255|unique:danh_muc_san_phams,ma_danh_muc,' . $this->id,
      'ten_danh_muc' => 'sometimes|required|string|max:255',
'group_code' => 'sometimes|nullable|string|in: NS,CSVC,TIEC,TD,CPK,CPQL,CPFT,CPFG,GG,NHAN_SU,CO_SO_VAT_CHAT,THUE_DIA_DIEM,CHI_PHI_KHAC,CHI_PHI_QUAN_LY,CHI_PHI_PHAT_SINH_TANG,CHI_PHI_PHAT_SINH_GIAM,GIAM_GIA',


        // 🔹 Danh mục cha (Tầng trên)
        'parent_id'     => 'sometimes|nullable|integer|exists:danh_muc_san_phams,id',
      'ghi_chu' => 'nullable|string',
      'trang_thai' => 'sometimes|required|boolean',
      'image' => 'nullable|string',
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
      'ma_danh_muc.required' => 'Mã danh mục là bắt buộc',
      'ma_danh_muc.max' => 'Mã danh mục không được vượt quá 255 ký tự',
      'ma_danh_muc.unique' => 'Mã danh mục đã tồn tại',
      'ten_danh_muc.required' => 'Tên danh mục là bắt buộc',
      'ten_danh_muc.max' => 'Tên danh mục không được vượt quá 255 ký tự',
      'trang_thai.required' => 'Trạng thái là bắt buộc',
    ];
  }
}