<?php

namespace Golem15\Translate\Components;

use Cms\Classes\ComponentBase;
use Golem15\Translate\Classes\Translator;
use Golem15\Translate\Models\Locale;
use Request;
use Config;
use Cookie;

class LocaleSuggestionBanner extends ComponentBase
{
    public function componentDetails(): array
    {
        return [
            'name' => 'Locale Suggestion Banner',
            'description' => 'Shows banner when browser language differs from current page'
        ];
    }

    public function onRun()
    {
        $this->page['showBanner'] = $this->shouldShowBanner();
        $this->page['suggestedLocale'] = $this->getSuggestedLocale();
        $this->page['suggestedLocaleName'] = $this->getSuggestedLocaleName();
    }

    protected function shouldShowBanner(): bool
    {
        // Don't show if manually set (user explicitly chose a language)
        $cookieName = Config::get('golem15.translate::browserDetection.manualSelectionCookie', 'locale_manually_set');
        if (Request::cookie($cookieName)) {
            return false;
        }

        // Don't show if dismissed this session
        if (session('locale_banner_dismissed')) {
            return false;
        }

        // Don't show if 1-week dismissal cookie exists
        if (Request::cookie('locale_banner_dismissed')) {
            return false;
        }

        // Check if browser language differs from current
        return $this->getSuggestedLocale() !== null;
    }

    protected function getSuggestedLocale(): ?string
    {
        $browserLocale = $this->detectBrowserLocale();
        $currentLocale = Translator::instance()->getLocale();

        if ($browserLocale && $browserLocale !== $currentLocale) {
            return $browserLocale;
        }

        return null;
    }

    protected function detectBrowserLocale(): ?string
    {
        $acceptLanguage = Request::header('Accept-Language');
        if (!$acceptLanguage) return null;

        $enabledLocales = array_keys(Locale::listEnabled());
        $candidates = explode(',', $acceptLanguage);

        foreach ($candidates as $candidate) {
            // Extract primary language code (e.g., "pl-PL;q=0.9" -> "pl")
            $code = strtolower(substr(trim(explode(';', $candidate)[0]), 0, 2));
            if (in_array($code, $enabledLocales)) {
                return $code;
            }
        }

        return null;
    }

    protected function getSuggestedLocaleName(): ?string
    {
        $locale = $this->getSuggestedLocale();
        if (!$locale) return null;

        $locales = Locale::listEnabled();
        return $locales[$locale] ?? null;
    }

    public function onDismissBanner()
    {
        session(['locale_banner_dismissed' => true]);

        // Set 1-week dismissal cookie (7 days = 10080 minutes)
        Cookie::queue('locale_banner_dismissed', '1', 10080);

        return ['#locale-suggestion-banner' => ''];
    }

    public function onSwitchToSuggested()
    {
        $locale = post('locale');
        if (!Locale::isValid($locale)) {
            return;
        }

        Translator::instance()->setLocale($locale, true);

        // Set manual selection cookie (1 year)
        Cookie::queue(
            Config::get('golem15.translate::browserDetection.manualSelectionCookie', 'locale_manually_set'),
            '1',
            Config::get('golem15.translate::browserDetection.manualSelectionExpiry', 525600)
        );

        return \Redirect::refresh();
    }
}
