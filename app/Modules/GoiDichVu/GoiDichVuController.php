<?php

namespace App\Modules\GoiDichVu;

use App\Class\CustomResponse;
use App\Class\Helper;
use App\Http\Controllers\Controller;
use App\Modules\GoiDichVu\Validates\CreateGoiDichVuRequest;
use App\Modules\GoiDichVu\Validates\UpdateGoiDichVuRequest;
use Illuminate\Http\Request;

class GoiDichVuController extends Controller
{
    protected GoiDichVuService $service;

    public function __construct(GoiDichVuService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/goi-dich-vu/packages
     *
     * - Lấy danh sách GÓI DỊCH VỤ (tầng 3)
     * - Có thể filter theo:
     *    + category_id
     *    + group_id (thông qua category.group_id)
     *    + tên gói, mã gói, trạng thái...
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
     * POST /api/goi-dich-vu/packages
     *
     * - Tạo mới GÓI DỊCH VỤ
     * - Cho phép gửi kèm danh sách items (chi tiết gói)
     */
    public function store(CreateGoiDichVuRequest $request)
    {
        $result = $this->service->create($request->validated());

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Tạo gói dịch vụ thành công');
    }

    /**
     * GET /api/goi-dich-vu/packages/{id}
     *
     * - Lấy chi tiết 1 gói dịch vụ
     * - Bao gồm:
     *    + category.group
     *    + items.sanPham (chi tiết dịch vụ trong gói)
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
     * PUT/PATCH /api/goi-dich-vu/packages/{id}
     *
     * - Cập nhật thông tin gói dịch vụ
     * - Nếu gửi kèm "items" thì sẽ xoá hết chi tiết cũ & ghi lại mới
     */
    public function update(UpdateGoiDichVuRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result, 'Cập nhật gói dịch vụ thành công');
    }

    /**
     * DELETE /api/goi-dich-vu/packages/{id}
     *
     * - Xoá gói dịch vụ
     * - Cascade sẽ xoá luôn chi tiết gói (goi_dich_vu_items)
     */
    public function destroy(int $id)
    {
        $result = $this->service->delete($id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success([], 'Xoá gói dịch vụ thành công');
    }

    /**
     * GET /api/goi-dich-vu/packages/options
     *
     * - Lấy danh sách gói dịch vụ dạng options (value=id, label=ten_goi)
     * - Có thể filter theo:
     *    + category_id
     *    + group_id
     */
    public function getOptions(Request $request)
    {
        $params = $request->only(['category_id', 'group_id']);

        $result = $this->service->getOptions($params);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return CustomResponse::success($result);
    }
}
