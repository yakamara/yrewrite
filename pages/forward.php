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

$showlist = true;
$data_id = rex_request('data_id', 'int', 0);
$func = rex_request('func', 'string');
$csrf = rex_csrf_token::factory('yrewrite_forward');

$domains = rex_yrewrite::getDomains();

rex_yrewrite_forward::init();

if (1 == count($domains)) {
    echo rex_view::error($this->i18n('error_domain_missing'));
    $func = '';
    $showlist = false;
}

if ('' != $func) {
    $yform = new rex_yform();
    // $yform->setDebug(TRUE);
    $yform->setHiddenField('page', 'yrewrite/forward');
    $yform->setHiddenField('func', $func);
    $yform->setHiddenField('save', '1');

    $yform->setObjectparams('main_table', rex::getTable('yrewrite_forward'));
    $yform->setObjectparams('form_name', 'yrewrite_forward_form');

    $yform->setValueField('choice', ['status', $this->i18n('forward_status'), $this->i18n('forward_active').'=1,'.$this->i18n('forward_inactive').'=0']);
    $yform->setValueField('choice', ['domain_id', $this->i18n('domain'), 'select id,domain as name from '.rex::getTable('yrewrite_domain') . ' ORDER BY name']);
    $yform->setValueField('text', ['url', $this->i18n('forward_url'), 'notice' => '<small>'.$this->i18n('forward_url_info').'</small>']);
    $yform->setValidateField('preg_match', ['url', '@^[%_\.+\-\w]+[/%_\.+\,\-\w]*(?<!\/)(?:\?.+)?$@u', $this->i18n('warning_chars')]);
    // $this->i18n('warning_noslash')
    $yform->setValidateField('size_range', ['url', 1, 255, $this->i18n('warning_nottolong')]);
    $yform->setValidateField('empty', ['url', $this->i18n('forward_enter_url')]);
    $yform->setValidateField('unique', ['domain_id,url', $this->i18n('forward_domainurl_already_defined')]);
    $yform->setValueField('choice', ['movetype', $this->i18n('forward_move_method'), $this->i18n('forward_301').'=301,'.$this->i18n('forward_302').'=302,'.$this->i18n('forward_303').'=303,'.$this->i18n('forward_307').'=307', '', '', '303']);
    $yform->setValueField('choice', ['type', $this->i18n('forward_type'), $this->i18n('forward_type_article').'=article,'.$this->i18n('forward_type_extern').'=extern,'.$this->i18n('forward_type_media').'=media']);

    $yform->setValueField('html', ['', '<div id="rex-yrewrite-forward-article">']);
    $yform->setValueField('be_link', ['article_id', $this->i18n('forward_article_id')]);
    $yform->setValueField('choice', ['clang', $this->i18n('forward_clang'), 'select id, name from '.rex::getTable('clang')]);
    $yform->setValueField('html', ['', '</div>']);

    $yform->setValueField('html', ['', '<div id="rex-yrewrite-forward-extern">']);
    $yform->setValueField('text', ['extern', $this->i18n('forward_extern')]);
    $yform->setValueField('html', ['', '</div>']);

    $yform->setValueField('html', ['', '<div id="rex-yrewrite-forward-media">']);
    $yform->setValueField('be_media', ['media', $this->i18n('forward_media')]);
    $yform->setValueField('html', ['', '</div>']);

    $yform->setValueField('date', ['expiry_date', $this->i18n('expiry_date'), idate('Y')]);

    echo '<script nonce="' . rex_response::getNonce() . '">

jQuery(document).ready(function() {
  function rex_yrewrite_update_form() {
    jQuery("#rex-yrewrite-forward-article").hide();
    jQuery("#rex-yrewrite-forward-extern").hide();
    jQuery("#rex-yrewrite-forward-media").hide();
    jQuery("#rex-yrewrite-forward-"+jQuery("#yform-yrewrite_forward_form-type select").val()).show();
  }

  jQuery("#yform-yrewrite_forward_form-type select").on("change loaded", function(){
    rex_yrewrite_update_form();
  })

  rex_yrewrite_update_form();

});

</script>';

    $yform->setActionField('callback', [static function () use ($yform) {
        if ('0000-00-00' === ($yform->objparams['value_pool']['sql']['expiry_date'] ?? null)) {
            $yform->objparams['value_pool']['sql']['expiry_date'] = null;
        }
    }]);

    if ('delete' == $func) {
        if (!$csrf->isValid()) {
            echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
        } else {
            $d = rex_sql::factory();
            $d->setQuery('delete from ' . rex::getTable('yrewrite_forward') . ' where id=' . $data_id);
            echo rex_view::success($this->i18n('forward_deleted'));
            rex_yrewrite_forward::generatePathFile();
        }
    } elseif ('edit' == $func) {
        $yform->setHiddenField('data_id', $data_id);
        $yform->setActionField('db', [rex::getTable('yrewrite_forward'), 'id=' . $data_id]);
        $yform->setObjectparams('main_id', $data_id);
        $yform->setObjectparams('main_where', "id=$data_id");
        $yform->setObjectparams('getdata', true);
        $yform->setObjectparams('submit_btn_label', $this->i18n('save'));
        $form = $yform->getForm();

        if ($yform->objparams['actions_executed']) {
            echo rex_view::success($this->i18n('forward_updated'));
            rex_yrewrite_forward::generatePathFile();
        } else {
            $showlist = false;
            $fragment = new rex_fragment();
            $fragment->setVar('class', 'edit', false);
            $fragment->setVar('title', $this->i18n('forward_edit'));
            $fragment->setVar('body', $form, false);
            echo $fragment->parse('core/page/section.php');
        }
    } elseif ('add' == $func) {
        $yform->setActionField('db', [rex::getTable('yrewrite_forward')]);
        $yform->setObjectparams('submit_btn_label', rex_i18n::msg('add'));
        $form = $yform->getForm();

        if ($yform->objparams['actions_executed']) {
            echo rex_view::success($this->i18n('forward_added'));
            rex_yrewrite_forward::generatePathFile();
        } else {
            $showlist = false;

            $fragment = new rex_fragment();
            $fragment->setVar('class', 'edit', false);
            $fragment->setVar('title', $this->i18n('forward_add'));
            $fragment->setVar('body', $form, false);
            echo $fragment->parse('core/page/section.php');
        }
    }
}

