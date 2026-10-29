<?php

namespace Yakamara\YRewrite;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\LoadOrder;
use Redaxo\Core\Addon\OwnedSchema;
use Redaxo\Core\Backend\MainPage;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Content\ExtensionPoint\ArticleAdded;
use Redaxo\Core\Content\ExtensionPoint\ArticleConvertedToCategory;
use Redaxo\Core\Content\ExtensionPoint\ArticleConvertedToStartArticle;
use Redaxo\Core\Content\ExtensionPoint\ArticleCopied;
use Redaxo\Core\Content\ExtensionPoint\ArticleDeleted;
use Redaxo\Core\Content\ExtensionPoint\ArticleMoved;
use Redaxo\Core\Content\ExtensionPoint\ArticleStatusChanged;
use Redaxo\Core\Content\ExtensionPoint\ArticleUpdated;
use Redaxo\Core\Content\ExtensionPoint\ArticleUrl;
use Redaxo\Core\Content\ExtensionPoint\CategoryAdded;
use Redaxo\Core\Content\ExtensionPoint\CategoryConvertedToArticle;
use Redaxo\Core\Content\ExtensionPoint\CategoryDeleted;
use Redaxo\Core\Content\ExtensionPoint\CategoryMoved;
use Redaxo\Core\Content\ExtensionPoint\CategoryStatusChanged;
use Redaxo\Core\Content\ExtensionPoint\CategoryUpdated;
use Redaxo\Core\Content\ExtensionPoint\ContentPageSidebar;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Index;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaManager\ExtensionPoint\MediaManagerUrl;
use Redaxo\Core\Security\ContentSecurityPolicy;
use Redaxo\Core\Security\Permission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Asset;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Html;

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

            // the backend previews articles of other domains in frames, e.g. in the article history
            if (Core::isBackend()) {
                foreach (YRewrite::getDomains() as $domain) {
                    $url = parse_url($domain->getUrl());
                    if (isset($url['scheme'], $url['host'])) {
                        ContentSecurityPolicy::backend()->add('frame-src', $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : ''));
                    }
                }
            }

            if ('robots' === Request::request('rex_yrewrite_func', 'string')) {
                new Seo()->sendRobotsTxt();
            }

            // if anything changes -> refresh PathFile
            // Registered in every context, not only in the backend: structure changes can
            // also be triggered headless (e.g. the api addon, console commands or cronjobs),
            // and the path cache has to be refreshed there too. These extension points only
            // fire when content actually changes, so there is no overhead on regular frontend
            // requests. The typed points are mapped to the legacy names the path generator
            // distinguishes; only the status and update points concern a single language.
            $articlePoints = [
                ArticleAdded::class => 'ART_ADDED',
                ArticleUpdated::class => 'ART_UPDATED',
                ArticleDeleted::class => 'ART_DELETED',
                ArticleStatusChanged::class => 'ART_STATUS',
                ArticleMoved::class => 'ART_MOVED',
                ArticleCopied::class => 'ART_COPIED',
                ArticleConvertedToStartArticle::class => 'ART_TO_STARTARTICLE',
                CategoryConvertedToArticle::class => 'CAT_TO_ART',
            ];
            foreach ($articlePoints as $class => $name) {
                Extension::register($class, static function (ExtensionPoint $ep) use ($name): void {
                    /** @var ArticleAdded|ArticleUpdated|ArticleDeleted|ArticleStatusChanged|ArticleMoved|ArticleCopied|ArticleConvertedToStartArticle|CategoryConvertedToArticle $ep */
                    $article = $ep->article;
                    YRewrite::generatePathFile([
                        'id' => $article->id,
                        'parent_id' => $article->categoryId ?? 0,
                        'extension_point' => $name,
                    ] + (in_array($name, ['ART_UPDATED', 'ART_STATUS'], true) ? ['language' => $article->languageId] : []));
                });
            }
            $categoryPoints = [
                CategoryAdded::class => 'CAT_ADDED',
                CategoryUpdated::class => 'CAT_UPDATED',
                CategoryDeleted::class => 'CAT_DELETED',
                CategoryStatusChanged::class => 'CAT_STATUS',
                CategoryMoved::class => 'CAT_MOVED',
                ArticleConvertedToCategory::class => 'ART_TO_CAT',
            ];
            foreach ($categoryPoints as $class => $name) {
                Extension::register($class, static function (ExtensionPoint $ep) use ($name): void {
                    /** @var CategoryAdded|CategoryUpdated|CategoryDeleted|CategoryStatusChanged|CategoryMoved|ArticleConvertedToCategory $ep */
                    $category = $ep->category;
                    YRewrite::generatePathFile([
                        'id' => $category->id,
                        'parent_id' => $category->parentId ?? 0,
                        'extension_point' => $name,
                    ] + (in_array($name, ['CAT_UPDATED', 'CAT_STATUS'], true) ? ['language' => $category->languageId] : []));
                });
            }

            // Backend-only UI: per-article URL & SEO panels in the structure content sidebar.
            if (Core::isBackend()) {
                $user = Core::getUser();
                $panelParams = static fn (ContentPageSidebar $ep): array => [
                    'article_id' => $ep->article->id,
                    'language' => $ep->article->languageId,
                    'content_section_id' => $ep->contentSectionId,
                    'page' => $ep->page,
                ];
                if (!$this->getConfig('hide_url_block') && $user?->hasPerm('yrewrite[url]')) {
                    Extension::register(ContentPageSidebar::class, function (ContentPageSidebar $ep) use ($panelParams): string {
                        $panel = (string) $this->includeFile('pages/content.yrewrite_url.php', ['params' => $panelParams($ep)]);
                        return $ep->subject . new Section(Html::raw('<i class="rex-icon rex-icon-info"></i> ' . $this->i18n('rewriter')), Html::raw($panel), collapsible: true)->render();
                    });
                }
                if (!$this->getConfig('hide_seo_block') && $user?->hasPerm('yrewrite[seo]')) {
                    Extension::register(ContentPageSidebar::class, function (ContentPageSidebar $ep) use ($panelParams): string {
                        $panel = (string) $this->includeFile('pages/content.yrewrite_seo.php', ['params' => $panelParams($ep)]);
                        return $ep->subject . new Section(Html::raw('<i class="rex-icon rex-icon-info"></i> ' . $this->i18n('seo')), Html::raw($panel), collapsible: true)->render();
                    });
                }
            }

            Extension::register(ArticleUrl::class, static function (ArticleUrl $ep): string {
                return YRewrite::rewrite([
                    'subject' => $ep->subject,
                    'id' => $ep->articleId,
                    'language' => $ep->languageId,
                    'params' => $ep->params,
                ]);
            });

            Extension::register(MediaManagerUrl::class, static function (MediaManagerUrl $ep): string {
                return YRewrite::rewriteMedia(['type' => $ep->type, 'file' => $ep->fileName, 'buster' => $ep->buster]);
            });

            if ('cli' !== PHP_SAPI) {
                YRewrite::prepare();
            }
        }, ExtensionLevel::Early);

        if ('sitemap' === Request::request('rex_yrewrite_func', 'string')) {
            Extension::register('PACKAGES_INCLUDED', static function (): void {
                new Seo()->sendSitemap();
            }, ExtensionLevel::Late);
        }

        Extension::register('YREWRITE_PREPARE', static function (ExtensionPoint $ep): false {
            $params = $ep->getParams();
            $params['subject'] = $ep->subject;
            return Forward::getForward($params);
        });
    }

    /** @return iterable<Page> */
    #[Override]
    public function getPages(): iterable
    {
        $main = new MainPage('addons', 'yrewrite', 'YRewrite');
        $main->setRequiredPermissions('yrewrite[forward]');
        $main->setPath($this->getPath('pages/index.php'));

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
        // All URL/SEO columns are optional and therefore nullable: every INSERT into rex_article that
        // core (or an addon, or the API) performs sets only core's own columns, and MySQL strict mode
        // rejects a missing NOT NULL column without a default with "1364 Field ... doesn't have a
        // default value" — which would break creating any article or category.
        $table = Table::get(Core::TABLE_PREFIX . 'article');
        $urlTypeExists = $table->hasColumn('yrewrite_url_type');
        $table
            ->ensureColumn(new Column('yrewrite_url_type', "enum('AUTO','CUSTOM','REDIRECTION_INTERNAL','REDIRECTION_EXTERNAL')", false, 'AUTO'))
            ->ensureColumn(new Column('yrewrite_url', 'text', true), 'yrewrite_url_type')
            ->ensureColumn(new Column('yrewrite_redirection', 'varchar(191)', true), 'yrewrite_url')
            ->ensureColumn(new Column('yrewrite_title', 'varchar(191)', true), 'yrewrite_redirection')
            ->ensureColumn(new Column('yrewrite_description', 'text', true), 'yrewrite_title')
            ->ensureColumn(new Column('yrewrite_image', 'varchar(191)', true), 'yrewrite_description')
            ->ensureColumn(new Column('yrewrite_changefreq', 'varchar(10)', true), 'yrewrite_image')
            ->ensureColumn(new Column('yrewrite_priority', 'varchar(5)', true), 'yrewrite_changefreq')
            ->ensureColumn(new Column('yrewrite_index', 'tinyint(1)', true), 'yrewrite_priority')
            ->ensureColumn(new Column('yrewrite_canonical_url', 'text', true), 'yrewrite_index')
            ->alter();

        if (!$urlTypeExists) {
            Sql::factory()->setQuery('UPDATE ' . (Core::TABLE_PREFIX . 'article') . ' SET yrewrite_url_type = IF(yrewrite_url != "", "CUSTOM", "AUTO")');
        }

        // Only domain/start_id/notfound_id are required; the rest is optional and therefore nullable,
        // so the backend form may omit fields (MySQL strict mode rejects missing NOT NULL columns).
        Table::get(Core::TABLE_PREFIX . 'yrewrite_domain')
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

        Table::get(Core::TABLE_PREFIX . 'yrewrite_alias')
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('alias_domain', 'varchar(191)'))
            ->ensureColumn(new Column('domain_id', 'int(11)'))
            ->ensureColumn(new Column('clang_start', 'int(11)'))
            ->ensureIndex(new Index('alias_domain', ['alias_domain'], Index::UNIQUE))
            ->ensure();

        Table::get(Core::TABLE_PREFIX . 'yrewrite_forward')
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
        $c->setQuery('ALTER TABLE `' . (Core::TABLE_PREFIX . 'yrewrite_domain') . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        $c->setQuery('ALTER TABLE `' . (Core::TABLE_PREFIX . 'yrewrite_alias') . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        $c->setQuery('ALTER TABLE `' . (Core::TABLE_PREFIX . 'yrewrite_forward') . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');

        Addon::require('yrewrite')->clearCache();
    }

    #[Override]
    public function getOwnedSchema(): OwnedSchema
    {
        return new OwnedSchema(
            tables: [Core::TABLE_PREFIX . 'yrewrite_domain', Core::TABLE_PREFIX . 'yrewrite_alias', Core::TABLE_PREFIX . 'yrewrite_forward'],
            columns: [Core::TABLE_PREFIX . 'article' => [
                'yrewrite_url_type', 'yrewrite_url', 'yrewrite_redirection', 'yrewrite_title',
                'yrewrite_description', 'yrewrite_image', 'yrewrite_changefreq', 'yrewrite_priority',
                'yrewrite_index', 'yrewrite_canonical_url',
            ]],
        );
    }
}
