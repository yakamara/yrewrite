<?php

/**
 * Article content sidebar panel: SEO data (yrewrite_* columns).
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
use Yakamara\YRewrite\Backend\SeoSettings;
use Yakamara\YRewrite\Seo;
use Yakamara\YRewrite\YRewriteAddon;

$articleId = (int) $params['article_id'];
$clang = (int) $params['language'];
$contentSectionId = (int) $params['content_section_id'];

$article = Article::get($articleId, $clang);
if (null === $article) {
    return '';
}

$applyUrl = Url::backendPage($params['page'], ['article_id' => $articleId, 'language' => $clang, 'content_section_id' => $contentSectionId]);

// The SEO settings live on rex_article and apply to all languages.
$data = SeoSettings::fromArticle($article);
$form = new Form($data, name: 'yrewrite_seo', vertical: true);

if ($form->handleRequest()) {
    $sql = Sql::factory();
    $sql->setTable('rex_article');
    $sql->setWhere(['id' => $articleId]);
    $sql->setValues($data->toRow());
    $sql->update();

    ArticleCache::delete($articleId);

    Response::sendRedirect($applyUrl);
}

$seo = new Seo($articleId, $clang);

$content = (string) $form->capture(function (Form $form): void {
    foreach ($form->getFormErrors() as $error) {
        echo Message::error((string) Html::from($error));
    }
    echo $form->rest();
    echo $form->buttons();
});

$descInfo = $this->i18n('domain_description_info');
$content .= '
    <script nonce="' . Response::getNonce() . '">
        (function () {
            var ta = document.querySelector(\'[name="yrewrite_seo[description]"]\');
            if (!ta) { return; }
            var hint = document.createElement("p");
            hint.className = "help-block";
            ta.parentNode.appendChild(hint);
            function upd() { hint.innerHTML = ta.value.replace(/(\r\n|\n|\r)/gm, "").length + " ' . $descInfo . '"; }
            ta.addEventListener("input", upd);
            upd();
            var title = document.querySelector(\'[name="yrewrite_seo[title]"]\');
            if (title && !title.getAttribute("placeholder")) { title.setAttribute("placeholder", ' . json_encode($seo->getTitle()) . '); }
        })();
    </script>';

return '<section id="rex-page-sidebar-yrewrite-seo">' . $content . '</section>';
