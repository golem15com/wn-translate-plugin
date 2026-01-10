<?php

namespace Golem15\Translate\FormWidgets;

use Backend\FormWidgets\RichEditor;
use Golem15\Translate\Models\Locale;

/**
 * ML Rich Editor
 * Renders a multi-lingual WYSIWYG editor.
 *
 * @package Golem15\Translate
 * @author Alexey Bobkov, Samuel Georges
 */
class MLRichEditor extends RichEditor
{
    use \Golem15\Translate\Traits\MLControl;

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mlricheditor';

    public $originalAssetPath;
    public $originalViewPath;

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        parent::init();
        $this->initLocale();
    }

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        $this->actAsParent();
        $parentContent = parent::render();
        $this->actAsParent(false);

        if (!$this->isAvailable) {
            return $parentContent;
        }

        $this->vars['richeditor'] = $parentContent;
        return $this->makePartial('mlricheditor');
    }

    public function prepareVars()
    {
        parent::prepareVars();
        $this->prepareLocaleVars();
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
        $this->actAsParent();
        parent::loadAssets();
        $this->actAsParent(false);

        if (Locale::isAvailable()) {
            $this->loadLocaleAssets();
            $this->addJs('js/mlricheditor.js');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function onLoadPageLinksForm()
    {
        $this->actAsParent();
        return parent::onLoadPageLinksForm();
    }

    /**
     * {@inheritDoc}
     */
    protected function getParentViewPath()
    {
        return base_path().'/modules/backend/formwidgets/richeditor/partials';
    }

    /**
     * {@inheritDoc}
     */
    protected function getParentAssetPath()
    {
        return '/modules/backend/formwidgets/richeditor/assets';
    }
}
