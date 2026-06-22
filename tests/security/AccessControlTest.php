<?php namespace Golem15\Translate\Tests\Security;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Security regression tests for HIGH finding TRANSLATE-001.
 * Each test method is named test_translate_NNN_<short_slug> and references
 * the finding in .planning/audit/plugins/golem15/translate/FINDINGS.md.
 *
 * Per Phase 7 D-20: PoC tests use HTTP-only + unit fidelity.
 * These tests assert that the remediation remains in place and should pass
 * while the code is fixed, failing only if the vulnerable behavior is
 * reintroduced.
 */
#[Group('security')]
class AccessControlTest extends \Golem15\Translate\Tests\TranslatePluginTestCase
{
    /**
     * TRANSLATE-001: Message model must not use $guarded = [] because that
     * disables mass-assignment protection.
     *
     * A declaration of `protected $guarded = [];` means ANY attribute can be
     * mass-assigned via fill(). While direct public-facing routes don't call
     * Message::create() with user data, the backend Messages controller's
     * data.updateRecord handler passes raw AJAX POST data. An attacker with
     * manage_messages permission could mass-assign arbitrary columns (code,
     * message_data, found) to corrupt the translation cache and inject
     * attacker-controlled strings into all front-end pages.
     *
     * EXPECTATION: The fixed model uses $guarded = ['*'] or a specific
     * $fillable whitelist, preventing mass-assignment of sensitive columns.
     * This regression test should pass while that protection remains in place
     * and fail only if $guarded = [] is reintroduced.
     *
     * @see .planning/audit/plugins/golem15/translate/FINDINGS.md #TRANSLATE-001
     * @see .planning/audit/DASHBOARD.md #TRANSLATE-001
     */
    #[Test]
    #[Group('security')]
    public function test_translate_001_message_model_guarded_empty(): void
    {
        $messageModelPath = dirname(__DIR__, 2) . '/models/Message.php';
        $this->assertFileExists(
            $messageModelPath,
            'TRANSLATE-001: Message.php must exist for the source-pattern check.'
        );
        $source = file_get_contents($messageModelPath);
        $this->assertNotFalse(
            $source,
            'TRANSLATE-001: Message.php must be readable for the source-pattern check.'
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
