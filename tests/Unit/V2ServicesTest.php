<?php

namespace Tests\Unit;

use App\Services\V2\DocumentNumberingService;
use App\Services\V2\V2WriteGuard;
use App\Services\FiscalYearService;
use Carbon\CarbonImmutable;
use LogicException;
use Tests\TestCase;

class V2ServicesTest extends TestCase
{
    public function test_v2_writes_are_disabled_by_default(): void
    {
        config(['edoc.v2.enabled' => false, 'edoc.v2.write_enabled' => false]);

        $this->expectException(LogicException::class);
        app(V2WriteGuard::class)->ensureEnabled();
    }

    public function test_document_numbering_uses_thai_fiscal_year_boundary(): void
    {
        $service = app(DocumentNumberingService::class);

        $this->assertSame(2026, $service->fiscalYear(CarbonImmutable::parse('2026-09-30')));
        $this->assertSame(2027, $service->fiscalYear(CarbonImmutable::parse('2026-10-01')));
    }

    public function test_fiscal_year_range_is_october_through_september(): void
    {
        $service = app(FiscalYearService::class);

        $this->assertSame(2025, $service->year(CarbonImmutable::parse('2024-10-01')));
        $this->assertSame(2025, $service->year(CarbonImmutable::parse('2025-09-30')));
        $this->assertSame(['2024-10-01 00:00:00', '2025-09-30 23:59:59'], $service->range(2025));
    }
}
