<?php

declare(strict_types=1);

namespace App\Services\Code;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TaskInputReader
{
    /**
     * Resolve task markdown input from argument, file path, editor, or STDIN.
     */
    public function resolve(Command $command, string $workspaceRoot, ?string $rawArgument, bool $useEditor): string
    {
        if (!empty($rawArgument)) {
            $workspacePath = $workspaceRoot . '/' . ltrim($rawArgument, '/');
            if (File::exists($workspacePath)) {
                return (string) File::get($workspacePath);
            }

            if (File::exists($rawArgument)) {
                return (string) File::get($rawArgument);
            }

            return $rawArgument;
        }

        return $useEditor ? $this->openInEditor() : $this->captureMultiLineInput($command);
    }

    /**
     * Capture multi-line input from STDIN until 'EOF'. Supports ':e' to switch to editor.
     */
    public function captureMultiLineInput(Command $command): string
    {
        $command->line("<comment>Enter tasks (separate multiple tasks with '---', type 'EOF' to finish):</comment>\n");
        $lines = [];

        while (!feof(STDIN)) {
            $line = fgets(STDIN);
            if ($line === false || trim($line) === 'EOF') {
                break;
            }

            if (trim($line) === ':e') {
                return $this->openInEditor();
            }

            $lines[] = rtrim($line, "\r\n");
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Open task input directly in the system text editor.
     */
    public function openInEditor(string $initialContent = ''): string
    {
        $editor = getenv('EDITOR') ?: (PHP_OS_FAMILY === 'Windows' ? 'notepad' : 'vim');
        $tempFile = tempnam(sys_get_temp_dir(), 'pnc_task_') . '.md';

        $template = $initialContent !== ''
            ? $initialContent
            : "# Enter tasks below and save (:wq).\n# Separate tasks with '---'. Lines starting with '#' are ignored.\n\n";

        file_put_contents($tempFile, $template);

        $descriptors = file_exists('/dev/tty')
            ? [0 => ['file', '/dev/tty', 'r'], 1 => ['file', '/dev/tty', 'w'], 2 => ['file', '/dev/tty', 'w']]
            : [0 => STDIN, 1 => STDOUT, 2 => STDERR];

        $process = proc_open($editor . ' ' . escapeshellarg($tempFile), $descriptors, $pipes);
        if (is_resource($process)) {
            proc_close($process);
        }

        $content = file_exists($tempFile) ? (string) file_get_contents($tempFile) : '';
        @unlink($tempFile);

        $lines = explode("\n", $content);
        $filtered = array_filter($lines, fn (string $line): bool => !str_starts_with(trim($line), '#'));

        return trim(implode("\n", $filtered));
    }
}
