<?php

namespace App;

use App\Models\Course;
use Illuminate\Support\Collection;

class GradeSheetEvaluator
{
    private const PASSING_GRADE = 75.0;

    /**
     * @param array{
     *     sheet_name: string,
     *     headers: array<string, string>,
     *     records: list<array{
     *         row_number: int,
     *         subject_code: string,
     *         subject_description: string,
     *         prerequisite: string,
     *         lecture_hours: string,
     *         laboratory_hours: string,
     *         credit_units: string,
     *         grade: string
     *     }>
     * } $worksheet
     * @return array{
     *     sheet_name: string,
     *     passing_grade: float,
     *     overall_status: string,
     *     overall_status_label: string,
     *     total_subjects: int,
     *     passed_subjects: int,
     *     failed_subjects: int,
     *     missing_grade_subjects: int,
     *     blocked_subjects: int,
     *     total_credit_units: float,
     *     passed_credit_units: float,
     *     failed_credit_units: float,
     *     numeric_grade_average: float|null,
     *     failed_subject_codes: list<string>,
     *     blocked_subject_codes: list<string>,
     *     records: list<array{
     *         row_number: int,
     *         subject_code: string,
     *         subject_description: string,
     *         prerequisite: string,
     *         lecture_hours: string,
     *         laboratory_hours: string,
     *         credit_units: string,
     *         credit_units_value: float,
     *         grade: string,
     *         numeric_grade: float|null,
     *         status: string,
     *         status_label: string,
     *         reason_code: string,
     *         reason: string,
     *         is_blocked_for_next_term: bool,
     *         blocking_subject_codes: list<string>
     *     }>
     * }
     */
    public function evaluate(array $worksheet): array
    {
        $savedCourses = $this->savedCoursesByCode($worksheet['records']);

        $evaluatedRecords = array_map(
            fn (array $record): array => $this->evaluateRecord($record),
            $worksheet['records'],
        );

        $failedSubjectCodes = array_values(array_map(
            fn (array $record): string => $record['subject_code'],
            array_filter(
                $evaluatedRecords,
                fn (array $record): bool => $record['status'] === 'fail',
            ),
        ));

        $records = array_map(
            fn (array $record): array => $this->applyPrerequisiteBlocking($record, $failedSubjectCodes, $savedCourses),
            $evaluatedRecords,
        );

        $failedRecords = array_values(array_filter(
            $records,
            fn (array $record): bool => $record['status'] === 'fail',
        ));

        $passedRecords = array_values(array_filter(
            $records,
            fn (array $record): bool => $record['status'] === 'pass',
        ));

        $numericGrades = array_values(array_filter(
            array_map(
                fn (array $record): ?float => $record['numeric_grade'],
                $records,
            ),
            fn (?float $grade): bool => $grade !== null,
        ));

        return [
            'evaluation_type' => 'workbook',
            'sheet_name' => $worksheet['sheet_name'],
            'semester' => null,
            'passing_grade' => self::PASSING_GRADE,
            'overall_status' => $failedRecords === [] ? 'pass' : 'fail',
            'overall_status_label' => $failedRecords === [] ? 'PASS' : 'FAIL',
            'can_proceed' => $failedRecords === [],
            'progression_status_label' => $failedRecords === [] ? 'Can proceed' : 'Cannot fully proceed',
            'total_subjects' => count($records),
            'passed_subjects' => count($passedRecords),
            'failed_subjects' => count($failedRecords),
            'missing_grade_subjects' => count(array_filter(
                $failedRecords,
                fn (array $record): bool => $record['reason_code'] === 'missing_grade',
            )),
            'blocked_subjects' => count(array_filter(
                $records,
                fn (array $record): bool => $record['is_blocked_for_next_term'],
            )),
            'total_credit_units' => $this->sumCreditUnits($records),
            'passed_credit_units' => $this->sumCreditUnits($passedRecords),
            'failed_credit_units' => $this->sumCreditUnits($failedRecords),
            'numeric_grade_average' => $numericGrades === []
                ? null
                : round(array_sum($numericGrades) / count($numericGrades), 2),
            'failed_subject_codes' => $failedSubjectCodes,
            'blocked_subject_codes' => array_values(array_map(
                fn (array $record): string => $record['subject_code'],
                array_filter(
                    $records,
                    fn (array $record): bool => $record['is_blocked_for_next_term'],
                ),
            )),
            'blocked_courses' => [],
            'records' => $records,
        ];
    }

