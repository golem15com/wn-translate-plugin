<?php namespace Golem15\Translate\Tests;

if (class_exists('\System\Tests\Bootstrap\PluginTestCase')) {
    class BaseTestCase extends \System\Tests\Bootstrap\PluginTestCase
    {
    }
} else {
    class BaseTestCase extends \PluginTestCase
    {
    }
}

abstract class TranslatePluginTestCase extends BaseTestCase
{
    protected $refreshPlugins = [
        'golem15.translate',
    ];

    public function setUp(): void
    {
        parent::setUp();

        // PluginTestCase boots Laravel before winter:up runs, so SystemServiceProvider
        // sees no migrations table and forces PluginManager into noInit mode. That
        // bypasses Plugin::register() and Plugin::boot() (and therefore the
        // backend.form.extendFieldsBefore listener that registers ML field
        // replacements). Once migrations have run, force the plugin through its
        // full register+boot lifecycle so its event listeners are active.
        \System\Classes\PluginManager::$noInit = false;
        $manager = \System\Classes\PluginManager::instance();
        $plugin = $manager->findByIdentifier('Golem15.Translate');
        if ($plugin) {
            $plugin->register();
            $plugin->boot();
        }
    }
}
