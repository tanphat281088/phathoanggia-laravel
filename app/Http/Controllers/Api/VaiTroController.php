<?php

namespace App\Http\Controllers\Api;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Class\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VaiTro;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class VaiTroController extends Controller
{
  /**
   * Chỉ chủ hệ thống mới được thao tác RBAC.
   * RBAC_OWNER_EMAIL có thể cấu hình trong .env, mặc định admin@gmail.com
   */
  private function isSystemOwner(): bool
  {
    $owner = env('RBAC_OWNER_EMAIL', 'admin@gmail.com');
    return strcasecmp(Auth::user()->email ?? '', $owner) === 0;
  }

  /**
   * Lấy permission registry: ưu tiên V2 khi PERMISSION_ENGINE=v2
   */
  /**
   * Lấy permission registry: FIX — luôn dùng V2 (permission_registry.php)
   */
  private function getPermissionRegistry(): array
  {
    $items = config('permission_registry'); // bỏ hẳn engine v1
    return is_array($items) ? $items : [];
  }


  /**
   * Validate phan_quyen (JSON mảng [{name,actions:{...}},...]) theo registry.
   * @return array{0:bool,1:string}
   */
  private function validatePhanQuyen(string $phanQuyenJson): array
  {
    $decoded = json_decode($phanQuyenJson, true);
    if (!is_array($decoded)) {
      return [false, 'Dữ liệu phân quyền không hợp lệ (không phải JSON mảng).'];
    }

    // registry -> map module => danh sách action hợp lệ
    $registry   = $this->getPermissionRegistry();
    $allowedMap = [];
    foreach ($registry as $item) {
      if (!isset($item['name']) || !isset($item['actions']) || !is_array($item['actions'])) {
        continue;
      }
      $allowedMap[(string)$item['name']] = array_keys($item['actions']);
    }

    foreach ($decoded as $entry) {
      $name    = $entry['name']    ?? null;
      $actions = $entry['actions'] ?? null;

      if (!$name || !is_array($actions)) {
        return [false, "Phần tử phân quyền thiếu 'name' hoặc 'actions'."];
      }
      if (!array_key_exists($name, $allowedMap)) {
        return [false, "Module quyền không hợp lệ: '{$name}'."];
      }
      foreach ($actions as $actKey => $actVal) {
        if (!in_array($actKey, $allowedMap[$name], true)) {
          return [false, "Action '{$actKey}' không hợp lệ cho module '{$name}'."];
        }
        if (!is_bool($actVal)) {
          return [false, "Giá trị action '{$name}.{$actKey}' phải là boolean."];
        }
      }
    }

    return [true, 'OK'];
  }

  /**
   * Chuẩn hoá phan_quyen: map legacy action (list/store/update/...) -> chuẩn (index/create/edit/...)
   * và chỉ giữ boolean. Trả về JSON đã chuẩn hoá.
   */
  private function normalizePhanQuyen(string $phanQuyenJson): string
  {
    $decoded = json_decode($phanQuyenJson, true);
    if (!is_array($decoded)) {
      return '[]';
    }

    $actionMap = [
      'list'        => 'index',
      'store'       => 'create',
      'update'      => 'edit',
      'detail'      => 'show',
      'showDetail'  => 'show',
      'remove'      => 'delete',
      'destroy'     => 'delete',
      'export'      => 'export',
      'showMenu'    => 'showMenu',
      // 'convert' giữ nguyên tên (không map)
    ];

    $out = [];
    foreach ($decoded as $entry) {
      $name    = isset($entry['name']) ? trim((string)$entry['name']) : '';
      $actions = (isset($entry['actions']) && is_array($entry['actions'])) ? $entry['actions'] : [];
      if ($name === '') {
        continue;
      }

      // ✅ FIX LEGACY: cskh-points dùng 'send' → đổi sang 'sendZns'
      if ($name === 'cskh-points' && array_key_exists('send', $actions)) {
        if (!array_key_exists('sendZns', $actions)) {
          $actions['sendZns'] = (bool)$actions['send'];
        }
        unset($actions['send']); // bỏ hẳn key cũ
      }

      $normActions = [];
      foreach ($actions as $k => $v) {
        $key = $actionMap[$k] ?? $k;     // map legacy -> chuẩn nếu có
        $normActions[$key] = (bool)$v;   // ép boolean
      }

      // ✳️ BỎ các module không tick gì (tất cả action = false)
      if (!in_array(true, array_values($normActions), true)) {
        continue;
      }

      $out[] = ['name' => $name, 'actions' => $normActions];
    }

    return json_encode($out, JSON_UNESCAPED_UNICODE);
  }




  public function index(Request $request)
  {
    $params = $request->all();
    $params = Helper::validateFilterParams($params);

    $query = VaiTro::query();

    // Filter + pagination
    $result = FilterWithPagination::findWithPagination(
      $query,
      $params,
      ['vai_tros.*']
    );

    return CustomResponse::success([
      'collection' => $result['collection'],
      'total'      => $result['total'],
      'pagination' => null,
    ]);
  }

