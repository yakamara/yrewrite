<?php

/**
 * @var Yakamara\YRewrite\YRewriteAddon $this
 */

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;
use Yakamara\YRewrite\Backend\AliasData;
use Yakamara\YRewrite\YRewrite;

use function Redaxo\Core\View\escape;

$showlist = true;
$dataId = Request::request('data_id', 'int', 0);
$func = Request::request('func', 'string');
$csrf = CsrfToken::factory('yrewrite_alias_domains');
$table = Core::TABLE_PREFIX . 'yrewrite_alias';

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
        Sql::factory()->setQuery('DELETE FROM ' . $table . ' WHERE id = ?', [$dataId]);
        echo Message::success($this->i18n('domain_deleted'));
        YRewrite::deleteCache();
    }
    $func = '';
}

if ('edit' === $func || 'add' === $func) {
    $showlist = false;

    $row = 'edit' === $func ? (Sql::factory()->getArray('SELECT * FROM ' . $table . ' WHERE id = ?', [$dataId])[0] ?? null) : null;
    if ('edit' === $func && null === $row) {
        echo Message::error($this->i18n('no_domain_defined'));
        $showlist = true;
    } else {
        $data = null !== $row ? AliasData::fromRow($row) : new AliasData();
        $form = new Form($data, name: 'yrewrite_alias', title: 'edit' === $func ? $this->i18n('edit_domain') : $this->i18n('add_domain'), cancelUrl: Url::currentBackendPage());

        if ($form->handleRequest()) {
            if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', $data->aliasDomain)) {
                $form->addError($this->i18n('domain_not_well_formed'), 'aliasDomain');
            }
            if ($form->isValid()) {
                $sql = Sql::factory();
                $sql->setTable($table);
                $sql->setValues($data->toRow());
                if (null !== $row) {
                    $sql->setWhere(['id' => $dataId]);
                    $sql->update();
                } else {
                    $sql->insert();
                }
                YRewrite::deleteCache();
                Response::sendRedirect(Url::currentBackendPage(['info' => 'edit' === $func ? 'updated' : 'added']));
            }
        }

        echo $form->render();
    }
}

if ($showlist) {
    $info = Request::get('info', 'string', '');
    if ('added' === $info) {
        echo Message::success($this->i18n('domain_added'));
    } elseif ('updated' === $info) {
        echo Message::success($this->i18n('domain_updated'));
    }

    $list = new DataList('SELECT * FROM ' . $table . ' ORDER BY alias_domain', rowsPerPage: 100);
    $list->addParam('page', 'yrewrite/alias_domains');
    $editUrl = static fn (array $row): string => $list->getUrl(['func' => 'edit', 'data_id' => $row['id']]);

    $list->column(
        'icon',
        Html::raw('<a href="' . escape($list->getUrl(['func' => 'add'])) . '"' . \Redaxo\Core\Backend\Accesskey::attributes($this->i18n('add_domain'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>'),
        static fn (): Html => Html::raw('<i class="rex-icon fa-sitemap"></i>'),
        type: ColumnType::Icon,
        link: $editUrl,
    );
    $list->column('alias_domain', $this->i18n('alias_domain'));
    $list->column('domain_id', $this->i18n('domain'), static fn (array $row): string => YRewrite::getDomainById((int) $row['domain_id'])?->getUrl() ?? '');
    $list->column('edit', I18n::msg('edit'), static fn (): Html => Html::raw('<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit')), type: ColumnType::Action, link: $editUrl);
    $list->column(
        'delete',
        null,
        static fn (): Html => Html::raw('<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete')),
        type: ColumnType::Action,
        link: static fn (array $row): string => $list->getUrl(['data_id' => $row['id'], 'func' => 'delete'] + $csrf->getUrlParams()),
        linkAttributes: ['data-confirm' => I18n::msg('delete') . ' ?'],
    );

    echo new Section($this->i18n('alias_domains'), content: $list)->render();
}
