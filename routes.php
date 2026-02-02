<?php

use Illuminate\Foundation\Application as Laravel;
use Golem15\Translate\Classes\Translator;
use Golem15\Translate\Models\Message;

/*
 * Adds a custom route to check for the locale prefix.
 */
$beforeCallback = function () {
    if (Config::get('golem15.translate::disableLocalePrefixRoutes', false)) {
        return;
    }

    if (App::runningInBackend()) {
        return;
    }

    $translator = Translator::instance();

    if (
        !$translator->isConfigured() ||
        !$translator->loadLocaleFromRequest() ||
        (!$locale = $translator->getLocale())
    ) {
        return;
    }

    // Set manual selection cookie when URL has locale prefix
    // This prevents browser detection from overriding explicit URL visits
    \Cookie::queue(
        \Config::get('golem15.translate::browserDetection.manualSelectionCookie', 'locale_manually_set'),
        '1',
        \Config::get('golem15.translate::browserDetection.manualSelectionExpiry', 525600)
    );

    /*
     * Register routes
     */
    Route::group(['prefix' => $locale, 'middleware' => 'web'], function () {
        Route::any('{slug?}', 'Cms\Classes\CmsController@run')->where('slug', '(.*)?');
    });

    Route::any($locale, 'Cms\Classes\CmsController@run')->middleware('web');

    /*
     * Ensure Url::action() retains the localized URL
     * by re-registering the route after the CMS.
     */
    Event::listen('cms.route', function () use ($locale) {
        Route::group(['prefix' => $locale, 'middleware' => 'web'], function () {
            Route::any('{slug?}', 'Cms\Classes\CmsController@run')->where('slug', '(.*)?');
        });
    });
};

if (version_compare(Laravel::VERSION, '9.0.0', '>=')) {
    Event::listen('system.route', $beforeCallback);
} else {
    App::before($beforeCallback);
}

/*
 * Save any used messages to the contextual cache.
 */
App::after(function () {
    if (class_exists('Golem15\Translate\Models\Message')) {
        Message::saveToCache();
    }
});
