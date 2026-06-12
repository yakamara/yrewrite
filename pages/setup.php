<?php

/**
 * @var Yakamara\YRewrite\YRewriteAddon $this
 */

use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Yakamara\YRewrite\Settings;
use Yakamara\YRewrite\YRewrite;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$csrf = CsrfToken::factory('yrewrite_setup');

if ('' !== $func) {
    if (!$csrf->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
    } elseif ('htaccess' === $func) {
        if (YRewrite::copyHtaccess()) {
            echo Message::success($this->i18n('htaccess_hasbeenset'));
        } else {
            echo Message::error($this->i18n('htaccess_hasnotbeenset'));
        }
    }
}

$exampleCode = highlight_string(<<<'PHP'
<?php
    $seo = new \Yakamara\YRewrite\Seo();
    echo $seo->getTags();
?>
PHP, true);

$content = '
    <h3>' . $this->i18n('htaccess_set') . '</h3>
    <p>' . I18n::rawMsg('yrewrite_htaccess_info') . '</p>
    <p><a class="btn btn-primary" href="' . Url::currentBackendPage(['func' => 'htaccess'] + $csrf->getUrlParams()) . '">' . $this->i18n('htaccess_set') . '</a></p>

    <h3>' . $this->i18n('info_headline') . '</h3>
    <p>' . I18n::rawMsg('yrewrite_info_text') . '</p>

    <h3>' . $this->i18n('info_tipps') . '</h3>
    <p>' . I18n::rawMsg('yrewrite_info_tipps_text') . '</p>

    <h3>' . $this->i18n('info_seo') . '</h3>
    <p>' . I18n::rawMsg('yrewrite_info_seo_text') . '<br /><br />' . $exampleCode . '</p>
';

$fragment = new Fragment();
$fragment->setVar('title', $this->i18n('setup'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// visibility settings form
echo Settings::processFormPost();
echo Settings::getForm();

// sitemap / robots overview per configured domain
$domains = [];
foreach (YRewrite::getDomains() as $name => $domain) {
    if ('default' !== $name) {
        $domains[] = '<tr><td><a target="_blank" href="' . escape($domain->getUrl()) . '">' . escape($name) . '</a></td>'
            . '<td><a target="_blank" href="' . escape($domain->getUrl()) . 'sitemap.xml">sitemap.xml</a></td>'
            . '<td><a target="_blank" href="' . escape($domain->getUrl()) . 'robots.txt">robots.txt</a></td></tr>';
    }
}

$tables = '<table class="table table-hover">
    <tr><th>Domain</th><th>Sitemap</th><th>robots.txt</th></tr>
    ' . implode('', $domains) . '
    </table>';

$fragment = new Fragment();
$fragment->setVar('title', $this->i18n('info_sitemaprobots'));
$fragment->setVar('content', $tables, false);
echo $fragment->parse('core/page/section.php');
