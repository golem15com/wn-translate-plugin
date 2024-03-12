<?php namespace Golem15\Translate\Tests\Fixtures\Classes;

use Golem15\Translate\Classes\ThemeScanner;

class MessageScanner extends ThemeScanner
{
    public function getMessages($string)
    {
        return $this->processStandardTags($string);
    }
}
