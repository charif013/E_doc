<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignDocumentNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'doc_number' => ['required', 'string', 'max:255'],
            'running_number' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
