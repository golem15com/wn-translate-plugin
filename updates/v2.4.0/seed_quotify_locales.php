<?php

namespace Golem15\Translate\Updates;

use DB;
use Winter\Storm\Database\Updates\Seeder;

/**
 * Seed Polish and German locales for Quotify.pro
 */
class SeedQuotifyLocales extends Seeder
{
    protected $locales = [
        [
            'code' => 'pl',
            'name' => 'Polski',
            'is_default' => false,
            'is_enabled' => true,
            'sort_order' => 2,
        ],
        [
            'code' => 'de',
            'name' => 'Deutsch',
            'is_default' => false,
            'is_enabled' => true,
            'sort_order' => 3,
        ],
    ];

    public function run()
    {
        foreach ($this->locales as $locale) {
            // Idempotent: only insert if locale doesn't exist
            $exists = DB::table('winter_translate_locales')
                ->where('code', $locale['code'])
                ->exists();

            if (!$exists) {
                DB::table('winter_translate_locales')->insert($locale);
            }
        }
    }
}
