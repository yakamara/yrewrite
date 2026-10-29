<?php

/**
 * @var Yakamara\YRewrite\YRewriteAddon $this
 */

use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\TableColumnType;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;
use Yakamara\YRewrite\Backend\DomainData;
use Yakamara\YRewrite\YRewrite;

use function Redaxo\Core\View\escape;

$showlist = true;
$dataId = Request::request('data_id', 'int', 0);
$func = Request::request('func', 'string');
$csrf = CsrfToken::factory('yrewrite_domains');
$table = Core::TABLE_PREFIX . 'yrewrite_domain';

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
        $data = null !== $row ? DomainData::fromRow($row) : DomainData::defaults();
        $form = new Form($data, name: 'yrewrite_domain', title: 'edit' === $func ? $this->i18n('edit_domain') : $this->i18n('add_domain'), cancelUrl: Url::currentBackendPage());

        if ($form->handleRequest()) {
            if (!preg_match('/^(?:http[s]?:\/\/)?[a-zA-Z0-9][a-zA-Z0-9._-]*(?::\d+)?(?:\/[^\\\\\/\:\*\?\"<>\|]*)*(?:\/[a-zA-Z0-9_%,\.\=\?\-#&]*)*$/', $data->domain)) {
                $form->addError($this->i18n('domain_not_well_formed'), 'domain');
            }
            $exists = Sql::factory()->getArray('SELECT id FROM ' . $table . ' WHERE domain = ? AND id <> ?', [$data->domain, $dataId]);
            if ([] !== $exists) {
                $form->addError($this->i18n('domain_already_defined'), 'domain');
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

        $content = (string) $form->render();

        // toggle: hidden start clang disables auto start clang
        $content .= '
            <script nonce="' . Response::getNonce() . '">
                (function () {
                    var auto = document.querySelector(\'[name="yrewrite_domain[clangStartAuto]"]\');
                    var hidden = document.querySelector(\'[name="yrewrite_domain[clangStartHidden]"]\');
                    if (!auto || !hidden) { return; }
                    hidden.addEventListener("change", function () {
                        if (hidden.checked) { auto.disabled = true; auto.checked = false; }
                        else { auto.disabled = false; }
                    });
                    hidden.dispatchEvent(new Event("change"));
                })();
            </script>
        ';

        echo $content;
    }
}

if ($showlist) {
    $info = Request::get('info', 'string', '');
    if ('added' === $info) {
        echo Message::success($this->i18n('domain_added'));
    } elseif ('updated' === $info) {
        echo Message::success($this->i18n('domain_updated'));
    }

    $list = new DataList('SELECT * FROM ' . $table . ' ORDER BY domain', rowsPerPage: 100);
    $list->addParam('page', 'yrewrite/domains');
    $editUrl = static fn (array $row): string => $list->getUrl(['func' => 'edit', 'data_id' => $row['id']]);

    $list->column(
        'icon',
        Html::raw('<a href="' . escape($list->getUrl(['func' => 'add'])) . '"' . \Redaxo\Core\Backend\Accesskey::attributes($this->i18n('add_domain'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>'),
        static fn (): Html => Html::raw('<i class="rex-icon fa-sitemap"></i>'),
        type: TableColumnType::Icon,
        link: $editUrl,
    );

    $showArticle = function (array $row, string $field): string|Html {
        $id = (int) $row[$field];
        if (0 === $id) {
            return $this->i18n('root');
        }
        if ($article = Article::get($id)) {
            $link = $article->isStartArticle()
                ? Url::backendPage('structure', ['category_id' => $id, 'clang' => 1])
                : Url::backendPage('content/edit', ['article_id' => $id, 'mode' => 'edit', 'clang' => 1]);
            return Html::raw(escape($article->name) . ' [<a href="' . escape($link) . '">' . $id . '</a>]');
        }
        return '[' . $id . ']';
    };

    $list->column('domain', $this->i18n('domain'));
    $list->column('mount_id', $this->i18n('mount_id'), static fn (array $row) => $showArticle($row, 'mount_id'));
    $list->column('start_id', $this->i18n('start_id'), static fn (array $row) => $showArticle($row, 'start_id'));
    $list->column('notfound_id', $this->i18n('notfound_id'), static fn (array $row) => $showArticle($row, 'notfound_id'));
    $list->column('clangs', $this->i18n('clangs'), function (array $row): string|Html {
        $clangs = trim((string) $row['clangs'], '|, ');
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
            $startClang = Language::get((int) $row['clang_start']);
            return Html::raw(escape(implode(', ', $names)) . '<br />' . escape($this->i18n('clang_start') . ': ' . ($startClang?->name ?? '')));
        }
        return implode(', ', $names);
    });
    $list->column('edit', I18n::msg('edit'), static fn (): Html => Html::raw('<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit')), type: TableColumnType::Action, link: $editUrl);
    $list->column(
        'delete',
        null,
        static fn (): Html => Html::raw('<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete')),
        type: TableColumnType::Action,
        link: static fn (array $row): string => $list->getUrl(['data_id' => $row['id'], 'func' => 'delete'] + $csrf->getUrlParams()),
        linkAttributes: ['data-confirm' => I18n::msg('delete') . ' ?'],
    );

    echo new Section($this->i18n('domains'), content: $list)->render();
}
