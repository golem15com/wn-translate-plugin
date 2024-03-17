<?php

namespace Golem15\Translate\Support;

class LanguageInfo
{
    public static function getNameForCode(string $languageCode)
    {
        $list = plugins_path('golem15/translate/assets/languages.json');
        $languages = json_decode(file_get_contents($list), true);
        if (array_key_exists($languageCode, $languages)) {
            return $languages[$languageCode];
        }
        return 'English';
    }
}
