<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

final class StaffReportPreamble
{
    /**
     * @param  array<string, string>  $filters
     * @return list<list<string>>
     */
    public static function rows(string $title, array $filters, bool $aggregatesOnly = false): array
    {
        $now = Carbon::now()->timezone((string) config('app.timezone'));
        $filterParts = [];
        foreach ($filters as $label => $value) {
            $filterParts[] = $label.'='.($value !== '' ? $value : 'all');
        }

        $rows = [
            ['Report', $title],
            ['Generated at', $now->toDateTimeString()],
            ['Timezone', 'Asia/Manila'],
            ['Filters', $filterParts === [] ? 'none' : implode('; ', $filterParts)],
            ['Model', 'Figures are estimates from the recorded model version on each row. Placeholder heuristic versions are rule-based estimates, not trained models or validated probabilities.'],
            ['Program-shift', 'Program-shift figures are counts of students. The indicator is qualitative and has no percentage.'],
        ];

        if ($aggregatesOnly) {
            $rows[] = ['Scope', 'College aggregates only. Individual student names, numbers, and records are not included.'];
        } else {
            $rows[] = ['Scope', 'Authorized students in the signed-in department head assignment. Questionnaire answers and household details are omitted.'];
        }

        $rows[] = ['Disclaimer', 'These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.'];

        return $rows;
    }
}
