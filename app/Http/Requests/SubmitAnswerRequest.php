<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
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
            'guessed_track_id' => ['nullable', 'integer', 'exists:tracks,id', 'required_without:text_guess'],
            'text_guess' => ['nullable', 'string', 'max:255', 'required_without:guessed_track_id'],
            'answer_time_ms' => ['required', 'integer', 'min:0', 'max:120000'],
        ];
    }
}
