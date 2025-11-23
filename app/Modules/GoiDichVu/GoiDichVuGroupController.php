<?php

namespace App\Modules\GoiDichVu;

use App\Class\CustomResponse;
use App\Class\Helper;
use App\Http\Controllers\Controller;
use App\Modules\GoiDichVu\Validates\CreateGoiDichVuGroupRequest;
use App\Modules\GoiDichVu\Validates\UpdateGoiDichVuGroupRequest;
use Illuminate\Http\Request;

class GoiDichVuGroupController extends Controller
{
    protected GoiDichVuGroupService $service;

    public function __construct(GoiDichVuGroupService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/goi-dich-vu/groups
     * - Lấy danh sách nhóm danh mục gói dịch vụ (tầng 1)
     * - Hỗ trợ filter + phân trang theo chuẩn FilterWithPagination
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
     * POST /api/goi-dich-vu/groups
     * - Tạo mới 1 nhóm danh mục gói dịch vụ
     */
    public function store(CreateGoiDichVuGroupRequest $request)
    {
        $result = $this->service->create($request->validated());

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Tạo nhóm danh mục gói dịch vụ thành công');
    }

    /**
     * GET /api/goi-dich-vu/groups/{id}
     * - Lấy chi tiết 1 nhóm danh mục gói dịch vụ
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
     * PUT/PATCH /api/goi-dich-vu/groups/{id}
     * - Cập nhật thông tin nhóm danh mục gói dịch vụ
     */
    public function update(UpdateGoiDichVuGroupRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Cập nhật nhóm danh mục gói dịch vụ thành công');
    }

    /**
     * DELETE /api/goi-dich-vu/groups/{id}
     * - Xoá nhóm danh mục gói dịch vụ
     */
    public function destroy(int $id)
    {
        $result = $this->service->delete($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success([], 'Xoá nhóm danh mục gói dịch vụ thành công');
    }

    /**
     * GET /api/goi-dich-vu/groups/options
     * - Lấy danh sách nhóm dạng options (value=id, label=ten_nhom) cho combobox
     */
    public function getOptions()
    {
        $result = $this->service->getOptions();

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result);
    }
}
