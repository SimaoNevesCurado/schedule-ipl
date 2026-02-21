<?php

declare(strict_types=1);

namespace App\Actions;

final readonly class BuildScheduleCatalogAction
{
    /**
     * @param array<int, array{
     *     schedule_key: string,
     *     unit: string,
     *     option_code: string,
     *     class_type: string,
     *     day: string,
     *     day_index: int,
     *     start: string,
     *     end: string,
     *     mandatory: bool
     * }> $rows
     * @return array{
     *     schedules: array<int, array<string, mixed>>,
     *     units: array<int, array<string, mixed>>
     * }
     */
    public function handle(array $rows): array
    {
        $schedules = [];
        $units = [];

        foreach ($rows as $row) {
            $scheduleKey = $row['schedule_key'];
            $event = [
                'unit' => $row['unit'],
                'optionCode' => $row['option_code'],
                'classType' => $row['class_type'],
                'day' => $row['day'],
                'dayIndex' => $row['day_index'],
                'start' => $row['start'],
                'end' => $row['end'],
                'mandatory' => $row['mandatory'],
                'startMinutes' => $this->toMinutes($row['start']),
                'endMinutes' => $this->toMinutes($row['end']),
            ];

            $schedules[$scheduleKey]['key'] ??= $scheduleKey;
            $schedules[$scheduleKey]['events'] ??= [];
            $schedules[$scheduleKey]['events'][] = $event;

            $unitName = $row['unit'];
            $optionCode = $row['option_code'];

            $units[$unitName]['name'] ??= $unitName;
            $units[$unitName]['mandatoryEvents'] ??= [];
            $units[$unitName]['options'] ??= [];

            if ($row['mandatory']) {
                $units[$unitName]['mandatoryEvents'][$this->eventSignature($event)] = $event;

                continue;
            }

            $units[$unitName]['options'][$optionCode]['code'] ??= $optionCode;
            $units[$unitName]['options'][$optionCode]['events'] ??= [];
            $units[$unitName]['options'][$optionCode]['events'][$this->eventSignature($event)] = $event;
        }

        $normalizedSchedules = [];

        foreach ($schedules as $schedule) {
            $events = $this->deduplicateEvents($schedule['events']);

            usort($events, fn (array $left, array $right): int => [$left['dayIndex'], $left['startMinutes']] <=> [$right['dayIndex'], $right['startMinutes']]);

            $metrics = $this->calculateMetrics($events);

            $normalizedSchedules[] = [
                'key' => $schedule['key'],
                'events' => $events,
                'metrics' => $metrics,
            ];
        }

        usort($normalizedSchedules, fn (array $left, array $right): int => $left['metrics']['score'] <=> $right['metrics']['score']);

        $normalizedUnits = [];

        foreach ($units as $unit) {
            $options = [];

            foreach ($unit['options'] as $option) {
                $events = array_values($option['events']);
                usort($events, fn (array $left, array $right): int => [$left['dayIndex'], $left['startMinutes']] <=> [$right['dayIndex'], $right['startMinutes']]);

                $options[] = [
                    'code' => $option['code'],
                    'events' => $events,
                ];
            }

            usort($options, fn (array $left, array $right): int => $left['code'] <=> $right['code']);

            $mandatoryEvents = array_values($unit['mandatoryEvents']);
            usort($mandatoryEvents, fn (array $left, array $right): int => [$left['dayIndex'], $left['startMinutes']] <=> [$right['dayIndex'], $right['startMinutes']]);

            $normalizedUnits[] = [
                'name' => $unit['name'],
                'mandatoryEvents' => $mandatoryEvents,
                'options' => $options,
            ];
        }

        usort($normalizedUnits, fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        return [
            'schedules' => $normalizedSchedules,
            'units' => $normalizedUnits,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateEvents(array $events): array
    {
        $deduplicated = [];

        foreach ($events as $event) {
            $deduplicated[$this->eventSignature($event)] = $event;
        }

        return array_values($deduplicated);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function eventSignature(array $event): string
    {
        return implode('|', [
            $event['unit'],
            $event['optionCode'],
            $event['classType'],
            $event['dayIndex'],
            $event['start'],
            $event['end'],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array{
     *     daysCount: int,
     *     gaps: int,
     *     earliest: string|null,
     *     latest: string|null,
     *     totalMinutes: int,
     *     balance: int,
     *     score: int
     * }
     */
    private function calculateMetrics(array $events): array
    {
        if ($events === []) {
            return [
                'daysCount' => 0,
                'gaps' => 0,
                'earliest' => null,
                'latest' => null,
                'totalMinutes' => 0,
                'balance' => 0,
                'score' => 0,
            ];
        }

        $days = [];
        $eventsPerDay = [];
        $earliest = null;
        $latest = null;
        $totalMinutes = 0;

        foreach ($events as $event) {
            $dayIndex = $event['dayIndex'];

            $days[$dayIndex] = true;
            $eventsPerDay[$dayIndex] ??= [];
            $eventsPerDay[$dayIndex][] = $event;

            $eventStart = $event['startMinutes'];
            $eventEnd = $event['endMinutes'];

            $earliest = $earliest === null ? $eventStart : min($earliest, $eventStart);
            $latest = $latest === null ? $eventEnd : max($latest, $eventEnd);
            $totalMinutes += max(0, $eventEnd - $eventStart);
        }

        $gaps = 0;
        $counts = [];

        foreach ($eventsPerDay as $dayEvents) {
            usort($dayEvents, fn (array $left, array $right): int => $left['startMinutes'] <=> $right['startMinutes']);

            $counts[] = count($dayEvents);

            for ($index = 1; $index < count($dayEvents); $index++) {
                $gap = $dayEvents[$index]['startMinutes'] - $dayEvents[$index - 1]['endMinutes'];

                if ($gap > 0) {
                    $gaps += $gap;
                }
            }
        }

        $balance = $counts === [] ? 0 : max($counts) - min($counts);
        $span = max(0, ($latest ?? 0) - ($earliest ?? 0));
        $score = (count($days) * 100) + ($gaps * 2) + $span + ($balance * 20);

        return [
            'daysCount' => count($days),
            'gaps' => $gaps,
            'earliest' => $earliest === null ? null : $this->fromMinutes($earliest),
            'latest' => $latest === null ? null : $this->fromMinutes($latest),
            'totalMinutes' => $totalMinutes,
            'balance' => $balance,
            'score' => $score,
        ];
    }

    private function toMinutes(string $time): int
    {
        [$hour, $minute] = explode(':', $time);

        return (((int) $hour) * 60) + (int) $minute;
    }

    private function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
