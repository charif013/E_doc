<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;

class RoomSeeder extends Seeder
{
    public function run()
    {
        Room::updateOrCreate(
            ['name' => 'ห้องประชุมชั้น 2'],
            ['capacity' => 30, 'status' => 'active']
        );
    }
}
