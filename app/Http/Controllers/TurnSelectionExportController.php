<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ParseTurnSelectionTextAction;
use App\Http\Requests\TurnSelectionExportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final readonly class TurnSelectionExportController
{
    public function __construct(
        private ParseTurnSelectionTextAction $parseTurnSelectionText,
    ) {
        //
    }

    public function index(): View
    {
        return view('turns-export');
    }

    public function apply(TurnSelectionExportRequest $request): RedirectResponse
    {
        $parsed = $this->parseTurnSelectionText->handle((string) $request->validated('selection_text'));

        if ($parsed['sessions'] === []) {
            return back()->withErrors([
                'selection_text' => 'Indica pelo menos uma linha com dia e hora. Exemplo: Seg 08:00-10:00 API T1',
            ])->withInput();
        }

        $rows = $this->buildRowsFromSessions($parsed['sessions']);

        $request->session()->put('schedules.text.rows', $rows);
        $request->session()->put('schedules.text.source', 'texto');
        $request->session()->forget('schedules.upload.path');
        $request->session()->forget('schedules.upload.original_name');

        return redirect()->route('schedules.index');
    }

    /**
     * @param array<int, array{day: string, start: string, end: string, unit: string, classType: string, optionCode: string|null}> $sessions
     * @return array<int, array{schedule_key: string, unit: string, option_code: string, class_type: string, day: string, day_index: int, start: string, end: string, mandatory: bool}>
     */
    private function buildRowsFromSessions(array $sessions): array
    {
        $dayIndexMap = [
            'SEG' => 1,
            'TER' => 2,
            'QUA' => 3,
            'QUI' => 4,
            'SEX' => 5,
            'SAB' => 6,
            'DOM' => 7,
        ];

        $rows = [];

        foreach ($sessions as $session) {
            $day = $session['day'];
            $optionCode = $session['optionCode'] ?? $session['classType'];

            $rows[] = [
                'schedule_key' => 'Selecao Manual (texto)',
                'unit' => $session['unit'],
                'option_code' => $optionCode,
                'class_type' => $session['classType'],
                'day' => $day,
                'dayIndex' => $dayIndexMap[$day] ?? 0,
                'start' => $session['start'],
                'end' => $session['end'],
                'mandatory' => $session['classType'] === 'OBR',
            ];
        }

        return array_map(fn (array $row): array => [
            'schedule_key' => $row['schedule_key'],
            'unit' => $row['unit'],
            'option_code' => $row['option_code'],
            'class_type' => $row['class_type'],
            'day' => $row['day'],
            'day_index' => $row['dayIndex'],
            'start' => $row['start'],
            'end' => $row['end'],
            'mandatory' => $row['mandatory'],
        ], $rows);
    }
}
