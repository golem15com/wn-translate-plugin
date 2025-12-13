<?php

namespace Golem15\Translate\FormWidgets;

use Backend\Classes\FormWidgetBase;

/**
 * ML Url
 * Renders a multi-lingual url field.
 *
 * @package Golem15\Translate
 */
class MLUrl extends FormWidgetBase
{
    use \Golem15\Translate\Traits\MLControl;

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mlurl';

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->initLocale();
    }

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        $this->prepareLocaleVars();

        $this->vars['urlField'] = $this->makePartial('~/modules/backend/widgets/form/partials/_field_url');

        if (!$this->isAvailable) {
            return $this->vars['urlField'];
        }

        return $this->makePartial('mlurl');
    }

    /**
     * Returns an array of translated values for this field
     * @return array
     */
    public function getSaveValue($value)
    {
        return $this->getLocaleSaveValue($value);
    }
}
