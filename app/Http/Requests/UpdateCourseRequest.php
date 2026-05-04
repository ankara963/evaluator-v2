<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('courses', 'code')->ignore($this->route('course')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'semester' => ['required', 'integer', 'min:1', 'max:'.Course::MAX_SEMESTER],
            'lecture_hours' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'laboratory_hours' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'credit_units' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'is_active' => ['nullable', 'boolean'],
            'prerequisite_course_ids' => ['nullable', 'array'],
            'prerequisite_course_ids.*' => [
                'integer',
                Rule::exists('courses', 'id'),
                Rule::notIn([$this->route('course')?->id]),
            ],
        ];
    }
}
