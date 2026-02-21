<?php

declare(strict_types=1);

namespace App\Actions;

final readonly class GenerateAutomaticSchedulesFromTextRowsAction
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
     * @return array<int, array{
     *     schedule_key: string,
     *     unit: string,
     *     option_code: string,
     *     class_type: string,
     *     day: string,
     *     day_index: int,
     *     start: string,
     *     end: string,
     *     mandatory: bool
     * }>
     */
    public function handle(array $rows, int $maxSchedules = 300): array
    {
        $mandatoryRows = [];
        $groups = [];

        foreach ($rows as $row) {
            if (($row['mandatory'] ?? false) === true) {
                $mandatoryRows[] = $row;

                continue;
            }

            $unit = strtoupper(trim((string) ($row['unit'] ?? '')));
            $classType = strtoupper(trim((string) ($row['class_type'] ?? '')));
            $optionCode = strtoupper(trim((string) ($row['option_code'] ?? '')));

            if ($unit === '' || $classType === '' || $optionCode === '') {
                continue;
            }

            $groupKey = $unit.'::'.$classType;
            $eventKey = $this->eventKey($row);
            $groups[$groupKey][$optionCode][$eventKey] = $row;
        }

        $normalizedGroups = [];

        foreach ($groups as $options) {
            $normalizedOptions = [];

            foreach ($options as $optionRows) {
                $normalizedOptions[] = array_values($optionRows);
            }

            if ($normalizedOptions !== []) {
                $normalizedGroups[] = $normalizedOptions;
            }
        }

        $validSchedules = $this->buildBalancedSchedules(
            $normalizedGroups,
            $mandatoryRows,
            max(1, $maxSchedules),
        );

        if ($validSchedules === [] && $rows !== []) {
            return $this->rowsForSchedule('Manual 1', $rows);
        }

        $result = [];

        foreach ($validSchedules as $index => $scheduleRows) {
            $result = [...$result, ...$this->rowsForSchedule(sprintf('Auto %03d', $index + 1), $scheduleRows)];
        }

        return $result;
    }

    /**
     * @param array<int, array<int, array<int, array<string, mixed>>>> $groups
     * @param array<int, array<string, mixed>> $mandatoryRows
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildBalancedSchedules(array $groups, array $mandatoryRows, int $maxSchedules): array
    {
        if ($groups === []) {
            return [$mandatoryRows];
        }

        $primaryGroup = array_shift($groups);

        if (! is_array($primaryGroup) || $primaryGroup === []) {
            return [];
        }

        $branches = [];
        $perOptionQuota = max(1, (int) ceil($maxSchedules / count($primaryGroup)));

        foreach ($primaryGroup as $optionRows) {
            $branchSchedules = [];

            $this->buildSchedules(
                $groups,
                $mandatoryRows,
                0,
                $optionRows,
                $branchSchedules,
                $perOptionQuota,
            );

            if ($branchSchedules !== []) {
                $branches[] = $branchSchedules;
            }
        }

        if ($branches === []) {
            return [];
        }

        $merged = [];
        $cursor = 0;

        while (count($merged) < $maxSchedules) {
            $addedInRound = false;

            foreach ($branches as $branchSchedules) {
                if (! array_key_exists($cursor, $branchSchedules)) {
                    continue;
                }

                $merged[] = $branchSchedules[$cursor];
                $addedInRound = true;

                if (count($merged) >= $maxSchedules) {
                    break;
                }
            }

            if (! $addedInRound) {
                break;
            }

            $cursor++;
        }

        return $merged;
    }

    /**
     * @param array<int, array<int, array<int, array<string, mixed>>>> $groups
     * @param array<int, array<string, mixed>> $mandatoryRows
     * @param array<int, array<string, mixed>> $currentRows
     * @param array<int, array<int, array<string, mixed>>> $validSchedules
     */
    private function buildSchedules(
        array $groups,
        array $mandatoryRows,
        int $index,
        array $currentRows,
        array &$validSchedules,
        int $maxSchedules,
    ): void {
        if (count($validSchedules) >= $maxSchedules) {
            return;
        }

        if ($index >= count($groups)) {
            $candidateRows = [...$mandatoryRows, ...$currentRows];

            if (! $this->hasOverlap($candidateRows)) {
                $validSchedules[] = $candidateRows;
            }

            return;
        }

        foreach ($groups[$index] as $optionRows) {
            $candidateRows = [...$currentRows, ...$optionRows];

            if ($this->hasOverlap([...$mandatoryRows, ...$candidateRows])) {
                continue;
            }

            $this->buildSchedules($groups, $mandatoryRows, $index + 1, $candidateRows, $validSchedules, $maxSchedules);

            if (count($validSchedules) >= $maxSchedules) {
                return;
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{
     *     schedule_key: string,
     *     unit: string,
     *     option_code: string,
     *     class_type: string,
     *     day: string,
     *     day_index: int,
     *     start: string,
     *     end: string,
     *     mandatory: bool
     * }>
     */
    private function rowsForSchedule(string $scheduleKey, array $rows): array
    {
        return array_map(fn (array $row): array => [
            'schedule_key' => $scheduleKey,
            'unit' => (string) $row['unit'],
            'option_code' => (string) $row['option_code'],
            'class_type' => (string) $row['class_type'],
            'day' => (string) $row['day'],
            'day_index' => (int) $row['day_index'],
            'start' => (string) $row['start'],
            'end' => (string) $row['end'],
            'mandatory' => (bool) $row['mandatory'],
        ], $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function eventKey(array $row): string
    {
        return implode('|', [
            strtoupper((string) ($row['unit'] ?? '')),
            strtoupper((string) ($row['option_code'] ?? '')),
            strtoupper((string) ($row['class_type'] ?? '')),
            (int) ($row['day_index'] ?? 0),
            (string) ($row['start'] ?? ''),
            (string) ($row['end'] ?? ''),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function hasOverlap(array $rows): bool
    {
        $events = array_map(fn (array $row): array => [
            'day_index' => (int) ($row['day_index'] ?? 0),
            'start_minutes' => $this->toMinutes((string) ($row['start'] ?? '00:00')),
            'end_minutes' => $this->toMinutes((string) ($row['end'] ?? '00:00')),
        ], $rows);

        for ($leftIndex = 0; $leftIndex < count($events); $leftIndex++) {
            for ($rightIndex = $leftIndex + 1; $rightIndex < count($events); $rightIndex++) {
                $left = $events[$leftIndex];
                $right = $events[$rightIndex];

                if ($left['day_index'] !== $right['day_index']) {
                    continue;
                }

                if ($left['start_minutes'] < $right['end_minutes'] && $right['start_minutes'] < $left['end_minutes']) {
                    return true;
                }
            }
        }

        return false;
    }

    private function toMinutes(string $time): int
    {
        [$hour, $minute] = explode(':', $time);

        return (((int) $hour) * 60) + (int) $minute;
    }
}
