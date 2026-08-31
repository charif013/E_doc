<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'room_id' => ['required', 'exists:rooms,id'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'booking_type' => ['required', 'in:meeting,general_use,maintenance'],
            'description' => ['nullable', 'string', 'max:5000'],
            'invitees' => ['nullable', 'array'],
            'invitees.*' => ['integer', 'distinct', 'exists:users,id'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
        ];
    }
}
