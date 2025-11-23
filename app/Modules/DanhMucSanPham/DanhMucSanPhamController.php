<?php

namespace App\Modules\DanhMucSanPham;

use App\Http\Controllers\Controller;
use App\Modules\DanhMucSanPham\Validates\CreateDanhMucSanPhamRequest;
use App\Modules\DanhMucSanPham\Validates\UpdateDanhMucSanPhamRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DanhMucSanPhamImport;
use Illuminate\Support\Str;

class DanhMucSanPhamController extends Controller
{
  protected $danhMucSanPhamService;

  public function __construct(DanhMucSanPhamService $danhMucSanPhamService)
  {
    $this->danhMucSanPhamService = $danhMucSanPhamService;
  }

  /**
   * Lấy danh sách DanhMucSanPhams
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->danhMucSanPhamService->getAll($params);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([
      'collection' => $result['data'],
      'total' => $result['total'],
      'pagination' => $result['pagination'] ?? null
    ]);
  }

  /**
   * Tạo mới DanhMucSanPham
   */
  public function store(CreateDanhMucSanPhamRequest $request)
  {
    $result = $this->danhMucSanPhamService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin DanhMucSanPham
   */
  public function show($id)
  {
    $result = $this->danhMucSanPhamService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật DanhMucSanPham
   */
  public function update(UpdateDanhMucSanPhamRequest $request, $id)
  {
    $result = $this->danhMucSanPhamService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa DanhMucSanPham
   */
  public function destroy($id)
  {
    $result = $this->danhMucSanPhamService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách DanhMucSanPham dạng option
   * - Hỗ trợ ?level=1 (tầng 1), ?level=2 (tầng 2)
   */
  /**
   * Lấy danh sách DanhMucSanPham dạng option
   * - Hỗ trợ:
   *    + ?level=1 (tầng 1: không có parent_id)
   *    + ?level=2 (tầng 2: có parent_id)
   *    + ?group_code=NS|CSVC|TIEC|TD|CPK (lọc theo nhóm dịch vụ)
   */
  public function getOptions(Request $request)
  {
    // Nhận cả level + group_code (FE có thể gửi 1 trong 2 hoặc cả 2)
    // Nhận cả level + group_code + parent_id
    $params = $request->only(['level', 'group_code', 'parent_id']);


    $result = $this->danhMucSanPhamService->getOptions($params);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }



  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/DanhMucSanPham.xlsx');

    if (!file_exists($path)) {
      return CustomResponse::error('File không tồn tại');
    }

    return response()->download($path);
  }

  public function importExcel(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:xlsx,xls,csv',
    ]);

    try {
      $data = $request->file('file');
      $filename = Str::random(10) . '.' . $data->getClientOriginalExtension();
      $path = $data->move(public_path('excel'), $filename);

      $import = new DanhMucSanPhamImport();
      Excel::import($import, $path);

      $thanhCong = $import->getThanhCong();
      $thatBai = $import->getThatBai();

      // Xóa file sau khi import
      if (file_exists($path)) {
        unlink($path);
      }

      if ($thatBai > 0) {
        return CustomResponse::error('Import không thành công. Có ' . $thatBai . ' bản ghi lỗi và ' . $thanhCong . ' bản ghi thành công');
      }

      return CustomResponse::success([
        'success' => $thanhCong,
        'fail' => $thatBai
      ], 'Import thành công ' . $thanhCong . ' bản ghi');
    } catch (\Exception $e) {
      return CustomResponse::error('Lỗi import: ' . $e->getMessage(), 500);
    }
  }
}