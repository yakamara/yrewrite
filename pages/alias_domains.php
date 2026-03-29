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
$csrf = rex_csrf_token::factory('yrewrite_alias_domains');

$domains = rex_yrewrite::getDomains();

if (1 == count($domains)) {
    echo rex_view::error($this->i18n('error_domain_missing'));
    $func = '';
    $showlist = false;
}

if ('' != $func) {
    $yform = new rex_yform();
    // $yform->setDebug(TRUE);
    $yform->setHiddenField('page', 'yrewrite/alias_domains');
    $yform->setHiddenField('func', $func);
    $yform->setHiddenField('save', '1');

    $yform->setObjectparams('main_table', rex::getTable('yrewrite_alias'));
    $yform->setObjectparams('form_name', 'yrewrite_alias_domains_form');

    $yform->setValueField('text', [
        'name' => 'alias_domain',
        'label' => $this->i18n('alias_domain_refersto'),
        'notice' => $this->i18n('alias_domain_refersto_notice'),
    ]);
    $yform->setValueField('choice', ['domain_id', $this->i18n('domain_willbereferdto'), 'SELECT id, domain AS label FROM ' . rex::getTable('yrewrite_domain')]);
    if (rex_clang::count() > 1) {
        $yform->setValueField('choice', ['clang_start', $this->i18n('clang_start'), 'SELECT id, name AS label FROM ' . rex::getTable('clang') . ' ORDER BY id']);
    }

    $yform->setValidateField('empty', ['alias_domain', $this->i18n('no_domain_defined')]);
    $yform->setValidateField('empty', ['domain_id', $this->i18n('no_domain_defined')]);
    $yform->setValidateField('unique', ['alias_domain', $this->i18n('domain_already_defined')]);
    $yform->setValidateField('preg_match', ['alias_domain', '/[a-zA-Z0-9][a-zA-Z0-9._-]*/', $this->i18n('domain_not_well_formed')]);

    if ('delete' == $func) {
        if (!$csrf->isValid()) {
            echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
        } else {
            $d = rex_sql::factory();
            $d->setQuery('delete from ' . rex::getTable('yrewrite_alias') . ' where id=' . $data_id);
            echo rex_view::success($this->i18n('domain_deleted'));
            rex_yrewrite::deleteCache();
        }
    } elseif ('edit' == $func) {
        $yform->setHiddenField('data_id', $data_id);
        $yform->setActionField('db', [rex::getTable('yrewrite_alias'), 'id=' . $data_id]);
        $yform->setObjectparams('main_id', $data_id);
        $yform->setObjectparams('main_where', "id=$data_id");
        $yform->setObjectparams('getdata', true);
        $yform->setObjectparams('submit_btn_label', $this->i18n('save'));
        $form = $yform->getForm();

        if ($yform->objparams['actions_executed']) {
            echo rex_view::success($this->i18n('domain_updated'));
            rex_yrewrite::deleteCache();
        } else {
            $showlist = false;
            $fragment = new rex_fragment();
            $fragment->setVar('class', 'edit', false);
            $fragment->setVar('title', $this->i18n('edit_domain'));
            $fragment->setVar('body', $form, false);
            echo $fragment->parse('core/page/section.php');
        }
    } elseif ('add' == $func) {
        $yform->setActionField('db', [rex::getTable('yrewrite_alias')]);
        $yform->setObjectparams('submit_btn_label', rex_i18n::msg('add'));
        $form = $yform->getForm();

        if ($yform->objparams['actions_executed']) {
            echo rex_view::success($this->i18n('domain_added'));
            rex_yrewrite::deleteCache();
        } else {
            $showlist = false;
            $fragment = new rex_fragment();
            $fragment->setVar('class', 'edit', false);
            $fragment->setVar('title', $this->i18n('add_domain'));
            $fragment->setVar('body', $form, false);
            echo $fragment->parse('core/page/section.php');
        }
    }
}

if ($showlist) {
    $sql = 'SELECT * FROM ' . rex::getTable('yrewrite_alias') . ' ORDER BY alias_domain';

    $list = rex_list::factory($sql, 100);
    $list->setColumnFormat('id', 'Id');
    $list->addParam('page', 'yrewrite/alias_domains');

    $tdIcon = '<i class="fa fa-sitemap"></i>';
    $thIcon = '<a href="' . $list->getUrl(['func' => 'add']) . '"' . rex::getAccesskey($this->i18n('add_domain'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'data_id' => '###id###']);

    $list->setColumnParams('id', ['data_id' => '###id###', 'func' => 'edit']);
    $list->setColumnSortable('id');

    $list->removeColumn('id');
    $list->removeColumn('clang_start');

    $list->setColumnLabel('alias_domain', $this->i18n('alias_domain'));

    $list->setColumnLabel('domain_id', $this->i18n('domain'));
    $list->setColumnFormat('domain_id', 'custom', static function ($params) {
        $domain = rex_yrewrite::getDomainById($params['subject']);

        return $domain ? $domain->getUrl() : '';
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
    $fragment->setVar('title', $this->i18n('domains'));
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
