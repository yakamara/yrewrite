<?php

/** @var rex_addon $this */

use FriendsOfRedaxo\Api\RouteCollection;
use Yakamara\YRewrite\Api\RoutePackage\YRewrite;

if (!rex::isBackend()) {
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), DIRECTORY_SEPARATOR) . '/';
    rex_url::init(new rex_path_default_provider($path, 'redaxo', false));
} elseif (rex::getUser()) {
    rex_view::addCssFile($this->getAssetsUrl('yrewrite.css'));
}

// Additional permissions for url & seo editing
rex_perm::register('yrewrite[url]', rex_i18n::msg('yrewrite_perm_url_edit'));
rex_perm::register('yrewrite[seo]', rex_i18n::msg('yrewrite_perm_seo_edit'));

// API AddOn Routes
if (rex_addon::get('api')->isAvailable()) {
    // kompletter Namespace
    RouteCollection::registerRoutePackage(new YRewrite());
}

rex_extension::register('PACKAGES_INCLUDED', function ($params) {
    rex_yrewrite::init();

    if ('robots' == rex_request('rex_yrewrite_func', 'string')) {
        $robots = new rex_yrewrite_seo();
        $robots->sendRobotsTxt();
    }

    // if anything changes -> refresh PathFile
    if (rex::isBackend()) {
        $extensionPoints = [
            'CAT_ADDED',   'CAT_UPDATED',   'CAT_DELETED', 'CAT_STATUS',  'CAT_MOVED',
            'ART_ADDED',   'ART_UPDATED',   'ART_DELETED', 'ART_STATUS',  'ART_MOVED', 'ART_COPIED',
            'ART_META_UPDATED', 'ART_TO_STARTARTICLE', 'ART_TO_CAT', 'CAT_TO_ART',
            /* 'CLANG_ADDED', */ 'CLANG_UPDATED', /* 'CLANG_DELETED', */
            /* 'ARTICLE_GENERATED' */
            // 'ALL_GENERATED'
        ];
        foreach ($extensionPoints as $extensionPoint) {
            rex_extension::register($extensionPoint, static function (rex_extension_point $ep) {
                $params = $ep->getParams();
                $params['subject'] = $ep->getSubject();
                $params['extension_point'] = $ep->getName();
                rex_yrewrite::generatePathFile($params);
            });
        }

        // prevent article deletion if used in domain settings
        rex_extension::register('ART_PRE_DELETED', static function (rex_extension_point $ep) {
            $warning = [];
            $params = $ep->getParams();
            $article_id = $params['id'];

            $sql = rex_sql::factory();
            $sql->setQuery('SELECT id, domain FROM `' . rex::getTablePrefix() . 'yrewrite_domain` '
                . 'WHERE start_id = :article_id OR mount_id = :article_id OR notfound_id = :article_id', [
                    'article_id' => $article_id,
                ]);

            // Warnings
            for ($i = 0; $i < $sql->getRows(); ++$i) {
                $message = '<a href="' . rex_url::backendPage('yrewrite/domains', ['func' => 'edit', 'data_id' => $sql->getValue('id')]) . '">YRewrite ' . rex_i18n::msg('yrewrite_domains') . ': ' . $sql->getValue('domain') . '</a>';
                $warning[] = $message;
                $sql->next();
            }

            if (count($warning) > 0) {
                throw new rex_api_exception(rex_i18n::msg('yrewrite_error_article_in_use') . '<ul><li>' . implode('</li><li>', $warning) . '</li></ul>');
            }
        });

        // prevent deletion of seo image
        rex_extension::register('MEDIA_IS_IN_USE', static function (rex_extension_point $ep) {
            $warning = $ep->getSubject();
            $params = $ep->getParams();
            $filename = $params['filename'];

            $sql = rex_sql::factory();
            $sql->setQuery('SELECT id, clang_id, name FROM `' . rex::getTablePrefix() . 'article` WHERE yrewrite_image = ?', [$filename]);

            for ($i = 0; $i < $sql->getRows(); ++$i) {
                $message = rex_i18n::msg('yrewrite_seoimage_error_delete') . ' <a href="javascript:openPage(\'index.php?page=content/edit&mode=edit&article_id=' .
                    $sql->getValue('id') . '&clang=' . $sql->getValue('clang_id') . '\')">' . $sql->getValue('name') . '</a>';
                if (!in_array($message, $warning)) {
                    $warning[] = $message;
                }
                $sql->next();
            }

            return $warning;
        });
    }

    // rex_extension::register('ALL_GENERATED', 'rex_yrewrite::init');
    rex_extension::register('URL_REWRITE', static function (rex_extension_point $ep) {
        $params = $ep->getParams();
        $params['subject'] = $ep->getSubject();
        return rex_yrewrite::rewrite($params);
    });

    rex_extension::register('MEDIA_MANAGER_URL', static function (rex_extension_point $ep) {
        return rex_yrewrite::rewriteMedia($ep->getParams());
    });

    if ('cli' !== PHP_SAPI) {
        rex_yrewrite::prepare();
    }

    if (rex::isBackend()) {
        if (!$this->getConfig('yrewrite_hide_url_block') && rex::getUser() instanceof rex_user && rex::getUser()->hasPerm('yrewrite[url]')) {
            rex_extension::register('STRUCTURE_CONTENT_SIDEBAR', function (rex_extension_point $ep) {
                $params = $ep->getParams();
                $subject = $ep->getSubject();

                $panel = include rex_path::addon('yrewrite', 'pages/content.yrewrite_url.php');

                $fragment = new rex_fragment();
                $fragment->setVar('title', '<i class="rex-icon rex-icon-info"></i> ' . rex_i18n::msg('yrewrite_rewriter'), false);
                $fragment->setVar('body', $panel, false);
                $fragment->setVar('article_id', $params['article_id'], false);

                $fragment->setVar('collapse', true);
                $fragment->setVar('collapsed', false);
                $content = $fragment->parse('core/page/section.php');

                return $subject . $content;
            });
        }

        if (!$this->getConfig('yrewrite_hide_seo_block') && rex::getUser() instanceof rex_user && rex::getUser()->hasPerm('yrewrite[seo]')) {
            rex_extension::register('STRUCTURE_CONTENT_SIDEBAR', function (rex_extension_point $ep) {
                $params = $ep->getParams();
                $subject = $ep->getSubject();

                $panel = include rex_path::addon('yrewrite', 'pages/content.yrewrite_seo.php');

                $fragment = new rex_fragment();
                $fragment->setVar('title', '<i class="rex-icon rex-icon-info"></i> ' . rex_i18n::msg('yrewrite_rewriter_seo'), false);
                $fragment->setVar('body', $panel, false);
                $fragment->setVar('article_id', $params['article_id'], false);
                $fragment->setVar('clang', $params['clang'], false);
                $fragment->setVar('ctype', $params['ctype'], false);
                $fragment->setVar('collapse', true);
                $fragment->setVar('collapsed', false);
                $content = $fragment->parse('core/page/section.php');

                return $subject . $content;
            });
        }
    }
}, rex_extension::EARLY);

if ('sitemap' == rex_request('rex_yrewrite_func', 'string')) {
    rex_extension::register('PACKAGES_INCLUDED', static function ($params) {
        $sitemap = new rex_yrewrite_seo();
        $sitemap->sendSitemap();
    }, rex_extension::LATE);
}

rex_extension::register('YREWRITE_PREPARE', static function (rex_extension_point $ep) {
    $params = $ep->getParams();
    $params['subject'] = $ep->getSubject();
    return rex_yrewrite_forward::getForward($params);
});
