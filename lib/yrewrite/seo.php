<?php

/**
 * YREWRITE Addon.
 *
 * @author jan.kristinus@yakamara.de
 *
 * @package redaxo\yrewrite
 */

class rex_yrewrite_seo
{
    public $article;
    public $domain;

    public static $priority = ['1.0', '0.7', '0.5', '0.3', '0.1', '0.0'];
    public static $priority_default = '';
    public static $index_setting_default = 0;
    public static $changefreq = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
    public static $changefreq_default = 'weekly';
    public static $robots_default = "User-agent: *\nDisallow:";
    public static $title_scheme_default = '%T / %SN';

    /** @var string */
    public static $meta_title_field = 'yrewrite_title';
    /** @var string */
    public static $meta_description_field = 'yrewrite_description';
    /** @var string */
    public static $meta_image_field = 'yrewrite_image';
    /** @var string */
    public static $meta_changefreq_field = 'yrewrite_changefreq';
    /** @var string */
    public static $meta_priority_field = 'yrewrite_priority';
    /** @var string */
    public static $meta_index_field = 'yrewrite_index';
    /** @var string */
    public static $meta_canonical_url_field = 'yrewrite_canonical_url';

    public function __construct($article_id = 0, $clang = null)
    {
        if (0 == $article_id) {
            $article_id = rex_article::getCurrentId();
        }
        if (null === $clang) {
            $clang = rex_clang::getCurrentId();
        }

        if ($article = rex_article::get($article_id, $clang)) {
            $this->article = $article;
            $this->domain = rex_yrewrite::getDomainByArticleId($article_id, $clang);
        }
    }

    public function getTags(): string
    {
        $tags = [];
        $tagsOg = [];
        $tagsTwitter = [];
        $tagsTwitter['twitter:card'] = '<meta name="twitter:card" content="summary">';

        $title = rex_escape($this->getTitle());
        $tags['title'] = '<title>'.$title.'</title>';
        $tagsOg['og:title'] = '<meta property="og:title" content="'.$title.'">';
        $tagsOg['og:type'] = '<meta property="og:type" content="website">';
        $tagsTwitter['twitter:title'] = '<meta name="twitter:title" content="'.$title.'">';

        $description = rex_escape($this->getDescription());
        if ('' != $description) {
            $tags['description'] = '<meta name="description" content="'.$description.'">';
            $tagsOg['og:description'] = '<meta property="og:description" content="'.$description.'">';
            $tagsTwitter['twitter:description'] = '<meta name="twitter:description" content="'.$description.'">';
        }

        $image = $this->getImage();
        if ('' != $image) {
            $media = rex_media::get($image);
            $tagsOg['og:image'] = '<meta property="og:image" content="'.rtrim($this->domain->getUrl(), '/').rex_media_manager::getUrl('yrewrite_seo_image', $image).'">';
            if ($media) {
                if ($media->getTitle()) {
                    $tagsOg['og:image:alt'] = '<meta property="og:image:alt" content="'.rex_escape($media->getTitle()).'">';
                }
                $tagsOg['og:image:type'] = '<meta property="og:image:type" content="'.rex_escape($media->getType()).'">';
            }
            $tagsOg['twitter:image'] = '<meta name="twitter:image" content="'.rtrim($this->domain->getUrl(), '/').rex_media_manager::getUrl('yrewrite_seo_image', $image).'">';
            if ($media && $media->getTitle()) {
                $tagsOg['twitter:image:alt'] = '<meta name="twitter:image:alt" content="'.rex_escape($media->getTitle()).'">';
            }
        }

        $index = $this->article->getValue(self::$meta_index_field) ?? self::$index_setting_default;

        $content = 'noindex, nofollow';
        if (1 == $index || (0 == $index && $this->article->isOnline())) {
            $content = 'index, follow';
        } elseif (2 == $index) {
            $content = 'noindex, follow';
        }
        $tags['robots'] = '<meta name="robots" content="'.$content.'">';

        $canonicalUrl = rex_escape($this->getCanonicalUrl());
        if (1 == $index || (0 == $index && $this->article->isOnline())) {
            $tags['canonical'] = '<link rel="canonical" href="'.$canonicalUrl.'">';
        }
        $tagsOg['og:url'] = '<meta property="og:url" content="'.$canonicalUrl.'">';
        $tagsTwitter['twitter:url'] = '<meta name="twitter:url" content="'.$canonicalUrl.'">';

        $hrefs = $this->getHrefLangs();
        foreach ($hrefs as $code => $url) {
            $tags['hreflang:'.$code] = '<link rel="alternate" hreflang="' . $code . '" href="' . $url . '">';
        }

        $tags += $tagsOg + $tagsTwitter;
        $tags = rex_extension::registerPoint(new \rex_extension_point('YREWRITE_SEO_TAGS', $tags));
        return implode("\n", $tags);
    }

