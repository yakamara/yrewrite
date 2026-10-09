<?php

/**
 * Article content sidebar panel: per-article URL (yrewrite_url_type / yrewrite_url / yrewrite_redirection).
 *
 * @var YRewriteAddon $this
 * @var array{article_id: int, language: int, ctype: int} $params
 */

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\RexVar\LinkVar;
use Redaxo\Core\View\Message;
use Yakamara\YRewrite\YRewrite;
use Yakamara\YRewrite\YRewriteAddon;

use function Redaxo\Core\View\escape;

$articleId = (int) $params['article_id'];
$clang = (int) $params['language'];
$ctype = (int) $params['ctype'];

$domain = YRewrite::getDomainByArticleId($articleId, $clang);

// the domain start article always has the URL "/", it cannot be changed
if (YRewrite::isDomainStartArticle($articleId, $clang)) {
    return '<section id="rex-page-sidebar-yrewrite-url">'
        . Message::warning($this->i18n('startarticleisalways', $domain->getName()))
        . '</section>';
}

$article = Article::get($articleId, $clang);
$currentType = (string) ($article?->getValue('yrewrite_url_type') ?: 'AUTO');
$currentRedirection = (string) ($article?->getValue('yrewrite_redirection') ?? '');
$externalValue = 'REDIRECTION_EXTERNAL' === $currentType ? $currentRedirection : '';
$internalValue = 'REDIRECTION_INTERNAL' === $currentType && is_numeric($currentRedirection) ? (int) $currentRedirection : null;

$applyUrl = Url::backendPage('content/edit', ['article_id' => $articleId, 'language' => $clang, 'ctype' => $ctype]);

// The URL settings live on rex_article and apply to all languages. The where clause is written differently than in
// the SEO panel ("id = …" vs "id=…") because the form name is the md5 of table and where.
$form = Form::factory('rex_article', 'yrewrite_url', 'id = ' . $articleId);
$form->addParam('page', 'content/edit');
$form->addParam('article_id', $articleId);
$form->addParam('language', $clang);
$form->addParam('ctype', $ctype);
$form->setApplyUrl($applyUrl);
$form->setEditMode(true);

Extension::register('REX_FORM_SAVED', static function (ExtensionPoint $ep) use ($form, $articleId): void {
    if ($ep->getParam('form') !== $form) {
        return;
    }

    // yrewrite_redirection is not a form column (it holds either an article id or a URL); write it manually
    $sql = Sql::factory();
    $sql->setQuery('SELECT yrewrite_url_type FROM rex_article WHERE id = ?', [$articleId]);
    $savedType = (string) $sql->getValue('yrewrite_url_type');
    $redirection = match ($savedType) {
        'REDIRECTION_INTERNAL' => (string) Request::post('yrewrite_redirection_internal', 'int'),
        'REDIRECTION_EXTERNAL' => Request::post('yrewrite_redirection_external', 'string'),
        default => '',
    };
    $upd = Sql::factory();
    $upd->setTable('rex_article');
    $upd->setWhere(['id' => $articleId]);
    $upd->setValue('yrewrite_redirection', $redirection);
    $upd->update();

    ArticleCache::delete($articleId);
    YRewrite::generatePathFile([
        'id' => $articleId,
        'extension_point' => 'ART_UPDATED',
    ]);
});

$field = $form->addSelectField('yrewrite_url_type');
$field->setLabel($this->i18n('url_type'));
$field->setAttribute('data-yrewrite-url-type-select', '1');
$select = $field->getSelect();
$select->addOption($this->i18n('url_type_auto'), 'AUTO');
$select->addOption($this->i18n('url_type_custom'), 'CUSTOM');
$select->addOption($this->i18n('url_type_redirection_internal'), 'REDIRECTION_INTERNAL');
$select->addOption($this->i18n('url_type_redirection_external'), 'REDIRECTION_EXTERNAL');

$form->addRawField('<div data-yrewrite-url-type="CUSTOM">');
$field = $form->addTextField('yrewrite_url');
$field->setLabel($this->i18n('url_type_custom'));
$field->getValidator()
    ->add('maxLength', $this->i18n('warning_nottolong'), 250)
    // allow empty (AUTO / redirection); validate charset only for custom urls
    ->add('match', $this->i18n('warning_chars'), '/^[%#_\.+\-\/a-zA-Z0-9]*$/');
$form->addRawField('</div>');

// yrewrite_redirection holds either an article id (internal) or a URL (external). It is no core form
// column field; both inputs are raw and persisted in the REX_FORM_SAVED hook above.
// The 4th argument is the category the linkmap opens in — an ?int, not an options array.
$internalWidget = LinkVar::getWidget(8421, 'yrewrite_redirection_internal', $internalValue, 0);
$form->addRawField(
    '<div data-yrewrite-url-type="REDIRECTION_INTERNAL"><div class="rex-form-group form-group">'
    . '<label class="control-label">' . escape($this->i18n('url_type_redirection_internal')) . '</label>'
    . $internalWidget
    . '</div></div>',
);

$form->addRawField(
    '<div data-yrewrite-url-type="REDIRECTION_EXTERNAL"><div class="rex-form-group form-group">'
    . '<label class="control-label">' . escape($this->i18n('url_type_redirection_external')) . '</label>'
    . '<input class="form-control" type="url" name="yrewrite_redirection_external" placeholder="https://example.com" value="' . escape($externalValue) . '">'
    . '</div></div>',
);

$content = $form->get();

$content .= '
    <script nonce="' . Response::getNonce() . '">
        (function () {
            var sel = document.querySelector(\'[data-yrewrite-url-type-select]\');
            if (!sel) { return; }
            function update() {
                var current = sel.value;
                document.querySelectorAll(\'[data-yrewrite-url-type]\').forEach(function (el) {
                    el.style.display = (el.getAttribute(\'data-yrewrite-url-type\') === current) ? \'\' : \'none\';
                });
            }
            sel.addEventListener("change", update);
            update();
        })();
    </script>';

return '<section id="rex-page-sidebar-yrewrite-url">' . $content . '</section>';
