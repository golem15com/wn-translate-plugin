![Translate](assets/img/hero.png)

# Golem15.Translate Plugin

Full multilingual system for Winter CMS. Translates CMS pages, database models, static content, mail templates, URLs, theme settings, and frontend messages. Includes browser language detection, hreflang SEO tags, locale suggestion banners, and a backend UI for managing languages and translations. Replaces Winter.Translate.

## Features

- **Locale Management** - Multiple languages with enable/disable, sorting, and a single default
- **Message Translation** - `|_` and `|__` Twig filters with parameter substitution and pluralization
- **Model Translation** - `TranslatableModel` behavior for translating any model attribute via morphable storage
- **Page & URL Translation** - Translatable CMS page URLs with locale prefix routing
- **Content File Translation** - Language-suffixed content files (`welcome.fr.htm`)
- **Mail Template Translation** - Localized mail views and translatable template fields
- **Theme Data Translation** - Mark theme customization fields as `translatable: true`
- **Browser Language Detection** - Accept-Language parsing with manual selection tracking
- **Locale Suggestion Banner** - Component that prompts visitors when browser language differs from page
- **Hreflang SEO Tags** - `AlternateHrefLangElements` component for search engine localization
- **Indexed Translations** - `transWhere` query scope for searching translated attributes
- **ML Form Widgets** - Automatic replacement of text, textarea, richeditor, markdown, repeater, blocks, nestedform, mediafinder, and URL fields with multilingual variants
- **Import/Export** - Bulk message import/export via CLI and backend
- **Theme Scanner** - Scans templates for translatable strings and imports theme-defined messages

## Installation

Included as a git submodule in the Golem15 starter stack.

```bash
composer require winter/wn-translate-plugin
php artisan migrate
```

---

## Selecting a Language

Visitors select a language by URL prefix, which is stored in session:

- `https://site.com/ru/` - Russian
- `https://site.com/fr/` - French
- `https://site.com/` - Default or user's stored preference

### Locale Detection Priority

The middleware resolves locale in this order:

1. **URL prefix** - `/en/page` (explicit override)
2. **User preference** - Authenticated user's `preferred_locale` attribute
3. **Session** - Previously stored language selection
4. **Browser detection** - Accept-Language header (only if no manual selection cookie)
5. **Default locale** - System fallback

---

## Components

### LocalePicker

Language switching dropdown with locale-aware URL redirect:

```twig
[localePicker]
==
{% component 'localePicker' %}
```

Or build a custom switcher:

```twig
<a href="javascript:;" data-request="onSwitchLocale" data-request-data="locale: 'en'">English</a>
<a href="javascript:;" data-request="onSwitchLocale" data-request-data="locale: 'pl'">Polski</a>
```

**AJAX Handlers:**
- `onSwitchLocale` - Switches locale, translates URL parameters via events, and redirects

**Page Variables:** `locales`, `activeLocale`, `activeLocaleName`

### AlternateHrefLangElements

Outputs `<link rel="alternate" hreflang="...">` tags for SEO:

```twig
[alternateHrefLangElements]
==
{% component 'alternateHrefLangElements' %}
```

### LocaleSuggestionBanner

Shows a banner when the visitor's browser language differs from the current page language:

```twig
[localeSuggestionBanner]
==
{% component 'localeSuggestionBanner' %}
```

