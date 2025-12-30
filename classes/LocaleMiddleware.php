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
                // Priority 3 & 4: Session or default locale (original behavior)
                if (Config::get('golem15.translate::prefixDefaultLocale')) {
                    $translator->loadLocaleFromSession();
                } else {
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
}
