<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeErrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'repository_id' => ['required', 'integer', 'exists:repositories,id'],
            'error_log'     => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'repository_id.exists' => 'The selected repository does not exist.',
            'error_log.max'        => 'The error log must not exceed 10,000 characters.',
        ];
    }
}
