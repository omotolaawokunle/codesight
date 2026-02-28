<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'repository_id'   => ['required', 'integer', 'exists:repositories,id'],
            'query'           => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'repository_id.exists' => 'The selected repository does not exist.',
            'query.max'            => 'Your question must not exceed 2,000 characters.',
            'conversation_id.exists' => 'The selected conversation does not exist.',
        ];
    }
}