    /**
     * @param Collection<int, Course> $courses
     * @param array<int|string, string|null> $grades
     * @return array<string, mixed>
     */
    public function evaluateSemester(int $semester, Collection $courses, array $grades): array
    {
        $worksheet = [
            'sheet_name' => "Semester {$semester}",
            'headers' => [],
            'records' => $courses
                ->values()
                ->map(fn (Course $course, int $index): array => $this->recordForCourse($course, $grades, $index + 1))
                ->all(),
        ];

        $evaluatedRecords = array_map(
            fn (array $record): array => $this->evaluateRecord($record),
            $worksheet['records'],
        );

        $failedSubjectCodes = array_values(array_map(
            fn (array $record): string => $record['subject_code'],
            array_filter(
                $evaluatedRecords,
                fn (array $record): bool => $record['status'] === 'fail',
            ),
        ));

        $blockedCourses = $this->blockedFutureCourses($semester, $failedSubjectCodes);
        $blockedCourseData = $this->blockedCourseData($blockedCourses, $failedSubjectCodes);
        $records = array_map(
            fn (array $record): array => $this->applyFutureBlocking($record, $blockedCourseData),
            $evaluatedRecords,
        );

        $failedRecords = array_values(array_filter(
            $records,
            fn (array $record): bool => $record['status'] === 'fail',
        ));

        $passedRecords = array_values(array_filter(
            $records,
            fn (array $record): bool => $record['status'] === 'pass',
        ));

        $numericGrades = array_values(array_filter(
            array_map(
                fn (array $record): ?float => $record['numeric_grade'],
                $records,
            ),
            fn (?float $grade): bool => $grade !== null,
        ));

        $canProceed = $failedRecords === [];
        $hasBlockedFutureCourses = $blockedCourseData !== [];

        return [
            'evaluation_type' => 'semester',
            'sheet_name' => $worksheet['sheet_name'],
            'semester' => $semester,
            'passing_grade' => self::PASSING_GRADE,
            'overall_status' => $canProceed ? 'pass' : 'fail',
            'overall_status_label' => $canProceed
                ? 'CAN PROCEED'
                : ($hasBlockedFutureCourses ? 'PROCEED WITH RESTRICTIONS' : 'RETAKE REQUIRED'),
            'can_proceed' => $canProceed,
            'progression_status_label' => $canProceed
                ? 'Can proceed to the next semester'
                : ($hasBlockedFutureCourses ? 'Can proceed only to unblocked subjects' : 'Retake failed subjects'),
            'total_subjects' => count($records),
            'passed_subjects' => count($passedRecords),
            'failed_subjects' => count($failedRecords),
            'missing_grade_subjects' => count(array_filter(
                $failedRecords,
                fn (array $record): bool => $record['reason_code'] === 'missing_grade',
            )),
            'blocked_subjects' => count($blockedCourseData),
            'total_credit_units' => $this->sumCreditUnits($records),
            'passed_credit_units' => $this->sumCreditUnits($passedRecords),
            'failed_credit_units' => $this->sumCreditUnits($failedRecords),
            'numeric_grade_average' => $numericGrades === []
                ? null
                : round(array_sum($numericGrades) / count($numericGrades), 2),
            'failed_subject_codes' => $failedSubjectCodes,
            'blocked_subject_codes' => array_values(array_map(
                fn (array $course): string => $course['code'],
                $blockedCourseData,
            )),
            'blocked_courses' => $blockedCourseData,
            'records' => $records,
        ];
    }

