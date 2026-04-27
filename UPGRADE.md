# Security Upgrade Guide

**Plugin:** golem15/translate
**Projected version bump:** MAJOR
**Security audit phase:** 7 (Cross-Plugin Analysis & Remediation Planning)
**Generated:** 2026-04-27

> This guide documents breaking changes from the security audit remediation.
> Each section corresponds to a finding in .planning/audit/plugins/golem15/translate/FINDINGS.md.

## TRANSLATE-001: Mass-assignment on Message model via $guarded = []

**Severity:** HIGH
**Breaking change:** The Message model now uses `$guarded = ['*']` with explicit `$fillable`; any code that mass-assigns arbitrary Message attributes via `fill()` or `create()` will have non-whitelisted fields silently ignored.

### What changed

The Message model previously had `$guarded = []`, making all attributes mass-assignable. The fix sets `$guarded = ['*']` and defines explicit `$fillable` with only the fields needed for normal operation (`code`, `message_data`). This prevents mass-assignment of `id`, `found`, timestamps, and any other columns.

### Migration steps

1. Review any code that calls `Message::create($data)`, `$message->fill($data)`, or `Message::firstOrNew($attributes)->save()` with arrays containing fields beyond `code` and `message_data`.
2. The backend Table widget's `data.updateRecord` handler passes `from` and `to` keys, which are processed by `updateTableData()` before reaching the model. This flow is not affected since it uses explicit attribute assignment.
3. If you have custom import scripts that pass additional attributes to `Message::create()`, update them to set non-fillable attributes via explicit assignment (e.g., `$message->found = true; $message->save();`).
4. The standard `ImportCommand` console command creates messages with `['code' => ..., 'message_data' => ...]` which remains compatible.

### Before / after code

```php
// Before (vulnerable) -- all attributes mass-assignable
$message = Message::create([
    'code' => 'app.welcome',
    'message_data' => ['en' => 'Welcome'],
    'found' => true,           // was accepted
    'id' => 99999,             // was accepted (dangerous)
]);

// After (secure) -- only code and message_data are fillable
$message = Message::create([
    'code' => 'app.welcome',
    'message_data' => ['en' => 'Welcome'],
    // 'found' and 'id' silently ignored
]);
// Set non-fillable attributes explicitly:
$message->found = true;
$message->save();
```

### Required env / config changes

None.

### Composer constraint changes (if any)

Update `golem15/translate` to `^2.0` in downstream composer.json.

### Verification

- Run `vendor/bin/phpunit --configuration plugins/golem15/translate/phpunit.xml --group security` -- `test_translate_001_message_model_guarded_empty` should PASS after fix.
- Verify that the backend Messages table widget still allows editing translations correctly.
- Verify that `php artisan translate:import` and `php artisan translate:scan` still function correctly.
- Test that the `|_` Twig filter continues to resolve translations as expected.