    /** @deprecated use getTags instead */
    public function getTitleTag()
    {
        return '<title>'.rex_escape(strip_tags($this->getTitle())).'</title>'; // lang="de"
    }

    /** @deprecated use getTags instead */
    public function getDescriptionTag()
    {
        return '<meta name="description" content="'.rex_escape(strip_tags($this->getDescription())).'">'; //  lang="de"
    }

    /** @deprecated use getTags instead */
    public function getCanonicalUrlTag()
    {
        return '<link rel="canonical" href="'.rex_escape($this->getCanonicalUrl()).'">';
    }

    /** @deprecated use getTags instead */
    public function getRobotsTag()
    {
        $index = $this->article->getValue(self::$meta_index_field) ?? self::$index_setting_default;

        if (1 == $index || (0 == $index && $this->article->isOnline())) {
            return '<meta name="robots" content="index, follow">';
        }
        if (2 == $index) {
            return '<meta name="robots" content="noindex, follow">';
        }
        return '<meta name="robots" content="noindex, nofollow">';
    }

    public function getTitle()
    {
        $title = $this->article->getValue(self::$meta_title_field);
        if ('' == $title) {
            $title = htmlspecialchars_decode(trim($this->domain->getTitle()));
        }
        if ('' == $title) {
            $title = self::$title_scheme_default;
        }

        $title = str_replace('%T', $this->article->getValue('name'), $title);
        $title = str_replace('%SN', rex::getServerName(), $title);

        return $this->cleanString($title);
    }

    public function getDescription()
    {
        return $this->cleanString($this->article->getValue(self::$meta_description_field));
    }

    public function getImage()
    {
        return $this->cleanString($this->article->getValue(self::$meta_image_field));
    }

    public function getCanonicalUrl()
    {
        $canonical_url = trim($this->article->getValue(self::$meta_canonical_url_field));
        if ('' == $canonical_url) {
            $canonical_url = rex_yrewrite::getFullUrlByArticleId($this->article->getId(), $this->article->getClangId());
        }
        $canonical_url = rex_extension::registerPoint(new rex_extension_point('YREWRITE_CANONICAL_URL', $canonical_url, ['article' => $this->article]));
        return $canonical_url;
    }

    public function getHrefLangs()
    {
        $current_mount_id = $this->domain->getMountId();
        $lang_domains = [];

        if ($this->domain->isStartClangAuto() && $this->domain->getStartId() === rex_article::getCurrentId()) {
            $lang_domains['x-default'] = $this->domain->getUrl();
        }

        foreach (rex_yrewrite::getDomains() as $domain) {
            if ($current_mount_id == $domain->getMountId()) {
                foreach ($domain->getClangs() as $clang) {
                    if ($lang = rex_clang::get($clang)) {
                        $article = rex_article::getCurrent($clang);
                        if ($article->isOnline() && $lang->isOnline()) {
                            $lang_domains[$lang->getCode()] = rex_yrewrite::getFullUrlByArticleId($article->getId(), $lang->getId());
                        }
                    }
                }
            }
        }

        return rex_extension::registerPoint(new rex_extension_point('YREWRITE_HREFLANG_TAGS', $lang_domains, ['article' => $this->article]));
    }

    /** @deprecated use getTags instead */
    public function getHreflangTags()
    {
        $return = '';
        $lang_domains = $this->getHrefLangs();

        foreach ($lang_domains as $code => $url) {
            $return .= '<link rel="alternate" hreflang="' . $code . '" href="' . $url . '">';
        }
        return $return;
    }

    public function cleanString($str)
    {
        return str_replace(["\n", "\r"], [' ', ''], $str ?? '');
    }

    // ----- global static functions

    public function sendRobotsTxt($domain = '')
    {
        if ('' == $domain) {
            $domain = rex_yrewrite::getHost();
        }

        header('Content-Type: text/plain');
        // header content length ?
        $content = 'Sitemap: '.rex_yrewrite::getFullPath('sitemap.xml')."\n\n";

        if (rex_yrewrite::getDomainByName($domain)) {
            $robots = rex_yrewrite::getDomainByName($domain)->getRobots();
            if ('' != $robots) {
                $content .= $robots;
            } else {
                $content .= self::$robots_default;
            }
        }

        echo $content;
        exit;
    }

