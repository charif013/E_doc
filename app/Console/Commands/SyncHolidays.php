<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Holiday;
use Carbon\Carbon;

class SyncHolidays extends Command
{
    protected $signature = 'holidays:sync {year?}'; // สั่งรัน php artisan holidays:sync 2026
    protected $description = 'Sync Thai public holidays from Google Calendar API to Database';

    public function handle()
    {
        $year = $this->argument('year') ?? now()->year;
        $this->info("🔄 กำลังดึงข้อมูลวันหยุดปี {$year} จาก Google Calendar...");

        // 🌟 ใช้ Calendar ID วันหยุดของไทยจาก Google
       // โค้ดใหม่ที่ถูกต้อง 🌟
        $calendarId = 'th.th#holiday@group.v.calendar.google.com';
        $apiKey = env('GOOGLE_API_KEY'); // ต้องไปสร้าง API Key ใน Google Cloud Console มาใส่ใน .env นะครับ

        $timeMin = Carbon::create($year, 1, 1)->toRfc3339String();
        $timeMax = Carbon::create($year, 12, 31)->toRfc3339String();

        $url = "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events";

        $response = Http::get($url, [
            'key' => $apiKey,
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
        ]);

        if ($response->successful()) {
            $events = $response->json()['items'] ?? [];
            $count = 0;

            foreach ($events as $event) {
                if (isset($event['start']['date'])) {
                    // บันทึกลงตาราง holiday (ใช้ updateOrCreate เพื่อป้องกันข้อมูลซ้ำ)
                    Holiday::updateOrCreate(
                        ['holiday_date' => $event['start']['date']],
                        [
                            'name' => $event['summary'],
                            'source' => 'google_api'
                        ]
                    );
                    $count++;
                }
            }
            $this->info("✅ สำเร็จ! ซิงค์วันหยุดลง Database จำนวน {$count} วัน");
        } else {
               $this->error("❌ ดึงข้อมูลล้มเหลว: HTTP Status " . $response->status());
               $this->error("รายละเอียดจาก Google: " . $response->body());
           }
    }
}
