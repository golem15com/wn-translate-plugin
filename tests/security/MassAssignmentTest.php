<?php namespace Golem15\Translate\Tests\Security;

use Golem15\Translate\Models\Attribute;

/**
 * Security regression test for TRANSLATE-002 / UTIL-02.
 * Behavioral check — exercises actual fill() to confirm the runtime mass-assignment
 * protection enforced by the model, instead of inspecting source patterns.
 *
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
        $model = new Attribute();
        $fillable = $model->getFillable();

        $this->assertNotEmpty(
            $fillable,
            'TRANSLATE-002 / UTIL-02: Attribute model must expose at least one fillable attribute for runtime mass-assignment protection to be testable.'
        );

        $allowedAttribute = $fillable[0];
        $allowedValue = 'security-test-allowed';
        $forbiddenAttribute = '__security_test_forbidden__';
        $forbiddenValue = 'security-test-forbidden';

        $this->assertNotContains(
            $forbiddenAttribute,
            $fillable,
            'TRANSLATE-002 / UTIL-02: The forbidden test attribute must not be part of the model fillable allowlist.'
        );

        $model->fill([
            $allowedAttribute => $allowedValue,
            $forbiddenAttribute => $forbiddenValue,
        ]);

        $this->assertSame(
            $allowedValue,
            $model->getAttribute($allowedAttribute),
            'TRANSLATE-002 / UTIL-02: A fillable attribute should be assignable through mass assignment.'
        );

        $this->assertArrayNotHasKey(
            $forbiddenAttribute,
            $model->getAttributes(),
            'TRANSLATE-002 / UTIL-02: A non-fillable attribute must not be assigned through mass assignment.'
        );
    }
}
