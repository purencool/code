<?php

declare(strict_types=1);

namespace App\Services\Code;

use Illuminate\Support\Facades\File;

class CodeSourceResolver
{
    /**
     * Resolve source code to explain from disk or task snippet.
     *
     * @return array{code: string, source_name: string}
     */
    public function resolve(string $workspaceRoot, ?string $targetClass, string $taskBody): array
    {
        $fullPath = $workspaceRoot . '/' . ltrim($targetClass ?? '', '/');

        if (!empty($targetClass) && File::exists($fullPath)) {
            return [
                'code' => (string) File::get($fullPath),
                'source_name' => $targetClass,
            ];
        }

        if (preg_match('/```(?:php)?\s*(.*?)\s*```/s', $taskBody, $matches)) {
            return [
                'code' => trim($matches[1]),
                'source_name' => 'Pasted code block',
            ];
        }

        return [
            'code' => trim($taskBody),
            'source_name' => 'Pasted input buffer',
        ];
    }
}
