<?php namespace Golem15\Translate\Tests\Security;

use Golem15\Translate\Console\ImportCommand;
use Golem15\Translate\Support\TranslationScanner;
use ReflectionMethod;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Security regression tests for TRANSLATE-003 / UTIL-03 (path-traversal in TranslationScanner)
 * and TRANSLATE-004 / UTIL-04 (out-of-root --path argument in ImportCommand).
 *
 * @group security
 */
class PathTraversalTest extends \Golem15\Translate\Tests\TranslatePluginTestCase
{
    /**
     * UTIL-03: TranslationScanner::readLocale rejects path-traversal and malformed locale codes.
     *
     * @test
     * @group security
     * @see .planning/audit/plugins/golem15/translate/FINDINGS.md #TRANSLATE-003
     */
    public function test_translate_003_path_traversal(): void
    {
        $scanner = TranslationScanner::instance();

        $method = new ReflectionMethod(TranslationScanner::class, 'readLocale');
        $method->setAccessible(true);

        $traversalCases = [
            '../../etc',
            '..',
            '../',
            '/etc',
            'en/../..',
            "en\0",
            'EN',
            'en_US',
        ];

        foreach ($traversalCases as $bad) {
            $this->assertSame(
                [],
                $method->invoke($scanner, $bad),
                "UTIL-03: readLocale must reject locale '" . addcslashes($bad, "\0..\37") . "' and return []."
            );
        }
    }

    /**
     * UTIL-04: ImportCommand rejects --path values outside base_path().
     *
     * Behavioral test — invokes the actual Artisan command with an out-of-root path
     * and asserts a non-zero exit code plus the documented error message.
     *
     * @test
     * @group security
     * @see .planning/audit/plugins/golem15/translate/FINDINGS.md #TRANSLATE-004
     */
    public function test_translate_004_import_path_out_of_root(): void
    {
        $outsideDir = sys_get_temp_dir();
        $resolvedOutside = realpath($outsideDir);
        $resolvedRoot = realpath(base_path());

        if ($resolvedOutside === false
            || $resolvedRoot === false
            || strncmp($resolvedOutside, $resolvedRoot . DIRECTORY_SEPARATOR, strlen($resolvedRoot . DIRECTORY_SEPARATOR)) === 0
        ) {
            $this->markTestSkipped('System temp dir is inside base_path(); cannot construct an out-of-root --path.');
        }

        $outsidePath = $resolvedOutside . '/golem15-translate-out-of-root-' . uniqid() . '.json';
        $bytesWritten = file_put_contents($outsidePath, '[]');
        $this->assertNotFalse(
            $bytesWritten,
            'Test precondition failed: could not create the out-of-root temporary JSON file.'
        );
        $this->assertFileExists(
            $outsidePath,
            'Test precondition failed: the out-of-root temporary JSON file must exist before executing the command.'
        );

        try {
            [$exitCode, $output] = $this->runImportCommand(['--path' => $outsidePath]);

            $this->assertNotSame(
                0,
                $exitCode,
                'UTIL-04: ImportCommand must return a non-zero exit code for paths outside base_path().'
            );
            $this->assertStringContainsString(
                'outside the project root',
                $output,
                'UTIL-04: ImportCommand must surface the documented "outside the project root" error.'
            );
        } finally {
            @unlink($outsidePath);
        }
    }

    /**
     * UTIL-04: ImportCommand rejects --path values that resolve to a directory.
     *
     * @test
     * @group security
     */
    public function test_translate_004_import_path_must_be_file(): void
    {
        // A subdirectory inside the project root — passes containment but fails the is_file() check.
        $directoryInsideRoot = base_path('plugins');
        $this->assertDirectoryExists(
            $directoryInsideRoot,
            'Test precondition: plugins/ directory must exist inside base_path() to drive the is_file() check.'
        );

        [$exitCode, $output] = $this->runImportCommand(['--path' => $directoryInsideRoot]);

        $this->assertNotSame(
            0,
            $exitCode,
            'UTIL-04: ImportCommand must reject directory --path values with a non-zero exit code.'
        );
        $this->assertStringContainsString(
            'not a regular file',
            $output,
            'UTIL-04: ImportCommand must surface the "not a regular file" error for directory paths inside the project root.'
        );
    }

    /**
     * Run the ImportCommand in isolation via Symfony's CommandTester.
     * Avoids relying on the Artisan command registry being populated in the
     * PluginTestCase boot sequence.
     *
     * @return array{0:int,1:string} [exitCode, displayedOutput]
     */
    private function runImportCommand(array $arguments): array
    {
        $command = $this->app->make(ImportCommand::class);
        $command->setLaravel($this->app);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute($arguments);

        return [$exitCode, $tester->getDisplay()];
    }
}
