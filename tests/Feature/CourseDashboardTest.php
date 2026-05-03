<?php

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the course dashboard lists saved courses', function () {
    $course = Course::factory()->create([
        'code' => 'MATH101',
        'title' => 'College Algebra',
    ]);

    $dependentCourse = Course::factory()->create([
        'code' => 'MATH102',
        'title' => 'Trigonometry',
    ]);

    $dependentCourse->prerequisites()->sync([$course->id]);

    $this->get(route('grade-evaluator.index'))
        ->assertOk()
        ->assertSee('Course Dashboard')
        ->assertSee('MATH101')
        ->assertSee('MATH102')
        ->assertSee('Semester')
        ->assertSee('College Algebra')
        ->assertSee('Trigonometry');
});

test('the course dashboard route redirects into the main dashboard section', function () {
    $this->get(route('courses.index'))
        ->assertRedirect(route('grade-evaluator.index').'#courses');
});

test('a course can be created with prerequisites', function () {
    $prerequisites = Course::factory()->count(2)->create();

    $response = $this->post(route('courses.store'), [
        'code' => 'CS201',
        'title' => 'Data Structures',
        'semester' => 3,
        'lecture_hours' => 2,
        'laboratory_hours' => 1,
        'credit_units' => 3,
        'is_active' => '1',
        'prerequisite_course_ids' => $prerequisites->pluck('id')->all(),
    ]);

    $course = Course::query()->where('code', 'CS201')->firstOrFail();

    $response->assertRedirect(route('grade-evaluator.index').'#courses');
    expect($course->title)->toBe('Data Structures');
    expect($course->semester)->toBe(3);
    expect($course->prerequisites()->pluck('courses.id')->all())
        ->toEqualCanonicalizing($prerequisites->pluck('id')->all());
});

test('a course can be updated and prerequisites can be replaced', function () {
    $course = Course::factory()->create([
        'code' => 'CS202',
        'title' => 'Old Title',
    ]);
    $oldPrerequisite = Course::factory()->create(['code' => 'CS101']);
    $newPrerequisite = Course::factory()->create(['code' => 'CS102']);

    $course->prerequisites()->sync([$oldPrerequisite->id]);

    $response = $this->put(route('courses.update', $course), [
        'code' => 'cs202',
        'title' => 'Algorithms',
        'semester' => 4,
        'lecture_hours' => 3,
        'laboratory_hours' => 0,
        'credit_units' => 3,
        'prerequisite_course_ids' => [$newPrerequisite->id],
    ]);

    $response->assertRedirect(route('grade-evaluator.index').'#courses');

    $course->refresh();

    expect($course->code)->toBe('CS202');
    expect($course->title)->toBe('Algorithms');
    expect($course->semester)->toBe(4);
    expect($course->is_active)->toBeFalse();
    expect($course->prerequisites()->pluck('courses.id')->all())->toBe([$newPrerequisite->id]);
});

test('a course can be deleted from the dashboard', function () {
    $course = Course::factory()->create();

    $response = $this->delete(route('courses.destroy', $course));

    $response->assertRedirect(route('grade-evaluator.index').'#courses');
    $this->assertDatabaseMissing('courses', ['id' => $course->id]);
});