if ($showlist) {
    $sql = 'SELECT * FROM ' . rex::getTable('yrewrite_forward');
    if ('domain_id' == rex_get('sort', 'string')) {
        $sql .= ' ORDER BY url';
        if ('desc' == rex_get('sorttype', 'string')) {
            $sql .= ' DESC';
        }
    } elseif (!rex_get('sort')) {
        $sql .= ' ORDER BY id DESC';
    }

    $list = rex_list::factory($sql, 100);
    //    $list->setColumnFormat('id', 'Id');
    $list->addParam('page', 'yrewrite/forward');

    $tdIcon = '<i class="fa fa-sitemap"></i>';
    $thIcon = '<a href="' . $list->getUrl(['func' => 'add']) . '"' . rex::getAccesskey($this->i18n('forward_add'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'data_id' => '###id###']);

    $list->setColumnParams('id', ['data_id' => '###id###', 'func' => 'edit']);
    $list->setColumnSortable('id');
    $list->setColumnSortable('movetype');
    $list->setColumnSortable('domain_id');
    $list->setColumnSortable('status');

    $list->setColumnLabel('expiry_date', $this->i18n('expiry_date'));
    $list->setColumnFormat('expiry_date', 'intlDate');

    $list->setColumnLabel('domain_id', $this->i18n('forward_url'));
    $list->setColumnFormat('domain_id', 'custom', static function ($params) {
        $domain = rex_yrewrite::getDomainById($params['subject']);
        $url = $domain ? $domain->getUrl() : '';
        $url .= $params['list']->getValue('url');
        $url = '<a href="'.$url.'" onclick="window.open(this.href); return false;"><i class="rex-icon rex-icon-package-addon fa-external-link"></i> '.$url.'</a>';
        return $url;
    });

    $list->setColumnLabel('status', $this->i18n('forward_status'));
    // $list->setColumnLabel('url', $this->i18n('forward_url'));
    $list->removeColumn('url');
    $list->removeColumn('type');

    $list->setColumnLabel('movetype', $this->i18n('yrewrite_forward_movetype'));

    //    $list->removeColumn('id');
    $list->removeColumn('article_id');
    $list->removeColumn('clang');
    $list->removeColumn('extern');
    $list->removeColumn('media');
    //    $list->removeColumn('movetype');
    $list->removeColumn('domain');

    // $list->setColumnLabel('status', rex_i18n::msg('b_function'));
    $list->setColumnParams('status', ['func' => 'status', 'oid' => '###id###']);
    $list->setColumnLayout('status', ['<th>###VALUE###</th>', '<td>###VALUE###</td>']);
    $list->setColumnFormat('status', 'custom', static function ($params) {
        $list = $params['list'];
        if (1 == $list->getValue('status')) {
            $str = '<span class="rex-online">'.rex_i18n::msg('yrewrite_forward_active').'</span>';
        } else {
            $str = '<span class="rex-offline">'.rex_i18n::msg('yrewrite_forward_inactive').'</span>';
        }
        return $str;
    },
    );

    $list->addColumn('forward_target', '', 3);
    $list->setColumnLabel('forward_target', $this->i18n('forward_type'));
    $list->setColumnFormat('forward_target', 'custom', static function (array $params) {
        $list = $params['list'];

        switch ($list->getValue('type')) {
            case 'article':
                $article = rex_article::get($list->getValue('article_id'), $list->getValue('clang'));
                if (!$article) {
                    return rex_i18n::msg('yrewrite_forward_article_deleted');
                }
                if (!$article->isOnline()) {
                    return rex_i18n::msg('yrewrite_forward_article_offline');
                }

                return $article->getUrl();

            case 'extern':
                return $list->getValue('extern');

            case 'media':
                $media = rex_media::get($list->getValue('media'));
                if (!$media) {
                    return rex_i18n::msg('yrewrite_forward_media_deleted');
                }

                return $media->getUrl();

            default:
                return $params['value'];
        }
    });

    $list->addColumn(rex_i18n::msg('function'), '<i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('edit'));
    $list->setColumnLayout(rex_i18n::msg('function'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(rex_i18n::msg('function'), ['data_id' => '###id###', 'func' => 'edit', 'start' => rex_request('start', 'string')]);

    $list->addColumn(rex_i18n::msg('delete'), '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('delete'));
    $list->setColumnLayout(rex_i18n::msg('delete'), ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(rex_i18n::msg('delete'), ['data_id' => '###id###', 'func' => 'delete'] + $csrf->getUrlParams());
    $list->addLinkAttribute(rex_i18n::msg('delete'), 'onclick', 'return confirm(\' id=###id### ' . rex_i18n::msg('delete') . ' ?\')');

    $content = $list->get();

    $fragment = new rex_fragment();
    $fragment->setVar('title', $this->i18n('forward'));
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
