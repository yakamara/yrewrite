<?php

/**
 * @var Yakamara\YRewrite\YRewriteAddon $this
 */

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Validator\ValidationRule;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Yakamara\YRewrite\YRewrite;

$showlist = true;
$dataId = Request::request('data_id', 'int', 0);
$func = Request::request('func', 'string');
$csrf = CsrfToken::factory('yrewrite_alias_domains');

// at least one real domain (besides "default") is required
if (count(YRewrite::getDomains()) <= 1) {
    echo Message::error($this->i18n('error_domain_missing'));
    $func = '';
    $showlist = false;
}

if ('delete' === $func) {
    if (!$csrf->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        Sql::factory()->setQuery('DELETE FROM ' . Core::getTable('yrewrite_alias') . ' WHERE id = ?', [$dataId]);
        echo Message::success($this->i18n('domain_deleted'));
        YRewrite::deleteCache();
    }
    $func = '';
}

if ('edit' === $func || 'add' === $func) {
    $showlist = false;

    Extension::register('REX_FORM_SAVED', static function (ExtensionPoint $ep): void {
        $form = $ep->getParam('form');
        if ($form instanceof Form && $form->getTableName() === Core::getTable('yrewrite_alias')) {
            YRewrite::deleteCache();
        }
    });

    $form = Form::factory(Core::getTable('yrewrite_alias'), '', 'id = ' . $dataId);
    $form->addParam('data_id', $dataId);
    $form->setApplyUrl(Url::currentBackendPage());
    $form->setEditMode('edit' === $func);

    $field = $form->addTextField('alias_domain');
    $field->setLabel($this->i18n('alias_domain_refersto'));
    $field->setNotice($this->i18n('alias_domain_refersto_notice'));
    $field->getValidator()
        ->add(ValidationRule::NOT_EMPTY, $this->i18n('no_domain_defined'))
        ->add(ValidationRule::MATCH, $this->i18n('domain_not_well_formed'), '/[a-zA-Z0-9][a-zA-Z0-9._-]*/');

    $field = $form->addSelectField('domain_id');
    $field->setLabel($this->i18n('domain_willbereferdto'));
    $field->getValidator()->add(ValidationRule::NOT_EMPTY, $this->i18n('no_domain_defined'));
    $select = $field->getSelect();
    $select->addSqlOptions('SELECT domain, id FROM ' . Core::getTable('yrewrite_domain') . ' ORDER BY domain');

    if (Language::count() > 1) {
        $field = $form->addSelectField('clang_start');
        $field->setLabel($this->i18n('clang_start'));
        $select = $field->getSelect();
        foreach (Language::getAll() as $clang) {
            $select->addOption($clang->name, $clang->id);
        }
    }

    $content = $form->get();

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', 'edit' === $func ? $this->i18n('edit_domain') : $this->i18n('add_domain'));
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}

if ($showlist) {
    $list = DataList::factory('SELECT * FROM ' . Core::getTable('yrewrite_alias') . ' ORDER BY alias_domain', 100);
    $list->addParam('page', 'yrewrite/alias_domains');

    $tdIcon = '<i class="rex-icon fa-sitemap"></i>';
    $thIcon = '<a href="' . $list->getUrl(['func' => 'add']) . '"' . Core::getAccesskey($this->i18n('add_domain'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'data_id' => '###id###']);

    $list->setColumnParams('id', ['data_id' => '###id###', 'func' => 'edit']);
    $list->setColumnSortable('id');
    $list->removeColumn('id');
    $list->removeColumn('clang_start');

    $list->setColumnLabel('alias_domain', $this->i18n('alias_domain'));

    $list->setColumnLabel('domain_id', $this->i18n('domain'));
    $list->setColumnFormat('domain_id', 'custom', static function ($params) {
        $domain = YRewrite::getDomainById((int) $params['subject']);
        return $domain ? $domain->getUrl() : '';
    });

    $list->addColumn(I18n::msg('edit'), '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit'));
    $list->setColumnLayout(I18n::msg('edit'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('edit'), ['data_id' => '###id###', 'func' => 'edit']);

    $list->addColumn(I18n::msg('delete'), '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'));
    $list->setColumnLayout(I18n::msg('delete'), ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('delete'), ['data_id' => '###id###', 'func' => 'delete'] + $csrf->getUrlParams());
    $list->addLinkAttribute(I18n::msg('delete'), 'data-confirm', I18n::msg('delete') . ' ?');

    $content = $list->get();

    $fragment = new Fragment();
    $fragment->setVar('title', $this->i18n('alias_domains'));
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
