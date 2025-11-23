<?php

namespace App\Modules\GoiDichVu;

use App\Class\CustomResponse;
use App\Class\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GoiDichVuCategoryController extends Controller
{
    protected GoiDichVuCategoryService $service;

    public function __construct(GoiDichVuCategoryService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/goi-dich-vu/categories
     * - Lấy danh sách NHÓM GÓI DỊCH VỤ (tầng 2)
     * - Có thể filter theo group_id, tên, trạng thái...
     * - Phân trang theo chuẩn FilterWithPagination
     */
    public function index(Request $request)
    {
        $params = $request->all();

        // Chuẩn hoá & validate param filter/sort/page
        $params = Helper::validateFilterParams($params);

        $result = $this->service->getAll($params);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success([
            'collection' => $result['data'],
            'total'      => $result['total'],
            'pagination' => $result['pagination'] ?? null,
        ]);
    }

    /**
     * POST /api/goi-dich-vu/categories
     * - Tạo mới NHÓM GÓI DỊCH VỤ
     *
     * Lưu ý:
     *  - Ở đây tạm dùng validate trực tiếp từ Request->validate,
     *    nếu bạn muốn tách riêng FormRequest (CreateGoiDichVuCategoryRequest)
     *    thì mình sẽ tạo thêm file sau.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_id'     => 'required|integer|exists:goi_dich_vu_groups,id',
            'ma_nhom_goi'  => 'nullable|string|max:100|unique:goi_dich_vu_categories,ma_nhom_goi',
            'ten_nhom_goi' => 'required|string|max:255',
            'ghi_chu'      => 'nullable|string|max:500',
            'trang_thai'   => 'nullable|boolean',
        ], [
            'group_id.required' => 'Nhóm danh mục gói dịch vụ là bắt buộc',
            'group_id.integer'  => 'Nhóm danh mục gói dịch vụ không hợp lệ',
            'group_id.exists'   => 'Nhóm danh mục gói dịch vụ không tồn tại',

            'ma_nhom_goi.max'    => 'Mã nhóm gói không được vượt quá 100 ký tự',
            'ma_nhom_goi.unique' => 'Mã nhóm gói đã tồn tại',

            'ten_nhom_goi.required' => 'Tên nhóm gói dịch vụ là bắt buộc',
            'ten_nhom_goi.max'      => 'Tên nhóm gói dịch vụ không được vượt quá 255 ký tự',

            'ghi_chu.max' => 'Ghi chú không được vượt quá 500 ký tự',

            'trang_thai.boolean' => 'Trạng thái phải là kiểu đúng/sai (boolean)',
        ]);

        $result = $this->service->create($validated);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Tạo nhóm gói dịch vụ thành công');
    }

    /**
     * GET /api/goi-dich-vu/categories/{id}
     * - Lấy chi tiết 1 nhóm gói dịch vụ
     */
    public function show(int $id)
    {
        $result = $this->service->getById($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result);
    }

    /**
     * PUT/PATCH /api/goi-dich-vu/categories/{id}
     * - Cập nhật nhóm gói dịch vụ
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'group_id'     => 'sometimes|required|integer|exists:goi_dich_vu_groups,id',
            'ma_nhom_goi'  => 'sometimes|nullable|string|max:100|unique:goi_dich_vu_categories,ma_nhom_goi,' . $id,
            'ten_nhom_goi' => 'sometimes|required|string|max:255',
            'ghi_chu'      => 'sometimes|nullable|string|max:500',
            'trang_thai'   => 'sometimes|nullable|boolean',
        ], [
            'group_id.required' => 'Nhóm danh mục gói dịch vụ là bắt buộc',
            'group_id.integer'  => 'Nhóm danh mục gói dịch vụ không hợp lệ',
            'group_id.exists'   => 'Nhóm danh mục gói dịch vụ không tồn tại',

            'ma_nhom_goi.max'    => 'Mã nhóm gói không được vượt quá 100 ký tự',
            'ma_nhom_goi.unique' => 'Mã nhóm gói đã tồn tại',

            'ten_nhom_goi.required' => 'Tên nhóm gói dịch vụ là bắt buộc',
            'ten_nhom_goi.max'      => 'Tên nhóm gói dịch vụ không được vượt quá 255 ký tự',

            'ghi_chu.max' => 'Ghi chú không được vượt quá 500 ký tự',

            'trang_thai.boolean' => 'Trạng thái phải là kiểu đúng/sai (boolean)',
        ]);

        $result = $this->service->update($id, $validated);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Cập nhật nhóm gói dịch vụ thành công');
    }

    /**
     * DELETE /api/goi-dich-vu/categories/{id}
     * - Xoá nhóm gói dịch vụ
     */
    public function destroy(int $id)
    {
        $result = $this->service->delete($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success([], 'Xoá nhóm gói dịch vụ thành công');
    }

    /**
     * GET /api/goi-dich-vu/categories/options
     * - Lấy danh sách nhóm gói dịch vụ dạng options cho combobox
     *   + Có thể filter theo group_id (nhóm danh mục gói)
     */
    public function getOptions(Request $request)
    {
        $params = $request->only(['group_id']);

        $result = $this->service->getOptions($params);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result);
    }
}
