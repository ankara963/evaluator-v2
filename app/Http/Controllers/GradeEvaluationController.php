<?php

namespace App\Http\Controllers;

use App\GradeSheetParser;
use App\GradeEvaluationNarrator;
use App\GradeSheetEvaluator;
use App\Http\Requests\EvaluateGradeSheetRequest;
use App\Http\Requests\EvaluateSemesterRequest;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class GradeEvaluationController extends Controller
{
    public function index(Request $request): View
    {
        return view('grade-evaluator', $this->dashboardData(
            $request,
            ['useAi' => true],
        ));
    }

    public function store(
        EvaluateGradeSheetRequest $request,
        GradeSheetParser $parser,
        GradeSheetEvaluator $evaluator,
        GradeEvaluationNarrator $narrator,
    ): View {
        $uploadedFile = $request->file('grade_sheet');

        try {
            $worksheet = $parser->parse(
                $uploadedFile->getRealPath(),
                $uploadedFile->getClientOriginalName(),
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'grade_sheet' => $exception->getMessage(),
            ]);
        }

        $evaluation = $evaluator->evaluate($worksheet);
        $narration = $narrator->narrate($evaluation, $request->boolean('use_ai'));

        return view('grade-evaluator', $this->dashboardData(
            $request,
            [
            'evaluation' => $evaluation,
            'narration' => $narration,
            'uploadedFileName' => $uploadedFile->getClientOriginalName(),
            'useAi' => $request->boolean('use_ai'),
            ],
        ));
    }

    public function evaluateSemester(
        EvaluateSemesterRequest $request,
        GradeSheetEvaluator $evaluator,
        GradeEvaluationNarrator $narrator,
    ): View {
        $semester = $request->integer('semester');
        $courses = Course::query()
            ->with('prerequisites:id,code,title,semester')
            ->where('is_active', true)
            ->where('semester', $semester)
            ->orderBy('code')
            ->get();

        if ($courses->isEmpty()) {
            throw ValidationException::withMessages([
                'semester' => 'Select a semester that has active courses assigned.',
            ]);
        }

        $evaluation = $evaluator->evaluateSemester(
            $semester,
            $courses,
            $request->input('grades', []),
        );
        $narration = $narrator->narrate($evaluation, $request->boolean('use_ai'));

        return view('grade-evaluator', $this->dashboardData(
            $request,
            [
                'evaluation' => $evaluation,
                'narration' => $narration,
                'uploadedFileName' => "Semester {$semester} grades",
                'selectedSemester' => $semester,
                'gradeInput' => $request->input('grades', []),
                'useAi' => $request->boolean('use_ai'),
            ],
        ));
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo implode(',', [
                'Subject Code',
                'Subject Description',
                'Pre Requisite/ Core Requisite',
                'Lecture Hrs',
                'Laboratory Hrs',
                'Credit Units',
                'Grade',
            ]);
            echo "\n";
            echo implode(',', [
                'MATH101',
                'College Algebra',
                '',
                '3',
                '0',
                '3',
                '85',
            ]);
            echo "\n";
            echo implode(',', [
                'MATH102',
                'Trigonometry',
                'MATH101',
                '3',
                '0',
                '3',
                '',
            ]);
            echo "\n";
        }, 'grade-evaluator-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function excelTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo $this->excelTemplateContents();
        }, 'grade-evaluator-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function excelTemplateContents(): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'grade-template');

        if ($temporaryPath === false) {
            throw new RuntimeException('The Excel template could not be created.');
        }

        $archive = new ZipArchive();
        $opened = $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            unlink($temporaryPath);

            throw new RuntimeException('The Excel template could not be created.');
        }

        $archive->addFromString('[Content_Types].xml', $this->workbookContentTypesXml());
        $archive->addFromString('_rels/.rels', $this->workbookRootRelationshipsXml());
        $archive->addFromString('xl/workbook.xml', $this->workbookXml());
        $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml([
            ['A' => 'Subject Code', 'B' => 'Subject Description', 'C' => 'Pre Requisite/ Core Requisite', 'D' => 'Lecture Hrs', 'E' => 'Laboratory Hrs', 'F' => 'Credit Units', 'G' => 'Grade'],
            ['A' => 'MATH101', 'B' => 'College Algebra', 'D' => '3', 'E' => '0', 'F' => '3', 'G' => '85'],
            ['A' => 'MATH102', 'B' => 'Trigonometry', 'C' => 'MATH101', 'D' => '3', 'E' => '0', 'F' => '3'],
        ]));
        $archive->close();

        $contents = file_get_contents($temporaryPath);
        unlink($temporaryPath);

        if ($contents === false) {
            throw new RuntimeException('The Excel template could not be read.');
        }

        return $contents;
    }

    private function workbookContentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;
    }

    private function workbookRootRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Template" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML;
    }

    private function workbookRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function worksheetXml(array $rows): string
    {
        $rowXml = '';

        foreach ($rows as $index => $row) {
            $cellsXml = '';

            foreach ($row as $column => $value) {
                if ($value === '') {
                    continue;
                }

                $reference = $column.($index + 1);
                $cellsXml .= $this->worksheetCellXml($reference, $value);
            }

            $rowXml .= sprintf('<row r="%d">%s</row>', $index + 1, $cellsXml);
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>{$rowXml}</sheetData>
</worksheet>
XML;
    }

    private function worksheetCellXml(string $reference, string $value): string
    {
        if (is_numeric($value)) {
            return sprintf('<c r="%s"><v>%s</v></c>', $reference, $value);
        }

        return sprintf(
            '<c r="%s" t="inlineStr"><is><t>%s</t></is></c>',
            $reference,
            htmlspecialchars($value, ENT_XML1 | ENT_QUOTES),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function dashboardData(Request $request, array $data = []): array
    {
        $editingCourse = null;

        if ($request->filled('edit_course')) {
            $editingCourse = Course::query()
                ->with('prerequisites:id')
                ->find($request->integer('edit_course'));
        }

        $requestedSemester = (int) ($data['selectedSemester'] ?? ($request->integer('semester') ?: 1));

        $maxSemester = max(
            8,
            (int) Course::query()->max('semester'),
            $requestedSemester,
        );
        $selectedSemester = (int) old('semester', $requestedSemester);
        $courses = Course::query()
            ->with('prerequisites:id,code,title,semester')
            ->orderBy('semester')
            ->orderBy('code')
            ->get();
        $activeCoursesBySemester = $courses
            ->where('is_active', true)
            ->groupBy('semester');

        return array_merge([
            'courses' => $courses,
            'coursesBySemester' => $activeCoursesBySemester,
            'semesters' => range(1, $maxSemester),
            'selectedSemester' => max(1, $selectedSemester),
            'gradeInput' => [],
            'courseFormCourse' => $editingCourse ?? new Course([
                'semester' => max(1, $selectedSemester),
                'lecture_hours' => 0,
                'laboratory_hours' => 0,
                'credit_units' => 0,
                'is_active' => true,
            ]),
            'availablePrerequisites' => Course::query()
                ->when(
                    $editingCourse instanceof Course,
                    fn ($query) => $query->whereKeyNot($editingCourse->id),
                )
                ->orderBy('semester')
                ->orderBy('code')
                ->get(),
            'selectedPrerequisiteIds' => $editingCourse instanceof Course
                ? $editingCourse->prerequisites->pluck('id')->all()
                : [],
            'editingCourse' => $editingCourse,
        ], $data);
    }
}
