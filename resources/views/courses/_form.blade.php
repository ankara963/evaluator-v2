@php
    /** @var \App\Models\Course $course */
    $buttonLabel = $buttonLabel ?? 'Save Course';
    $hasCourseErrors = $errors->has('code')
        || $errors->has('title')
        || $errors->has('semester')
        || $errors->has('lecture_hours')
        || $errors->has('laboratory_hours')
        || $errors->has('credit_units')
        || $errors->has('prerequisite_course_ids')
        || $errors->has('prerequisite_course_ids.*');
@endphp

@if ($hasCourseErrors)
    <section class="error-box">
        <strong>Course could not be saved.</strong>
        <ul>
            @error('code') <li>{{ $message }}</li> @enderror
            @error('title') <li>{{ $message }}</li> @enderror
            @error('semester') <li>{{ $message }}</li> @enderror
            @error('lecture_hours') <li>{{ $message }}</li> @enderror
            @error('laboratory_hours') <li>{{ $message }}</li> @enderror
            @error('credit_units') <li>{{ $message }}</li> @enderror
            @error('prerequisite_course_ids') <li>{{ $message }}</li> @enderror
            @error('prerequisite_course_ids.*') <li>{{ $message }}</li> @enderror
        </ul>
    </section>
@endif

<div class="form-grid">
    <label class="field">
        <span>Course Code</span>
        <input type="text" name="code" value="{{ old('code', $course->code) }}" required>
    </label>

    <label class="field field-wide">
        <span>Course Title</span>
        <input type="text" name="title" value="{{ old('title', $course->title) }}" required>
    </label>

    <label class="field">
        <span>Semester</span>
        <select name="semester" required>
            @foreach (($semesters ?? range(1, 8)) as $semester)
                <option value="{{ $semester }}" @selected((int) old('semester', $course->semester ?? 1) === (int) $semester)>
                    Semester {{ $semester }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="field">
        <span>Lecture Hours</span>
        <input type="number" step="0.01" min="0" name="lecture_hours" value="{{ old('lecture_hours', $course->lecture_hours) }}" required>
    </label>

    <label class="field">
        <span>Laboratory Hours</span>
        <input type="number" step="0.01" min="0" name="laboratory_hours" value="{{ old('laboratory_hours', $course->laboratory_hours) }}" required>
    </label>

    <label class="field">
        <span>Credit Units</span>
        <input type="number" step="0.01" min="0" name="credit_units" value="{{ old('credit_units', $course->credit_units) }}" required>
    </label>

    <label class="toggle">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $course->is_active))>
        <span>Active course</span>
    </label>
</div>

<section class="prerequisite-panel">
    <div class="section-title">
        <h2>Prerequisites</h2>
        <span>{{ count($selectedPrerequisiteIds) }} selected</span>
    </div>

    @if ($availablePrerequisites->isEmpty())
        <p class="muted-note">Create more courses first, then assign prerequisites here.</p>
    @else
        <div class="prerequisite-grid">
            @foreach ($availablePrerequisites as $availableCourse)
                <label class="prerequisite-option">
                    <input
                        type="checkbox"
                        name="prerequisite_course_ids[]"
                        value="{{ $availableCourse->id }}"
                        @checked(in_array($availableCourse->id, old('prerequisite_course_ids', $selectedPrerequisiteIds), true))
                    >
                    <span>
                        <strong>{{ $availableCourse->code }}</strong>
                        <small>Semester {{ $availableCourse->semester }} - {{ $availableCourse->title }}</small>
                    </span>
                </label>
            @endforeach
        </div>
    @endif
</section>

<div class="action-row">
    <button class="button" type="submit">{{ $buttonLabel }}</button>
    @if (! empty($editingCourse))
        <a class="secondary-link" href="{{ route('grade-evaluator.index') }}#courses">Cancel edit</a>
    @endif
</div>
