<?php namespace Golem15\Translate\Tests\Security;

/**
 * Security PoC test for MEDIUM finding TRANSLATE-002 (UTIL-02).
 * @group security
 */
class MassAssignmentTest extends \PluginTestCase
{
    protected $refreshPlugins = ['Golem15.Translate'];

    /**
     * @test
     * @group security
     * @see .planning/audit/plugins/golem15/translate/FINDINGS.md #TRANSLATE-002
     */
    public function test_translate_002_attribute_fillable(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/models/Attribute.php');
        $this->assertMatchesRegularExpression(
            '/protected\s+\$fillable\s*=\s*\[/',
            $source,
            'TRANSLATE-002 / UTIL-02: Attribute model must declare an explicit $fillable allowlist.'
        );
        $this->assertMatchesRegularExpression(
            "/protected\s+\\\$guarded\s*=\s*\\['\\*'\\]/",
            $source,
            'TRANSLATE-002 / UTIL-02: Attribute model must reset $guarded to ["*"].'
        );
    }
}
