<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $numbering = optional($this->route('id')
            ? \App\Models\LeaveRequest::find($this->route('id'))
            : null)->workflow_status === 'pending_numbering';

        return [
            'is_approved' => ['required', 'boolean'],
            'pin' => ['required_if:is_approved,1', 'nullable', 'string'],
            'reject_reason' => ['required_if:is_approved,0', 'nullable', 'string', 'max:2000'],
            'running_number' => [$numbering ? 'required' : 'nullable', 'integer', 'min:1'],
            'leave_number' => [$numbering ? 'required' : 'nullable', 'string', 'max:100'],
        ];
    }
}
