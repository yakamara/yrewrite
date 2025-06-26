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
 * @var array{article_id: int, clang: int, ctype: int} $params
 */

$content = '';
$addon = rex_addon::get('yrewrite');

$article_id = $params['article_id'];
$clang = $params['clang'];
$ctype = $params['ctype'];

$select_priority = [];
$select_priority[] = rex_i18n::msg('yrewrite_priority_auto').'=';
foreach (rex_yrewrite_seo::$priority as $priority) {
    $select_priority[] = rex_i18n::msg('yrewrite_priority_'.str_replace('.', '_', $priority)).'='.$priority;
}

$select_changefreq = [];
foreach (rex_yrewrite_seo::$changefreq as $changefreq) {
    $select_changefreq[] = rex_i18n::msg('yrewrite_changefreq_'.$changefreq).'='.$changefreq;
}

$index_setting = [];
$index_setting[] = rex_i18n::msg('yrewrite_index_status').'=0';
$index_setting[] = rex_i18n::msg('yrewrite_index_index').'=1';
$index_setting[] = rex_i18n::msg('yrewrite_index_noindex').'=-1';
$index_setting[] = rex_i18n::msg('yrewrite_index_noindex_follow').'=2';

$yform = new rex_yform();
$yform->setObjectparams('form_action', rex_url::backendController(['page' => 'content/edit', 'article_id' => $article_id, 'clang' => $clang, 'ctype' => $ctype], false));
$yform->setObjectparams('form_name', 'yrewrite-seo');
$yform->setHiddenField('yrewrite_func', 'seo');

$yform->setObjectparams('form_showformafterupdate', 1);

$yform->setObjectparams('main_table', rex::getTable('article'));
$yform->setObjectparams('main_id', $article_id);
$yform->setObjectparams('main_where', 'id='.$article_id.' and clang_id='.$clang);
$yform->setObjectparams('getdata', true);

$seo = new rex_yrewrite_seo($article_id, $clang);
$seo->article->yrewrite_title = '';

$yform->setValueField('text', ['name' => 'yrewrite_title', 'label' => rex_i18n::msg('yrewrite_seotitle'), 'placeholder' => $seo->getTitle()]);
$yform->setValueField('textarea', ['yrewrite_description', rex_i18n::msg('yrewrite_seodescription'), 'rows' => 3]);
$yform->setValueField('be_media', ['yrewrite_image', rex_i18n::msg('yrewrite_seoimage'), '1', '0', 'general', 'jpg,gif,png,jpeg,webp']);

$yform->setValueField('choice', ['yrewrite_changefreq', rex_i18n::msg('yrewrite_changefreq'), implode(',', $select_changefreq), '', '', rex_yrewrite_seo::$changefreq_default]);
$yform->setValueField('choice', ['yrewrite_priority', rex_i18n::msg('yrewrite_priority'), implode(',', $select_priority), '', '', rex_yrewrite_seo::$priority_default]);

$yform->setValueField('choice', ['yrewrite_index', rex_i18n::msg('yrewrite_index'), implode(',', $index_setting), '', '', rex_yrewrite_seo::$index_setting_default]);

$yform->setValueField('text', ['yrewrite_canonical_url', rex_i18n::msg('yrewrite_canonical_url')]);

$yform->setActionField('db', [rex::getTable('article'), 'id=' . $article_id.' and clang_id='.$clang]);
$yform->setObjectparams('submit_btn_label', $addon->i18n('update'));
$form = $yform->getForm();

if ($yform->objparams['actions_executed']) {
    $form = rex_view::success(rex_i18n::msg('yrewrite_seoupdated')) . $form;
    rex_article_cache::delete($article_id, $clang);
}

$form .= '
    <script nonce="' . rex_response::getNonce() . '">
        jQuery(document).ready(function () {
            jQuery("#yrewrite-seo #yform-yrewrite-seo-yrewrite_description").append(\'<p class="help-block"><small></small></p>\');
            jQuery("#yrewrite-seo #yform-yrewrite-seo-yrewrite_description textarea").bind ("change input keyup keydown keypress mouseup mousedown cut copy paste", function (e) {
                var v = jQuery(this).val().replace(/(\r\n|\n|\r)/gm, "").length;
                jQuery("#yrewrite-seo #yform-yrewrite-seo-yrewrite_description").find("p small").html( v + \' '.$this->i18n('domain_description_info').' \');
                return true;
            }).trigger("keydown");
        });
    </script>';

$form = '<section id="rex-page-sidebar-yrewrite-seo" data-pjax-container="#rex-page-sidebar-yrewrite-seo" data-pjax-no-history="1">'.$form.'</section>';

return $form;
