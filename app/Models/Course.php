<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    public const int MAX_SEMESTER = 4;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'title',
        'semester',
        'lecture_hours',
        'laboratory_hours',
        'credit_units',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'lecture_hours' => 'decimal:2',
        'laboratory_hours' => 'decimal:2',
        'credit_units' => 'decimal:2',
        'semester' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $course): void {
            $course->code = mb_strtoupper(trim($course->code));
            $course->title = trim($course->title);
            $course->semester = min(self::MAX_SEMESTER, max(1, (int) $course->semester));
        });
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'course_prerequisite',
            'course_id',
            'prerequisite_course_id',
        )->orderBy('code');
    }

    public function dependentCourses(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'course_prerequisite',
            'prerequisite_course_id',
            'course_id',
        )->orderBy('code');
    }
}