  public function store(Request $request)
  {
    // Chỉ chủ hệ thống được tạo vai trò
    if (!$this->isSystemOwner()) {
      return CustomResponse::error('Chỉ tài khoản quản trị hệ thống (admin@gmail.com) mới được thao tác vai trò.', 403);
    }

    $validator = Validator::make($request->all(), [
      'ma_vai_tro'  => 'required|string|max:255|unique:vai_tros,ma_vai_tro',
      'ten_vai_tro' => 'required|string|max:255',
      'phan_quyen'  => 'required|json',
      'trang_thai'  => 'nullable|in:0,1',
    ]);

    if ($validator->fails()) {
      return CustomResponse::error("Thông tin không hợp lệ", $validator->errors());
    }

    // Kiểm tra phan_quyen theo registry
// Chuẩn hoá action legacy -> chuẩn
$normalizedJson = $this->normalizePhanQuyen($request->input('phan_quyen'));

// Validate theo registry
[$ok, $msg] = $this->validatePhanQuyen($normalizedJson);
if (!$ok) {
  return CustomResponse::error($msg);
}

try {
  $data               = $request->only(['ma_vai_tro','ten_vai_tro','trang_thai']);
  $data['phan_quyen'] = $normalizedJson;
  $data['trang_thai'] = isset($data['trang_thai']) ? (int)$data['trang_thai'] : 1;
  $vaiTro             = VaiTro::create($data);


      return CustomResponse::success($vaiTro, 'Tạo vai trò thành công');
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }

  public function show($id)
  {
    $vaiTro = VaiTro::find($id);
    if (!$vaiTro) {
      return CustomResponse::error('Không tìm thấy vai trò');
    }
    return CustomResponse::success($vaiTro);
  }

  public function update(Request $request, $id)
  {
    $vaiTro = VaiTro::find($id);
    if (!$vaiTro) {
      return CustomResponse::error('Không tìm thấy vai trò');
    }

    // Chỉ chủ hệ thống được cập nhật vai trò
    if (!$this->isSystemOwner()) {
      return CustomResponse::error('Chỉ tài khoản quản trị hệ thống (admin@gmail.com) mới được thao tác vai trò.', 403);
    }

    // Xử lý riêng vai trò hệ thống ADMIN
    if (strcasecmp($vaiTro->ma_vai_tro, 'ADMIN') === 0) {
      // Không cho đổi mã vai trò ADMIN để giữ “neo”
      if ($request->filled('ma_vai_tro') && strcasecmp($request->input('ma_vai_tro'), 'ADMIN') !== 0) {
        return CustomResponse::error('Không thể đổi mã vai trò của ADMIN.');
      }
      // Chủ hệ thống được phép cập nhật phan_quyen của ADMIN (đi tiếp validate như thường)
    }

    // Validate dữ liệu cập nhật
    $validator = Validator::make($request->all(), [
      'ma_vai_tro'  => 'sometimes|string|max:255|unique:vai_tros,ma_vai_tro,' . $vaiTro->id,
      'ten_vai_tro' => 'sometimes|string|max:255',
      'phan_quyen'  => 'sometimes|json',
      'trang_thai'  => 'sometimes|in:0,1',
    ]);

    if ($validator->fails()) {
      return CustomResponse::error("Thông tin không hợp lệ", $validator->errors());
    }

    // Nếu có phan_quyen -> kiểm tra theo registry
// Nếu có phan_quyen -> normalize + validate theo registry
if ($request->filled('phan_quyen')) {
  $normalizedJson = $this->normalizePhanQuyen($request->input('phan_quyen'));
  [$ok, $msg] = $this->validatePhanQuyen($normalizedJson);
  if (!$ok) {
    return CustomResponse::error($msg);
  }
  // Ghi đè phan_quyen bằng bản đã chuẩn hoá
  $request->merge(['phan_quyen' => $normalizedJson]);
}


    $data = $request->only(['ma_vai_tro','ten_vai_tro','phan_quyen','trang_thai']);
    if (isset($data['trang_thai'])) {
      $data['trang_thai'] = (int)$data['trang_thai'] === 1 ? 1 : 0;
    }

    $vaiTro->update($data);
    return CustomResponse::success($vaiTro->fresh(), "Cập nhật thành công");
  }

  public function destroy($id)
  {
    $vaiTro = VaiTro::find($id);
    if (!$vaiTro) {
      return CustomResponse::error('Không tìm thấy vai trò');
    }

    // Chỉ chủ hệ thống được xóa vai trò
    if (!$this->isSystemOwner()) {
      return CustomResponse::error('Chỉ tài khoản quản trị hệ thống (admin@gmail.com) mới được thao tác vai trò.', 403);
    }

    // Khóa xóa vai trò hệ thống ADMIN
    if (strcasecmp($vaiTro->ma_vai_tro, 'ADMIN') === 0) {
      return CustomResponse::error("Không thể xoá vai trò hệ thống (ADMIN).");
    }

    // Chặn xoá khi đang được sử dụng
    $countUsers = User::where('ma_vai_tro', $vaiTro->ma_vai_tro)->count();
    if ($countUsers > 0) {
      return CustomResponse::error("Vai trò đang được sử dụng cho {$countUsers} người dùng, không thể xóa");
    }

    $vaiTro->delete();
    return CustomResponse::success([], 'Xoá vai trò thành công');
  }

  public function options()
  {
    $vaiTros = VaiTro::where('trang_thai', 1)
      ->select('ma_vai_tro', 'ten_vai_tro')
      ->get()
      ->map(function ($vaiTro) {
        return [
          'value' => $vaiTro->ma_vai_tro,
          'label' => $vaiTro->ten_vai_tro,
        ];
      });

    return CustomResponse::success($vaiTros);
  }
}
