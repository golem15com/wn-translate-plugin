<?php namespace Golem15\Translate\Tests\Security;

/**
 * Security PoC tests for MEDIUM findings TRANSLATE-003, TRANSLATE-004 (UTIL-03, UTIL-04).
 * @group security
 */
class PathTraversalTest extends \PluginTestCase
{
    protected $refreshPlugins = ['Golem15.Translate'];

    /**
     * UTIL-03 / TRANSLATE-003 — TranslationScanner::readLocale rejects path-traversal locale names.
     *
     * Source-pattern fallback: assert TranslationScanner.php contains a locale-name regex
     * AND a realpath/containment check inside readLocale().
     *
     * @test
     * @group security
     */
    public function test_translate_003_path_traversal(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/support/TranslationScanner.php');

        // Find the readLocale method body
        $pattern = '/function\s+readLocale\s*\([^)]*\)[^{]*\{(.*?)\n\s{4}\}/s';
        if (preg_match($pattern, $source, $matches)) {
            $body = $matches[1];
            $hasLocaleRegex = (
                str_contains($body, "preg_match")
                || str_contains($body, '[a-z]{2,3}')
            );
            $hasContainmentCheck = (
                str_contains($body, 'realpath')
                || str_contains($body, 'str_starts_with')
            );
            $this->assertTrue(
                $hasLocaleRegex,
                'UTIL-03: readLocale must validate $locale against a strict regex like /^[a-z]{2,3}(-[A-Z]{2})?$/.'
            );
            $this->assertTrue(
                $hasContainmentCheck,
                'UTIL-03: readLocale must verify the resolved path is contained within localePath() via realpath().'
            );
        } else {
            $this->fail('UTIL-03: Could not locate readLocale() method body.');
        }
    }

    /**
     * UTIL-04 / TRANSLATE-004 — ImportCommand validates --path against allowed roots.
     *
     * @test
     * @group security
     */
    public function test_translate_004_import_path(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/console/ImportCommand.php');

        $hasRealpath = str_contains($source, 'realpath');
        $hasContainment = (
            str_contains($source, 'str_starts_with')
            || str_contains($source, 'base_path')
        );
        $this->assertTrue(
            $hasRealpath,
            'UTIL-04: ImportCommand::handle must call realpath() on the --path argument.'
        );
        $this->assertTrue(
            $hasContainment,
            'UTIL-04: ImportCommand::handle must verify the resolved path is contained within an allowed root.'
        );
    }
}
