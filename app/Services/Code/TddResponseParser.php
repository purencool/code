<?php

declare(strict_types=1);

namespace App\Services\Code;

class TddResponseParser
{
    /**
     * @return array{explanation: string, test_files: array<int, array{path: string, code: string}>}
     */
    public function parseTestPlan(string $rawResponse, string $task): array
    {
        $meta = (new TddPromptBuilder())->parseTaskMetadata($task);
        $targetPath = $meta['target_test'];

        $code = $this->extractPhpCodeBlock($rawResponse);

        if ($code === null) {
            return [
                'explanation' => 'No test code could be parsed.',
                'test_files'  => [],
            ];
        }

        return [
            'explanation' => 'Generated test suite.',
            'test_files'  => [
                [
                    'path' => $targetPath,
                    'code' => $code,
                ],
            ],
        ];
    }

    /**
     * @return array{files: array<int, array{path: string, content: string}>}
     */
    public function parseImplementation(string $rawResponse, string $task): array
    {
        $meta = (new TddPromptBuilder())->parseTaskMetadata($task);
        $targetPath = $meta['target_class'];

        $code = $this->extractPhpCodeBlock($rawResponse);

        if ($code === null) {
            return ['files' => []];
        }

        return [
            'files' => [
                [
                    'path'    => $targetPath,
                    'content' => $code,
                ],
            ],
        ];
    }

    public function extractPhpCodeBlock(string $raw): ?string
    {
        if (preg_match('/```(?:php)?\s*([\s\S]*?)```/i', $raw, $matches)) {
            $extracted = trim($matches[1]);
            if (str_contains($extracted, '<?php')) {
                return $extracted;
            }
        }

        if (preg_match('/<\?php[\s\S]+/i', $raw, $matches)) {
            return trim($matches[0]);
        }

        return null;
    }
}
