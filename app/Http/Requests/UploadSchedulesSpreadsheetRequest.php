<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UploadSchedulesSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'spreadsheet' => ['required', 'file', 'mimes:xlsx', 'max:12288'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'spreadsheet.required' => 'Seleciona um ficheiro Excel.',
            'spreadsheet.mimes' => 'O ficheiro tem de ser .xlsx.',
            'spreadsheet.max' => 'O ficheiro nao pode ultrapassar 12MB.',
        ];
    }
}
