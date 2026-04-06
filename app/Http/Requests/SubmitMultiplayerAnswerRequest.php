<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitMultiplayerAnswerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'guessed_track_id' => ['nullable', 'integer', 'exists:tracks,id'],
            'text_guess' => ['nullable', 'string', 'max:255'],
            'answer_time_ms' => ['required', 'integer', 'min:0', 'max:300000'],
        ];
    }
}
