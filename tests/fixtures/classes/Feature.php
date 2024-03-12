<?php namespace Golem15\Translate\Tests\Fixtures\Classes;

use Cms\Classes\CmsCompoundObject;

/**
 * Feature Model
 */
class Feature extends CmsCompoundObject
{
    public $implement = ['@Golem15.Translate.Behaviors.TranslatableCmsObject'];

    /**
     * @var array Attributes that support translation, if available.
     */
    public $translatable = [
        'markup'
    ];

    /**
     * @var string The container name associated with the model, eg: pages.
     */
    protected $dirName = 'features';
}
