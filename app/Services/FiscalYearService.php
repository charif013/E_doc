<?php

namespace App\Services;

use Carbon\CarbonInterface;

class FiscalYearService
{
    /**
     * Return the Gregorian year in which the Thai fiscal year ends.
     *
     * For example, 1 October 2026 through 30 September 2027 is fiscal year 2027
     * (B.E. 2570 when displayed to users).
     */
    public function year(CarbonInterface $date): int
    {
        return $date->month >= 10 ? $date->year + 1 : $date->year;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function range(int $fiscalYear): array
    {
        return [
            ($fiscalYear - 1).'-10-01 00:00:00',
            $fiscalYear.'-09-30 23:59:59',
        ];
    }
}
