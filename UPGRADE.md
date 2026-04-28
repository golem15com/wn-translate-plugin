# Security Upgrade Guide

**Plugin:** golem15/translate
**Projected version bump:** MAJOR
**Security audit phase:** 7 (Cross-Plugin Analysis & Remediation Planning)
**Generated:** 2026-04-27

> This guide documents breaking changes from the security audit remediation.
> Each section corresponds to a finding in .planning/audit/plugins/golem15/translate/FINDINGS.md.

## TRANSLATE-001 (UTIL-01): Mass-assignment on Message model via $guarded = []

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

## TRANSLATE-002 (UTIL-02): Mass-assignment on Attribute model (no $guarded declared)

**Severity:** MEDIUM
**Breaking change:** The Attribute model now uses `$guarded = ['*']` with explicit `$fillable`; any code that mass-assigns arbitrary Attribute fields via `fill()` or `create()` will have non-whitelisted fields silently ignored.

### What changed

The Attribute model previously had no `$guarded` declaration (Eloquent default: no protection). The fix sets `$guarded = ['*']` and defines explicit `$fillable` with the legitimate writable columns: `locale`, `model_type`, `model_id`, `attribute_data`.

### Migration steps

1. Review any code that calls `Attribute::create($data)` or `$attribute->fill($data)` with arrays containing fields beyond `locale`, `model_type`, `model_id`, `attribute_data`.
2. If you have custom code that writes additional columns (custom-extended schema), add them to the `$fillable` array in your fork or use explicit setters.

### Required env / config changes

None.

### Verification

- Run `vendor/bin/phpunit --configuration plugins/golem15/translate/phpunit.xml --group security` -- `test_translate_002_attribute_fillable` should PASS after fix.

## TRANSLATE-003 (UTIL-03): Path traversal in TranslationScanner::readLocale

**Severity:** MEDIUM
**Breaking change:** Locale names that do not match `/^[a-z]{2,3}(-[A-Z]{2})?$/` are now silently rejected (returns `[]`). Additionally, the resolved file path must be contained within `localePath()` via `realpath()`.

### What changed

`TranslationScanner::readLocale($locale)` previously concatenated the `$locale` parameter directly into a file path without validation, allowing path traversal (e.g., `../../etc/passwd`). The fix adds:
1. A strict regex validation: `/^[a-z]{2,3}(-[A-Z]{2})?$/` (e.g. `en`, `pl`, `eng`, `pt-BR`)
2. A defense-in-depth `realpath()` containment check ensuring the resolved path stays inside `localePath()`

### Migration steps

1. If your project uses non-standard locale codes (e.g., `en_US` with underscore, or four-letter codes), they will now be rejected. Convert them to the standard format (`en-US` with hyphen).
2. Symlinks under `localePath()` pointing outside the allowed root will be rejected by the `realpath()` containment check.

### Required env / config changes

None.

### Verification

- Run `vendor/bin/phpunit --configuration plugins/golem15/translate/phpunit.xml --group security` -- `test_translate_003_path_traversal` should PASS after fix.

## TRANSLATE-004 (UTIL-04): Arbitrary file read in ImportCommand via --path option

**Severity:** MEDIUM
**Breaking change:** The `translate:import` console command now validates the `--path` option against the project root. Paths outside `base_path()` are rejected with an error message.

### What changed

`ImportCommand::handle()` previously accepted any file path via `--path` without validation, allowing reading of arbitrary files (e.g., `/etc/passwd`). The fix adds:
1. `realpath()` resolution of the provided path
2. `str_starts_with()` containment check against `base_path()`

### Migration steps

1. If you import translation files from outside the project root, copy or symlink them into the project directory first.
2. The `realpath()` check follows symlinks, so symlinks pointing outside the project root will be rejected.

### Required env / config changes

None.

### Verification

- Run `php artisan translate:import --path=/etc/hosts` -- should return "Path is outside the project root" error.
- Run `vendor/bin/phpunit --configuration plugins/golem15/translate/phpunit.xml --group security` -- `test_translate_004_import_path` should PASS after fix.
