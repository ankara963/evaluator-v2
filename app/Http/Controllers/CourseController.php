<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;

class CourseController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to(route('grade-evaluator.index').'#courses');
    }

    public function create(): RedirectResponse
    {
        return redirect()->to(route('grade-evaluator.index').'#courses');
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $course = Course::create($this->courseAttributes($request->validated()));
        $course->prerequisites()->sync($request->validated('prerequisite_course_ids', []));

        return redirect()
            ->to(route('grade-evaluator.index').'#courses')
            ->with('status', "Course {$course->code} created.");
    }

    public function edit(Course $course): RedirectResponse
    {
        return redirect()->to(route('grade-evaluator.index', [
            'edit_course' => $course->id,
        ]).'#courses');
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $course->update($this->courseAttributes($request->validated()));
        $course->prerequisites()->sync($request->validated('prerequisite_course_ids', []));

        return redirect()
            ->to(route('grade-evaluator.index').'#courses')
            ->with('status', "Course {$course->code} updated.");
    }

    public function destroy(Course $course): RedirectResponse
    {
        $code = $course->code;
        $course->delete();

        return redirect()
            ->to(route('grade-evaluator.index').'#courses')
            ->with('status', "Course {$code} deleted.");
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function courseAttributes(array $validated): array
    {
        return [
            'code' => $validated['code'],
            'title' => $validated['title'],
            'semester' => $validated['semester'],
            'lecture_hours' => $validated['lecture_hours'],
            'laboratory_hours' => $validated['laboratory_hours'],
            'credit_units' => $validated['credit_units'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
