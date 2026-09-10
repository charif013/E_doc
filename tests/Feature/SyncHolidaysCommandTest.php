<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncHolidaysCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_an_invalid_year_without_sending_a_request(): void
    {
        Http::fake();

        $this->artisan('holidays:sync', ['year' => 'invalid'])
            ->expectsOutput('ปีต้องเป็นตัวเลขระหว่าง 1900 ถึง 2100')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_it_fails_before_sending_a_request_when_the_api_key_is_missing(): void
    {
        config(['services.google_calendar.api_key' => null]);
        Http::fake();

        $this->artisan('holidays:sync', ['year' => 2026])
            ->expectsOutput('ยังไม่ได้กำหนด GOOGLE_API_KEY')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_syncs_valid_all_day_events_for_the_entire_year(): void
    {
        config(['services.google_calendar.api_key' => 'test-key']);
        Http::fake([
            'https://www.googleapis.com/*' => Http::response([
                'items' => [
                    ['summary' => 'วันทดสอบ', 'start' => ['date' => '2026-12-31']],
                    ['summary' => 'ข้ามรายการที่ไม่มีวันที่', 'start' => []],
                ],
            ]),
        ]);

        $this->artisan('holidays:sync', ['year' => 2026])
            ->expectsOutput('✅ สำเร็จ! ซิงค์วันหยุดลง Database จำนวน 1 วัน')
            ->assertSuccessful();

        $this->assertDatabaseHas('holidays', [
            'holiday_date' => '2026-12-31',
            'name' => 'วันทดสอบ',
            'source' => 'google_api',
        ]);
        Http::assertSent(fn (Request $request) => $request['timeMin'] === '2026-01-01T00:00:00+07:00'
            && $request['timeMax'] === '2027-01-01T00:00:00+07:00');
    }
}
