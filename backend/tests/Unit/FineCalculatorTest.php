<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FineCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FineCalculatorTest extends TestCase
{
    /** @return array<string, array{string, string, float, int, float}> */
    public static function cases(): array
    {
        return [
            'returned early' => ['2026-03-15', '2026-03-10', 5.0, 0, 0.0],
            'returned on due date' => ['2026-03-15', '2026-03-15', 5.0, 0, 0.0],
            'one day late' => ['2026-03-15', '2026-03-16', 5.0, 1, 5.0],
            'across month boundary' => ['2026-02-25', '2026-03-03', 2.5, 6, 15.0],
            'across leap day' => ['2028-02-27', '2028-03-01', 10.0, 3, 30.0],
            'zero rate' => ['2026-03-01', '2026-03-31', 0.0, 30, 0.0],
            'negative rate is ignored' => ['2026-03-01', '2026-03-05', -5.0, 4, 0.0],
        ];
    }

    #[DataProvider('cases')]
    public function testCalculatesDaysAndAmount(string $due, string $returned, float $rate, int $expectedDays, float $expectedFine): void
    {
        $calculator = new FineCalculator();

        self::assertSame($expectedDays, $calculator->daysOverdue($due, $returned));
        self::assertSame($expectedFine, $calculator->overdueFine($due, $returned, $rate));
    }
}
