<?php

namespace Yakamara\YRewrite;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

/**
 * Visibility / behaviour settings shown on the setup page.
 *
 * Port of REDAXO 5 `rex_yrewrite_settings`.
 */
class Settings
{
    protected static function getAddon(): Addon
    {
        return Addon::require('yrewrite');
    }

    public static function processFormPost(): string
    {
        $addon = self::getAddon();
        $message = '';

        if (Request::post('submit', 'boolean')) {
            $addon->setConfig('unicode_urls', Request::post('yrewrite_unicode_urls', 'bool'));
            $addon->setConfig('hide_url_block', Request::post('yrewrite_hide_url_block', 'bool'));
            $addon->setConfig('hide_seo_block', Request::post('yrewrite_hide_seo_block', 'bool'));

            YRewrite::deleteCache();

            $message = Message::success($addon->i18n('settings_saved'));
        }

        return $message;
    }

    public static function getForm(): string
    {
        $addon = self::getAddon();

        $checkboxElements = [
            [
                'label' => '<label for="yrewrite-unicode-urls">' . $addon->i18n('unicode_urls') . '</label>',
                'field' => '<input type="checkbox" id="yrewrite-unicode-urls" name="yrewrite_unicode_urls" value="1" ' . ($addon->getConfig('unicode_urls') ? ' checked="checked"' : '') . ' />',
            ],
            [
                'label' => '<label for="yrewrite-hide-url-block">' . $addon->i18n('hide_url_block') . '</label>',
                'field' => '<input type="checkbox" id="yrewrite-hide-url-block" name="yrewrite_hide_url_block" value="1" ' . ($addon->getConfig('hide_url_block') ? ' checked="checked"' : '') . ' />',
            ],
            [
                'label' => '<label for="yrewrite-hide-seo-block">' . $addon->i18n('hide_seo_block') . '</label>',
                'field' => '<input type="checkbox" id="yrewrite-hide-seo-block" name="yrewrite_hide_seo_block" value="1" ' . ($addon->getConfig('hide_seo_block') ? ' checked="checked"' : '') . ' />',
            ],
        ];

        $fragment = new Fragment();
        $fragment->setVar('elements', $checkboxElements, false);
        $checkboxes = $fragment->parse('core/form/checkbox.php');

        $submitElements = [
            ['field' => '<button class="btn btn-save rex-form-aligned" type="submit" name="submit" value="1" ' . Core::getAccesskey($addon->i18n('save'), 'save') . '>' . $addon->i18n('save') . '</button>'],
        ];

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $submitElements, false);
        $submit = $fragment->parse('core/form/submit.php');

        $fragment = new Fragment();
        $fragment->setVar('class', 'edit');
        $fragment->setVar('title', $addon->i18n('settings'));
        $fragment->setVar('body', $checkboxes, false);
        $fragment->setVar('buttons', $submit, false);

        return '
            <form action="' . Url::currentBackendPage() . '" method="post">
                ' . $fragment->parse('core/page/section.php') . '
            </form>
        ';
    }
}
