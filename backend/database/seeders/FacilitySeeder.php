<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create major hub facilities in Thailand
        $majorHubs = [
            [
                'code' => 'BKK01',
                'name' => 'Bangkok Central Hub',
                'name_th' => 'ศูนย์คัดแยกกลางกรุงเทพฯ',
                'facility_type' => 'hub',
                'latitude' => 13.7563,
                'longitude' => 100.5018,
                'address' => 'Bangkok, Thailand',
                'timezone' => 'Asia/Bangkok',
                'active' => true,
            ],
            [
                'code' => 'CNX01',
                'name' => 'Chiang Mai Hub',
                'name_th' => 'ศูนย์คัดแยกเชียงใหม่',
                'facility_type' => 'hub',
                'latitude' => 18.7883,
                'longitude' => 98.9853,
                'address' => 'Chiang Mai, Thailand',
                'timezone' => 'Asia/Bangkok',
                'active' => true,
            ],
            [
                'code' => 'HKT01',
                'name' => 'Phuket Hub',
                'name_th' => 'ศูนย์คัดแยกภูเก็ต',
                'facility_type' => 'hub',
                'latitude' => 7.8804,
                'longitude' => 98.3923,
                'address' => 'Phuket, Thailand',
                'timezone' => 'Asia/Bangkok',
                'active' => true,
            ],
            [
                'code' => 'KKC01',
                'name' => 'Khon Kaen Hub',
                'name_th' => 'ศูนย์คัดแยกขอนแก่น',
                'facility_type' => 'hub',
                'latitude' => 16.4322,
                'longitude' => 102.8236,
                'address' => 'Khon Kaen, Thailand',
                'timezone' => 'Asia/Bangkok',
                'active' => true,
            ],
        ];

        foreach ($majorHubs as $hub) {
            Facility::create($hub);
        }

        // Create delivery offices
        $deliveryOffices = [
            [
                'code' => 'BKK_DO01',
                'name' => 'Bangkok Sukhumvit Delivery Office',
                'name_th' => 'สำนักงานส่งสุขุมวิท',
                'facility_type' => 'delivery_office',
                'latitude' => 13.7307,
                'longitude' => 100.5418,
                'address' => 'Sukhumvit Road, Bangkok',
                'timezone' => 'Asia/Bangkok',
                'active' => true,
            ],
            [
                'code' => 'BKK_DO02',
                'name' => 'Bangkok Silom Delivery Office',
                'name_th' => 'สำนักงานส่งสีลม',
                'facility_type' => 'delivery_office',
                'latitude' => 13.7244,
                'longitude' => 100.5343,
                'address' => 'Silom Road, Bangkok',
                'timezone' => 'Asia/Bangkok',
                'active' => true,
            ],
            [
                'code' => 'CNX_DO01',
                'name' => 'Chiang Mai City Delivery Office',
                'name_th' => 'สำนักงานส่งเมืองเชียงใหม่',
                'facility_type' => 'delivery_office',
                'latitude' => 18.7906,
                'longitude' => 98.9817,
                'address' => 'Chiang Mai City Center',
                'timezone' => 'Asia/Bangkok',
                'active' => true,
            ],
        ];

        foreach ($deliveryOffices as $office) {
            Facility::create($office);
        }

        // Create sorting centers
        $sortingCenters = [
            [
                'code' => 'BKK_SC01',
                'name' => 'Bangkok North Sorting Center',
                'name_th' => 'ศูนย์คัดแยกกรุงเทพเหนือ',
                'facility_type' => 'sorting_center',
                'latitude' => 13.8199,
                'longitude' => 100.5138,
                'address' => 'North Bangkok',
                'timezone' => 'Asia/Bangkok',
                'active' => true,
            ],
            [
                'code' => 'BKK_SC02',
                'name' => 'Bangkok South Sorting Center',
                'name_th' => 'ศูนย์คัดแยกกรุงเทพใต้',
                'facility_type' => 'sorting_center',
                'latitude' => 13.6904,
                'longitude' => 100.5226,
                'address' => 'South Bangkok',
                'timezone' => 'Asia/Bangkok',
                'active' => true,
            ],
        ];

        foreach ($sortingCenters as $center) {
            Facility::create($center);
        }

        // Create additional facilities using factory
        Facility::factory(20)->create();
        Facility::factory(5)->hub()->create();
        Facility::factory(10)->deliveryOffice()->create();
    }
}