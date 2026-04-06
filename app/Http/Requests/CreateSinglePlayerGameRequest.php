<?php

namespace App\Http\Requests;

use App\Enums\AnswerMode;
use App\Enums\Difficulty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSinglePlayerGameRequest extends FormRequest
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
            'artist_id' => ['required', 'integer', 'exists:artists,id'],
            'difficulty' => ['required', 'string', Rule::enum(Difficulty::class)],
            'answer_mode' => ['sometimes', 'string', Rule::enum(AnswerMode::class)],
        ];
    }
}
