<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Clock;
use App\Helpers\ListQuery;
use App\Repositories\FineRepositoryInterface;
use App\Repositories\IssueRepositoryInterface;
use App\Repositories\ReportRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;
use App\Validators\Rules;
use App\Validators\Validator;

final class ReportService
{
    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly IssueRepositoryInterface $issues,
        private readonly FineRepositoryInterface $fines,
        private readonly ReservationRepositoryInterface $reservations,
        private readonly SettingsService $settings,
        private readonly Clock $clock,
    ) {
    }

    /** @return array<string, int|float> */
    public function summary(): array
    {
        $row = $this->reports->summary($this->clock->todayString());
        $result = [];
        foreach ($row as $key => $value) {
            $result[$key] = str_starts_with($key, 'fines_') ? round((float) $value, 2) : (int) $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $params from, to, limit
     * @return list<Row>
     */
    public function popularBooks(array $params): array
    {
        [$from, $to, $limit] = $this->range($params, 10);

        return $this->reports->popularBooks($from, $to, $limit);
    }

    /**
     * @param array<string, mixed> $params from, to, limit
     * @return list<Row>
     */
    public function topCategories(array $params): array
    {
        [$from, $to, $limit] = $this->range($params, 8);

        return $this->reports->topCategories($from, $to, $limit);
    }

    /** @return list<Row> */
    public function overdue(): array
    {
        $rate = $this->settings->float('fine_per_day');

        return array_map(
            static fn (array $row): array => $row + ['estimated_fine' => round((int) $row['days_overdue'] * $rate, 2)],
            $this->reports->overdue($this->clock->todayString())
        );
    }

    /**
     * @param array<string, mixed> $params months
     * @return list<array{month: string, label: string, issues: int, returns: int}>
     */
    public function monthlyIssues(array $params): array
    {
        $months = (int) (Validator::validate($params, Rules::dateRange())['months'] ?? 12);
        $start = $this->clock->today()->modify('first day of this month')->modify('-' . ($months - 1) . ' months');
        $counts = $this->reports->monthlyCounts($start->format('Y-m-d'));

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->modify("+{$i} months");
            $key = $month->format('Y-m');
            $series[] = [
                'month' => $key,
                'label' => $month->format('M Y'),
                'issues' => $counts['issues'][$key] ?? 0,
                'returns' => $counts['returns'][$key] ?? 0,
            ];
        }

        return $series;
    }

    /** @return Row */
    public function studentDashboard(int $studentId): array
    {
        $today = $this->clock->today();
        $active = $this->issues->paginate(
            ListQuery::simple('i.due_date', 'ASC', ['student_id' => (string) $studentId, 'status' => 'active'], 50)
        )['data'];
        $dueSoonLimit = $today->modify('+3 days')->format('Y-m-d');
        $todayString = $today->format('Y-m-d');

        return [
            'active_issues' => $active,
            'due_soon' => array_values(array_filter($active, static fn (array $i): bool => $i['due_date'] >= $todayString && $i['due_date'] <= $dueSoonLimit)),
            'overdue' => array_values(array_filter($active, static fn (array $i): bool => $i['due_date'] < $todayString)),
            'unpaid_fines' => $this->fines->unpaidTotalForStudent($studentId),
            'reservations' => $this->reservations->paginate(
                ListQuery::simple('r.id', 'ASC', ['student_id' => (string) $studentId, 'status' => 'active'], 20)
            )['data'],
            'recommendations' => $this->reports->recommendations($studentId, 6),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: string, 2: int}
     */
    private function range(array $params, int $defaultLimit): array
    {
        $data = Validator::validate($params, Rules::dateRange());
        $to = $data['to'] ?? $this->clock->todayString();
        $from = $data['from'] ?? $this->clock->today()->modify('-1 year')->format('Y-m-d');

        return [(string) $from, (string) $to, (int) ($data['limit'] ?? $defaultLimit)];
    }
}
