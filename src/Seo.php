<?php

namespace Yakamara\YRewrite;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;

use function in_array;

use function Redaxo\Core\View\escape;

use const DATE_W3C;

/**
 * SEO helper: meta tags, sitemap.xml and robots.txt.
 *
 * Port of REDAXO 5 `rex_yrewrite_seo`.
 */
class Seo
{
    public ?Article $article = null;
    public ?Domain $domain = null;

    /** @var list<string> */
    public static array $priority = ['1.0', '0.7', '0.5', '0.3', '0.1', '0.0'];
    public static string $priorityDefault = '';
    public static int $indexSettingDefault = 0;
    /** @var list<string> */
    public static array $changefreq = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
    public static string $changefreqDefault = 'weekly';
    public static string $robotsDefault = "User-agent: *\nDisallow:";
    public static string $titleSchemeDefault = '%T / %SN';

    public static string $metaTitleField = 'yrewrite_title';
    public static string $metaDescriptionField = 'yrewrite_description';
    public static string $metaImageField = 'yrewrite_image';
    public static string $metaChangefreqField = 'yrewrite_changefreq';
    public static string $metaPriorityField = 'yrewrite_priority';
    public static string $metaIndexField = 'yrewrite_index';
    public static string $metaCanonicalUrlField = 'yrewrite_canonical_url';

    public function __construct(int $articleId = 0, ?int $clang = null)
    {
        if (0 === $articleId) {
            $articleId = Article::getCurrentId();
        }
        $clang ??= Language::getCurrentId();

        if ($article = Article::get($articleId, $clang)) {
            $this->article = $article;
            $this->domain = YRewrite::getDomainByArticleId($articleId, $clang);
        }
    }

    public function getTags(): string
    {
        $tags = [];
        $tagsOg = [];
        $tagsTwitter = [];
        $tagsTwitter['twitter:card'] = '<meta name="twitter:card" content="summary">';

        $title = escape($this->getTitle());
        $tags['title'] = '<title>' . $title . '</title>';
        $tagsOg['og:title'] = '<meta property="og:title" content="' . $title . '">';
        $tagsOg['og:type'] = '<meta property="og:type" content="website">';
        $tagsTwitter['twitter:title'] = '<meta name="twitter:title" content="' . $title . '">';

        $description = escape($this->getDescription());
        if ('' !== $description) {
            $tags['description'] = '<meta name="description" content="' . $description . '">';
            $tagsOg['og:description'] = '<meta property="og:description" content="' . $description . '">';
            $tagsTwitter['twitter:description'] = '<meta name="twitter:description" content="' . $description . '">';
        }

        $image = $this->getImage();
        if ('' !== $image && null !== $this->domain) {
            $media = Media::get($image);
            $tagsOg['og:image'] = '<meta property="og:image" content="' . rtrim($this->domain->getUrl(), '/') . MediaManager::getUrl('yrewrite_seo_image', $image) . '">';
            if ($media) {
                if ('' !== $media->title) {
                    $tagsOg['og:image:alt'] = '<meta property="og:image:alt" content="' . escape($media->title) . '">';
                }
                $tagsOg['og:image:type'] = '<meta property="og:image:type" content="' . escape($media->type) . '">';
            }
            $tagsOg['twitter:image'] = '<meta name="twitter:image" content="' . rtrim($this->domain->getUrl(), '/') . MediaManager::getUrl('yrewrite_seo_image', $image) . '">';
            if ($media && '' !== $media->title) {
                $tagsOg['twitter:image:alt'] = '<meta name="twitter:image:alt" content="' . escape($media->title) . '">';
            }
        }

        $index = $this->article?->getValue(self::$metaIndexField) ?? self::$indexSettingDefault;

        $content = 'noindex, nofollow';
        if (1 == $index || (0 == $index && $this->article?->isOnline())) {
            $content = 'index, follow';
        } elseif (2 == $index) {
            $content = 'noindex, follow';
        }
        $tags['robots'] = '<meta name="robots" content="' . $content . '">';

        $canonicalUrl = escape($this->getCanonicalUrl());
        if (1 == $index || (0 == $index && $this->article?->isOnline())) {
            $tags['canonical'] = '<link rel="canonical" href="' . $canonicalUrl . '">';
        }
        $tagsOg['og:url'] = '<meta property="og:url" content="' . $canonicalUrl . '">';
        $tagsTwitter['twitter:url'] = '<meta name="twitter:url" content="' . $canonicalUrl . '">';

        $hrefs = $this->getHrefLangs();
        foreach ($hrefs as $code => $url) {
            $tags['hreflang:' . $code] = '<link rel="alternate" hreflang="' . $code . '" href="' . $url . '">';
        }

        $tags += $tagsOg + $tagsTwitter;
        /** @var array<string, string> $tags */
        $tags = Extension::dispatch(new ExtensionPoint('YREWRITE_SEO_TAGS', $tags));
        return implode("\n", $tags);
    }

