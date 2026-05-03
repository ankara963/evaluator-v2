<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255', 'unique:courses,code'],
            'title' => ['required', 'string', 'max:255'],
            'semester' => ['required', 'integer', 'min:1', 'max:12'],
            'lecture_hours' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'laboratory_hours' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'credit_units' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'is_active' => ['nullable', 'boolean'],
            'prerequisite_course_ids' => ['nullable', 'array'],
            'prerequisite_course_ids.*' => ['integer', Rule::exists('courses', 'id')],
        ];
    }
}
