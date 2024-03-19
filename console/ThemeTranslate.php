<?php

namespace Golem15\Translate\Console;


use Golem15\Translate\Support\TranslationScanner;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;

class ThemeTranslate extends Command
{

    /**
     * The console command name.
     */
    protected $name = 'theme:translate';

    /**
     * The console command description.
     */
    protected $description = 'Generates missing theme translation entries.';


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
        /*
         * Extract the author and name from the plugin code
         */
        $theme = $this->argument('name');

        $destinationPath = themes_path(strtolower($theme));

        $scanner = TranslationScanner::instance();
        $vars = [
            'path' => $destinationPath,
            'author' => 'themes',
            'plugin' => 'arsha'
        ];
        collect([
            'behaviors',
            'classes',
            'console',
            'controllers',
            'components',
            'helpers',
            'models',
            'partials',
            'widgets',
            'formwidgets',
            'reportwidgets',
            'traits',
            'twig',
            'views',
            'content',
            'config'
        ])->each(function ($path) use ($destinationPath, $scanner, $vars) {
            $target = "{$destinationPath}/{$path}";

            if (is_dir($target)) {
                $this->info(sprintf("Scanning {$path}..."));
                $this->noFiles($scanner->with($vars)->scan($target));
            }
        });

        collect([
            'Plugin.php',
        ])->each(function ($path) use ($destinationPath, $scanner, $vars) {
            $target = "{$destinationPath}/{$path}";

            if (is_file($target)) {
                $this->info(sprintf("Scanning {$path}..."));
                $this->noFiles($scanner->with($vars)->scanFile($target));
            }
        });

        $this->info(sprintf('Successfully generated translation entries for plugin "%s"', $theme));
    }


    /**
     * Get the console command arguments.
     */
    protected function getArguments()
    {
        return [
            [
                'name',
                InputArgument::REQUIRED,
                'The name of the plugin to scan. Eg: Golem15.Blog'
            ],
        ];
    }


    /**
     * Get the console command options.
     */
    protected function getOptions()
    {
        return [];
    }


    protected function noFiles($found)
    {
        if ($found === false) {
            $this->info(sprintf('... no file found.'));
        } else {
            $this->info(sprintf('... found %s new entries.', $found));
        }
    }

}
