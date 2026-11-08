<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Agent;

use App\Services\Agent\GitCheckpointManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GitCheckpointManagerTest extends TestCase
{
    private string $tempRepo;
    private GitCheckpointManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new GitCheckpointManager();
        $this->tempRepo = sys_get_temp_dir() . '/pnc_git_checkpoint_' . uniqid();
        mkdir($this->tempRepo, 0777, true);

        exec("git -C " . escapeshellarg($this->tempRepo) . " init -b main 2>&1");
        exec("git -C " . escapeshellarg($this->tempRepo) . " config user.email 'agent@pnc.test'");
        exec("git -C " . escapeshellarg($this->tempRepo) . " config user.name 'PncAgent'");

        file_put_contents("{$this->tempRepo}/base.txt", "Initial Commit State\n");
        exec("git -C " . escapeshellarg($this->tempRepo) . " add base.txt");
        exec("git -C " . escapeshellarg($this->tempRepo) . " commit -m 'Initial baseline' 2>&1");
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempRepo)) {
            File::deleteDirectory($this->tempRepo);
        }
        parent::tearDown();
    }

    public function test_it_identifies_clean_working_tree(): void
    {
        $isClean = $this->manager->isClean($this->tempRepo);
        $dirtyFiles = $this->manager->getDirtyFiles($this->tempRepo);

        $this->assertTrue($isClean);
        $this->assertEmpty($dirtyFiles);
    }

    public function test_it_detects_uncommitted_modifications_and_untracked_files(): void
    {
        file_put_contents("{$this->tempRepo}/base.txt", "Altered line\n", FILE_APPEND);
        file_put_contents("{$this->tempRepo}/staged.txt", "Staged content\n");
        exec("git -C " . escapeshellarg($this->tempRepo) . " add staged.txt");
        file_put_contents("{$this->tempRepo}/untracked.txt", "Untracked artifact\n");

        $isClean = $this->manager->isClean($this->tempRepo);
        $dirtyFiles = $this->manager->getDirtyFiles($this->tempRepo);

        $this->assertFalse($isClean);
        $this->assertArrayHasKey('base.txt', $dirtyFiles);
        $this->assertArrayHasKey('staged.txt', $dirtyFiles);
        $this->assertArrayHasKey('untracked.txt', $dirtyFiles);
        $this->assertSame('M', $dirtyFiles['base.txt']);
        $this->assertSame('A', $dirtyFiles['staged.txt']);
        $this->assertSame('??', $dirtyFiles['untracked.txt']);
    }

    public function test_it_creates_checkpoint_and_restores_modified_files_on_rollback(): void
    {
        $checkpointId = $this->manager->createCheckpoint($this->tempRepo, 'pre-feature-run');

        file_put_contents("{$this->tempRepo}/base.txt", "Corrupted state introduced by agent.\n");
        $this->assertSame("Corrupted state introduced by agent.\n", file_get_contents("{$this->tempRepo}/base.txt"));

        $this->manager->rollback($this->tempRepo, $checkpointId);

        $this->assertSame("Initial Commit State\n", file_get_contents("{$this->tempRepo}/base.txt"));
        $this->assertTrue($this->manager->isClean($this->tempRepo));
    }

    public function test_it_purges_newly_created_untracked_artifacts_on_rollback(): void
    {
        $checkpointId = $this->manager->createCheckpoint($this->tempRepo, 'pre-untracked-run');

        $failedTestPath = "{$this->tempRepo}/tests/Unit/BrokenTest.php";
        mkdir(dirname($failedTestPath), 0777, true);
        file_put_contents($failedTestPath, "<?php // generated file");

        $this->assertFileExists($failedTestPath);

        $this->manager->rollback($this->tempRepo, $checkpointId);

        $this->assertFileDoesNotExist($failedTestPath);
        $this->assertTrue($this->manager->isClean($this->tempRepo));
    }
}
