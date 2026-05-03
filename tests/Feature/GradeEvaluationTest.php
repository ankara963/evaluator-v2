<?php

use App\Models\Course;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the grade evaluator page loads', function () {
    $this->get(route('grade-evaluator.index'))
        ->assertOk()
        ->assertSee('Evaluate semester progression.')
        ->assertSee('Course Dashboard')
        ->assertSee('Blank Grade = FAIL')
        ->assertSee('Evaluate Semester')
        ->assertSee('Workbook Import')
        ->assertSee('Download CSV template')
        ->assertSee('Download Excel template');
});

test('a semester can be evaluated from saved course grades', function () {
    $prerequisiteCourse = Course::factory()->create([
        'code' => 'MATH101',
        'title' => 'College Algebra',
        'semester' => 1,
    ]);

    $dependentCourse = Course::factory()->create([
        'code' => 'MATH102',
        'title' => 'Trigonometry',
        'semester' => 2,
    ]);

    $dependentCourse->prerequisites()->sync([$prerequisiteCourse->id]);

    $response = $this->post(route('semester-evaluator.evaluate'), [
        'semester' => 1,
        'grades' => [
            $prerequisiteCourse->id => '70',
        ],
        'use_ai' => '0',
    ]);

    $response->assertOk()
        ->assertSee('PROCEED WITH RESTRICTIONS')
        ->assertSee('Blocked Future Subjects')
        ->assertSee('MATH102')
        ->assertViewHas('evaluation', function (array $evaluation): bool {
            return $evaluation['evaluation_type'] === 'semester'
                && $evaluation['semester'] === 1
                && $evaluation['failed_subject_codes'] === ['MATH101']
                && $evaluation['blocked_subject_codes'] === ['MATH102']
                && $evaluation['blocked_subjects'] === 1
                && $evaluation['can_proceed'] === false
                && $evaluation['records'][0]['blocks_subject_codes'] === ['MATH102'];
        });
});

test('a passed semester can proceed', function () {
    $course = Course::factory()->create([
        'code' => 'ENG101',
        'title' => 'Communication Skills',
        'semester' => 1,
    ]);

    $response = $this->post(route('semester-evaluator.evaluate'), [
        'semester' => 1,
        'grades' => [
            $course->id => '88',
        ],
        'use_ai' => '0',
    ]);

    $response->assertOk()
        ->assertSee('CAN PROCEED')
        ->assertViewHas('evaluation', function (array $evaluation): bool {
            return $evaluation['overall_status'] === 'pass'
                && $evaluation['can_proceed'] === true
                && $evaluation['blocked_subjects'] === 0;
        });
});

test('blank grades automatically fail the uploaded sheet', function () {
    $response = $this->post(route('grade-evaluator.evaluate'), [
        'grade_sheet' => gradeSheetUpload([
            ['A' => 'Subject Code', 'F' => 'Credit Units', 'H' => 'Grade'],
            ['A' => 'FCMATH', 'F' => '3', 'H' => '95'],
            ['A' => 'CPROG2', 'F' => '3'],
        ]),
        'use_ai' => '1',
    ]);

    $response->assertOk()
        ->assertSee('CPROG2')
        ->assertSee('Missing grade. Blank grades automatically fail.')
        ->assertViewHas('evaluation', function (array $evaluation): bool {
            return $evaluation['overall_status'] === 'fail'
                && $evaluation['passed_subjects'] === 1
                && $evaluation['failed_subjects'] === 1
                && $evaluation['missing_grade_subjects'] === 1
                && $evaluation['records'][1]['subject_code'] === 'CPROG2'
                && $evaluation['records'][1]['status'] === 'fail'
                && $evaluation['records'][1]['reason_code'] === 'missing_grade';
        })
        ->assertViewHas('narration', function (array $narration): bool {
            return $narration['source'] === 'fallback'
                && str_contains($narration['content'], 'Overall result: FAIL.');
        });
});

test('failed prerequisite subjects block dependent subjects for the next term', function () {
    $response = $this->post(route('grade-evaluator.evaluate'), [
        'grade_sheet' => gradeSheetUpload([
            ['A' => 'Subject Code', 'C' => 'Pre Requisite/ Core Requisite', 'F' => 'Credit Units', 'H' => 'Grade'],
            ['A' => 'MATH101', 'F' => '3', 'H' => '72'],
            ['A' => 'MATH102', 'C' => 'MATH101', 'F' => '3', 'H' => '88'],
            ['A' => 'ENG201', 'C' => 'ENG101', 'F' => '3', 'H' => '91'],
        ]),
        'use_ai' => '0',
    ]);

    $response->assertOk()
        ->assertSee('Blocked next term by: MATH101')
        ->assertSee('Cannot be taken next term because prerequisite subjects failed: MATH101.')
        ->assertViewHas('evaluation', function (array $evaluation): bool {
            return $evaluation['failed_subject_codes'] === ['MATH101']
                && $evaluation['blocked_subject_codes'] === ['MATH102']
                && $evaluation['blocked_subjects'] === 1
                && $evaluation['records'][1]['subject_code'] === 'MATH102'
                && $evaluation['records'][1]['is_blocked_for_next_term'] === true
                && $evaluation['records'][1]['blocking_subject_codes'] === ['MATH101']
                && $evaluation['records'][2]['subject_code'] === 'ENG201'
                && $evaluation['records'][2]['is_blocked_for_next_term'] === false;
        })
        ->assertViewHas('narration', function (array $narration): bool {
            return $narration['source'] === 'rules'
                && str_contains($narration['content'], '1 subjects are blocked for the next term because prerequisites failed.');
        });
});

