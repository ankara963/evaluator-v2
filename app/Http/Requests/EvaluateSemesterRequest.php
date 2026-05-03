<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EvaluateSemesterRequest extends FormRequest
{
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
            'semester' => ['required', 'integer', 'min:1', 'max:12'],
            'grades' => ['nullable', 'array'],
            'grades.*' => ['nullable', 'string', 'max:20'],
            'use_ai' => ['nullable', 'boolean'],
        ];
    }
}
