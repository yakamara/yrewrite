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
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Component\SectionVariant;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;
use Yakamara\YRewrite\Backend\ForwardData;
use Yakamara\YRewrite\Forward;
use Yakamara\YRewrite\YRewrite;

use function Redaxo\Core\View\escape;

$showlist = true;
$dataId = Request::request('data_id', 'int', 0);
$func = Request::request('func', 'string');
$csrf = CsrfToken::factory('yrewrite_forward');
$table = Core::TABLE_PREFIX . 'yrewrite_forward';

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
        Sql::factory()->setQuery('DELETE FROM ' . $table . ' WHERE id = ?', [$dataId]);
        echo Message::success($this->i18n('forward_deleted'));
        Forward::init();
        Forward::generatePathFile();
    }
    $func = '';
}

if ('status' === $func) {
    if (!$csrf->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        Sql::factory()->setQuery('UPDATE ' . $table . ' SET status = 1 - status WHERE id = ?', [Request::request('oid', 'int')]);
        Forward::init();
        Forward::generatePathFile();
    }
    $func = '';
}

if ('edit' === $func || 'add' === $func) {
    $showlist = false;

    $row = 'edit' === $func ? (Sql::factory()->getArray('SELECT * FROM ' . $table . ' WHERE id = ?', [$dataId])[0] ?? null) : null;
    if ('edit' === $func && null === $row) {
        echo Message::error($this->i18n('forward_no_domain_selected'));
        $showlist = true;
    } else {
        $data = null !== $row ? ForwardData::fromRow($row) : new ForwardData();
        $form = new Form($data, name: 'yrewrite_forward', title: 'edit' === $func ? $this->i18n('forward_edit') : $this->i18n('forward_add'), cancelUrl: Url::currentBackendPage());

        if ($form->handleRequest()) {
            if (!preg_match('@^[%_\.+\-\w]+[/%_\.+\,\-\w]*(?<!\/)(?:\?.+)?$@u', $data->url)) {
                $form->addError($this->i18n('warning_chars'), 'url');
            }
            $exists = Sql::factory()->getArray('SELECT id FROM ' . $table . ' WHERE domain_id = ? AND url = ? AND id <> ?', [$data->domainId ?? 0, $data->url, $dataId]);
            if ([] !== $exists) {
                $form->addError($this->i18n('forward_domainurl_already_defined'), 'url');
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
                Forward::init();
                Forward::generatePathFile();
                Response::sendRedirect(Url::currentBackendPage(['info' => 'edit' === $func ? 'updated' : 'added']));
            }
        }

        // the rows of the target type are shown, the others hidden: the fields are grouped by a wrapper per type
        $content = (string) $form->capture(function (Form $form): void {
            $body = Html::capture(static function () use ($form): void {
                foreach ($form->getFormErrors() as $error) {
                    echo Message::error((string) Html::from($error));
                }
                echo $form->row('status'), $form->row('domainId'), $form->row('url'), $form->row('movetype'), $form->row('type');
                echo '<div data-yrewrite-forward-type="article">', $form->row('article'), $form->row('clang'), '</div>';
                echo '<div data-yrewrite-forward-type="extern">', $form->row('extern'), '</div>';
                echo '<div data-yrewrite-forward-type="media">', $form->row('media'), '</div>';
                echo $form->row('expiryDate');
            });
            echo new Section(
                $form->title ?? '',
                $body,
                footer: Html::raw('<div class="rex-form-panel-footer">' . $form->buttons() . '</div>'),
                variant: SectionVariant::Edit,
            )->render();
        });

        $content .= '
            <script nonce="' . Response::getNonce() . '">
                (function () {
                    var typeSelect = document.querySelector(\'[name="yrewrite_forward[type]"]\');
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

        echo $content;
    }
}

if ($showlist) {
    $info = Request::get('info', 'string', '');
    if ('added' === $info) {
        echo Message::success($this->i18n('forward_added'));
    } elseif ('updated' === $info) {
        echo Message::success($this->i18n('forward_updated'));
    }

    $list = new DataList('SELECT * FROM ' . $table . ' ORDER BY id DESC', rowsPerPage: 100);
    $list->addParam('page', 'yrewrite/forward');
    $editUrl = static fn (array $row): string => $list->getUrl(['func' => 'edit', 'data_id' => $row['id']]);

    $list->column(
        'icon',
        Html::raw('<a href="' . escape($list->getUrl(['func' => 'add'])) . '"' . \Redaxo\Core\Backend\Accesskey::attributes($this->i18n('forward_add'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>'),
        static fn (): Html => Html::raw('<i class="rex-icon fa-sitemap"></i>'),
        type: ColumnType::Icon,
        link: $editUrl,
    );
    $list->column('id', 'id', sortable: true, link: $editUrl);
    $list->column('domain_id', $this->i18n('forward_url'), static function (array $row): Html {
        $url = (YRewrite::getDomainById((int) $row['domain_id'])?->getUrl() ?? '') . $row['url'];
        return Html::raw('<a href="' . escape($url) . '" target="_blank" rel="noopener"><i class="rex-icon fa-up-right-from-square"></i> ' . escape($url) . '</a>');
    });
    $list->column('forward_target', $this->i18n('forward_type'), static function (array $row): string {
        switch ($row['type']) {
            case 'article':
                $article = Article::get((int) $row['article_id'], (int) $row['clang']);
                if (!$article) {
                    return I18n::msg('yrewrite_forward_article_deleted');
                }
                if (!$article->isOnline()) {
                    return I18n::msg('yrewrite_forward_article_offline');
                }
                return $article->getUrl();
            case 'extern':
                return (string) $row['extern'];
            case 'media':
                $media = Media::get((string) $row['media']);
                if (!$media) {
                    return I18n::msg('yrewrite_forward_media_deleted');
                }
                return $media->getUrl();
            default:
                return '';
        }
    });
    $list->column(
        'status',
        $this->i18n('forward_status'),
        static fn (array $row): Html => 1 == $row['status']
            ? Html::raw('<span class="rex-online">' . I18n::msg('yrewrite_forward_active') . '</span>')
            : Html::raw('<span class="rex-offline">' . I18n::msg('yrewrite_forward_inactive') . '</span>'),
        link: static fn (array $row): string => $list->getUrl(['func' => 'status', 'oid' => $row['id']] + $csrf->getUrlParams()),
    );
    $list->column('movetype', $this->i18n('forward_movetype'), sortable: true);
    $list->column('expiry_date', $this->i18n('expiry_date'));
    $list->column('edit', I18n::msg('edit'), static fn (): Html => Html::raw('<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit')), type: ColumnType::Action, link: $editUrl);
    $list->column(
        'delete',
        null,
        static fn (): Html => Html::raw('<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete')),
        type: ColumnType::Action,
        link: static fn (array $row): string => $list->getUrl(['data_id' => $row['id'], 'func' => 'delete'] + $csrf->getUrlParams()),
        linkAttributes: ['data-confirm' => I18n::msg('delete') . ' ?'],
    );

    echo new Section($this->i18n('forward'), content: $list)->render();
}
