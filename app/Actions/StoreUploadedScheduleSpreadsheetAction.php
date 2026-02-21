<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class StoreUploadedScheduleSpreadsheetAction
{
    public function handle(UploadedFile $file): string
    {
        $fileName = sprintf('%s-%s.xlsx', now()->format('YmdHis'), Str::random(8));
        $storedPath = $file->storeAs('uploads/schedules', $fileName, 'local');

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('Falha ao guardar o ficheiro Excel.');
        }

        return Storage::disk('local')->path($storedPath);
    }
}
