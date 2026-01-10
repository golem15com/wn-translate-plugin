<?php

namespace Golem15\Translate\FormWidgets;

use Backend\Classes\FormWidgetBase;

/**
 * ML Textarea
 * Renders a multi-lingual textarea field.
 *
 * @package Golem15\Translate
 * @author Alexey Bobkov, Samuel Georges
 */
class MLTextarea extends FormWidgetBase
{
    use \Golem15\Translate\Traits\MLControl;

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mltextarea';

    /**
     * @var string If translation is unavailable, fall back to this standard field.
     */
    const FALLBACK_TYPE = 'textarea';

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
            return $this->makePartial('mltextarea');
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
        $this->addJs('js/mltextarea.js');
    }

}
