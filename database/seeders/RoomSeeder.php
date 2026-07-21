<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;

class RoomSeeder extends Seeder
{
    public function run()
    {
        Room::create(['name' => 'ห้องประชุมสภา อบต.', 'capacity' => 30]);
        Room::create(['name' => 'ห้องประชุมเล็ก (ชั้น 2)', 'capacity' => 10]);
    }
}