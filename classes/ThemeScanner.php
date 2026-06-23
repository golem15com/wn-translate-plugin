<?php

namespace Golem15\Translate\Classes;

use Cms\Classes\Layout;
use Cms\Classes\Page;
use Cms\Classes\Partial;
use Cms\Classes\Theme;
use Event;
use File;
use System\Models\MailTemplate;
use Golem15\Translate\Classes\Translator;
use Golem15\Translate\Models\Message;

/**
 * Theme scanner class
 *
 * @package Golem15\Translate
 * @author Alexey Bobkov, Samuel Georges
 */
class ThemeScanner
{
    /**
     * Helper method for scanForMessages()
     * @return void
     */
    public static function scan()
    {
        $obj = new static;

        $obj->scanForMessages();

        /**
         * @event golem15.translate.themeScanner.afterScan
         * Fires after theme scanning.
         *
         * Example usage:
         *
         *     Event::listen('golem15.translate.themeScanner.afterScan', function (ThemeScanner $scanner) {
         *         // added an extra scan. Add generation files...
         *     });
         *
         */
        Event::fire('golem15.translate.themeScanner.afterScan', [$obj]);
    }

    /**
     * Scans theme templates and config for messages.
     * @return void
     */
    public function scanForMessages()
    {
        // Set all messages initially as being not found. The scanner later
        // sets the entries it finds as found.
        Message::query()->update(['found' => false]);

        $this->scanThemeConfigForMessages();
        $this->scanThemeTemplatesForMessages();
        $this->scanPluginTemplatesForMessages();
        $this->scanMailTemplatesForMessages();
    }

    /**
     * Scans the theme configuration for defined messages
     * @return void
     */
    public function scanThemeConfigForMessages()
    {
        $theme = Theme::getActiveTheme();
        if (!$theme) {
            return;
        }

        $config = $theme->getConfigArray('translate');

        if (!count($config)) {
            return;
        }

        $translator = Translator::instance();
        $keys = [];

        foreach ($config as $locale => $messages) {
            if (is_string($messages)) {
                // $message is a yaml filename, load the yaml file
                $messages = $theme->getConfigArray('translate.'.$locale);
            }
            $keys = array_merge($keys, array_keys($messages));
        }

        Message::importMessages($keys);

        foreach ($config as $locale => $messages) {
            if (is_string($messages)) {
                // $message is a yaml filename, load the yaml file
                $messages = $theme->getConfigArray('translate.'.$locale);
            }
            Message::importMessageCodes($messages, $locale);
        }
    }

    /**
     * Scans the theme templates for message references.
     * @return void
     */
    public function scanThemeTemplatesForMessages()
    {
        $messages = [];

        foreach (Layout::all() as $layout) {
            $messages = array_merge($messages, $this->parseContent($layout->markup));
        }

        foreach (Page::all() as $page) {
            $messages = array_merge($messages, $this->parseContent($page->markup));
        }

        foreach (Partial::all() as $partial) {
            $messages = array_merge($messages, $this->parseContent($partial->markup));
        }

        Message::importMessages($messages);
    }

    public function scanPluginTemplatesForMessages()
    {
        $messages = [];

        $pluginsPath = base_path('plugins');
        if (!is_dir($pluginsPath)) {
            return;
        }

        // Robust: scan any .htm file under plugins/*/*/components/
        foreach (File::directories($pluginsPath) as $authorDir) {
            foreach (File::directories($authorDir) as $pluginDir) {
                $componentsDir = $pluginDir . DIRECTORY_SEPARATOR . 'components';
                if (!is_dir($componentsDir)) {
                    continue;
                }

                foreach (File::allFiles($componentsDir) as $file) {
                    if (strtolower($file->getExtension()) !== 'htm') {
                        continue;
                    }

                    $contents = File::get($file->getPathname());
                    if ($contents !== null && $contents !== '') {
                        // Avoid O(n^2) array_merge in loops
                        foreach ($this->parseContent($contents) as $msg) {
                            $messages[] = $msg;
                        }
                    }
                }
            }
        }

        // Optional but usually good: de-dupe before import
        $messages = array_values(array_unique($messages));

        Message::importMessages($messages);
    }

    /**
     * Scans the mail templates for message references.
     * @return void
     */
    public function scanMailTemplatesForMessages()
    {
        $messages = [];

        foreach (MailTemplate::allTemplates() as $mailTemplate) {
            $messages = array_merge($messages, $this->parseContent($mailTemplate->subject));
            $messages = array_merge($messages, $this->parseContent($mailTemplate->content_html));
        }

        Message::importMessages($messages);
    }

    /**
     * Parse the known language tag types in to messages.
     * @param  string $content
     * @return array
     */
    public function parseContent($content)
    {
        $messages = [];
        if ($content) {
            $messages = array_merge($messages, $this->processStandardTags($content));
        }

        return $messages;
    }

    /**
     * Process standard language filter tag (_|)
     * @param  string $content
     * @return array
     */
    protected function getFilters()
    {
        return [
            '_',
            '__',
            'transRaw',
            'transRawPlural',
            'localeUrl'
        ];
    }

    /**
     * Get an array of Twig tokens
     * @param  string $string
     * @return array
     */
    protected function findTwigTokensInString($string)
    {
        $loader = new \Twig\Loader\ArrayLoader();
        $env = new \Twig\Environment($loader);
        $source = new \Twig\Source($string, 'test');

        try {
            $stream = $env->tokenize($source);
        }
        catch (\Exception $e) {
            return [];
        }

        $tokens = [];

        // Walking the token stream raises two deprecations per token: Twig 3.19+
        // deprecates Token::getType() (E_USER_DEPRECATED) and PHP 8.4+ deprecates the
        // dynamic Token::$typeString property assigned below (E_DEPRECATED). Under the
        // framework deprecation handler each notice is very expensive (it resolves the
        // container and triggers class autoloading), so scanning every template turns a
        // theme scan into an effective hang. Swallow only these deprecation notices
        // while tokenizing -- behaviour is unchanged -- and let every other error fall
        // through to the real handler.
        set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED);
        try {
            while (!$stream->isEOF()) {
                $token = $stream->next();
                $token->typeString = $token->typeToString($token->getType(), true);
                $tokens[] = $token;
            }
        } finally {
            restore_error_handler();
        }

        return $tokens;
    }

    /**
     * Searches for strings to be translated within a given Twig string
     * @param  string $content
     * @return array
     */
    protected function processStandardTags($content)
    {
        $tokens = $this->findTwigTokensInString($content);

        $translatable_strings = [];
        $var_token_started = false;
        for ($i = 0; $i < count($tokens); $i++) {
            switch ($tokens[$i]->typeString) {
                case 'VAR_START_TYPE':
                    $var_token_started = true;
                    continue 2;
                case 'VAR_END_TYPE':
                    $var_token_started = false;
                    continue 2;
            }
            if (
                $var_token_started
                && $tokens[$i]->typeString === 'STRING_TYPE'
                && in_array($tokens[$i+1]->typeString, ['PUNCTUATION_TYPE', 'OPERATOR_TYPE'])
                && $tokens[$i+1]->getValue() === '|'
                && $tokens[$i+2]->typeString === 'NAME_TYPE'
                && in_array($tokens[$i+2]->getValue(), $this->getFilters())
            ) {
                $translatable_strings[] = stripslashes($tokens[$i]->getValue());
                $i += 2;
            }
        }

        return $translatable_strings;
    }
}
