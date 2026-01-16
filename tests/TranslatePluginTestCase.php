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
}
