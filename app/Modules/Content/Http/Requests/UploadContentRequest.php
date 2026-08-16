<?php

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Storage\AudioStorage;
use App\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

class UploadContentRequest extends FormRequest
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
        $maxKb = (int) app(SettingsService::class)->get('audio_max_upload_mb') * 1024;

        return [
            'file' => ['required', 'file', 'extensions:'.implode(',', AudioStorage::allowedExtensions()), "max:{$maxKb}"],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
