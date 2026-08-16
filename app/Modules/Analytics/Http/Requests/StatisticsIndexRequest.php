<?php

namespace App\Modules\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paramètres de consultation des statistiques d'écoute (MOD-12-P1, US-048).
 */
class StatisticsIndexRequest extends FormRequest
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
            'period' => ['sometimes', 'string', 'in:7d,30d,90d'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function period(): string
    {
        return $this->input('period', '30d');
    }

    public function limit(): int
    {
        return (int) $this->input('limit', 10);
    }
}