    /**
     * @param array{
     *     row_number: int,
     *     subject_code: string,
     *     subject_description: string,
     *     prerequisite: string,
     *     lecture_hours: string,
     *     laboratory_hours: string,
     *     credit_units: string,
     *     grade: string
     * } $record
     * @return array{
     *     row_number: int,
     *     subject_code: string,
     *     subject_description: string,
     *     prerequisite: string,
     *     lecture_hours: string,
     *     laboratory_hours: string,
     *     credit_units: string,
     *     credit_units_value: float,
     *     grade: string,
     *     numeric_grade: float|null,
     *     status: string,
     *     status_label: string,
     *     reason_code: string,
     *     reason: string,
     *     is_blocked_for_next_term: bool,
     *     blocking_subject_codes: list<string>
     * }
     */
    private function evaluateRecord(array $record): array
    {
        $creditUnitsValue = $this->number($record['credit_units']) ?? 0.0;
        $grade = trim($record['grade']);
        $numericGrade = $this->number($grade);
        $status = 'pass';
        $statusLabel = 'Pass';
        $reasonCode = 'passed';
        $reason = 'Meets the passing threshold.';

        if ($grade === '') {
            $status = 'fail';
            $statusLabel = 'Fail';
            $reasonCode = 'missing_grade';
            $reason = 'Missing grade. Blank grades automatically fail.';
        } elseif ($numericGrade === null) {
            $status = 'fail';
            $statusLabel = 'Fail';
            $reasonCode = 'invalid_grade';
            $reason = 'Grade is not numeric, so it cannot be evaluated as a pass.';
        } elseif ($numericGrade < self::PASSING_GRADE) {
            $status = 'fail';
            $statusLabel = 'Fail';
            $reasonCode = 'below_passing_grade';
            $reason = 'Grade is below the 75 passing threshold.';
        }

        return [
            'row_number' => $record['row_number'],
            'subject_code' => $record['subject_code'],
            'subject_description' => $record['subject_description'],
            'prerequisite' => $record['prerequisite'],
            'lecture_hours' => $record['lecture_hours'],
            'laboratory_hours' => $record['laboratory_hours'],
            'credit_units' => $record['credit_units'],
            'credit_units_value' => $creditUnitsValue,
            'grade' => $grade,
            'numeric_grade' => $numericGrade,
            'status' => $status,
            'status_label' => $statusLabel,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'is_blocked_for_next_term' => false,
            'blocking_subject_codes' => [],
            'blocks_subject_codes' => [],
        ];
    }

    /**
     * @param array<int|string, string|null> $grades
     * @return array{
     *     row_number: int,
     *     subject_code: string,
     *     subject_description: string,
     *     prerequisite: string,
     *     lecture_hours: string,
     *     laboratory_hours: string,
     *     credit_units: string,
     *     grade: string
     * }
     */
    private function recordForCourse(Course $course, array $grades, int $rowNumber): array
    {
        return [
            'row_number' => $rowNumber,
            'subject_code' => $course->code,
            'subject_description' => $course->title,
            'prerequisite' => $course->prerequisites
                ->pluck('code')
                ->implode(', '),
            'lecture_hours' => (string) $course->lecture_hours,
            'laboratory_hours' => (string) $course->laboratory_hours,
            'credit_units' => (string) $course->credit_units,
            'grade' => (string) ($grades[$course->id] ?? ''),
        ];
    }

    /**
     * @param array{
     *     row_number: int,
     *     subject_code: string,
     *     subject_description: string,
     *     prerequisite: string,
     *     lecture_hours: string,
     *     laboratory_hours: string,
     *     credit_units: string,
     *     credit_units_value: float,
     *     grade: string,
     *     numeric_grade: float|null,
     *     status: string,
     *     status_label: string,
     *     reason_code: string,
     *     reason: string,
     *     is_blocked_for_next_term: bool,
     *     blocking_subject_codes: list<string>
     * } $record
     * @param list<string> $failedSubjectCodes
     * @param array<string, Course> $savedCourses
     * @return array{
     *     row_number: int,
     *     subject_code: string,
     *     subject_description: string,
     *     prerequisite: string,
     *     lecture_hours: string,
     *     laboratory_hours: string,
     *     credit_units: string,
     *     credit_units_value: float,
     *     grade: string,
     *     numeric_grade: float|null,
     *     status: string,
     *     status_label: string,
     *     reason_code: string,
     *     reason: string,
     *     is_blocked_for_next_term: bool,
     *     blocking_subject_codes: list<string>
     * }
     */
    private function applyPrerequisiteBlocking(array $record, array $failedSubjectCodes, array $savedCourses): array
    {
        $blockingSubjectCodes = array_values(array_intersect(
            $this->prerequisiteSubjectCodesForRecord($record, $savedCourses),
            $failedSubjectCodes,
        ));

        if ($blockingSubjectCodes === []) {
            return $record;
        }

        $record['is_blocked_for_next_term'] = true;
        $record['blocking_subject_codes'] = $blockingSubjectCodes;
        $record['reason'] .= ' Cannot be taken next term because prerequisite subjects failed: '.implode(', ', $blockingSubjectCodes).'.';

        return $record;
    }

