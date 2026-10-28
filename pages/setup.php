<?php

/**
 * @var Yakamara\YRewrite\YRewriteAddon $this
 */

use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
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

$exampleCode = Str::highlight(<<<'PHP'
<?php
    $seo = new \Yakamara\YRewrite\Seo();
    echo $seo->getTags();
?>
PHP);

$content = '
    <h3>' . $this->i18n('htaccess_set') . '</h3>
    <p>' . I18n::rawMsg('yrewrite_htaccess_info') . '</p>
    <p><a class="rex-button" data-variant="primary" href="' . Url::currentBackendPage(['func' => 'htaccess'] + $csrf->getUrlParams()) . '">' . $this->i18n('htaccess_set') . '</a></p>

    <h3>' . $this->i18n('info_headline') . '</h3>
    <p>' . I18n::rawMsg('yrewrite_info_text') . '</p>

    <h3>' . $this->i18n('info_tipps') . '</h3>
    <p>' . I18n::rawMsg('yrewrite_info_tipps_text') . '</p>

    <h3>' . $this->i18n('info_seo') . '</h3>
    <p>' . I18n::rawMsg('yrewrite_info_seo_text') . '</p>' . $exampleCode . '
';

echo new Section($this->i18n('setup'), body: Html::raw($content))->render();

// visibility settings form
echo Settings::processFormPost();
echo Settings::getForm();

// sitemap / robots overview per configured domain
$link = static fn (string $url, string $label): Html => Html::raw('<a target="_blank" href="' . escape($url) . '">' . escape($label) . '</a>');
$domains = [];
foreach (YRewrite::getDomains() as $name => $domain) {
    if ('default' !== $name) {
        $domains[] = [
            $link($domain->getUrl(), $name),
            $link($domain->getUrl() . 'sitemap.xml', 'sitemap.xml'),
            $link($domain->getUrl() . 'robots.txt', 'robots.txt'),
        ];
    }
}

echo new Section($this->i18n('info_sitemaprobots'), content: new Table([
    TableColumn::forKey('Domain', 0),
    TableColumn::forKey('Sitemap', 1),
    TableColumn::forKey('robots.txt', 2),
], $domains, attributes: new HtmlAttributes(['data-hover' => true])))->render();
