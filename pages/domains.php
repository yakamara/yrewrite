<?php

/**
 * @var Yakamara\YRewrite\YRewriteAddon $this
 */

use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Validator\ValidationRule;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Yakamara\YRewrite\Seo;
use Yakamara\YRewrite\YRewrite;

$showlist = true;
$dataId = Request::request('data_id', 'int', 0);
$func = Request::request('func', 'string');
$csrf = CsrfToken::factory('yrewrite_domains');

if ('delete' === $func) {
    if (!$csrf->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        Sql::factory()->setQuery('DELETE FROM ' . Core::getTable('yrewrite_domain') . ' WHERE id = ?', [$dataId]);
        echo Message::success($this->i18n('domain_deleted'));
        YRewrite::deleteCache();
    }
    $func = '';
}

if ('edit' === $func || 'add' === $func) {
    $showlist = false;

    // clear the rewrite cache whenever the domain form was saved
    Extension::register('REX_FORM_SAVED', static function (ExtensionPoint $ep): void {
        $form = $ep->getParam('form');
        if ($form instanceof Form && $form->getTableName() === Core::getTable('yrewrite_domain')) {
            YRewrite::deleteCache();
        }
    });

    $form = Form::factory(Core::getTable('yrewrite_domain'), '', 'id = ' . $dataId, 'post', false);
    $form->addParam('data_id', $dataId);
    $form->setApplyUrl(Url::currentBackendPage());
    $form->setEditMode('edit' === $func);

    $field = $form->addTextField('domain');
    $field->setLabel($this->i18n('domain'));
    $field->setNotice('<small>' . $this->i18n('domain_info') . '</small>');
    $field->getValidator()
        ->add(ValidationRule::NOT_EMPTY, $this->i18n('no_domain_defined'))
        ->add(ValidationRule::MATCH, $this->i18n('domain_not_well_formed'), '/^(?:http[s]?:\/\/)?[a-zA-Z0-9][a-zA-Z0-9._-]*(?::\d+)?(?:\/[^\\/\:\*\?\"<>\|]*)*(?:\/[a-zA-Z0-9_%,\.\=\?\-#&]*)*$/');

    $field = $form->addArticleField('mount_id');
    $field->setLabel($this->i18n('mount_id'));
    $field->setNotice('<small>' . $this->i18n('mount_info') . '</small>');
    $field->setDefaultSaveValue(null); // empty -> NULL (int column)

    $field = $form->addArticleField('start_id');
    $field->setLabel($this->i18n('start_id'));
    $field->setNotice('<small>' . $this->i18n('start_info') . '</small>');
    $field->getValidator()->add(ValidationRule::NOT_EMPTY, $this->i18n('no_start_id_defined'));

    $field = $form->addArticleField('notfound_id');
    $field->setLabel($this->i18n('notfound_id'));
    $field->setNotice('<small>' . $this->i18n('notfound_info') . '</small>');
    $field->getValidator()->add(ValidationRule::NOT_EMPTY, $this->i18n('no_not_found_id_defined'));

    $field = $form->addSelectField('clangs');
    $field->setLabel($this->i18n('clangs'));
    $field->setNotice('<small>' . $this->i18n('clangs_info') . '</small>');
    $select = $field->getSelect();
    $select->setMultiple(true);
    $select->setSize(min(10, max(3, Language::count())));
    foreach (Language::getAll() as $clang) {
        $select->addOption($clang->name, $clang->id);
    }

    $field = $form->addSelectField('clang_start');
    $field->setLabel($this->i18n('clang_start'));
    $field->setNotice('<small>' . $this->i18n('clang_start_info') . '</small>');
    $select = $field->getSelect();
    foreach (Language::getAll() as $clang) {
        $select->addOption($clang->name, $clang->id);
    }

    $field = $form->addCheckboxField('clang_start_hidden');
    $field->setLabel($this->i18n('clang_start_hidden'));
    $field->addOption($this->i18n('clang_start_hidden'), 1);
    $field->setAttribute('data-yrewrite-clang-start-hidden', '1');

    $field = $form->addCheckboxField('clang_start_auto');
    $field->setLabel($this->i18n('clang_start_auto'));
    $field->addOption($this->i18n('clang_start_auto'), 1);
    $field->setAttribute('data-yrewrite-clang-start-auto', '1');

    $field = $form->addTextField('title_scheme');
    $field->setLabel($this->i18n('domain_title_scheme'));
    $field->setNotice('<small>' . $this->i18n('domain_title_scheme_info') . '</small>');
    if (!$form->isEditMode()) {
        $field->setValue(Seo::$titleSchemeDefault);
    }

    $field = $form->addCheckboxField('auto_redirect');
    $field->setLabel($this->i18n('auto_redirects'));
    $field->addOption($this->i18n('auto_redirect'), 1);

    $field = $form->addTextField('auto_redirect_days');
    $field->setLabel($this->i18n('auto_redirect_days'));
    $field->setNotice('<small>' . $this->i18n('auto_redirect_days_info') . '</small>');
    $field->setDefaultSaveValue(null); // empty -> NULL (int column)

    $field = $form->addTextAreaField('robots');
    $field->setLabel($this->i18n('domain_robots'));
    if (!$form->isEditMode()) {
        $field->setValue(Seo::$robotsDefault);
    }

    // $form->get() processes the POST and, on a successful save, redirects to the apply url
    $content = $form->get();

    // toggle: hidden start clang disables auto start clang
    $content .= '
        <script nonce="' . Response::getNonce() . '">
            (function () {
                var auto = document.querySelector(\'[data-yrewrite-clang-start-auto] input\') || document.querySelector(\'[data-yrewrite-clang-start-auto]\');
                var hidden = document.querySelector(\'[data-yrewrite-clang-start-hidden] input\') || document.querySelector(\'[data-yrewrite-clang-start-hidden]\');
                if (!auto || !hidden) { return; }
                hidden.addEventListener("change", function () {
                    if (hidden.checked) { auto.disabled = true; auto.checked = false; }
                    else { auto.disabled = false; }
                });
                hidden.dispatchEvent(new Event("change"));
            })();
        </script>
    ';

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', 'edit' === $func ? $this->i18n('edit_domain') : $this->i18n('add_domain'));
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}

if ($showlist) {
    $list = DataList::factory('SELECT * FROM ' . Core::getTable('yrewrite_domain') . ' ORDER BY domain', 100);
    $list->addParam('page', 'yrewrite/domains');

    $tdIcon = '<i class="rex-icon fa-sitemap"></i>';
    $thIcon = '<a href="' . $list->getUrl(['func' => 'add']) . '"' . Core::getAccesskey($this->i18n('add_domain'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'data_id' => '###id###']);

    $list->setColumnParams('id', ['data_id' => '###id###', 'func' => 'edit']);
    $list->setColumnSortable('id');
    $list->removeColumn('id');
    $list->removeColumn('auto_redirect');
    $list->removeColumn('auto_redirect_days');

    $list->setColumnLabel('domain', $this->i18n('domain'));
    $list->setColumnLabel('mount_id', $this->i18n('mount_id'));
    $list->setColumnLabel('start_id', $this->i18n('start_id'));
    $list->setColumnLabel('notfound_id', $this->i18n('notfound_id'));

    $list->setColumnLabel('clangs', $this->i18n('clangs'));
    $list->setColumnFormat('clangs', 'custom', function ($params) use ($list) {
        $clangs = trim((string) $params['subject'], '|, ');
        if ('' === $clangs) {
            return $this->i18n('alllangs');
        }
        $names = [];
        foreach (preg_split('/[|,]+/', $clangs) as $clang) {
            if ($lang = Language::get((int) $clang)) {
                $names[] = $lang->name;
            }
        }
        if (count($names) > 1) {
            $startClang = Language::get((int) $list->getValue('clang_start'));
            return implode(', ', $names) . '<br />' . $this->i18n('clang_start') . ': ' . ($startClang?->name ?? '');
        }
        return implode(', ', $names);
    });

    $list->removeColumn('clang_start');
    $list->removeColumn('clang_start_auto');
    $list->removeColumn('clang_start_hidden');
    $list->removeColumn('robots');
    $list->removeColumn('title_scheme');
    $list->removeColumn('description');

    $showArticle = function ($params) {
        $id = (int) $params['list']->getValue($params['field']);
        if (0 === $id) {
            return $this->i18n('root');
        }
        if ($article = Article::get($id)) {
            $link = $article->isStartArticle()
                ? 'index.php?page=structure&category_id=' . $id . '&clang=1'
                : 'index.php?page=content/edit&article_id=' . $id . '&mode=edit&clang=1';
            return $article->getValue('name') . ' [<a href="' . $link . '">' . $id . '</a>]';
        }
        return '[' . $id . ']';
    };

    $list->setColumnFormat('mount_id', 'custom', $showArticle, []);
    $list->setColumnFormat('start_id', 'custom', $showArticle, []);
    $list->setColumnFormat('notfound_id', 'custom', $showArticle, []);

    $list->addColumn(I18n::msg('edit'), '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit'));
    $list->setColumnLayout(I18n::msg('edit'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('edit'), ['data_id' => '###id###', 'func' => 'edit']);

    $list->addColumn(I18n::msg('delete'), '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'));
    $list->setColumnLayout(I18n::msg('delete'), ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('delete'), ['data_id' => '###id###', 'func' => 'delete'] + $csrf->getUrlParams());
    $list->addLinkAttribute(I18n::msg('delete'), 'data-confirm', I18n::msg('delete') . ' ?');

    $content = $list->get();

    $fragment = new Fragment();
    $fragment->setVar('title', $this->i18n('domains'));
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
