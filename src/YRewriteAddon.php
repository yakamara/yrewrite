<?php

namespace Yakamara\YRewrite;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\LoadOrder;
use Redaxo\Core\Backend\MainPage;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Index;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\Permission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Asset;
use Redaxo\Core\View\Fragment;

use const PHP_SAPI;

class YRewriteAddon extends Addon
{
    public protected(set) LoadOrder $load = LoadOrder::Early;

    public protected(set) array $defaultConfig = [
        'unicode_urls' => false,
        'hide_url_block' => false,
        'hide_seo_block' => false,
    ];

    #[Override]
    public function boot(): void
    {
        if (Core::isBackend() && Core::getUser()) {
            Asset::addCssFile($this->getAssetsUrl('yrewrite.css'));
        }

        // Additional permissions for url & seo editing
        Permission::register('yrewrite[url]', I18n::msg('yrewrite_perm_url_edit'));
        Permission::register('yrewrite[seo]', I18n::msg('yrewrite_perm_seo_edit'));
        Permission::register('yrewrite[forward]', $this->i18n('forward'));

        Extension::register('PACKAGES_INCLUDED', function (): void {
            YRewrite::init();

            if ('robots' === Request::request('rex_yrewrite_func', 'string')) {
                (new Seo())->sendRobotsTxt();
            }

            // if anything changes -> refresh PathFile
            // Registered in every context, not only in the backend: structure changes can
            // also be triggered headless (e.g. the api addon, console commands or cronjobs),
            // and the path cache has to be refreshed there too. These extension points only
            // fire when content actually changes, so there is no overhead on regular frontend
            // requests.
            $extensionPoints = [
                'CAT_ADDED', 'CAT_UPDATED', 'CAT_DELETED', 'CAT_STATUS', 'CAT_MOVED',
                'ART_ADDED', 'ART_UPDATED', 'ART_DELETED', 'ART_STATUS', 'ART_MOVED', 'ART_COPIED',
                'ART_META_UPDATED', 'ART_TO_STARTARTICLE', 'ART_TO_CAT', 'CAT_TO_ART',
                'CLANG_UPDATED',
            ];
            foreach ($extensionPoints as $extensionPoint) {
                Extension::register($extensionPoint, static function (ExtensionPoint $ep): void {
                    $params = $ep->getParams();
                    $params['subject'] = $ep->subject;
                    $params['extension_point'] = $ep->name;
                    YRewrite::generatePathFile($params);
                });
            }

            // Backend-only UI: per-article URL & SEO panels in the structure content sidebar.
            if (Core::isBackend()) {
                $user = Core::getUser();
                if (!$this->getConfig('hide_url_block') && $user?->hasPerm('yrewrite[url]')) {
                    Extension::register('STRUCTURE_CONTENT_SIDEBAR', function (ExtensionPoint $ep): string {
                        $panel = (string) $this->includeFile('pages/content.yrewrite_url.php', ['params' => $ep->getParams()]);
                        $fragment = new Fragment();
                        $fragment->setVar('title', '<i class="rex-icon rex-icon-info"></i> ' . $this->i18n('rewriter'), false);
                        $fragment->setVar('body', $panel, false);
                        $fragment->setVar('collapse', true);
                        $fragment->setVar('collapsed', false);
                        return $ep->subject . $fragment->parse('core/page/section.php');
                    });
                }
                if (!$this->getConfig('hide_seo_block') && $user?->hasPerm('yrewrite[seo]')) {
                    Extension::register('STRUCTURE_CONTENT_SIDEBAR', function (ExtensionPoint $ep): string {
                        $panel = (string) $this->includeFile('pages/content.yrewrite_seo.php', ['params' => $ep->getParams()]);
                        $fragment = new Fragment();
                        $fragment->setVar('title', '<i class="rex-icon rex-icon-info"></i> ' . $this->i18n('rewriter_seo'), false);
                        $fragment->setVar('body', $panel, false);
                        $fragment->setVar('collapse', true);
                        $fragment->setVar('collapsed', false);
                        return $ep->subject . $fragment->parse('core/page/section.php');
                    });
                }
            }

            Extension::register('URL_REWRITE', static function (ExtensionPoint $ep): string {
                $params = $ep->getParams();
                $params['subject'] = $ep->subject;
                return YRewrite::rewrite($params);
            });

            Extension::register('MEDIA_MANAGER_URL', static function (ExtensionPoint $ep): string {
                return YRewrite::rewriteMedia($ep->getParams());
            });

            if ('cli' !== PHP_SAPI) {
                YRewrite::prepare();
            }
        }, ExtensionLevel::Early);

        if ('sitemap' === Request::request('rex_yrewrite_func', 'string')) {
            Extension::register('PACKAGES_INCLUDED', static function (): void {
                (new Seo())->sendSitemap();
            }, ExtensionLevel::Late);
        }

        Extension::register('YREWRITE_PREPARE', static function (ExtensionPoint $ep): false {
            $params = $ep->getParams();
            $params['subject'] = $ep->subject;
            return Forward::getForward($params);
        });
    }

