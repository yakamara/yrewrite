<?php

/**
 * @author Daniel Weitenauer
 *
 * @package redaxo\yrewrite
 */

class rex_yrewrite_settings
{
    /**
     * @return rex_addon
     */
    protected static function getAddon()
    {
        return rex_addon::require('yrewrite');
    }

    /**
     * @return string
     */
    public static function processFormPost()
    {
        $addon = self::getAddon();

        $message = '';

        // Process form data
        if (rex_post('submit', 'boolean')) {
            $addon->setConfig('unicode_urls', rex_post('yrewrite_unicode_urls', 'bool'));
            $addon->setConfig('yrewrite_hide_url_block', rex_post('yrewrite_hide_url_block', 'bool'));
            $addon->setConfig('yrewrite_hide_seo_block', rex_post('yrewrite_hide_seo_block', 'bool'));
            $addon->setConfig('yrewrite_allow_article_ids', rex_post('yrewrite_allow_article_ids', 'string'));

            rex_yrewrite::deleteCache();

            $message = rex_view::success($addon->i18n('yrewrite_settings_saved'));
        }

        return $message;
    }

    /**
     * @return string
     */
    public static function getForm()
    {
        $addon = self::getAddon();

        $formElements = [];

        // Checkboxes
        $checkbox_elements = [
            [
                'label' => '<label for="yrewrite-unicode-urls">'.$addon->i18n('yrewrite_unicode_urls').'</label>',
                'field' => '<input type="checkbox" id="yrewrite-unicode-urls" name="yrewrite_unicode_urls" value="1" '.($addon->getConfig('unicode_urls') ? ' checked="checked"' : '').' />',
            ],
            [
                'label' => '<label for="yrewrite-hide-url-block">'.$addon->i18n('yrewrite_hide_url_block').'</label>',
                'field' => '<input type="checkbox" id="yrewrite-hide-url-block" name="yrewrite_hide_url_block" value="1" '.($addon->getConfig('yrewrite_hide_url_block') ? ' checked="checked"' : '').' />',
            ],
            [
                'label' => '<label for="yrewrite-hide-seo-block">'.$addon->i18n('yrewrite_hide_seo_block').'</label>',
                'field' => '<input type="checkbox" id="yrewrite-hide-seo-block" name="yrewrite_hide_seo_block" value="1" '.($addon->getConfig('yrewrite_hide_seo_block') ? ' checked="checked"' : '').' />',
            ],
        ];

        $fragment = new rex_fragment();
        $fragment->setVar('elements', $checkbox_elements, false);
        $content = $fragment->parse('core/form/checkbox.php');

        // Input Fields
        $inputGroups = [];
        $n = [];
        $n['field'] = '<input class="form-control" type="text" id="yrewrite_allow_article_ids" name="yrewrite_allow_article_ids" value="' . $addon->getConfig('yrewrite_allow_article_ids') . '" />';
        $n['left'] = $addon->i18n('yrewrite_allow_article_ids');
        $inputGroups[] = $n;

        $fragment = new rex_fragment();
        $fragment->setVar('elements', $inputGroups, false);
        $inputGroup = $fragment->parse('core/form/input_group.php');

        $n = [];
        $n['label'] = '';
        $n['field'] = $inputGroup;
        $formElements[] = $n;

        $fragment = new rex_fragment();
        $fragment->setVar('elements', $formElements, false);
        $content .= $fragment->parse('core/form/form.php');

        // Submit
        $submit_elements = [
            ['field' => '<button class="btn btn-save rex-form-aligned" type="submit" name="submit" value="1" '.rex::getAccesskey($addon->i18n('submit'), 'save').'>'.$addon->i18n('save').'</button>'],
        ];

        $fragment = new rex_fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $submit_elements, false);
        $submit = $fragment->parse('core/form/submit.php');

        // Form
        $fragment = new rex_fragment();
        $fragment->setVar('class', 'edit');
        $fragment->setVar('title', $addon->i18n('yrewrite_settings'));
        $fragment->setVar('body', $content, false);
        $fragment->setVar('buttons', $submit, false);

        return '
            <form action="'.rex_url::currentBackendPage().'" method="post">
                '.$fragment->parse('core/page/section.php').'
            </form>
        ';
    }

    /**
     * @return void
     */
    public static function install()
    {
        $addon = self::getAddon();

        if (!$addon->hasConfig('unicode_urls')) {
            $addon->setConfig('unicode_urls', false);
        }
        if (!$addon->hasConfig('yrewrite_hide_url_block')) {
            $addon->setConfig('yrewrite_hide_url_block', false);
        }
        if (!$addon->hasConfig('yrewrite_hide_url_block')) {
            $addon->setConfig('yrewrite_hide_url_block', false);
        }
        if (!$addon->hasConfig('yrewrite_allow_article_ids')) {
            $addon->setConfig('yrewrite_allow_article_ids', '');
        }
    }
}
