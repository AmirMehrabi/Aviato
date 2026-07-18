<?php

namespace Tests\Unit;

use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class JalaliTest extends TestCase
{
    public function test_it_formats_dates_in_the_customer_timezone_as_jalali(): void
    {
        $date = CarbonImmutable::parse('2026-03-21 20:30:00', 'UTC');

        $this->assertSame('1405/01/02 00:00', Jalali::format($date));
        $this->assertSame('1405/01/02', Jalali::format($date, 'Y/m/d'));
    }

    public function test_it_returns_a_dash_for_null_dates(): void
    {
        $this->assertSame('—', Jalali::format(null));
    }
}
