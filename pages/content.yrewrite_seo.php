<?php

/**
 * Article content sidebar panel: SEO data (yrewrite_* columns).
 *
 * @var YRewriteAddon $this
 * @var array{article_id: int, language: int, ctype: int} $params
 */

use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Translation\I18n;
use Yakamara\YRewrite\Seo;
use Yakamara\YRewrite\YRewriteAddon;

$articleId = (int) $params['article_id'];
$clang = (int) $params['language'];
$ctype = (int) $params['ctype'];

$applyUrl = Url::backendPage('content/edit', ['article_id' => $articleId, 'language' => $clang, 'ctype' => $ctype]);

// The SEO settings live on rex_article and apply to all languages. The where clause is written differently than in
// the URL panel ("id=…" vs "id = …") because the form name is the md5 of table and where.
$form = Form::factory('rex_article', 'yrewrite_seo', 'id=' . $articleId);
$form->addParam('page', 'content/edit');
$form->addParam('article_id', $articleId);
$form->addParam('language', $clang);
$form->addParam('ctype', $ctype);
$form->setApplyUrl($applyUrl);
$form->setEditMode(true);

Extension::register('REX_FORM_SAVED', static function (ExtensionPoint $ep) use ($form, $articleId): void {
    if ($ep->getParam('form') === $form) {
        ArticleCache::delete($articleId);
    }
});

$seo = new Seo($articleId, $clang);

$field = $form->addTextField('yrewrite_title');
$field->setLabel(I18n::msg('yrewrite_seotitle'));
$field->setAttribute('placeholder', $seo->getTitle());

$field = $form->addTextAreaField('yrewrite_description');
$field->setLabel(I18n::msg('yrewrite_seodescription'));
$field->setAttribute('rows', 3);

$field = $form->addMediaField('yrewrite_image');
$field->setLabel(I18n::msg('yrewrite_seoimage'));

$field = $form->addSelectField('yrewrite_changefreq');
$field->setLabel(I18n::msg('yrewrite_changefreq'));
$select = $field->getSelect();
foreach (Seo::$changefreq as $changefreq) {
    $select->addOption(I18n::msg('yrewrite_changefreq_' . $changefreq), $changefreq);
}
if (!$form->isEditMode()) {
    $field->setValue(Seo::$changefreqDefault);
}

$field = $form->addSelectField('yrewrite_priority');
$field->setLabel(I18n::msg('yrewrite_priority'));
$select = $field->getSelect();
$select->addOption(I18n::msg('yrewrite_priority_auto'), '');
foreach (Seo::$priority as $priority) {
    $select->addOption(I18n::msg('yrewrite_priority_' . str_replace('.', '_', $priority)), $priority);
}

$field = $form->addSelectField('yrewrite_index');
$field->setLabel(I18n::msg('yrewrite_index'));
$select = $field->getSelect();
$select->addOption(I18n::msg('yrewrite_index_status'), 0);
$select->addOption(I18n::msg('yrewrite_index_index'), 1);
$select->addOption(I18n::msg('yrewrite_index_noindex'), -1);
$select->addOption(I18n::msg('yrewrite_index_noindex_follow'), 2);

$field = $form->addTextField('yrewrite_canonical_url');
$field->setLabel(I18n::msg('yrewrite_canonical_url'));

$content = $form->get();

$descInfo = $this->i18n('domain_description_info');
$content .= '
    <script nonce="' . Response::getNonce() . '">
        (function () {
            var ta = document.querySelector(\'[name$="[yrewrite_description]"]\');
            if (!ta) { return; }
            var hint = document.createElement("p");
            hint.className = "help-block";
            ta.parentNode.appendChild(hint);
            function upd() { hint.innerHTML = ta.value.replace(/(\r\n|\n|\r)/gm, "").length + " ' . $descInfo . '"; }
            ta.addEventListener("input", upd);
            upd();
        })();
    </script>';

return '<section id="rex-page-sidebar-yrewrite-seo">' . $content . '</section>';
