<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DiemLamViec;

class FixedWorkpointsSeeder extends Seeder
{
    /**
     * Seed 16 địa điểm cố định:
     * - Văn phòng PHG, Melisa, Grand Palace, Queen, ...
     * - Để an toàn, em không đụng tới cột code/dia_chi (vì mình chưa chắc schema),
     *   mà nhét mã DDxxx vào trong tên luôn: "[DD001] Văn phòng PHG".
     */
    public function run(): void
    {
        // Bán kính mặc định (m) – anh chỉnh nếu cần
        $defaultRadius = 150;

        $points = [
            [
                'code' => 'DD001',
                'ten'  => 'Văn phòng PHG',
                'lat'  => 10.79995189778521,
                'lng'  => 106.65122910678122,
            ],
            [
                'code' => 'DD002',
                'ten'  => 'Melisa',
                'lat'  => 10.78243845657963,
                'lng'  => 106.63704360995207,
            ],
            [
                'code' => 'DD003',
                'ten'  => 'Grand Palace',
                'lat'  => 10.804045333219683,
                'lng'  => 106.65584402344474,
            ],
            [
                'code' => 'DD004',
                'ten'  => 'Queen Tân Bình',
                'lat'  => 10.793701713724376,
                'lng'  => 106.66310498296686,
            ],
            [
                'code' => 'DD005',
                'ten'  => 'Queen Kỳ Hòa',
                'lat'  => 10.774560243562915,
                'lng'  => 106.6708559406383,
            ],
            [
                'code' => 'DD006',
                'ten'  => 'Happy Gold',
                'lat'  => 10.789079312264251,
                'lng'  => 106.6375130252952,
            ],
            [
                'code' => 'DD007',
                'ten'  => 'Long Biên (Him Lam)',
                'lat'  => 10.830287344092941,
                'lng'  => 106.65009929645971,
            ],
            [
                'code' => 'DD008',
                'ten'  => 'Nhà hàng Phú Nhuận',
                'lat'  => 10.802680431255762,
                'lng'  => 106.68356995413106,
            ],
            [
                'code' => 'DD009',
                'ten'  => 'Đông Hồ',
                'lat'  => 10.774311862847924,
                'lng'  => 106.67185689645936,
            ],
            [
                'code' => 'DD010',
                'ten'  => 'Vườn Cau',
                'lat'  => 10.821857770409581,
                'lng'  => 106.69378202189885,
            ],
            [
                'code' => 'DD011',
                'ten'  => 'Vườn Cau 2',
                'lat'  => 10.82763420812715,
                'lng'  => 106.68962873878822,
            ],
            [
                'code' => 'DD012',
                'ten'  => 'Eros',
                'lat'  => 10.873945813268545,
                'lng'  => 106.64908042529586,
            ],
            [
                'code' => 'DD013',
                'ten'  => 'Dinh Độc Lập',
                'lat'  => 10.777183902210044,
                'lng'  => 106.69528063878771,
            ],
            [
                'code' => 'DD014',
                'ten'  => 'Đồng Xanh',
                'lat'  => 10.754743656749817,
                'lng'  => 106.63823769645929,
            ],
            [
                'code' => 'DD015',
                'ten'  => 'Viễn Đông',
                'lat'  => 10.768180789570382,
                'lng'  => 106.69202072913531,
            ],
            [
                'code' => 'DD016',
                'ten'  => 'Westen Palace',
                'lat'  => 10.765930669307801,
                'lng'  => 106.67492765598138,
            ],
        ];

        foreach ($points as $p) {
            $name = sprintf('[%s] %s', $p['code'], $p['ten']);

            // Idempotent: nếu đã có tên này thì UPDATE, chưa có thì CREATE
            DiemLamViec::query()->updateOrCreate(
                ['ten' => $name],
                [
                    'lat'        => $p['lat'],
                    'lng'        => $p['lng'],
                    'ban_kinh_m' => $defaultRadius,
                ]
            );
        }
    }
}