test('saved course prerequisites override worksheet prerequisite text', function () {
    $prerequisiteCourse = Course::factory()->create([
        'code' => 'MATH101',
        'title' => 'College Algebra',
    ]);

    $dependentCourse = Course::factory()->create([
        'code' => 'MATH102',
        'title' => 'Trigonometry',
    ]);

    $dependentCourse->prerequisites()->sync([$prerequisiteCourse->id]);

    $response = $this->post(route('grade-evaluator.evaluate'), [
        'grade_sheet' => gradeSheetUpload([
            ['A' => 'Subject Code', 'C' => 'Pre Requisite/ Core Requisite', 'F' => 'Credit Units', 'H' => 'Grade'],
            ['A' => 'MATH101', 'F' => '3', 'H' => '70'],
            ['A' => 'MATH102', 'C' => 'WRONG101', 'F' => '3', 'H' => '90'],
        ]),
        'use_ai' => '0',
    ]);

    $response->assertOk()
        ->assertSee('Blocked next term by: MATH101')
        ->assertViewHas('evaluation', function (array $evaluation): bool {
            return $evaluation['blocked_subject_codes'] === ['MATH102']
                && $evaluation['records'][1]['blocking_subject_codes'] === ['MATH101'];
        });
});

test('an xlsx file is required', function () {
    $response = $this->from(route('grade-evaluator.index'))
        ->post(route('grade-evaluator.evaluate'), [
            'grade_sheet' => UploadedFile::fake()->create('grades.pdf', 2, 'application/pdf'),
        ]);

    $response->assertRedirect(route('grade-evaluator.index'))
        ->assertSessionHasErrors('grade_sheet');
});

test('a csv file can be evaluated', function () {
    $response = $this->post(route('grade-evaluator.evaluate'), [
        'grade_sheet' => UploadedFile::fake()->createWithContent(
            'grade-sheet.csv',
            implode("\n", [
                'Subject Code,Subject Description,Pre Requisite/ Core Requisite,Lecture Hrs,Laboratory Hrs,Credit Units,Grade',
                'MATH101,College Algebra,,3,0,3,85',
                'MATH102,Trigonometry,MATH101,3,0,3,89',
            ]),
        ),
        'use_ai' => '0',
    ]);

    $response->assertOk()
        ->assertSee('CSV Import')
        ->assertSee('MATH102')
        ->assertViewHas('evaluation', function (array $evaluation): bool {
            return $evaluation['overall_status'] === 'pass'
                && $evaluation['total_subjects'] === 2
                && $evaluation['blocked_subjects'] === 0;
        });
});

test('the csv template can be downloaded', function () {
    $response = $this->get(route('grade-evaluator.template'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=grade-evaluator-template.csv');
});

test('the excel template can be downloaded', function () {
    $response = $this->get(route('grade-evaluator.excel-template'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('content-disposition', 'attachment; filename=grade-evaluator-template.xlsx');
});

function gradeSheetUpload(array $rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'grade-sheet.xlsx',
        workbookContents($rows),
    );
}

function workbookContents(array $rows): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'grade-sheet');

    if ($temporaryPath === false) {
        throw new RuntimeException('A temporary workbook file could not be created.');
    }

    $archive = new ZipArchive();
    $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('[Content_Types].xml', workbookContentTypesXml());
    $archive->addFromString('_rels/.rels', workbookRootRelationshipsXml());
    $archive->addFromString('xl/workbook.xml', workbookXml());
    $archive->addFromString('xl/_rels/workbook.xml.rels', workbookRelationshipsXml());
    $archive->addFromString('xl/worksheets/sheet1.xml', worksheetXml($rows));
    $archive->close();

    $contents = file_get_contents($temporaryPath);
    unlink($temporaryPath);

    if ($contents === false) {
        throw new RuntimeException('The temporary workbook could not be read.');
    }

    return $contents;
}

function workbookContentTypesXml(): string
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

function workbookRootRelationshipsXml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
}

function workbookXml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML;
}

function workbookRelationshipsXml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;
}

function worksheetXml(array $rows): string
{
    $rowXml = '';

    foreach ($rows as $index => $row) {
        $cellsXml = '';

        foreach ($row as $column => $value) {
            if ($value === '') {
                continue;
            }

            $reference = $column.($index + 1);
            $cellsXml .= worksheetCellXml($reference, $value);
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

function worksheetCellXml(string $reference, string $value): string
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