    public function sendSitemap($domain = '')
    {
        $domains = rex_yrewrite::getDomains();

        if ('' == $domain) {
            $domain = rex_yrewrite::getHost();
        }

        $sitemap = [];

        if (rex_yrewrite::getDomainByName($domain) || 1 == count($domains)) {
            if (1 == count($domains)) {
                $domain = rex_yrewrite::getDefaultDomain();
            } else {
                $domain = rex_yrewrite::getDomainByName($domain);
            }

            $domain_article_id = $domain->getStartId();
            $paths = 0;
            if ($dai = rex_article::get($domain_article_id)) {
                $paths = count($dai->getParentTree());
            }

            foreach (rex_yrewrite::getPathsByDomain($domain->getName()) as $article_id => $path) {
                foreach ($domain->getClangs() as $clang_id) {
                    if (!isset($path[$clang_id]) || !rex_clang::get($clang_id)->isOnline()) {
                        continue;
                    }

                    $article = rex_article::get($article_id, $clang_id);
                    $index = $article->getValue(self::$meta_index_field) ?? self::$index_setting_default;

                    if (
                        $article &&
                        $article->isPermitted() &&
                        (1 == $index || ($article->isOnline() && 0 == $index)) &&
                        ($article_id != $domain->getNotfoundId() || $article_id == $domain->getStartId())
                    ) {
                        $changefreq = $article->getValue(self::$meta_changefreq_field);
                        if (!in_array($changefreq, self::$changefreq)) {
                            $changefreq = self::$changefreq_default;
                        }

                        $priority = $article->getValue(self::$meta_priority_field);

                        if (!in_array($priority, self::$priority)) {
                            $article_paths = count($article->getParentTree());
                            $prio = $article_paths - $paths - 1;
                            if ($prio < 0) {
                                $prio = 0;
                            }

                            if (isset(self::$priority[$prio])) {
                                $priority = self::$priority[$prio];
                            } else {
                                $priority = self::$priority_default;
                            }
                        }

                        $sitemap_entry =
                          "\n".'<url>'.
                          "\n\t".'<loc>'.rex_yrewrite::getFullPath($path[$clang_id]).'</loc>'.
                          "\n\t".'<lastmod>'.date(DATE_W3C, $article->getUpdateDate()).'</lastmod>'; // Serverzeitzone passt
                        if ($article->getValue(self::$meta_image_field)) {
                            $media = rex_media::get((string) $article->getValue(self::$meta_image_field));
                            if ($media) {
                                $sitemap_entry .= "\n\t".'<image:image>'.
                                    "\n\t\t".'<image:loc>'.rtrim(rex_yrewrite::getDomainByArticleId($article->getId())->getUrl(), '/').rex_media_manager::getUrl('yrewrite_seo_image', $media->getFileName()).'</image:loc>'.
                                    ($media->getTitle() ? "\n\t\t".'<image:title>'.rex_escape($media->getTitle()).'</image:title>' : '').
                                    "\n\t".'</image:image>';
                            }
                        }
                        $sitemap_entry .= "\n\t".'<changefreq>'.$changefreq.'</changefreq>'.
                          "\n\t".'<priority>'.$priority.'</priority>'.
                          "\n".'</url>';
                        $sitemap[] = $sitemap_entry;
                    }
                }
            }
            $sitemap = rex_extension::registerPoint(new rex_extension_point('YREWRITE_DOMAIN_SITEMAP', $sitemap, ['domain' => $domain]));
        }
        $sitemap = rex_extension::registerPoint(new rex_extension_point('YREWRITE_SITEMAP', $sitemap));

        rex_response::cleanOutputBuffers();
        header('Content-Type: application/xml');
        $content = '<?xml version="1.0" encoding="UTF-8"?>';
        $content .= "\n".'<?xml-stylesheet type="text/xsl" href="assets/addons/yrewrite/xsl-stylesheets/xml-sitemap.xsl"?>';
        $content .= "\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
        $content .= implode("\n", $sitemap);
        $content .= "\n".'</urlset>';
        echo $content;
        exit;
    }

    /** @deprecated */
    public static function checkArticlePerm($article)
    {
        return $article->isPermitted();
    }
}
