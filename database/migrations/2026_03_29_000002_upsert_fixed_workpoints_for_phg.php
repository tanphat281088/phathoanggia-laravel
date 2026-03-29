<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('diem_lam_viecs')) {
            return;
        }

        $now = now();

        $rows = [
            [
                'code'       => 'DD001',
                'ten'        => 'Văn phòng PHG',
                'aliases'    => ['Trụ sở PHG', 'Văn phòng PHG'],
                'dia_chi'    => '102 Nguyễn Minh Hoàng, P12, Q. Tân Bình',
                'lat'        => '10.8004410',
                'lng'        => '106.6478842',
                'ban_kinh_m' => 100,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD002',
                'ten'        => 'Melisa',
                'aliases'    => ['Melisa'],
                'dia_chi'    => '83 Thoại Ngọc Thầu, P. Hoàng Thạnh, Q. Tân Phú',
                'lat'        => '10.7824385',
                'lng'        => '106.6370436',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD003',
                'ten'        => 'Grand Palace',
                'aliases'    => ['Grand Palace'],
                'dia_chi'    => '142/18 Cộng Hòa, P4, Q. Tân Bình',
                'lat'        => '10.8040453',
                'lng'        => '106.6558440',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD004',
                'ten'        => 'Queen Tân Bình',
                'aliases'    => ['Queen Tân Bình'],
                'dia_chi'    => '91B2 Phạm Văn Hai, P.3, Q. Tân Bình',
                'lat'        => '10.7937017',
                'lng'        => '106.6631050',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD005',
                'ten'        => 'Queen Kỳ Hòa',
                'aliases'    => ['Queen Kỳ Hòa'],
                'dia_chi'    => '16A Lê Hồng Phong, P. 12, Q. 10',
                'lat'        => '10.7745602',
                'lng'        => '106.6708559',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD006',
                'ten'        => 'Happy Gold',
                'aliases'    => ['Happy Gold'],
                'dia_chi'    => '600 Lũy Bán Bích, P. Tân Thành, Q. Tân Phú',
                'lat'        => '10.7890793',
                'lng'        => '106.6375130',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD007',
                'ten'        => 'Long Biên (Him Lam)',
                'aliases'    => ['Long Biên (Him Lam)', 'Long Biên', 'Him Lam'],
                'dia_chi'    => '6 Tân Sơn, P. 12, Q. Gò Vấp',
                'lat'        => '10.8302873',
                'lng'        => '106.6500993',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD008',
                'ten'        => 'Nhà hàng Phú Nhuận',
                'aliases'    => ['Nhà hàng Phú Nhuận'],
                'dia_chi'    => '124 Phan Đăng Lưu, P. 3, Q. Phú Nhuận',
                'lat'        => '10.8026804',
                'lng'        => '106.6835700',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD009',
                'ten'        => 'Đông Hồ',
                'aliases'    => ['Đông Hồ'],
                'dia_chi'    => '16A Lê Hồng Phong, P. 12, Q. 10',
                'lat'        => '10.7743119',
                'lng'        => '106.6718569',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD010',
                'ten'        => 'Vườn Cau',
                'aliases'    => ['Vườn Cau'],
                'dia_chi'    => '360 Phan Văn Trị, P. 11, Q. Bình Thạnh',
                'lat'        => '10.8218578',
                'lng'        => '106.6937820',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD011',
                'ten'        => 'Vườn Cau 2',
                'aliases'    => ['Vườn Cau 2'],
                'dia_chi'    => '171 Nguyễn Thái Sơn, P. 7, Q. Gò Vấp',
                'lat'        => '10.8276342',
                'lng'        => '106.6896287',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD012',
                'ten'        => 'Eros',
                'aliases'    => ['Eros'],
                'dia_chi'    => '287 Lê Văn Khương, P. Hiệp Thành, Q. 12',
                'lat'        => '10.8739458',
                'lng'        => '106.6490804',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD013',
                'ten'        => 'Dinh Độc Lập',
                'aliases'    => ['Dinh Độc Lập'],
                'dia_chi'    => '108 Nguyễn Du, P. Bến Thành, Q. 1',
                'lat'        => '10.7771839',
                'lng'        => '106.6952806',
                'ban_kinh_m' => 180,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD014',
                'ten'        => 'Đồng Xanh',
                'aliases'    => ['Đồng Xanh'],
                'dia_chi'    => '961 Hồng Bàng, P. 9, Q. 6',
                'lat'        => '10.7547437',
                'lng'        => '106.6382377',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD015',
                'ten'        => 'Viễn Đông',
                'aliases'    => ['Viễn Đông'],
                'dia_chi'    => '275A Phạm Ngũ Lão, P. Phạm Ngũ Lão, Q. 1',
                'lat'        => '10.7681808',
                'lng'        => '106.6920207',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
            [
                'code'       => 'DD016',
                'ten'        => 'Westen Palace',
                'aliases'    => ['Westen Palace', 'Western Palace'],
                'dia_chi'    => '443-445 Lê Hồng Phong, P. 2, Q. 10',
                'lat'        => '10.7659307',
                'lng'        => '106.6749277',
                'ban_kinh_m' => 150,
                'trang_thai' => 1,
            ],
        ];

        foreach ($rows as $row) {
            $existing = DB::table('diem_lam_viecs')
                ->whereIn('ten', $row['aliases'])
                ->orderBy('id')
                ->first();

            $payload = [
                'ten'        => $row['ten'],
                'dia_chi'    => $row['dia_chi'],
                'lat'        => $row['lat'],
                'lng'        => $row['lng'],
                'ban_kinh_m' => $row['ban_kinh_m'],
                'trang_thai' => $row['trang_thai'],
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('diem_lam_viecs')
                    ->where('id', $existing->id)
                    ->update($payload);
            } else {
                DB::table('diem_lam_viecs')->insert($payload + [
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Không xoá dữ liệu địa điểm cố định khi rollback để tránh làm null workpoint_id
        // trên các log chấm công đã phát sinh.
    }
};