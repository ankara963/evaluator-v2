<?php

namespace App;

use Illuminate\Support\Facades\Http;

class GradeEvaluationNarrator
{
    /**
     * @param array{
     *     overall_status_label: string,
     *     total_subjects: int,
     *     passed_subjects: int,
     *     failed_subjects: int,
     *     missing_grade_subjects: int,
     *     blocked_subjects: int,
     *     total_credit_units: float,
     *     failed_credit_units: float,
     *     numeric_grade_average: float|null,
     *     failed_subject_codes: list<string>,
     *     blocked_subject_codes: list<string>
     * } $evaluation
     * @return array{
     *     content: string,
     *     source: string,
     *     model: string|null,
     *     notice: string|null
     * }
     */
    public function narrate(array $evaluation, bool $shouldUseAi = true): array
    {
        $fallback = $this->fallbackNarration($evaluation);

        if (! $shouldUseAi) {
            return [
                'content' => $fallback,
                'source' => 'rules',
                'model' => null,
                'notice' => 'AI summary was disabled for this evaluation.',
            ];
        }

        $enabled = (bool) config('services.grade_evaluator_ai.enabled');
        $apiKey = (string) config('services.grade_evaluator_ai.api_key');
        $apiUrl = (string) config('services.grade_evaluator_ai.api_url');
        $model = (string) config('services.grade_evaluator_ai.model');
        $timeout = (int) config('services.grade_evaluator_ai.timeout', 15);

        if (! $enabled || $apiKey === '' || $apiUrl === '' || $model === '') {
            return [
                'content' => $fallback,
                'source' => 'fallback',
                'model' => null,
                'notice' => 'AI is not configured, so a rules-based summary is being shown.',
            ];
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($apiUrl, [
                    'model' => $model,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => 'You explain grade evaluations. Use only the supplied facts. Never change pass/fail outcomes. Keep the response under 120 words.',
                                ],
                            ],
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => $this->prompt($evaluation),
                                ],
                            ],
                        ],
                    ],
                ])
                ->throw();

            $content = data_get($response->json(), 'output.0.content.0.text');

            if (is_string($content) && trim($content) !== '') {
                return [
                    'content' => trim($content),
                    'source' => 'ai',
                    'model' => $model,
                    'notice' => null,
                ];
            }
        } catch (\Throwable) {
            // Fall back to deterministic narration if the AI call fails.
        }

        return [
            'content' => $fallback,
            'source' => 'fallback',
            'model' => null,
            'notice' => 'The AI request failed, so a rules-based summary is being shown.',
        ];
    }

    /**
     * @param array{
     *     overall_status_label: string,
     *     total_subjects: int,
     *     passed_subjects: int,
     *     failed_subjects: int,
     *     missing_grade_subjects: int,
     *     blocked_subjects: int,
     *     total_credit_units: float,
     *     failed_credit_units: float,
     *     numeric_grade_average: float|null,
     *     failed_subject_codes: list<string>,
     *     blocked_subject_codes: list<string>
     * } $evaluation
     */
    private function fallbackNarration(array $evaluation): string
    {
        $average = $evaluation['numeric_grade_average'] === null
            ? 'No numeric grades were found.'
            : 'Average numeric grade: '.number_format($evaluation['numeric_grade_average'], 2).'.';
        $failedSubjectCodes = $evaluation['failed_subject_codes'] === []
            ? 'No failed subjects.'
            : 'Failed subjects: '.implode(', ', array_slice($evaluation['failed_subject_codes'], 0, 8)).'.';
        $blockedSubjectCodes = $evaluation['blocked_subject_codes'] === []
            ? 'No next-term subjects are blocked by failed prerequisites.'
            : 'Blocked next-term subjects: '.implode(', ', array_slice($evaluation['blocked_subject_codes'], 0, 8)).'.';

        return sprintf(
            'Overall result: %s. %d of %d subjects passed, while %d failed. Blank grades caused %d automatic failures. %d subjects are blocked for the next term because prerequisites failed. Failed subjects account for %.2f of %.2f credit units. %s %s %s',
            $evaluation['overall_status_label'],
            $evaluation['passed_subjects'],
            $evaluation['total_subjects'],
            $evaluation['failed_subjects'],
            $evaluation['missing_grade_subjects'],
            $evaluation['blocked_subjects'],
            $evaluation['failed_credit_units'],
            $evaluation['total_credit_units'],
            $average,
            $failedSubjectCodes,
            $blockedSubjectCodes,
        );
    }

    /**
     * @param array{
     *     overall_status_label: string,
     *     total_subjects: int,
     *     passed_subjects: int,
     *     failed_subjects: int,
     *     missing_grade_subjects: int,
     *     blocked_subjects: int,
     *     total_credit_units: float,
     *     failed_credit_units: float,
     *     numeric_grade_average: float|null,
     *     failed_subject_codes: list<string>,
     *     blocked_subject_codes: list<string>
     * } $evaluation
     */
    private function prompt(array $evaluation): string
    {
        return json_encode([
            'overall_status' => $evaluation['overall_status_label'],
            'total_subjects' => $evaluation['total_subjects'],
            'passed_subjects' => $evaluation['passed_subjects'],
            'failed_subjects' => $evaluation['failed_subjects'],
            'missing_grade_subjects' => $evaluation['missing_grade_subjects'],
            'blocked_subjects' => $evaluation['blocked_subjects'],
            'total_credit_units' => $evaluation['total_credit_units'],
            'failed_credit_units' => $evaluation['failed_credit_units'],
            'numeric_grade_average' => $evaluation['numeric_grade_average'],
            'failed_subject_codes' => $evaluation['failed_subject_codes'],
            'blocked_subject_codes' => $evaluation['blocked_subject_codes'],
            'instruction' => 'Explain why the sheet passed or failed, mention that blank grades automatically fail, and mention subjects that are blocked next term because prerequisites failed.',
        ], JSON_THROW_ON_ERROR);
    }
}
