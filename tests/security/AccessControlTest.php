<?php namespace Golem15\Translate\Tests\Security;

/**
 * Security PoC tests for HIGH finding TRANSLATE-001.
 * Each test method is named test_translate_NNN_<short_slug> and references
 * the finding in .planning/audit/plugins/golem15/translate/FINDINGS.md.
 *
 * @group security
 *
 * Per Phase 7 D-20: PoC tests use HTTP-only + unit fidelity.
 * These tests MUST FAIL on current code (red-bar regression locks).
 * The remediation milestone's fixes will turn them green.
 */
class AccessControlTest extends \PluginTestCase
{
    protected $refreshPlugins = ['Golem15.Translate'];

    /**
     * TRANSLATE-001: Message model uses $guarded = [] disabling mass-assignment protection.
     *
     * The Message model declares `protected $guarded = [];` which means ANY attribute
     * can be mass-assigned via fill(). While direct public-facing routes don't call
     * Message::create() with user data, the backend Messages controller's
     * data.updateRecord handler passes raw AJAX POST data. An attacker with
     * manage_messages permission could mass-assign arbitrary columns (code,
     * message_data, found) to corrupt the translation cache and inject
     * attacker-controlled strings into all front-end pages.
     *
     * EXPECTATION (post-fix): Message model uses $guarded = ['*'] or specific
     * $fillable whitelist, preventing mass-assignment of sensitive columns.
     * TODAY (pre-fix): $guarded = [] disables all mass-assignment protection.
     * This assertion FAILS because $guarded is set to empty array.
     *
     * @test
     * @group security
     * @see .planning/audit/plugins/golem15/translate/FINDINGS.md #TRANSLATE-001
     * @see .planning/audit/DASHBOARD.md #TRANSLATE-001
     */
    public function test_translate_001_message_model_guarded_empty(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/models/Message.php'
        );

        // Check that $guarded is NOT set to empty array
        // The vulnerable pattern is: $guarded = []
        $hasEmptyGuarded = (bool) preg_match('/\$guarded\s*=\s*\[\s*\]/', $source);

        $this->assertFalse(
            $hasEmptyGuarded,
            'TRANSLATE-001: Message model declares $guarded = [] which disables all '
            . 'mass-assignment protection. Any backend user with manage_messages '
            . 'permission can mass-assign arbitrary columns (code, message_data, found) '
            . 'via the Table widget\'s data.updateRecord AJAX handler, corrupting the '
            . 'translation cache. Every deployment depends on Translate -- corrupted '
            . 'translations propagate to all front-end pages via the |_ Twig filter. '
            . 'GDPR-Art8-Applicable: YES (translator-controlled strings displayed to children). '
            . 'Post-fix: use $guarded = [\'*\'] or define a specific $fillable whitelist.'
        );
    }
}
