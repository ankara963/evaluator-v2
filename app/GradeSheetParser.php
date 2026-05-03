<?php

namespace App;

use RuntimeException;

class GradeSheetParser
{
    /**
     * @var array<string, string>
     */
    private const EXPECTED_HEADERS = [
        'subject code' => 'subject_code',
        'subject description' => 'subject_description',
        'pre requisite/ core requisite' => 'prerequisite',
        'lecture hrs' => 'lecture_hours',
        'laboratory hrs' => 'laboratory_hours',
        'credit units' => 'credit_units',
        'grade' => 'grade',
    ];

    public function __construct(private XlsxWorksheetParser $xlsxWorksheetParser) {}

    /**
     * @return array{
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
     * }
     */
    public function parse(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->parseCsv($path),
            'xlsx' => $this->xlsxWorksheetParser->parse($path),
            default => throw new RuntimeException('The uploaded grade sheet must be a valid .csv or .xlsx file.'),
        };
    }

    /**
     * @return array{
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
     * }
     */
    private function parseCsv(string $path): array
    {
        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('The uploaded worksheet could not be read.');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The uploaded CSV file could not be opened.');
        }

        try {
            $rows = [];

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }

                $rows[] = $this->csvRow($row);
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw new RuntimeException('The worksheet is empty.');
        }

        $columnMap = $this->headerColumns($rows[0]);
        $records = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $record = $this->record($row, $columnMap, $index + 2);

            if ($this->isEmptyRecord($record)) {
                continue;
            }

            $records[] = $record;
        }

        if ($records === []) {
            throw new RuntimeException('The worksheet does not contain any grade rows.');
        }

        return [
            'sheet_name' => 'CSV Import',
            'headers' => $columnMap,
            'records' => $records,
        ];
    }

    /**
     * @param list<string|null> $row
     * @return array<string, string>
     */
    private function csvRow(array $row): array
    {
        $cells = [];

        foreach ($row as $index => $value) {
            $cells[$this->columnName($index)] = $this->normalizeValue((string) ($value ?? ''));
        }

        return $cells;
    }

    private function columnName(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $index = intdiv($index - 1, 26);
        }

        return $name;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, string>
     */
    private function headerColumns(array $row): array
    {
        $columnMap = [];

        foreach ($row as $column => $header) {
            $normalizedHeader = $this->normalizeHeader($header);

            if (isset(self::EXPECTED_HEADERS[$normalizedHeader])) {
                $columnMap[$column] = self::EXPECTED_HEADERS[$normalizedHeader];
            }
        }

        if (! in_array('subject_code', $columnMap, true) || ! in_array('grade', $columnMap, true)) {
            throw new RuntimeException('The worksheet must include Subject Code and Grade columns.');
        }

        return $columnMap;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $columnMap
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
    private function record(array $row, array $columnMap, int $rowNumber): array
    {
        $record = [
            'row_number' => $rowNumber,
            'subject_code' => '',
            'subject_description' => '',
            'prerequisite' => '',
            'lecture_hours' => '',
            'laboratory_hours' => '',
            'credit_units' => '',
            'grade' => '',
        ];

        foreach ($columnMap as $column => $field) {
            $record[$field] = $row[$column] ?? '';
        }

        return $record;
    }

    /**
     * @param array<string, string|int> $record
     */
    private function isEmptyRecord(array $record): bool
    {
        foreach ($record as $field => $value) {
            if ($field === 'row_number') {
                continue;
            }

            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $header): string
    {
        return mb_strtolower($this->normalizeValue($header));
    }

    private function normalizeValue(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));

        return $normalized === null ? trim($value) : $normalized;
    }
}
