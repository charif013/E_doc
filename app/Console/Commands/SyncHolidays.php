<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class SyncHolidays extends Command
{
    protected $signature = 'holidays:sync {year?}'; // สั่งรัน php artisan holidays:sync 2026

    protected $description = 'Sync Thai public holidays from Google Calendar API to Database';

    public function handle(): int
    {
        $yearInput = $this->argument('year') ?? now()->year;
        $year = filter_var($yearInput, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1900, 'max_range' => 2100],
        ]);
        if ($year === false) {
            $this->error('ปีต้องเป็นตัวเลขระหว่าง 1900 ถึง 2100');

            return self::INVALID;
        }

        $apiKey = trim((string) config('services.google_calendar.api_key'));
        if ($apiKey === '') {
            $this->error('ยังไม่ได้กำหนด GOOGLE_API_KEY');

            return self::FAILURE;
        }

        $this->info("🔄 กำลังดึงข้อมูลวันหยุดปี {$year} จาก Google Calendar...");

        $calendarId = 'th.th#holiday@group.v.calendar.google.com';
        $timezone = (string) config('app.timezone', 'Asia/Bangkok');
        $timeMin = Carbon::create($year, 1, 1, 0, 0, 0, $timezone)->toRfc3339String();
        // Google Calendar treats timeMax as exclusive, so use the first instant of the next year.
        $timeMax = Carbon::create($year + 1, 1, 1, 0, 0, 0, $timezone)->toRfc3339String();

        $url = 'https://www.googleapis.com/calendar/v3/calendars/'.urlencode($calendarId).'/events';

        try {
            $response = Http::acceptJson()->timeout(20)->retry(2, 250)->get($url, [
                'key' => $apiKey,
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);
        } catch (Throwable $exception) {
            $this->error('❌ ติดต่อ Google Calendar ไม่สำเร็จ: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('❌ ดึงข้อมูลล้มเหลว: HTTP Status '.$response->status());
            $this->error('รายละเอียดจาก Google: '.$response->body());

            return self::FAILURE;
        }

        $events = $response->json('items', []);
        if (! is_array($events)) {
            $this->error('❌ รูปแบบข้อมูลจาก Google Calendar ไม่ถูกต้อง');

            return self::FAILURE;
        }

        $count = 0;
        foreach ($events as $event) {
            if (! is_array($event) || ! isset($event['start']['date'], $event['summary'])) {
                continue;
            }

            Holiday::updateOrCreate(
                ['holiday_date' => (string) $event['start']['date']],
                [
                    'name' => (string) $event['summary'],
                    'source' => 'google_api',
                ]
            );
            $count++;
        }

        $this->info("✅ สำเร็จ! ซิงค์วันหยุดลง Database จำนวน {$count} วัน");

        return self::SUCCESS;
    }
}