- Detects browser language from Accept-Language header
- Respects manual selection cookie (won't show if user already chose)
- Dismissable with 1-week cookie persistence

**AJAX Handlers:** `onDismissBanner`, `onSwitchToSuggested`

**Page Variables:** `showBanner`, `suggestedLocale`, `suggestedLocaleName`

---

## Message Translation

Translate frontend strings with the `|_` filter:

```twig
{{ 'site.name'|_ }}
{{ 'Welcome to our website!'|_ }}
{{ 'Hello :name!'|_({ name: 'Friend' }) }}
```

Pluralization with `|__`:

```twig
{{ 'There are no apples|There are :number apples!'|__(2, { number: 'two' }) }}
```

Force a specific locale:

```twig
{{ 'this is always english'|_({}, 'en') }}
```

Raw (unescaped) variants:

```twig
{{ 'Bold <b>text</b>'|transRaw }}
{{ 'item|items'|transRawPlural(count) }}
```

URL locale conversion:

```twig
{{ '/about'|localeUrl('de') }}
```

### Theme-Defined Messages

Define default translations in `theme.yaml`:

```yaml
translate:
    en:
        site.name: 'My Website'
        nav.home: 'Home'
    pl:
        site.name: 'Moja Strona'
        nav.home: 'Strona Glowna'
```

Or reference external YAML files:

```yaml
translate: config/lang.yaml
```

Or per-locale files:

```yaml
translate:
    en: config/lang-en.yaml
    pl: config/lang-pl.yaml
```

Messages are imported automatically when the theme is activated, or via the scanner.

---

## Model Translation

Add the `TranslatableModel` behavior and declare translatable attributes:

```php
class Post extends Model
{
    public $implement = ['Golem15.Translate.Behaviors.TranslatableModel'];

    public $translatable = ['title', 'content'];
}
```

Use the `@` prefix for soft implementation (no error if Translate isn't installed):

```php
public $implement = ['@Golem15.Translate.Behaviors.TranslatableModel'];
```

### Reading & Writing Translations

```php
$post = Post::first();

// Default language
echo $post->title;

// Switch context
$post->translateContext('fr');
echo $post->title; // French title

// Chainable shorthand
echo $post->lang('fr')->title;

// Without changing context
$post->getAttributeTranslated('title', 'fr');
$post->setAttributeTranslated('title', 'Titre', 'fr');
```

Works in Twig:

```twig
{{ post.lang('fr').title }}
```

### Nested JSON Attributes

Translate fields inside JSON columns with bracket notation:

```php
public $translatable = ['foo[bar][baz]'];
```

### Fallback Behavior

Untranslated attributes fall back to the default locale. Disable this per-instance:

```php
$post->setTranslatableUseFallback(false)->lang('fr');
$post->title; // NULL if no French translation
```

### Indexed Translations

Declare an attribute as indexed for database queries:

```php
public $translatable = [
    'title',
    ['slug', 'index' => true]
];
```

Query with `transWhere`:

```php
Post::transWhere('slug', 'hello-world')->first();
Post::transWhere('slug', 'hello-world', 'en')->first();
```

---

## Page & URL Translation

CMS pages support translated URLs via the `viewBag`:

```ini
url = "/contact"

[viewBag]
localeUrl[ru] = "/контакт"
localeUrl[fr] = "/nous-contacter"
```

Result:
- `/en/contact` - English
- `/fr/nous-contacter` - French
- `/ru/контакт` - Russian
- `/ru/contact` - 404

### URL Parameter Translation

Translate dynamic URL parameters when switching locales:

```php
Event::listen('translate.localePicker.translateParams', function ($page, $params, $oldLocale, $newLocale) {
    if ($page->baseFileName === 'blog-post') {
        return Post::translateParams($params, $oldLocale, $newLocale);
    }
});
```

### Query String Translation

```php
Event::listen('translate.localePicker.translateQuery', function ($page, $params, $oldLocale, $newLocale) {
    return MyModel::translateParams($params, $oldLocale, $newLocale);
});
```

---

## Content & Mail Translation

### Content Files

Content files use language suffixes:

- `welcome.htm` - Default language
- `welcome.ru.htm` - Russian
- `welcome.fr.htm` - French

### Mail Templates

Mail views use the same pattern:

- `mail-notify.htm` - Default
- `mail-notify-ru.htm` - Russian

The MailTemplate model is automatically extended with `TranslatableModel` for fields: `subject`, `description`, `content_html`, `content_text`.

Pass `_current_locale` in mail data to force a specific locale.

---

## Theme Data Translation

Mark theme customization fields as translatable in your theme's `fields.yaml`:

```yaml
website_name:
    tab: Info
    label: Website Name
    type: text
    translatable: true
```

---

## ML Form Widgets

The plugin automatically replaces standard form fields with multilingual equivalents in the backend:

| Standard Widget | ML Replacement |
|----------------|----------------|
| `text` | `mltext` |
| `textarea` | `mltextarea` |
| `richeditor` | `mlricheditor` |
| `markdown` | `mlmarkdowneditor` |
| `repeater` | `mlrepeater` |
| `nestedform` | `mlnestedform` |
| `blocks` | `mlblocks` |
| `mediafinder` | `mlmediafinder` |
| `url` | `mlurl` |

Replacement is automatic for models implementing `TranslatableModel`. Users can switch locales by clicking the locale indicator; hold CMD/CTRL to switch all fields at once.

---

## Console Commands

| Command | Description |
|---------|-------------|
| `translate:scan` | Scan theme templates for translatable strings (`--purge` to clear old messages first) |
| `translate:export` | Export messages to file |
| `translate:import` | Import messages from file |
| `plugin:translate` | Generate missing translation entries in plugin lang files |
| `theme:translate` | Generate missing translation entries in theme files |

---

## Events

| Event | Purpose |
|-------|---------|
| `translate.localePicker.translateParams` | Translate URL parameters when switching locale |
| `translate.localePicker.translateQuery` | Translate query string parameters when switching locale |
| `golem15.translate.themeScanner.afterScan` | Hook after theme message scanning completes |
| `pages.menu.referencesGenerated` | Localize Winter.Pages menu item titles and URLs |
| `pages.content.templateList` | Prune locale-suffixed content files from template lists |

---

## Extending a Plugin with Translatable Fields

Add translatable fields to another plugin's forms:

```php
Event::listen('backend.form.extendFieldsBefore', function ($widget) {
    if (!$widget->getController() instanceof \Winter\Pages\Controllers\Index) {
        return;
    }

    $widget->tabs['fields']['viewBag[myField]'] = [
        'tab' => 'My Tab',
        'label' => 'My Field',
        'type' => 'text'
    ];

    $widget->model->translatable = array_merge(
        $widget->model->translatable,
        ['viewBag[myField]']
    );
});
```

---

## Integration without jQuery

Switch locale via vanilla JavaScript:

```js
document.querySelector('#languageSelect').addEventListener('change', function () {
    const body = new URLSearchParams({
        _session_key: document.querySelector('input[name="_session_key"]').value,
        _token: document.querySelector('input[name="_token"]').value,
        locale: this.value
    });

    fetch(location.href + '/', {
        method: 'POST',
        body: body,
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-WINTER-REQUEST-HANDLER': 'onSwitchLocale',
            'X-WINTER-REQUEST-PARTIALS': '',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(res => window.location.replace(res.X_WINTER_REDIRECT));
});
```

---

## Configuration

Environment variables in `.env` or `config/golem15/translate/config.php`:

| Variable | Default | Description |
|----------|---------|-------------|
| `TRANSLATE_FORCE_LOCALE` | `null` | Force a specific default locale code |
| `TRANSLATE_PREFIX_LOCALE` | `true` | Prefix default locale in URLs |
| `TRANSLATE_CACHE_TIMEOUT` | `1440` | Translation cache TTL in minutes (24h) |
| `TRANSLATE_DISABLE_PREFIX_ROUTES` | `false` | Disable auto-generated locale prefix routes |
| `TRANSLATE_REDIRECT_STATUS` | `302` | HTTP status code for locale redirects |
| `TRANSLATE_BROWSER_DETECTION` | `true` | Enable Accept-Language auto-detection |

---

## Plugin Integrations

- **Winter.Pages** - Translates menu item titles/URLs, locale-aware cache keys, localized static content
- **Winter.Sitemap / Golem15.Sitemap** - Generates `xhtml:link` hreflang alternates and multi-locale sitemap entries
- **Golem15.User** - Reads `preferred_locale` from authenticated users
- **System.MailTemplate** - Translates subject, description, and content fields
- **System.File** - Translates title and description attributes

---

## Database Schema

| Table | Purpose |
|-------|---------|
| `winter_translate_locales` | Locale definitions (code, name, is_default, is_enabled, sort_order) |
| `winter_translate_messages` | Message translations (MD5 code, JSON message_data per locale) |
| `winter_translate_attributes` | Model translations (polymorphic: locale, model_id, model_type, attribute_data) |
| `winter_translate_indexes` | Indexed translation values for `transWhere` queries |

---

## Permissions

| Permission | Roles | Description |
|------------|-------|-------------|
| `golem15.translate.manage_locales` | Developer | Add, edit, delete languages |
| `golem15.translate.manage_messages` | Developer, Publisher | Translate messages |

---

## Localization

Backend UI ships with translations for English and Polish. The `lang/unsupported/` directory contains stubs for 20+ additional languages.

---

## License

MIT
