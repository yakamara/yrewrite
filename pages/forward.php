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
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Validator\ValidationRule;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Yakamara\YRewrite\Forward;
use Yakamara\YRewrite\YRewrite;

$showlist = true;
$dataId = Request::request('data_id', 'int', 0);
$func = Request::request('func', 'string');
$csrf = CsrfToken::factory('yrewrite_forward');

Forward::init();

if (count(YRewrite::getDomains()) <= 1) {
    echo Message::error($this->i18n('error_domain_missing'));
    $func = '';
    $showlist = false;
}

if ('delete' === $func) {
    if (!$csrf->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        Sql::factory()->setQuery('DELETE FROM ' . Core::getTable('yrewrite_forward') . ' WHERE id = ?', [$dataId]);
        echo Message::success($this->i18n('forward_deleted'));
        Forward::init();
        Forward::generatePathFile();
    }
    $func = '';
}

if ('status' === $func) {
    $sql = Sql::factory();
    $sql->setQuery('UPDATE ' . Core::getTable('yrewrite_forward') . ' SET status = 1 - status WHERE id = ?', [Request::request('oid', 'int')]);
    Forward::init();
    Forward::generatePathFile();
    $func = '';
}

if ('edit' === $func || 'add' === $func) {
    $showlist = false;

    Extension::register('REX_FORM_SAVED', static function (ExtensionPoint $ep): void {
        $form = $ep->getParam('form');
        if ($form instanceof Form && $form->getTableName() === Core::getTable('yrewrite_forward')) {
            Forward::init();
            Forward::generatePathFile();
        }
    });

    $form = Form::factory(Core::getTable('yrewrite_forward'), '', 'id = ' . $dataId);
    $form->addParam('data_id', $dataId);
    $form->setApplyUrl(Url::currentBackendPage());
    $form->setEditMode('edit' === $func);

    $field = $form->addSelectField('status');
    $field->setLabel($this->i18n('forward_status'));
    $select = $field->getSelect();
    $select->addOption($this->i18n('forward_active'), 1);
    $select->addOption($this->i18n('forward_inactive'), 0);

    $field = $form->addSelectField('domain_id');
    $field->setLabel($this->i18n('domain'));
    $select = $field->getSelect();
    $select->addSqlOptions('SELECT domain, id FROM ' . Core::getTable('yrewrite_domain') . ' ORDER BY domain');

    $field = $form->addTextField('url');
    $field->setLabel($this->i18n('forward_url'));
    $field->setNotice('<small>' . $this->i18n('forward_url_info') . '</small>');
    $field->getValidator()
        ->add(ValidationRule::NOT_EMPTY, $this->i18n('forward_enter_url'))
        ->add(ValidationRule::MAX_LENGTH, $this->i18n('warning_nottolong'), 255)
        ->add(ValidationRule::MATCH, $this->i18n('warning_chars'), '@^[%_\.+\-\w]+[/%_\.+\,\-\w]*(?<!\/)(?:\?.+)?$@u');

    $field = $form->addSelectField('movetype');
    $field->setLabel($this->i18n('forward_move_method'));
    $select = $field->getSelect();
    $select->addOption($this->i18n('forward_301'), 301);
    $select->addOption($this->i18n('forward_302'), 302);
    $select->addOption($this->i18n('forward_303'), 303);
    $select->addOption($this->i18n('forward_307'), 307);
    if (!$form->isEditMode()) {
        $field->setValue('303');
    }

    $field = $form->addSelectField('type');
    $field->setLabel($this->i18n('forward_type'));
    $field->setAttribute('data-yrewrite-forward-type-select', '1');
    $select = $field->getSelect();
    $select->addOption($this->i18n('forward_type_article'), 'article');
    $select->addOption($this->i18n('forward_type_extern'), 'extern');
    $select->addOption($this->i18n('forward_type_media'), 'media');

    // widget fields (article/media) ignore custom input attributes, so wrap each type group
    // in a div with the data attribute and toggle the wrappers via JS.
    $form->addRawField('<div data-yrewrite-forward-type="article">');
    $field = $form->addArticleField('article_id');
    $field->setLabel($this->i18n('forward_article_id'));
    $field->setDefaultSaveValue(null);

    $field = $form->addSelectField('clang');
    $field->setLabel($this->i18n('forward_clang'));
    $select = $field->getSelect();
    foreach (Language::getAll() as $clang) {
        $select->addOption($clang->name, $clang->id);
    }
    $form->addRawField('</div>');

    $form->addRawField('<div data-yrewrite-forward-type="extern">');
    $field = $form->addTextField('extern');
    $field->setLabel($this->i18n('forward_extern'));
    $form->addRawField('</div>');

    $form->addRawField('<div data-yrewrite-forward-type="media">');
    $field = $form->addMediaField('media');
    $field->setLabel($this->i18n('forward_media'));
    $form->addRawField('</div>');

    $field = $form->addTextField('expiry_date');
    $field->setLabel($this->i18n('expiry_date'));
    $field->setAttribute('type', 'date');
    $field->setDefaultSaveValue(null);

    $content = $form->get();

    $content .= '
        <script nonce="' . Response::getNonce() . '">
            (function () {
                var typeSelect = document.querySelector(\'[data-yrewrite-forward-type-select]\');
                if (!typeSelect) { return; }
                function update() {
                    var current = typeSelect.value;
                    document.querySelectorAll(\'[data-yrewrite-forward-type]\').forEach(function (el) {
                        el.style.display = (el.getAttribute(\'data-yrewrite-forward-type\') === current) ? \'\' : \'none\';
                    });
                }
                typeSelect.addEventListener("change", update);
                update();
            })();
        </script>
    ';

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', 'edit' === $func ? $this->i18n('forward_edit') : $this->i18n('forward_add'));
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}

if ($showlist) {
    $list = DataList::factory('SELECT * FROM ' . Core::getTable('yrewrite_forward') . ' ORDER BY id DESC', 100);
    $list->addParam('page', 'yrewrite/forward');

    $tdIcon = '<i class="rex-icon fa-sitemap"></i>';
    $thIcon = '<a href="' . $list->getUrl(['func' => 'add']) . '"' . Core::getAccesskey($this->i18n('forward_add'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'data_id' => '###id###']);

    $list->setColumnParams('id', ['data_id' => '###id###', 'func' => 'edit']);
    $list->setColumnSortable('id');

    $list->setColumnLabel('domain_id', $this->i18n('forward_url'));
    $list->setColumnFormat('domain_id', 'custom', static function ($params) {
        $domain = YRewrite::getDomainById((int) $params['subject']);
        $url = ($domain ? $domain->getUrl() : '') . $params['list']->getValue('url');
        return '<a href="' . $url . '" onclick="window.open(this.href); return false;"><i class="rex-icon rex-icon-package-addon fa-external-link"></i> ' . $url . '</a>';
    });

    $list->setColumnLabel('status', $this->i18n('forward_status'));
    $list->setColumnParams('status', ['func' => 'status', 'oid' => '###id###'] + $csrf->getUrlParams());
    $list->setColumnLayout('status', ['<th>###VALUE###</th>', '<td>###VALUE###</td>']);
    $list->setColumnFormat('status', 'custom', static function ($params) {
        if (1 == $params['list']->getValue('status')) {
            return '<span class="rex-online">' . I18n::msg('yrewrite_forward_active') . '</span>';
        }
        return '<span class="rex-offline">' . I18n::msg('yrewrite_forward_inactive') . '</span>';
    });

    $list->setColumnLabel('movetype', $this->i18n('forward_movetype'));
    $list->setColumnSortable('movetype');

    $list->addColumn('forward_target', '', 3);
    $list->setColumnLabel('forward_target', $this->i18n('forward_type'));
    $list->setColumnFormat('forward_target', 'custom', static function (array $params) {
        $list = $params['list'];
        switch ($list->getValue('type')) {
            case 'article':
                $article = Article::get((int) $list->getValue('article_id'), (int) $list->getValue('clang'));
                if (!$article) {
                    return I18n::msg('yrewrite_forward_article_deleted');
                }
                if (!$article->isOnline()) {
                    return I18n::msg('yrewrite_forward_article_offline');
                }
                return $article->getUrl();
            case 'extern':
                return (string) $list->getValue('extern');
            case 'media':
                $media = Media::get((string) $list->getValue('media'));
                if (!$media) {
                    return I18n::msg('yrewrite_forward_media_deleted');
                }
                return $media->getUrl();
            default:
                return (string) $params['value'];
        }
    });

    $list->removeColumn('url');
    $list->removeColumn('type');
    $list->removeColumn('article_id');
    $list->removeColumn('clang');
    $list->removeColumn('extern');
    $list->removeColumn('media');

    $list->addColumn(I18n::msg('edit'), '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit'));
    $list->setColumnLayout(I18n::msg('edit'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('edit'), ['data_id' => '###id###', 'func' => 'edit']);

    $list->addColumn(I18n::msg('delete'), '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'));
    $list->setColumnLayout(I18n::msg('delete'), ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('delete'), ['data_id' => '###id###', 'func' => 'delete'] + $csrf->getUrlParams());
    $list->addLinkAttribute(I18n::msg('delete'), 'data-confirm', I18n::msg('delete') . ' ?');

    $content = $list->get();

    $fragment = new Fragment();
    $fragment->setVar('title', $this->i18n('forward'));
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
