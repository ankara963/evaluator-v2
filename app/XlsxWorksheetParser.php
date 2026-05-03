<?php

namespace App;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class XlsxWorksheetParser
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

    private const DOCUMENT_RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

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
    public function parse(string $path): array
    {
        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('The uploaded workbook could not be read.');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($path);

        if ($openResult !== true) {
            throw new RuntimeException('The uploaded workbook must be a valid .xlsx file.');
        }

        try {
            $workbook = $this->loadDocument($zip, 'xl/workbook.xml');
            $sheet = $this->resolveFirstWorksheet($zip, $workbook);
            $sharedStrings = $this->sharedStrings($zip);
            $worksheet = $this->loadDocument($zip, $sheet['path']);
            $rows = $this->worksheetRows($worksheet, $sharedStrings);
        } finally {
            $zip->close();
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
            'sheet_name' => $sheet['name'],
            'headers' => $columnMap,
            'records' => $records,
        ];
    }

    private function cellValue(DOMElement $cell, array $sharedStrings, DOMXPath $xpath): string
    {
        $cellType = $cell->getAttribute('t');

        if ($cellType === 's') {
            $sharedStringIndex = (int) $this->queryString('string(./a:v)', $xpath, $cell);

            return $this->normalizeValue($sharedStrings[$sharedStringIndex] ?? '');
        }

        if ($cellType === 'inlineStr') {
            /** @var list<string> $fragments */
            $fragments = $xpath->query('./a:is//a:t', $cell);
            $value = '';

            foreach ($fragments as $fragment) {
                $value .= $fragment->textContent;
            }

            return $this->normalizeValue($value);
        }

        return $this->normalizeValue($this->queryString('string(./a:v)', $xpath, $cell));
    }

    /**
     * @param array<string, string> $columnMap
     * @param array<string, string> $row
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
        /** @var array{
         *     row_number: int,
         *     subject_code: string,
         *     subject_description: string,
         *     prerequisite: string,
         *     lecture_hours: string,
         *     laboratory_hours: string,
         *     credit_units: string,
         *     grade: string
         * } $record
         */
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
     * @param array<string, string> $record
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

    private function loadDocument(ZipArchive $zip, string $entry): DOMDocument
    {
        $contents = $zip->getFromName($entry);

        if ($contents === false) {
            throw new RuntimeException("The workbook is missing {$entry}.");
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;

        if (! $document->loadXML($contents)) {
            throw new RuntimeException("The workbook entry {$entry} is invalid.");
        }

        return $document;
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

    private function queryString(string $expression, DOMXPath $xpath, ?DOMNode $context = null): string
    {
        $value = $xpath->evaluate($expression, $context);

        return is_string($value) ? $value : '';
    }

    /**
     * @return array{name: string, path: string}
     */
    private function resolveFirstWorksheet(ZipArchive $zip, DOMDocument $workbook): array
    {
        $workbookXPath = new DOMXPath($workbook);
        $workbookXPath->registerNamespace('a', self::SPREADSHEET_NAMESPACE);
        $workbookXPath->registerNamespace('r', self::DOCUMENT_RELATIONSHIP_NAMESPACE);

        /** @var DOMElement|null $sheetNode */
        $sheetNode = $workbookXPath->query('/a:workbook/a:sheets/a:sheet')->item(0);

        if (! $sheetNode instanceof DOMElement) {
            throw new RuntimeException('The workbook does not contain any worksheets.');
        }

        $relationshipId = $sheetNode->getAttributeNS(self::DOCUMENT_RELATIONSHIP_NAMESPACE, 'id');

        if ($relationshipId === '') {
            throw new RuntimeException('The workbook worksheet relationship is missing.');
        }

        $relationships = $this->loadDocument($zip, 'xl/_rels/workbook.xml.rels');
        $relationshipXPath = new DOMXPath($relationships);
        $relationshipXPath->registerNamespace('r', self::PACKAGE_RELATIONSHIP_NAMESPACE);

        /** @var DOMElement|null $relationshipNode */
        $relationshipNode = $relationshipXPath->query("/r:Relationships/r:Relationship[@Id='{$relationshipId}']")->item(0);

        if (! $relationshipNode instanceof DOMElement) {
            throw new RuntimeException('The workbook could not resolve the first worksheet.');
        }

        $target = $relationshipNode->getAttribute('Target');

        if ($target === '') {
            throw new RuntimeException('The worksheet target path is missing.');
        }

        $normalizedTarget = ltrim($target, '/');

        return [
            'name' => $sheetNode->getAttribute('name') ?: 'Sheet1',
            'path' => str_starts_with($normalizedTarget, 'xl/')
                ? $normalizedTarget
                : 'xl/'.$normalizedTarget,
        ];
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');

        if ($contents === false) {
            return [];
        }

        $document = new DOMDocument();

        if (! $document->loadXML($contents)) {
            throw new RuntimeException('The workbook shared strings are invalid.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('a', self::SPREADSHEET_NAMESPACE);
        $sharedStrings = [];

        /** @var DOMElement $stringItem */
        foreach ($xpath->query('/a:sst/a:si') as $stringItem) {
            $value = '';

            /** @var DOMElement $fragment */
            foreach ($xpath->query('.//a:t', $stringItem) as $fragment) {
                $value .= $fragment->textContent;
            }

            $sharedStrings[] = $this->normalizeValue($value);
        }

        return $sharedStrings;
    }

    /**
     * @param list<string> $sharedStrings
     * @return list<array<string, string>>
     */
    private function worksheetRows(DOMDocument $worksheet, array $sharedStrings): array
    {
        $xpath = new DOMXPath($worksheet);
        $xpath->registerNamespace('a', self::SPREADSHEET_NAMESPACE);
        $rows = [];

        /** @var DOMElement $row */
        foreach ($xpath->query('/a:worksheet/a:sheetData/a:row') as $row) {
            $cells = [];

            /** @var DOMElement $cell */
            foreach ($xpath->query('./a:c', $row) as $cell) {
                $column = preg_replace('/\d+/', '', $cell->getAttribute('r'));

                if ($column === null || $column === '') {
                    continue;
                }

                $cells[$column] = $this->cellValue($cell, $sharedStrings, $xpath);
            }

            $rows[] = $cells;
        }

        return $rows;
    }
}
