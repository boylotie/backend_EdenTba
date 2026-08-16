<?php

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Programmation d'un message d'administration (US-040) : `scheduled_at` doit
 * être une date future (A2 : date passée refusée en 422).
 */
class ScheduleNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