    /**
     * @return iterable<Page>
     */
    #[Override]
    public function getPages(): iterable
    {
        $main = new MainPage('addons', 'yrewrite', 'YRewrite');
        $main->setRequiredPermissions('yrewrite[forward]');
        $main->setPath($this->getPath('pages/index.php'));
        $main->setPjax();

        $main->addSubpage(
            new Page('domains', $this->i18n('domains'))
                ->setRequiredPermissions('admin')
                ->setSubPath($this->getPath('pages/domains.php')),
        );
        $main->addSubpage(
            new Page('alias_domains', $this->i18n('alias_domains'))
                ->setRequiredPermissions('admin')
                ->setSubPath($this->getPath('pages/alias_domains.php')),
        );
        $main->addSubpage(
            new Page('forward', $this->i18n('forward'))
                ->setRequiredPermissions('yrewrite[forward]')
                ->setSubPath($this->getPath('pages/forward.php')),
        );
        $main->addSubpage(
            new Page('setup', $this->i18n('setup'))
                ->setRequiredPermissions('admin')
                ->setSubPath($this->getPath('pages/setup.php')),
        );

        return [$main];
    }

    #[Override]
    public function install(): void
    {
        $table = Table::get(Core::getTable('article'));
        $urlTypeExists = $table->hasColumn('yrewrite_url_type');
        $table
            ->ensureColumn(new Column('yrewrite_url_type', "enum('AUTO','CUSTOM','REDIRECTION_INTERNAL','REDIRECTION_EXTERNAL')", false, 'AUTO'))
            ->ensureColumn(new Column('yrewrite_url', 'text'), 'yrewrite_url_type')
            ->ensureColumn(new Column('yrewrite_redirection', 'varchar(191)'), 'yrewrite_url')
            ->ensureColumn(new Column('yrewrite_title', 'varchar(191)'), 'yrewrite_redirection')
            ->ensureColumn(new Column('yrewrite_description', 'text'), 'yrewrite_title')
            ->ensureColumn(new Column('yrewrite_image', 'varchar(191)'), 'yrewrite_description')
            ->ensureColumn(new Column('yrewrite_changefreq', 'varchar(10)', true), 'yrewrite_image')
            ->ensureColumn(new Column('yrewrite_priority', 'varchar(5)', true), 'yrewrite_changefreq')
            ->ensureColumn(new Column('yrewrite_index', 'tinyint(1)', true), 'yrewrite_priority')
            ->ensureColumn(new Column('yrewrite_canonical_url', 'text'), 'yrewrite_index')
            ->alter();

        if (!$urlTypeExists) {
            Sql::factory()->setQuery('UPDATE ' . Core::getTable('article') . ' SET yrewrite_url_type = IF(yrewrite_url != "", "CUSTOM", "AUTO")');
        }

        // Only domain/start_id/notfound_id are required; the rest is optional and therefore nullable,
        // so the backend form may omit fields (MySQL strict mode rejects missing NOT NULL columns).
        Table::get(Core::getTable('yrewrite_domain'))
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('domain', 'varchar(191)'))
            ->ensureColumn(new Column('mount_id', 'int(11)', true))
            ->ensureColumn(new Column('start_id', 'int(11)'))
            ->ensureColumn(new Column('notfound_id', 'int(11)'))
            ->ensureColumn(new Column('clangs', 'varchar(191)', true))
            ->ensureColumn(new Column('clang_start', 'int(11)', true))
            // checkbox columns hold the core-Form "|value|" notation, so they are varchar
            ->ensureColumn(new Column('clang_start_auto', 'varchar(5)', true))
            ->ensureColumn(new Column('clang_start_hidden', 'varchar(5)', true))
            ->ensureColumn(new Column('robots', 'text', true))
            ->ensureColumn(new Column('title_scheme', 'varchar(191)', true))
            ->ensureColumn(new Column('description', 'varchar(191)', true))
            ->ensureColumn(new Column('auto_redirect', 'varchar(5)', true))
            ->ensureColumn(new Column('auto_redirect_days', 'int(3)', true))
            ->ensureIndex(new Index('domain', ['domain'], Index::UNIQUE))
            ->ensure();

