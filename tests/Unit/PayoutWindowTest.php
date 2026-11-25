<?php

namespace Tests\Unit;

use App\Services\PayoutWindow;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PayoutWindowTest extends TestCase
{
    #[DataProvider('windows')]
    public function test_next_hour_end_respects_the_business_window(string $approval, string $expected): void
    {
        $result = (new PayoutWindow)->forApproval(CarbonImmutable::parse($approval, 'Africa/Nairobi'));
        $this->assertSame($expected, $result->timezone('Africa/Nairobi')->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $result->timezoneName);
    }

    public static function windows(): array
    {
        return [
            ['2026-11-24 10:15:00', '2026-11-24 11:59:00'],
            ['2026-11-24 10:59:59', '2026-11-24 11:59:00'],
            ['2026-11-24 11:00:00', '2026-11-24 12:59:00'],
            ['2026-11-24 18:59:59', '2026-11-24 19:59:00'],
            ['2026-11-24 19:00:00', '2026-11-25 08:59:00'],
            ['2026-11-24 23:15:00', '2026-11-25 08:59:00'],
            ['2026-11-24 06:00:00', '2026-11-24 08:59:00'],
            ['2026-11-24 07:15:00', '2026-11-24 08:59:00'],
            ['2026-11-24 08:59:00', '2026-11-24 09:59:00'],
        ];
    }
}
