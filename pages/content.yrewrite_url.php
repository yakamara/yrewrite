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

ob_start();

$addon = rex_addon::get('yrewrite');

$article_id = $params['article_id'];
$clang = $params['clang'];
$ctype = $params['ctype'];

// $yrewrite_url = stripslashes(rex_request('yrewrite_url'));
$domain = rex_yrewrite::getDomainByArticleId($article_id, $clang);
$isStartarticle = rex_yrewrite::isDomainStartArticle($article_id, $clang);

if ($isStartarticle) {
    echo rex_view::warning($addon->i18n('startarticleisalways', $domain->getName()));
} else {
    $yform = new rex_yform();
    $yform->setObjectparams('form_action', rex_url::backendController(['page' => 'content/edit', 'article_id' => $article_id, 'clang' => $clang, 'ctype' => $ctype], false));
    $yform->setObjectparams('form_name', 'yrewrite-url');
    $yform->setHiddenField('yrewrite_func', 'url');

    $yform->setObjectparams('form_showformafterupdate', 1);

    $yform->setObjectparams('main_table', rex::getTable('article'));
    $yform->setObjectparams('main_id', $article_id);
    $yform->setObjectparams('main_where', 'id='.$article_id.' and clang_id='.$clang);
    $yform->setObjectparams('getdata', true);

    $sql = rex_sql::factory();
    $sql->setQuery('
        SELECT
           *,
           IF(yrewrite_url_type = "REDIRECTION_INTERNAL", yrewrite_redirection, "") AS yrewrite_redirection_internal,
           IF(yrewrite_url_type = "REDIRECTION_EXTERNAL", yrewrite_redirection, "") AS yrewrite_redirection_external
        FROM '.rex::getTable('article').'
        WHERE id='.$article_id.' and clang_id='.$clang,
    );
    $yform->setObjectparams('sql_object', $sql);

    $yform->setValueField('choice', [
        'yrewrite_url_type',
        $addon->i18n('url_type'),
        'choices' => [
            'AUTO' => $addon->i18n('url_type_auto'),
            'CUSTOM' => $addon->i18n('url_type_custom'),
            'REDIRECTION_INTERNAL' => $addon->i18n('url_type_redirection_internal'),
            'REDIRECTION_EXTERNAL' => $addon->i18n('url_type_redirection_external'),
        ],
        'notice' => '&nbsp;',
    ]);

    $yform->setValueField('text', ['yrewrite_url', $addon->i18n('url_type_custom'), 'notice' => '&nbsp;', 'required' => 'required']);

    $yform->setValueField('be_link', ['yrewrite_redirection_internal', $addon->i18n('url_type_redirection_internal')]);
    $yform->setValidateField('compare_value', ['yrewrite_redirection_internal', $article_id, '==', rex_i18n::msg('yrewrite_warning_redirect_to_self')]);

    $yform->setValueField('text', ['yrewrite_redirection_external', $addon->i18n('url_type_redirection_external'), 'attributes' => [
        'type' => 'url',
        'required' => 'required',
        'placeholder' => 'https://example.com',
    ]]);

    $yform->setActionField('callback', [static function () use ($yform) {
        switch ($yform->objparams['value_pool']['sql']['yrewrite_url_type']) {
            case 'REDIRECTION_INTERNAL':
                $yform->objparams['value_pool']['sql']['yrewrite_redirection'] = $yform->objparams['value_pool']['sql']['yrewrite_redirection_internal'];
                break;
            case 'REDIRECTION_EXTERNAL':
                $yform->objparams['value_pool']['sql']['yrewrite_redirection'] = $yform->objparams['value_pool']['sql']['yrewrite_redirection_external'];
                break;
        }
        unset($yform->objparams['value_pool']['sql']['yrewrite_redirection_internal']);
        unset($yform->objparams['value_pool']['sql']['yrewrite_redirection_external']);
    }]);

    $yform->setValidateField('customfunction', ['name' => 'yrewrite_url', 'function' => static function ($func, $yrewrite_url) {
        return $yrewrite_url && strlen($yrewrite_url) > 250;
    }, 'params' => [], 'message' => rex_i18n::msg('yrewrite_warning_nottolong')]);

    $yform->setValidateField('customfunction', ['name' => 'yrewrite_url', 'function' => static function ($func, $yrewrite_url) {
        if ('' == $yrewrite_url) {
            return false;
        }
        return !preg_match('/^[%#_\.+\-\/a-zA-Z0-9]+$/', $yrewrite_url);
    }, 'params' => [], 'message' => rex_i18n::msg('yrewrite_warning_chars')]);

    $yform->setValidateField('customfunction', ['name' => 'yrewrite_url', 'function' => static function ($func, $yrewrite_url, $params, $field) {
        if ('' == $yrewrite_url) {
            return false;
        }
        $return = (($a = rex_yrewrite::getArticleIdByUrl($params['domain'], $yrewrite_url)) && (key($a) != $params['article_id'] || current($a) != $params['clang']));
        if ($return && '' != $yrewrite_url) {
            $field->setElement('message', rex_i18n::msg('yrewrite_warning_urlexists', key($a)));
        } else {
            $return = false;
        }
        return $return;
    }, 'params' => ['article_id' => $article_id, 'domain' => $domain, 'clang' => $clang], 'message' => rex_i18n::msg('yrewrite_warning_urlexists')]);

    $yform->setActionField('db', [rex::getTable('article'), 'id=' . $article_id.' and clang_id='.$clang]);
    $yform->setObjectparams('submit_btn_label', $addon->i18n('update'));
    $form = $yform->getForm();

    if ($yform->objparams['actions_executed']) {
        $form = rex_view::success($addon->i18n('urlupdated')) . $form;
        rex_yrewrite::generatePathFile([
            'id' => $article_id,
            'clang' => $clang,
            'extension_point' => 'ART_UPDATED',
        ]);
        rex_article_cache::delete($article_id, $clang);
    }

    echo $form;

    if ('AUTO' === rex_article::get($article_id, $clang)->getValue('yrewrite_url_type')) {
        $autoUrl = rex_getUrl();
        if (str_starts_with($autoUrl, $domain->getUrl())) {
            $autoUrl = substr($autoUrl, strlen($domain->getUrl()));
        } else {
            $autoUrl = substr($autoUrl, strlen($domain->getPath()));
        }
    } else {
        $autoUrl = '...';
    }

    echo '

<script type="text/javascript" nonce="' . rex_response::getNonce() . '">

jQuery(document).ready(function() {
    var $type = $("#yform-yrewrite-url-yrewrite_url_type");
    var $typeSelect = $type.find("select");
    var $autoPreview = $type.find("p.help-block");

    var types = {
        custom: $("#yform-yrewrite-url-yrewrite_url"),
        redirection_internal: $("#yform-yrewrite-url-yrewrite_redirection_internal"),
        redirection_external: $("#yform-yrewrite-url-yrewrite_redirection_external")
    }

    $typeSelect.closest("form").attr("data-pjax-scroll-to", "false");

    $typeSelect.change(function () {
        var current = $typeSelect.val().toLowerCase();
        $.each(types, function (key, $element) {
            if (key === current) {
                $element.show().find("input, select").prop("disabled", false);
            } else {
                $element.hide().find("input, select").prop("disabled", true);
            }
        });
        $autoPreview.toggle("auto" === current);
    }).change();

    var base = "'.('default' == $domain->getName() ? '&lt;default&gt;/' : $domain->getUrl()).'";
    var autoUrl = "'.$autoUrl.'";

    var updateUrl = function ($element, url) {
        $element.html(base + url).addClass("dont-break-out");
    }

    if ("AUTO" === $typeSelect.val()) {
        updateUrl($autoPreview, autoUrl);
    } else {
        $autoPreview.remove();
    }

    var updateCustomUrl = function () {
        var customUrl = types.custom.find("input").val();
        updateUrl(types.custom.find("p.help-block"), "" === customUrl ? autoUrl : customUrl);
    }

    types.custom.find("input").keyup(function() {
        updateCustomUrl();
    });

    updateCustomUrl();

});

</script>';
}

$form = ob_get_contents();
$content = '<section id="rex-page-sidebar-yrewrite-url" data-pjax-container="#rex-page-sidebar-yrewrite-url" data-pjax-no-history="1">'.$form.'</section>';
ob_end_clean();

return $content;
