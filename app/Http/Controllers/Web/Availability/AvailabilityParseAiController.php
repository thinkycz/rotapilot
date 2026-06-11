<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Availability;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\EmployeeProfile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AvailabilityParseAiController
{
    use ValidatesWebRequests;

    /**
     * Parse a natural-language availability description into structured rows.
     *
     * Returns a JSON preview (no DB writes). Manager confirms via store.
     */
    public function __invoke(Request $request): SymfonyResponse
    {
        $validated = $this->validateRequest($request, [
            'employee_profile_id' => 'required|integer|exists:employee_profiles,id',
            'text' => 'required|string|max:4000',
        ]);

        $employee = EmployeeProfile::query()->find((int) $validated->mixed('employee_profile_id'));
        if (!$employee instanceof EmployeeProfile) {
            \abort(404);
        }

        // Simple deterministic parser for the MVP — no AI SDK required.
        $text = (string) $validated->mixed('text');
        $rows = self::parseText($text);

        return \response()->json([
            'understanding' => 'Parsed "' . $text . '" into ' . \count($rows) . ' availability entries.',
            'availability' => $rows,
        ]);
    }

    /**
     * Lightweight deterministic parser.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function parseText(string $text): array
    {
        $rows = [];
        $dayMap = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
            'mon' => 1,
            'tue' => 2,
            'wed' => 3,
            'thu' => 4,
            'fri' => 5,
            'sat' => 6,
            'sun' => 7,
        ];

        $low = \mb_strtolower($text);

        // Match "Day [from X to Y]" or "Day X-Y"
        $pattern = '/\\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)\\b\\s*(?:from\\s*(\\d{1,2}:\\d{2})\\s*to\\s*(\\d{1,2}:\\d{2})|(\\d{1,2}:\\d{2})\\s*-\\s*(\\d{1,2}:\\d{2}))/i';
        if (\preg_match_all($pattern, $low, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $day = $dayMap[$m[1]] ?? null;
                $start = $m[2] !== '' ? $m[2] : ($m[4] ?? null);
                $end = $m[3] !== '' ? $m[3] : ($m[5] ?? null);
                if ($day === null || $start === null || $end === null) {
                    continue;
                }

                $type = 'available';
                if (\str_contains($low, 'prefer') && \str_contains($low, $m[1])) {
                    $type = 'preferred';
                }

                $rows[] = [
                    'date' => 'day_of_week:' . $day,
                    'type' => $type,
                    'start_time' => $start,
                    'end_time' => $end,
                    'note' => null,
                ];
            }
        }

        // Match "cannot work <day>" or "not <day>"
        $pattern2 = '/\\b(?:cannot|can\'?t|not)\\s+(?:work\\s+)?(?:on\\s+)?(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i';
        if (\preg_match_all($pattern2, $low, $matches)) {
            foreach ($matches[1] as $dayName) {
                $day = $dayMap[\mb_strtolower($dayName)] ?? null;
                if ($day === null) {
                    continue;
                }
                $rows[] = [
                    'date' => 'day_of_week:' . $day,
                    'type' => 'unavailable',
                    'start_time' => null,
                    'end_time' => null,
                    'note' => 'Cannot work ' . $dayName,
                ];
            }
        }

        return $rows;
    }
}