    /**
     * @param array{
     *     subject_code: string,
     *     prerequisite: string
     * } $record
     * @param array<string, Course> $savedCourses
     * @return list<string>
     */
    private function prerequisiteSubjectCodesForRecord(array $record, array $savedCourses): array
    {
        $subjectCode = mb_strtoupper(trim($record['subject_code']));
        $savedCourse = $savedCourses[$subjectCode] ?? null;

        if ($savedCourse instanceof Course) {
            return $savedCourse->prerequisites
                ->pluck('code')
                ->map(fn (string $code): string => mb_strtoupper(trim($code)))
                ->all();
        }

        return $this->prerequisiteSubjectCodes($record['prerequisite']);
    }

    /**
     * @return list<string>
     */
    private function prerequisiteSubjectCodes(string $prerequisite): array
    {
        preg_match_all('/[A-Z][A-Z0-9-]*/', strtoupper($prerequisite), $matches);

        $subjectCodes = array_values(array_unique(array_filter(
            $matches[0] ?? [],
            fn (string $value): bool => strlen($value) > 1,
        )));

        return $subjectCodes;
    }

    /**
     * @param list<array{
     *     row_number: int,
     *     subject_code: string,
     *     subject_description: string,
     *     prerequisite: string,
     *     lecture_hours: string,
     *     laboratory_hours: string,
     *     credit_units: string,
     *     grade: string
     * }> $records
     * @return array<string, Course>
     */
    private function savedCoursesByCode(array $records): array
    {
        $subjectCodes = array_values(array_unique(array_filter(array_map(
            fn (array $record): string => mb_strtoupper(trim($record['subject_code'])),
            $records,
        ))));

        if ($subjectCodes === []) {
            return [];
        }

        return Course::query()
            ->with('prerequisites:id,code')
            ->where('is_active', true)
            ->whereIn('code', $subjectCodes)
            ->get()
            ->keyBy(fn (Course $course): string => mb_strtoupper($course->code))
            ->all();
    }

    /**
     * @param list<string> $failedSubjectCodes
     * @return Collection<int, Course>
     */
    private function blockedFutureCourses(int $semester, array $failedSubjectCodes): Collection
    {
        if ($failedSubjectCodes === []) {
            return collect();
        }

        return Course::query()
            ->with('prerequisites:id,code,title')
            ->where('is_active', true)
            ->where('semester', '>', $semester)
            ->whereHas(
                'prerequisites',
                fn ($query) => $query->whereIn('code', $failedSubjectCodes),
            )
            ->orderBy('semester')
            ->orderBy('code')
            ->get();
    }

    /**
     * @param Collection<int, Course> $blockedCourses
     * @param list<string> $failedSubjectCodes
     * @return list<array{
     *     code: string,
     *     title: string,
     *     semester: int,
     *     blocking_subject_codes: list<string>
     * }>
     */
    private function blockedCourseData(Collection $blockedCourses, array $failedSubjectCodes): array
    {
        return $blockedCourses
            ->map(function (Course $course) use ($failedSubjectCodes): array {
                $blockingSubjectCodes = $course->prerequisites
                    ->pluck('code')
                    ->map(fn (string $code): string => mb_strtoupper(trim($code)))
                    ->intersect($failedSubjectCodes)
                    ->values()
                    ->all();

                return [
                    'code' => $course->code,
                    'title' => $course->title,
                    'semester' => $course->semester,
                    'blocking_subject_codes' => $blockingSubjectCodes,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $record
     * @param list<array{code: string, blocking_subject_codes: list<string>}> $blockedCourses
     * @return array<string, mixed>
     */
    private function applyFutureBlocking(array $record, array $blockedCourses): array
    {
        if ($record['status'] !== 'fail') {
            return $record;
        }

        $subjectCode = mb_strtoupper(trim($record['subject_code']));
        $blockedSubjectCodes = array_values(array_map(
            fn (array $course): string => $course['code'],
            array_filter(
                $blockedCourses,
                fn (array $course): bool => in_array($subjectCode, $course['blocking_subject_codes'], true),
            ),
        ));

        if ($blockedSubjectCodes === []) {
            return $record;
        }

        $record['blocks_subject_codes'] = $blockedSubjectCodes;
        $record['reason'] .= ' This failed subject blocks future subjects: '.implode(', ', $blockedSubjectCodes).'.';

        return $record;
    }

    private function number(string $value): ?float
    {
        $normalized = str_replace(',', '', trim($value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @param list<array{credit_units_value: float}> $records
     */
    private function sumCreditUnits(array $records): float
    {
        return round(array_sum(array_map(
            fn (array $record): float => $record['credit_units_value'],
            $records,
        )), 2);
    }
}
