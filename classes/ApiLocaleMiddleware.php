<?php

namespace Golem15\Translate\Classes;

use Closure;
use Golem15\Translate\Classes\Translator;
use Golem15\Translate\Models\Locale;

/**
 * API Locale Middleware
 *
 * Lightweight locale middleware for stateless API requests.
 * Unlike LocaleMiddleware, this doesn't use sessions or URL prefixes.
 *
 * Priority:
 * 1. Authenticated user's preferred_locale (from JWT)
 * 2. Accept-Language header (for unauthenticated/new users)
 * 3. Default locale
 */
class ApiLocaleMiddleware
{
    /**
     * Handle locale for API requests.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $translator = Translator::instance();

        // Ensure translate plugin is configured
        if (!$translator->isConfigured()) {
            return $next($request);
        }

        $locale = null;

        // Priority 1: Authenticated user's preference
        if (\Auth::check()) {
            $user = \Auth::getUser();
            if ($user && $user->preferred_locale && Locale::isValid($user->preferred_locale)) {
                $locale = $user->preferred_locale;
            }
        }

        // Priority 2: Accept-Language header
        if (!$locale) {
            $locale = $this->getLocaleFromHeader($request);
        }

        // Priority 3: Default locale
        if (!$locale) {
            $locale = $translator->getDefaultLocale();
        }

        // Set the locale (no session storage for stateless API)
        $translator->setLocale($locale, false);

        return $next($request);
    }

    /**
     * Extract locale from Accept-Language header.
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    protected function getLocaleFromHeader($request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');
        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header with quality values
        $languages = explode(',', $acceptLanguage);
        $enabledLocales = array_keys(Locale::listEnabled());

        foreach ($languages as $lang) {
            // Extract language code (before quality value)
            $code = strtolower(substr(trim(explode(';', $lang)[0]), 0, 2));

            if (in_array($code, $enabledLocales)) {
                return $code;
            }
        }

        return null;
    }
}