        Table::get(Core::getTable('yrewrite_alias'))
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('alias_domain', 'varchar(191)'))
            ->ensureColumn(new Column('domain_id', 'int(11)'))
            ->ensureColumn(new Column('clang_start', 'int(11)'))
            ->ensureIndex(new Index('alias_domain', ['alias_domain'], Index::UNIQUE))
            ->ensure();

        Table::get(Core::getTable('yrewrite_forward'))
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('domain_id', 'int(11)'))
            ->ensureColumn(new Column('status', 'int(11)'))
            ->ensureColumn(new Column('url', 'varchar(512)'))
            ->ensureColumn(new Column('type', 'varchar(191)'))
            ->ensureColumn(new Column('article_id', 'int(11)'))
            ->ensureColumn(new Column('clang', 'int(11)'))
            ->ensureColumn(new Column('extern', 'varchar(512)'))
            ->ensureColumn(new Column('media', 'varchar(191)'))
            ->ensureColumn(new Column('movetype', 'varchar(191)'))
            ->ensureColumn(new Column('expiry_date', 'date', true))
            ->ensure();

        $c = Sql::factory();
        $c->setQuery('ALTER TABLE `' . Core::getTable('yrewrite_domain') . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        $c->setQuery('ALTER TABLE `' . Core::getTable('yrewrite_alias') . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        $c->setQuery('ALTER TABLE `' . Core::getTable('yrewrite_forward') . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');

        // Add media manager type
        $c->setQuery('SELECT * FROM ' . Core::getTable('media_manager_type') . " WHERE name = 'yrewrite_seo_image'");
        if (0 === $c->getRows()) {
            $type = Sql::factory();
            $type->setTable(Core::getTable('media_manager_type'));
            $type->setValue('status', 0);
            $type->setValue('name', 'yrewrite_seo_image');
            $type->setValue('description', 'Rewrite SEO preview image for sitemap and open graph tags');
            $type->addGlobalCreateFields();
            $type->addGlobalUpdateFields();
            $type->insert();
            $lastId = $type->getLastId();

            $effect = Sql::factory();
            $effect->setTable(Core::getTable('media_manager_type_effect'));
            $effect->setValue('type_id', $lastId);
            $effect->setValue('effect', 'resize');
            $effect->setValue('parameters', '{"rex_effect_resize":{"rex_effect_resize_width":"4096","rex_effect_resize_height":"4096","rex_effect_resize_style":"maximum","rex_effect_resize_allow_enlarge":"not_enlarge"}}');
            $effect->setValue('priority', 1);
            $effect->addGlobalCreateFields();
            $effect->addGlobalUpdateFields();
            $effect->insert();
        }

        Addon::require('yrewrite')->clearCache();
    }

    #[Override]
    public function uninstall(): void
    {
        // drop the addon's own tables
        foreach (['yrewrite_domain', 'yrewrite_alias', 'yrewrite_forward'] as $table) {
            $t = Table::get(Core::getTable($table));
            if ($t->exists()) {
                $t->drop();
            }
        }

        // remove the columns added to the article table
        $article = Table::get(Core::getTable('article'));
        $changed = false;
        foreach ([
            'yrewrite_url_type', 'yrewrite_url', 'yrewrite_redirection', 'yrewrite_title',
            'yrewrite_description', 'yrewrite_image', 'yrewrite_changefreq', 'yrewrite_priority',
            'yrewrite_index', 'yrewrite_canonical_url',
        ] as $column) {
            if ($article->hasColumn($column)) {
                $article->removeColumn($column);
                $changed = true;
            }
        }
        if ($changed) {
            $article->alter();
        }

        // remove the media manager type and its effects
        $sql = Sql::factory();
        $sql->setQuery('SELECT id FROM ' . Core::getTable('media_manager_type') . " WHERE name = 'yrewrite_seo_image'");
        if ($sql->getRows() > 0) {
            $typeId = (int) $sql->getValue('id');
            Sql::factory()->setQuery('DELETE FROM ' . Core::getTable('media_manager_type_effect') . ' WHERE type_id = ?', [$typeId]);
            Sql::factory()->setQuery('DELETE FROM ' . Core::getTable('media_manager_type') . ' WHERE id = ?', [$typeId]);
        }

        $this->clearCache();
    }
}
