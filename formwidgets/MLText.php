<?php

namespace Golem15\Translate\FormWidgets;

use Backend\Classes\FormWidgetBase;

/**
 * ML Text
 * Renders a multi-lingual text field.
 *
 * @package Golem15\Translate
 * @author Alexey Bobkov, Samuel Georges
 */
class MLText extends FormWidgetBase
{
    use \Golem15\Translate\Traits\MLControl;

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mltext';

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

        if ($this->isAvailable) {
            return $this->makePartial('mltext');
        } else {
            return $this->renderFallbackField();
        }
    }

    /**
     * Returns an array of translated values for this field
     * @return array
     */
    public function getSaveValue($value)
    {
        return $this->getLocaleSaveValue($value);
    }

    /**
     * Rewrites post values to set the correct locale context before save
     * Reads the active locale from RLTranslateActiveLocale[fieldName]
     */
    protected function rewritePostValues()
    {
        $data = post('RLTranslateActiveLocale');
        if (!$data) {
            return;
        }

        // Find active locale by searching for key ending with [fieldName]
        // POST has "Achievement[name]" but we only know "name"
        $activeLocale = null;
        foreach ($data as $key => $value) {
            if (str_ends_with($key, '[' . $this->fieldName . ']')) {
                $activeLocale = $value;
                break;
            }
        }

        if (!$activeLocale) {
            return;
        }

        // Set model's translatable context to active locale
        if ($this->model && method_exists($this->model, 'translateContext')) {
            $this->model->translateContext($activeLocale);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function loadAssets()
    {
        $this->loadLocaleAssets();
    }
}
