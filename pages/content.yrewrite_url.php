<?php

/**
 * Article content sidebar panel: per-article URL (yrewrite_url_type / yrewrite_url / yrewrite_redirection).
 *
 * @var YRewriteAddon $this
 * @var array{article_id: int, language: int, content_section_id: int, page: string} $params
 */

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Response;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;
use Yakamara\YRewrite\Backend\UrlSettings;
use Yakamara\YRewrite\YRewrite;
use Yakamara\YRewrite\YRewriteAddon;

$articleId = (int) $params['article_id'];
$clang = (int) $params['language'];
$contentSectionId = (int) $params['content_section_id'];

$domain = YRewrite::getDomainByArticleId($articleId, $clang);

// the domain start article always has the URL "/", it cannot be changed
if (YRewrite::isDomainStartArticle($articleId, $clang)) {
    return '<section id="rex-page-sidebar-yrewrite-url">'
        . Message::warning($this->i18n('startarticleisalways', $domain->getName()))
        . '</section>';
}

$article = Article::get($articleId, $clang);
if (null === $article) {
    return '';
}

$applyUrl = Url::backendPage($params['page'], ['article_id' => $articleId, 'language' => $clang, 'content_section_id' => $contentSectionId]);

// The URL settings live on rex_article and apply to all languages.
$data = UrlSettings::fromArticle($article);
$form = new Form($data, name: 'yrewrite_url', vertical: true);

if ($form->handleRequest()) {
    if ('CUSTOM' === $data->urlType && !preg_match('/^[%#_\.+\-\/a-zA-Z0-9]*$/', $data->url)) {
        $form->addError($this->i18n('warning_chars'), 'url');
    }
    if ('REDIRECTION_INTERNAL' === $data->urlType && $data->redirectionInternal?->id === $articleId) {
        $form->addError($this->i18n('warning_redirect_to_self'), 'redirectionInternal');
    }
    if ($form->isValid()) {
        $sql = Sql::factory();
        $sql->setTable('rex_article');
        $sql->setWhere(['id' => $articleId]);
        $sql->setValues($data->toRow());
        $sql->update();

        ArticleCache::delete($articleId);
        YRewrite::generatePathFile([
            'id' => $articleId,
            'extension_point' => 'ART_UPDATED',
        ]);

        Response::sendRedirect($applyUrl);
    }
}

$content = (string) $form->capture(function (Form $form): void {
    foreach ($form->getFormErrors() as $error) {
        echo Message::error((string) Html::from($error));
    }
    echo $form->row('urlType');
    echo '<div data-yrewrite-url-type="CUSTOM">', $form->row('url'), '</div>';
    echo '<div data-yrewrite-url-type="REDIRECTION_INTERNAL">', $form->row('redirectionInternal'), '</div>';
    echo '<div data-yrewrite-url-type="REDIRECTION_EXTERNAL">', $form->row('redirectionExternal'), '</div>';
    echo $form->buttons();
});

$content .= '
    <script nonce="' . Response::getNonce() . '">
        (function () {
            var sel = document.querySelector(\'[name="yrewrite_url[urlType]"]\');
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
