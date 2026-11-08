<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Agent;

use App\Services\Agent\ScratchpadMemory;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ScratchpadMemoryTest extends TestCase
{
    private ScratchpadMemory $memory;
    private string $tempWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memory = new ScratchpadMemory();
        $this->tempWorkspace = sys_get_temp_dir() . '/pnc_scratchpad_test_' . uniqid();
        mkdir($this->tempWorkspace, 0777, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempWorkspace)) {
            File::deleteDirectory($this->tempWorkspace);
        }
        parent::tearDown();
    }

    public function test_it_tracks_task_checklist_state_transitions(): void
    {
        $this->memory->setTasks([
            'step_1' => 'Create migration',
            'step_2' => 'Define model',
        ]);

        $this->assertSame('pending', $this->memory->getTaskStatus('step_1'));
        $this->assertSame('pending', $this->memory->getTaskStatus('step_2'));

        $this->memory->markInProgress('step_1');
        $this->assertSame('in_progress', $this->memory->getTaskStatus('step_1'));

        $this->memory->markCompleted('step_1');
        $this->assertSame('completed', $this->memory->getTaskStatus('step_1'));
    }

    public function test_it_renders_markdown_checklist_and_syncs_to_disk(): void
    {
        $this->memory->setTasks([
            'step_1' => 'Create migration',
            'step_2' => 'Define model',
        ]);
        $this->memory->markCompleted('step_1');
        $this->memory->markInProgress('step_2');

        $markdown = $this->memory->toMarkdown();
        $this->assertStringContainsString('- [x] Create migration', $markdown);
        $this->assertStringContainsString('- [/] Define model', $markdown);

        $filePath = "{$this->tempWorkspace}/scratchpad.md";
        $this->memory->syncToFile($filePath);

        $this->assertFileExists($filePath);
        $this->assertSame($markdown, (string) File::get($filePath));
    }

    public function test_it_records_failed_approaches_and_identifies_duplicates(): void
    {
        $this->memory->recordFailure('preg_match regex strategy', 'Delimiter must not be alphanumeric');

        $this->assertTrue($this->memory->hasFailedStrategy('preg_match regex strategy'));
        $this->assertFalse($this->memory->hasFailedStrategy('token_get_all AST strategy'));
        $this->assertCount(1, $this->memory->getFailures());
    }

    public function test_it_generates_system_prompt_scratchpad_payload(): void
    {
        $this->memory->setTasks(['t1' => 'Initial setup']);
        $this->memory->recordFailure('Direct string replace', 'Anchor not found');

        $prompt = $this->memory->toPromptContext();
        $this->assertStringContainsString('AGENT WORKING SCRATCHPAD', $prompt);
        $this->assertStringContainsString('PREVIOUSLY FAILED ATTEMPTS (DO NOT REPEAT)', $prompt);
        $this->assertStringContainsString('Direct string replace', $prompt);
    }
}
