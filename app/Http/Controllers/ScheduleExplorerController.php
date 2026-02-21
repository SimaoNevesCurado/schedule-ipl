<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildScheduleCatalogAction;
use App\Actions\GenerateAutomaticSchedulesFromTextRowsAction;
use App\Actions\ReadSchedulesSpreadsheetAction;
use App\Http\Requests\ScheduleExplorerRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ScheduleExplorerController
{
    public function __construct(
        private ReadSchedulesSpreadsheetAction $readSchedulesSpreadsheet,
        private BuildScheduleCatalogAction $buildScheduleCatalog,
        private GenerateAutomaticSchedulesFromTextRowsAction $generateAutomaticSchedulesFromTextRows,
    ) {
        //
    }

    public function index(ScheduleExplorerRequest $request): Response|RedirectResponse
    {
        $validated = $request->validated();
        $sessionTextRows = $request->session()->get('schedules.text.rows');
        $sessionUploadPath = $request->session()->get('schedules.upload.path');
        $sessionUploadName = $request->session()->get('schedules.upload.original_name');

        if (is_array($sessionTextRows) && $sessionTextRows !== []) {
            $generatedRows = $this->generateAutomaticSchedulesFromTextRows->handle($sessionTextRows);
            $generatedCatalog = $this->buildScheduleCatalog->handle($generatedRows);
            $inputCatalog = $this->buildScheduleCatalog->handle($sessionTextRows);

            return Inertia::render('Schedules/Index', [
                'meta' => [
                    'filePath' => 'text://session',
                    'missingFile' => false,
                    'missingDependency' => false,
                    'parseError' => false,
                    'parseErrorMessage' => null,
                    'sheetName' => 'TextInput',
                    'sourceRows' => count($generatedRows),
                    'uploadedFileName' => 'input-texto',
                    'totalSchedules' => count($generatedCatalog['schedules']),
                    'totalUnits' => count($inputCatalog['units']),
                ],
                'schedules' => $generatedCatalog['schedules'],
                'units' => $inputCatalog['units'],
            ]);
        }

        $filePath = $validated['file_path'] ?? (is_string($sessionUploadPath) ? $sessionUploadPath : null);

        if (! is_string($filePath) || $filePath === '') {
            return redirect()->route('turn-selection-export.index');
        }

        $spreadsheetData = $this->readSchedulesSpreadsheet->handle($filePath);

        if (($spreadsheetData['meta']['missingFile'] ?? false) === true) {
            $request->session()->forget('schedules.upload.path');
            $request->session()->forget('schedules.upload.original_name');

            return redirect()->route('turn-selection-export.index')->withErrors([
                'selection_text' => 'O ficheiro gerado já não está disponível. Cola novamente o texto dos turnos.',
            ]);
        }

        $catalog = $this->buildScheduleCatalog->handle($spreadsheetData['rows']);

        return Inertia::render('Schedules/Index', [
            'meta' => [
                ...$spreadsheetData['meta'],
                'uploadedFileName' => is_string($sessionUploadName) ? $sessionUploadName : null,
                'totalSchedules' => count($catalog['schedules']),
                'totalUnits' => count($catalog['units']),
            ],
            'schedules' => $catalog['schedules'],
            'units' => $catalog['units'],
        ]);
    }
}
