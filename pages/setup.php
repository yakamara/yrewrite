<?php

/**
 * YREWRITE Addon.
 *
 * @author jan.kristinus@yakamara.de
 *
 * @package redaxo\yrewrite
 *
 * @psalm-scope-this rex_addon
 * @var rex_addon $this
 */

$func = rex_request('func', 'string');
$csrf = rex_csrf_token::factory('yrewrite_setup');

if ('' != $func) {
    if (!$csrf->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        if ('htaccess' == $func) {
            if (rex_yrewrite::copyHtaccess()) {
                echo rex_view::success($this->i18n('htaccess_hasbeenset'));
            } else {
                echo rex_view::error($this->i18n('htaccess_hasnotbeenset'));
            }
        }
    }
}

$content = '

            <h3>' . $this->i18n('htaccess_set') . '</h3>
            <p>' . rex_i18n::rawMsg('yrewrite_htaccess_info') . '</p>
            <p><a class="btn btn-primary" href="'.rex_url::currentBackendPage(['func' => 'htaccess'] + $csrf->getUrlParams()).'">' . $this->i18n('yrewrite_htaccess_set') . '</a></p>

            <h3>' . $this->i18n('info_headline') . '</h3>
            <p>' . rex_i18n::rawMsg('yrewrite_info_text') . '</p>


            <h3>' . $this->i18n('info_tipps') . '</h3>
            <p>' . rex_i18n::rawMsg('yrewrite_info_tipps_text') . '


            <h3>' . $this->i18n('info_seo') . '</h3>
            <p>' . rex_i18n::rawMsg('yrewrite_info_seo_text') . '

            <br /><br />'.highlight_string(<<<'PHP'
    <?php
        $seo = new rex_yrewrite_seo();
        echo $seo->getTags();
    ?>
    PHP, true).'
            </p>
            ';

$fragment = new rex_fragment();
$fragment->setVar('title', $this->i18n('setup'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

/**
 * Process and display visibility settings form.
 */
echo rex_yrewrite_settings::processFormPost();
echo rex_yrewrite_settings::getForm();

$domains = [];

foreach (rex_yrewrite::getDomains() as $name => $domain) {
    if ('default' != $name) {
        $domains[] = '<tr><td><a target="_blank" href="'.$domain->getUrl().'">'.htmlspecialchars($name).'</a></td><td><a target="_blank" href="'.$domain->getUrl().'sitemap.xml">sitemap.xml</a></td><td><a target="_blank" href="'.$domain->getUrl().'robots.txt">robots.txt</a></td></tr>';
    }
}

$tables = '<table class="table table-hover">
            <tr>
                <th>Domain</th><th>Sitemap</th><th>robots.txt</th></tr>
            '.implode('', $domains).'
            </table>';

$fragment = new rex_fragment();
$fragment->setVar('title', $this->i18n('info_sitemaprobots'));
$fragment->setVar('content', $tables, false);
echo $fragment->parse('core/page/section.php');
