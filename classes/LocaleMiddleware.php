<?php

namespace Golem15\Translate\Classes;

use Auth;
use Closure;
use Config;
use Golem15\Translate\Classes\Translator;

class LocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $translator = Translator::instance();
        $translator->isConfigured();

        // Priority 1: Check URL prefix (explicit override)
        if (!$translator->loadLocaleFromRequest()) {
            // Priority 2: Check authenticated user's preferred locale from database
            if (!$this->loadLocaleFromUser($translator)) {
                $localeLoaded = false;

                // Priority 3: Check for manual selection (session)
                if ($this->hasManualLocaleSelection($request)) {
                    $localeLoaded = $translator->loadLocaleFromSession();
                }

                // Priority 4: Browser language detection (only if no manual selection)
                if (!$localeLoaded) {
                    $localeLoaded = $this->loadLocaleFromBrowser($translator, $request);
                }

                // Priority 5: Default locale (fallback if nothing else worked)
                if (!$localeLoaded) {
                    $translator->setLocale($translator->getDefaultLocale());
                }
            }
        }

        return $next($request);
    }

    /**
     * Load locale from authenticated user's database preference.
     *
     * @param Translator $translator
     * @return bool True if user locale was loaded, false otherwise
     */
    protected function loadLocaleFromUser($translator)
    {
        // Check if user is authenticated (cached check, no query)
        if (!\Auth::check()) {
            return false;
        }

        // Get user from already-loaded auth session (no additional query)
        $user = \Auth::getUser();

        // Check if user has a preferred locale set
        if (!$user || !$user->preferred_locale) {
            return false;
        }

        // Validate that the preferred locale is still enabled
        // Note: Locale::isValid() uses cached locale list, no query
        if (!\Golem15\Translate\Models\Locale::isValid($user->preferred_locale)) {
            return false;
        }

        // Set the locale with session persistence (writes to session cache)
        // The second parameter (true) enables session storage for fast retrieval
        // on subsequent requests in the same session
        $translator->setLocale($user->preferred_locale, true);

        return true;
    }

    /**
     * Load locale from browser's Accept-Language header
     *
     * Parses Accept-Language header with quality values and returns first enabled locale.
     * Handles edge cases: malformed headers, missing header, wildcards.
     *
     * @param Translator $translator
     * @param \Illuminate\Http\Request $request
     * @return bool True if browser locale was loaded, false otherwise
     */
    protected function loadLocaleFromBrowser($translator, $request)
    {
        // Check if feature is enabled
        if (!Config::get('golem15.translate::browserDetection.enabled', true)) {
            return false;
        }

        // Get Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');

        if (!$acceptLanguage) {
            \Log::debug('LocaleMiddleware: No Accept-Language header present');
            return false;
        }

        // Parse header into locale candidates
        $candidates = $this->parseAcceptLanguage($acceptLanguage);

        if (empty($candidates)) {
            \Log::debug('LocaleMiddleware: Accept-Language parsing returned no candidates', [
                'header' => $acceptLanguage
            ]);
            return false;
        }

        // Get enabled locales from system (cached, no DB query)
        $enabledLocales = \Golem15\Translate\Models\Locale::listEnabled();
        $enabledCodes = array_keys($enabledLocales);

        // Find first matching locale
        foreach ($candidates as $candidate) {
            // Extract primary language code (e.g., "pl-PL" -> "pl")
            $primaryLanguage = strtolower(substr($candidate, 0, 2));

            // Check if this language is enabled
            if (in_array($primaryLanguage, $enabledCodes)) {
                $translator->setLocale($primaryLanguage, true);

                \Log::info('LocaleMiddleware: Browser language detected and applied', [
                    'detected' => $candidate,
                    'locale' => $primaryLanguage,
                    'ip' => $request->ip()
                ]);

                return true;
            }
        }

        \Log::debug('LocaleMiddleware: No matching locale found in Accept-Language', [
            'candidates' => $candidates,
            'enabled' => $enabledCodes
        ]);

        return false;
    }

    /**
     * Parse Accept-Language header into prioritized language codes
     *
     * Handles quality values (q=0.9), wildcards (*), and malformed entries.
     * Returns array sorted by quality value (highest first).
     *
     * Example: "pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7" -> ['pl-PL', 'pl', 'en-US', 'en']
     *
     * @param string $header Raw Accept-Language header value
     * @return array Array of language codes in priority order
     */
    protected function parseAcceptLanguage($header)
    {
        // Split by comma
        $languages = explode(',', $header);
        $parsed = [];

        foreach ($languages as $lang) {
            // Trim whitespace
            $lang = trim($lang);

            if (empty($lang) || $lang === '*') {
                continue; // Skip wildcards and empty values
            }

            // Check for quality value (e.g., "en-US;q=0.9")
            $parts = explode(';', $lang);
            $code = trim($parts[0]);
            $quality = 1.0; // Default quality

            // Parse quality value if present
            if (isset($parts[1]) && preg_match('/q=([\d.]+)/', $parts[1], $matches)) {
                $quality = floatval($matches[1]);
            }

            // Validate language code format (basic validation: aa or aa-AA)
            if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/i', $code)) {
                $parsed[] = [
                    'code' => $code,
                    'quality' => $quality
                ];
            }
        }

        // Sort by quality (highest first)
        usort($parsed, function($a, $b) {
            return $b['quality'] <=> $a['quality'];
        });

        // Extract just the codes
        return array_column($parsed, 'code');
    }

    /**
     * Check if user has manually selected a locale
     *
     * Checks for locale_manually_set cookie. If present, user has explicitly
     * chosen a language and should not receive auto-detection.
     *
     * @param \Illuminate\Http\Request $request
     * @return bool True if manual selection exists, false otherwise
     */
    protected function hasManualLocaleSelection($request)
    {
        $cookieName = Config::get(
            'golem15.translate::browserDetection.manualSelectionCookie',
            'locale_manually_set'
        );

        $manuallySet = $request->cookie($cookieName);

        if ($manuallySet) {
            \Log::debug('LocaleMiddleware: Manual locale selection cookie present, skipping browser detection');
            return true;
        }

        return false;
    }
}
