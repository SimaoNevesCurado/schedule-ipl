<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TurnSelectionExportRequest extends FormRequest
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
            'selection_text' => ['required', 'string', 'min:4', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'selection_text.required' => 'Cola o texto com os turnos para exportar.',
            'selection_text.min' => 'O texto parece demasiado curto.',
        ];
    }
}
