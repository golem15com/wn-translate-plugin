<?php

namespace Golem15\Translate\Console;

use Golem15\AI\Interfaces\Engine;
use Golem15\AI\Models\Prompt;
use Golem15\AI\Models\Settings;
use Golem15\AI\Support\EngineRegistry;
use Golem15\SmartSite\Factories\ContentFactory;
use Golem15\Translate\Support\LanguageInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Mistralys\Diff\Diff;
use Symfony\Component\Console\Input\InputArgument;

class PluginTranslateAI extends Command
{
    /**
     * The console command name.
     */
    protected $name = 'plugin:translate-ai';

    protected $signature = 'plugin:translate-ai {name} {language?}';

    /**
     * The console command description.
     */
    protected $description = 'Generates missing plugin translation entries.';


    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $plugin = $this->argument('name');
        $languageSelected = $this->argument('language');
        $this->info('Translating ' . $plugin . '... ');
        $parts = explode('.', $plugin);

        if (count($parts) != 2) {
            $this->error('Invalid plugin name, either too many dots or not enough.');
            $this->error('Example name: AuthorName.PluginName');

            return;
        }

        $pluginName = array_pop($parts);
        $authorName = array_pop($parts);

        $destinationPath = base_path() . '/plugins/' . strtolower($authorName) . '/' . strtolower($pluginName) . '/lang/';
        // there are xx/lang.php files in the folder above where xx is the code of the language
        // i want to loop through all the lang files and find data that includes "::lang." as a value

        $languages = scandir($destinationPath);
        $english = $destinationPath . 'en/lang.php';
        $contextLanguage = include($english);
        $pending = [];
        $context = [];
        $translated = [];
        $this->info('Scanning for missing translations...');
      //  Artisan::call('plugin:translate ' . $plugin);
        foreach ($languages as $language) {
            if ($language == '.' || $language == '..' || !is_dir($destinationPath . $language)) {
                continue;
            }
            if($languageSelected && $language != $languageSelected){
                continue;
            }
            $langFile = $destinationPath . $language . '/lang.php';
            if (file_exists($langFile)) {
                $langArray = include($langFile);
                $missingTranslations = $this->getMissingTranslations($langArray);
                if ($missingTranslations) {
                    $pending[$language] = $missingTranslations;
                }
            }
        }
        $combinedMissing = [];
        $this->info('Generating context...');
        $this->combineMissing($combinedMissing, $pending);
        $this->pruneContextArray($contextLanguage, $combinedMissing);
        $this->info('AI translations start.');
        $this->info(count($combinedMissing) . ' strings require translation.');
        foreach ($pending as $languageCode => $value) {
            if ($languageCode === 'en'){
                $c = $this->confirm('Skipping English translation?');
                if($c) {
                    continue;
                }
            }
            $this->info('Translating ' . $languageCode . '...');
            $translated[$languageCode] = $this->getTranslation($languageCode, $value, $contextLanguage);
            $this->info('Translation for ' . $languageCode);
            dump($translated[$languageCode]);
            $confirmed = $this->confirm('Apply?');
            if ($confirmed) {
                $langFile = $destinationPath . $languageCode . '/lang.php';
                $langArray = include($langFile);
                $translatedLangArray = $this->mergeLocaleFiles($langArray, $translated[$languageCode]);
                $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($translatedLangArray, true) . ';';
                file_put_contents($langFile, $content, LOCK_EX);
            }
        }
        $this->info('Finished.');
    }

    protected function getArguments()
    {
        return [
            [
                'name',
                InputArgument::REQUIRED,
                'The name of the plugin to scan. Eg: Golem15.Blog'
            ],
            [
                'language',
                InputArgument::OPTIONAL,
                'The language to translate. Eg: pl'
            ],
        ];
    }

    public function combineMissing(&$combined, $translations, $path = [], bool $firstLoop = true)
    {
        foreach ($translations as $key => $value) {
            $currentPath = array_merge($path, [$key]);
            if ($firstLoop) {
                $currentPath = [];
            }
            if (is_array($value)) {
                $this->combineMissing($combined, $value, $currentPath, false);
            } else {
                $pathString = implode('.', $currentPath);
                $combined[$pathString] = true;
            }
        }
    }

    public function pruneContextArray(&$sourceLanguage, $combinedMissing, $path = [])
    {
        foreach ($sourceLanguage as $key => &$value) {
            $currentPath = implode('.', array_merge($path, [$key]));

            if (is_array($value)) {
                $this->pruneContextArray($value, $combinedMissing, array_merge($path, [$key]));
                // If the sub-array is now empty, remove it as well
                if (empty($value)) {
                    unset($sourceLanguage[$key]);
                }
            } else {
                // If the current path is not marked as missing in combined missing translations, remove it
                if (!isset($combinedMissing[$currentPath])) {
                    unset($sourceLanguage[$key]);
                }
            }
        }
    }

    private function getMissingTranslations(array $langArray)
    {
        $missing = [];
        foreach ($langArray as $key => $value) {
            if (is_string($value)) {
                if (str_contains($value, '::lang.')) {
                    $missing[$key] = $value;
                }
            }
            if (is_array($value)) {
                $subMissing = $this->getMissingTranslations($value);
                if ($subMissing) {
                    $currentlyMissing = $missing[$key] ?? [];
                    $missing[$key] = array_merge($currentlyMissing, $subMissing);
                }
            }
        }

        return $missing;
    }

    private function getTranslation(int|string $languageCode, mixed $value, mixed $contextLanguage)
    {
        $prompt = new Prompt();
        $settings = Settings::instance();
        $prompt->language_model_id = $settings->default_model;
        $prompt->save();
        $prompt->refresh();
        $prompt->engine_id = $prompt->languageModel->engine_id;
        $langName = LanguageInfo::getNameForCode($languageCode);
        $query = 'Translate below content to language: ' . $langName . PHP_EOL;
        $query .= 'Always read target code from the CODE. I will give you context by adding default version, use it to translate all ::lang fields properly. Respond in JSON.' . PHP_EOL;
        $query .= 'Never respond back with ::lang items. Use the context to translate the content properly to ' .$langName .', all sources are there.';
        $query .= 'Source data: ' . json_encode($contextLanguage);
        $query .= 'Content to translate: ' . json_encode($value);
        $prompt->query = $query;
        $prompt->save();
        /** @var Engine $engine */
        $engine = app()->make(EngineRegistry::class)->getEngine($prompt->engine->class);
        $message = $engine->getResponse($prompt, [], true, false);

        return $message->getJSONResponse();
    }

    public function mergeLocaleFiles(mixed $langArray, mixed $translation)
    {
        $merged = $langArray;
        if (is_array($translation))
            foreach ($translation as $key => $val)
                if (is_array($translation[$key]))
                    $merged[$key] = is_array($merged[$key]) ? $this->mergeLocaleFiles($merged[$key], $translation[$key]) : $translation[$key];
                else
                    $merged[$key] = $val;

        return $merged;
    }

}
