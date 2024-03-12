<?php

namespace Golem15\Translate\Classes;

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

        if (!$translator->loadLocaleFromRequest()) {
            if (Config::get('golem15.translate::prefixDefaultLocale')) {
                $translator->loadLocaleFromSession();
            } else {
                $translator->setLocale($translator->getDefaultLocale());
            }
        }

        return $next($request);
    }
}