    public function getTitle(): string
    {
        $title = (string) $this->article?->getValue(self::$metaTitleField);
        if ('' === $title) {
            $title = htmlspecialchars_decode(trim((string) $this->domain?->getTitle()));
        }
        if ('' === $title) {
            $title = self::$titleSchemeDefault;
        }

        $title = str_replace('%T', (string) $this->article?->getValue('name'), $title);
        $title = str_replace('%SN', Core::getServerName(), $title);

        return $this->cleanString($title);
    }

    public function getDescription(): string
    {
        return $this->cleanString((string) $this->article?->getValue(self::$metaDescriptionField));
    }

    public function getImage(): string
    {
        return $this->cleanString((string) $this->article?->getValue(self::$metaImageField));
    }

    public function getCanonicalUrl(): string
    {
        $canonicalUrl = trim((string) $this->article?->getValue(self::$metaCanonicalUrlField));
        if ('' === $canonicalUrl && null !== $this->article) {
            $canonicalUrl = YRewrite::getFullUrlByArticleId($this->article->id, $this->article->clangId);
        }
        return (string) Extension::dispatch(new ExtensionPoint('YREWRITE_CANONICAL_URL', $canonicalUrl, ['article' => $this->article]));
    }

    /** @return array<string, string> */
    public function getHrefLangs(): array
    {
        $currentMountId = $this->domain?->getMountId();
        $langDomains = [];

        if ($this->domain?->isStartClangAuto() && $this->domain->getStartId() === Article::getCurrentId()) {
            $langDomains['x-default'] = $this->domain->getUrl();
        }

        foreach (YRewrite::getDomains() as $domain) {
            if ($currentMountId === $domain->getMountId()) {
                foreach ($domain->getClangs() as $clang) {
                    if ($lang = Language::get($clang)) {
                        $article = Article::getCurrent($clang);
                        if ($article && $article->isOnline() && $lang->isOnline()) {
                            $langDomains[$lang->code] = YRewrite::getFullUrlByArticleId($article->id, $lang->id);
                        }
                    }
                }
            }
        }

        /** @var array<string, string> $result */
        $result = Extension::dispatch(new ExtensionPoint('YREWRITE_HREFLANG_TAGS', $langDomains, ['article' => $this->article]));
        return $result;
    }

    public function cleanString(?string $str): string
    {
        return str_replace(["\n", "\r"], [' ', ''], $str ?? '');
    }

    // ----- global functions

    public function sendRobotsTxt(string $domain = ''): never
    {
        if ('' === $domain) {
            $domain = (string) YRewrite::getHost();
        }

        header('Content-Type: text/plain');
        $content = 'Sitemap: ' . YRewrite::getFullPath('sitemap.xml') . "\n\n";

        if ($d = YRewrite::getDomainByName($domain)) {
            $robots = $d->getRobots();
            $content .= '' !== $robots ? $robots : self::$robotsDefault;
        }

        echo $content;
        exit;
    }

    public function sendSitemap(string $domain = ''): never
    {
        $domains = YRewrite::getDomains();

        if ('' === $domain) {
            $domain = (string) YRewrite::getHost();
        }

        $sitemap = [];

        if (YRewrite::getDomainByName($domain) || 1 === count($domains)) {
            if (1 === count($domains)) {
                $domainObj = YRewrite::getDefaultDomain();
            } else {
                $domainObj = YRewrite::getDomainByName($domain);
            }

            $domainArticleId = $domainObj->getStartId();
            $paths = 0;
            if ($dai = Article::get($domainArticleId)) {
                $paths = count($dai->getParentTree());
            }

            foreach (YRewrite::getPathsByDomain($domainObj->getName()) as $articleId => $path) {
                foreach ($domainObj->getClangs() as $clangId) {
                    if (!isset($path[$clangId]) || !Language::get($clangId)?->isOnline()) {
                        continue;
                    }

                    $article = Article::get($articleId, $clangId);
                    if (!$article) {
                        continue;
                    }
                    $index = $article->getValue(self::$metaIndexField) ?? self::$indexSettingDefault;

                    if (
                        $article->isPermitted()
                        && (1 == $index || ($article->isOnline() && 0 == $index))
                        && ($articleId !== $domainObj->getNotfoundId() || $articleId === $domainObj->getStartId())
                    ) {
                        $changefreq = $article->getValue(self::$metaChangefreqField);
                        if (!in_array($changefreq, self::$changefreq, true)) {
                            $changefreq = self::$changefreqDefault;
                        }

                        $priority = $article->getValue(self::$metaPriorityField);

                        if (!in_array($priority, self::$priority, true)) {
                            $articlePaths = count($article->getParentTree());
                            $prio = $articlePaths - $paths - 1;
                            if ($prio < 0) {
                                $prio = 0;
                            }

                            $priority = self::$priority[$prio] ?? self::$priorityDefault;
                        }

                        $sitemapEntry =
                            "\n" . '<url>' .
                            "\n\t" . '<loc>' . YRewrite::getFullPath($path[$clangId]) . '</loc>' .
                            "\n\t" . '<lastmod>' . date(DATE_W3C, $article->updateDate) . '</lastmod>';
                        if ($article->getValue(self::$metaImageField)) {
                            $media = Media::get((string) $article->getValue(self::$metaImageField));
                            if ($media) {
                                $sitemapEntry .= "\n\t" . '<image:image>' .
                                    "\n\t\t" . '<image:loc>' . rtrim(YRewrite::getDomainByArticleId($article->id)->getUrl(), '/') . MediaManager::getUrl('yrewrite_seo_image', $media->fileName) . '</image:loc>' .
                                    ('' !== $media->title ? "\n\t\t" . '<image:title>' . escape($media->title) . '</image:title>' : '') .
                                    "\n\t" . '</image:image>';
                            }
                        }
                        $sitemapEntry .= "\n\t" . '<changefreq>' . $changefreq . '</changefreq>' .
                            "\n\t" . '<priority>' . $priority . '</priority>' .
                            "\n" . '</url>';
                        $sitemap[] = $sitemapEntry;
                    }
                }
            }
            $sitemap = Extension::dispatch(new ExtensionPoint('YREWRITE_DOMAIN_SITEMAP', $sitemap, ['domain' => $domainObj]));
        }
        $sitemap = Extension::dispatch(new ExtensionPoint('YREWRITE_SITEMAP', $sitemap));

        Response::cleanOutputBuffers();
        header('Content-Type: application/xml');
        $content = '<?xml version="1.0" encoding="UTF-8"?>';
        $content .= "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
        $content .= implode("\n", $sitemap);
        $content .= "\n" . '</urlset>';
        echo $content;
        exit;
    }
}
