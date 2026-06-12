<?php

namespace Yakamara\YRewrite;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Util\Str;

use function count;

use const DIRECTORY_SEPARATOR;

/**
 * Main rewrite API.
 *
 * Port of REDAXO 5 `rex_yrewrite`.
 */
class YRewrite
{
    /** @var array<int, array<int, Domain>> */
    private static array $domainsByMountId = [];

    /** @var array<string, Domain> */
    private static array $domainsByName = [];

    /** @var array<int, Domain> */
    private static array $domainsById = [];

    /** @var array<string, array{domain: Domain, clang_start: int}> */
    private static array $aliasDomains = [];

    private static string $pathfile = '';
    private static string $configfile = '';

    /** @var array{paths?: array<string, array<int, array<int, string>>>, redirections?: array<string, array<int, array<int, array<string, mixed>>>>} */
    public static array $paths = [];

    private static ?Scheme $scheme = null;

    public static function init(): void
    {
        if (null === self::$scheme) {
            self::setScheme(new Scheme());
        }

        self::$domainsByMountId = [];
        self::$domainsByName = [];
        self::$aliasDomains = [];
        self::$paths = [];

        self::addDomain(new Domain('default', null, self::getSubPath(), 0, Article::getSiteStartArticleId(), Article::getNotfoundArticleId(), Language::getAllIds(), Language::getStartId(), '', '', '', Language::count() <= 1));

        self::$pathfile = Path::addonCache('yrewrite', 'pathlist.json');
        self::$configfile = Path::addonCache('yrewrite', 'config.php');
        self::readConfig();
        self::readPathFile();
    }

    public static function getScheme(): Scheme
    {
        return self::$scheme ??= new Scheme();
    }

    public static function setScheme(Scheme $scheme): void
    {
        self::$scheme = $scheme;
    }

    // ----- domain

    public static function addDomain(Domain $domain): void
    {
        foreach ($domain->getClangs() as $clang) {
            self::$domainsByMountId[$domain->getMountId()][$clang] = $domain;
        }
        self::$domainsByName[$domain->getName()] = $domain;

        if ($id = $domain->getId()) {
            self::$domainsById[$id] = $domain;
        }
    }

    public static function addAliasDomain(string $fromDomain, int $toDomainId, int $clangStart = 0): void
    {
        if (isset(self::$domainsById[$toDomainId])) {
            self::$aliasDomains[$fromDomain] = [
                'domain' => self::$domainsById[$toDomainId],
                'clang_start' => $clangStart,
            ];
        }
    }

    /** @return array<string, Domain> */
    public static function getDomains(): array
    {
        return self::$domainsByName;
    }

    public static function getDomainByName(string $name): ?Domain
    {
        return self::$domainsByName[$name] ?? null;
    }

    public static function getDomainById(int $id): ?Domain
    {
        return self::$domainsById[$id] ?? null;
    }

    public static function getDefaultDomain(): Domain
    {
        return self::$domainsByName['default'];
    }

    // ----- article

    public static function getCurrentDomain(): ?Domain
    {
        $articleId = Article::getCurrent()?->id;
        $clangId = Language::getCurrentId();

        foreach (self::$domainsByName as $name => $domain) {
            if (isset(self::$paths['paths'][$name][$articleId][$clangId])) {
                return $domain;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function getFullUrlByArticleId(?int $articleId = null, ?int $clang = null, array $parameters = [], string $separator = '&amp;'): string
    {
        $params = [];
        $params['id'] = $articleId ?: Article::getCurrentId();
        $params['clang'] = $clang ?: Language::getCurrentId();
        $params['params'] = $parameters;
        $params['separator'] = $separator;

        return self::rewrite($params, [], true);
    }

    public static function getDomainByArticleId(int $aid, ?int $clang = null): Domain
    {
        $clang = $clang ?: Language::getCurrentId();

        foreach (self::$domainsByName as $name => $domain) {
            if (isset(self::$paths['paths'][$name][$aid][$clang]) || isset(self::$paths['redirections'][$name][$aid][$clang])) {
                return $domain;
            }
        }
        return self::$domainsByName['default'];
    }

    /** @return array<int, int>|false */
    public static function getArticleIdByUrl(Domain|string $domain, string $url): array|false
    {
        if ($domain instanceof Domain) {
            $domain = $domain->getName();
        }
        foreach (self::$paths['paths'][$domain] ?? [] as $cArticleId => $cO) {
            foreach ($cO as $cClang => $cUrl) {
                if ($url === $cUrl) {
                    return [$cArticleId => $cClang];
                }
            }
        }
        return false;
    }

    public static function isDomainStartArticle(int $aid, ?int $clang = null): bool
    {
        $clang = $clang ?: Language::getCurrentId();

        foreach (self::$domainsByMountId as $d) {
            if (isset($d[$clang]) && $d[$clang]->getStartId() === $aid) {
                return true;
            }
        }

        return false;
    }

    public static function isDomainMountpoint(int $aid, ?int $clang = null): bool
    {
        $clang = $clang ?: Language::getCurrentId();

        return isset(self::$domainsByMountId[$aid][$clang]);
    }

    public static function isInCurrentDomain(int $aid): bool
    {
        return self::getDomainByArticleId($aid)->getName() === self::getCurrentDomain()?->getName();
    }

    // ----- url

    /** @return array<int, array<int, string>> */
    public static function getPathsByDomain(string $domain): array
    {
        return self::$paths['paths'][$domain] ?? [];
    }

    public static function prepare(): bool
    {
        if (Core::isFrontend() && 'get' === Request::requestMethod() && !Request::get('rex-api-call') && $articleId = Request::get('article_id', 'int')) {
            $params = $_GET;
            $article = Article::get((int) $params['article_id'], (int) ($params['clang'] ?? 0) ?: Language::getCurrentId());
            if ($article instanceof Article) {
                unset($params['article_id'], $params['clang']);
                $url = self::getFullUrlByArticleId($articleId, null, $params, '&');
                Response::sendRedirect($url, Response::HTTP_MOVED_PERMANENTLY);
            }
        }

        if ($articleId = Request::request('article_id', 'int')) {
            $url = Url::article($articleId);
        } else {
            if (!isset($_SERVER['REQUEST_URI'])) {
                $_SERVER['REQUEST_URI'] = substr((string) $_SERVER['PHP_SELF'], 1);
                if (!empty($_SERVER['QUERY_STRING'])) {
                    $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
                }
            }

            $url = urldecode((string) $_SERVER['REQUEST_URI']);
        }

        $resolver = new PathResolver(self::$domainsByName, self::$domainsByMountId, self::$aliasDomains, self::$paths['paths'] ?? [], self::$paths['redirections'] ?? []);
        $resolver->resolve($url);

        return true;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $yparams
     */
    public static function rewrite(array $params = [], array $yparams = [], bool $fullpath = false): string
    {
        // Url wurde von einer anderen Extension bereits gesetzt
        if (isset($params['subject']) && '' != $params['subject']) {
            return (string) $params['subject'];
        }

        $id = $params['id'];
        $clang = $params['clang'];

        foreach (self::$paths['redirections'] ?? [] as $redirections) {
            if (isset($redirections[$id][$clang]['url'])) {
                return $redirections[$id][$clang]['url'];
            }

            if (isset($redirections[$id][$clang])) {
                $params['id'] = $redirections[$id][$clang]['id'];
                $params['clang'] = $redirections[$id][$clang]['clang'];
                return self::rewrite($params, $yparams, $fullpath);
            }
        }

        $domainName = self::getHost();

        $path = '';

        // same domain id check
        if (!$fullpath && isset(self::$paths['paths'][$domainName][$id][$clang])) {
            $domain = self::getDomainByName($domainName);
            $path = $domain->getPath() . self::$paths['paths'][$domainName][$id][$clang];
        }

        if ('' === $path) {
            foreach ((array) (self::$paths['paths'] ?? []) as $iDomain => $iId) {
                if (isset(self::$paths['paths'][$iDomain][$id][$clang])) {
                    $domain = self::getDomainByName($iDomain);
                    $path = 'default' === $domain->getName() ? $domain->getPath() : $domain->getUrl();
                    $path .= self::$paths['paths'][$iDomain][$id][$clang];
                    break;
                }
            }
        }

        // params
        $urlparams = '';
        if (isset($params['params']) && $params['params']) {
            $urlparams = self::buildQuery($params['params'], $params['separator'] ?? '&amp;');
        }

        return $path . ($urlparams ? '?' . $urlparams : '');
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function rewriteMedia(array $params): string
    {
        $buster = '';
        if (isset($params['buster']) && $params['buster']) {
            $buster = '?buster=' . $params['buster'];
        }

        return Url::frontend('media/' . $params['type'] . '/' . $params['file'] . $buster);
    }

    /**
     * Updates or generates the domain-path file list.
     *
     * @param array<string, mixed> $params
     */
    public static function generatePathFile(array $params): void
    {
        $oldPaths = self::$paths;

        $generator = new PathGenerator(self::getScheme(), self::$domainsByMountId, self::$paths['paths'] ?? [], self::$paths['redirections'] ?? []);

        $ep = $params['extension_point'] ?? '';
        switch ($ep) {
            // clang and id specific update
            case 'CAT_DELETED':
            case 'ART_DELETED':
                $generator->removeArticle($params['id'], $params['clang']);

                if ($params['parent_id'] > 0) {
                    if ($parent = Article::get($params['parent_id'], $params['clang'])) {
                        $generator->generate($parent);
                    }
                }

                break;
            case 'CAT_MOVED':
            case 'ART_MOVED':
                $clangId = $params['clang'] ?? $params['clang_id'];

                $generator->removeArticle($params['id'], $clangId);
                if ($art = Article::get($params['id'], $params['clang'])) {
                    $generator->generate($art);
                }

                break;
            case 'CAT_ADDED':
            case 'CAT_UPDATED':
            case 'CAT_STATUS':
            case 'CAT_TO_ART':
            case 'ART_ADDED':
            case 'ART_COPIED':
            case 'ART_UPDATED':
            case 'ART_META_UPDATED':
            case 'ART_STATUS':
            case 'ART_TO_STARTARTICLE':
            case 'ART_TO_CAT':
                ArticleCache::delete($params['id']);

                if ($art = Article::get($params['id'], $params['clang'])) {
                    $generator->generate($art);
                }

                break;
                // update all
            case 'CLANG_DELETED':
            case 'CLANG_ADDED':
            case 'CLANG_UPDATED':
            default:
                $generator->generateAll();
                break;
        }

        self::$paths = [
            'paths' => $generator->getPaths(),
            'redirections' => $generator->getRedirections(),
        ];

        $sql = Sql::factory()
            ->setTable(Core::getTable('yrewrite_forward'));

        // Alte Einträge ausschalten
        $sql->setWhere('expiry_date > "0000-00-00" AND expiry_date < :date', ['date' => date('Y-m-d')]);
        $sql->setValue('status', 0);
        $sql->update();

        // vergleicht alle Einträge aus old_paths mit der aktuellen path Liste.
        if ($oldPaths) {
            foreach ($oldPaths['paths'] ?? [] as $domainName => $oldArticlePaths) {
                $domain = self::getDomainByName($domainName);
                if (!$domain) {
                    continue;
                }
                $domainId = $domain->getId();
                $expiryDate = null;
                if ($domain->getAutoRedirectDays()) {
                    $expiryDate = date('Y-m-d', time() + $domain->getAutoRedirectDays() * 24 * 60 * 60);
                }

                // Autoredirect nicht setzen, wenn autoredirect für diese Domain nicht eingeschaltet ist
                if (!$domain->getAutoRedirect()) {
                    continue;
                }
                foreach ($oldArticlePaths as $artId => $oldArtPaths) {
                    foreach (Language::getAllIds() as $clangId) {
                        if (!isset(self::$paths['paths'][$domainName][$artId][$clangId]) || !isset($oldArtPaths[$clangId])) {
                            continue;
                        }

                        // Wenn es eine Abweichung im Pfad gibt, wird ein neuer Eintrag eingefügt
                        if (self::$paths['paths'][$domainName][$artId][$clangId] !== $oldArtPaths[$clangId]) {
                            if ('CAT_DELETED' === $ep || 'ART_DELETED' === $ep) {
                                $sql->setTable(Core::getTable('yrewrite_forward'));
                                $sql->setWhere(['article_id' => $artId]);
                                $sql->delete();
                            } elseif ('CLANG_DELETED' === $ep) {
                                $sql->setTable(Core::getTable('yrewrite_forward'));
                                $sql->setWhere(['clang' => $clangId]);
                                $sql->delete();
                            } elseif (in_array($ep, ['CAT_MOVED', 'CAT_UPDATED', 'ART_MOVED', 'ART_UPDATED', 'ART_META_UPDATED'], true)) {
                                $sql->setTable(Core::getTable('yrewrite_forward'));
                                $sql->setValues([
                                    'article_id' => $artId,
                                    'clang' => $clangId,
                                    'type' => 'article',
                                    'domain_id' => $domainId,
                                    'url' => trim($oldArtPaths[$clangId], '/'),
                                    'movetype' => '301',
                                    'status' => 1,
                                    'expiry_date' => $expiryDate,
                                ]);
                                $sql->insert();

                                // alte Redirects löschen wenn die URL der neuen URL des Artikels entspricht
                                $newUrl = Url::article($artId, $clangId);
                                $cleanUrl = trim(substr($newUrl, strpos($newUrl, $domainName) + strlen($domainName)), '/');
                                $sql->setTable(Core::getTable('yrewrite_forward'));
                                $sql->setValues([]);
                                $sql->setWhere(['url' => $cleanUrl]);
                                $sql->delete();
                            }
                        }
                    }
                }
            }
        }

        Forward::init();
        Forward::generatePathFile();
        File::putCache(self::$pathfile, self::$paths);
    }

    // ----- func

    public static function checkUrl(string $url): bool
    {
        return (bool) preg_match('/^[%_\.+\-\/a-zA-Z0-9]+$/', $url);
    }

    // ----- generate

    public static function generateConfig(): void
    {
        $content = '<?php ' . "\n";

        $gc = Sql::factory();

        $domains = $gc->getArray('select * from ' . Core::getTable('yrewrite_domain') . ' order by mount_id, clangs');
        foreach ($domains as $domain) {
            if (!$domain['domain']) {
                continue;
            }

            $name = (string) $domain['domain'];
            if (!str_contains($name, '//')) {
                $name = '//' . $name;
            }
            $parts = parse_url($name);
            $name = $parts['host'];
            if (isset($parts['port'])) {
                $name .= ':' . $parts['port'];
            }
            $path = '/';
            if (isset($parts['path'])) {
                $path = rtrim($parts['path'], '/') . '/';
            }

            // clangs may be stored pipe-delimited (|1|2|, core Form) or comma-separated (legacy)
            $clangs = trim((string) $domain['clangs'], '|, ');
            $clangIds = '' === $clangs ? [] : array_map('intval', (array) preg_split('/[|,]+/', $clangs));

            if ($domain['start_id'] > 0 && $domain['notfound_id'] > 0) {
                $content .= "\n" . '\\Yakamara\\YRewrite\\YRewrite::addDomain(new \\Yakamara\\YRewrite\\Domain('
                    . '"' . $name . '", '
                    . (isset($parts['scheme']) ? '"' . $parts['scheme'] . '"' : 'null') . ', '
                    . '"' . $path . '", '
                    . (int) $domain['mount_id'] . ', '
                    . (int) $domain['start_id'] . ', '
                    . (int) $domain['notfound_id'] . ', '
                    . ($clangIds ? '[' . implode(',', $clangIds) . ']' : 'null') . ', '
                    . (int) $domain['clang_start'] . ', '
                    . '"' . self::escapePhp($domain['title_scheme']) . '", '
                    . '"' . self::escapePhp($domain['description']) . '", '
                    . '"' . self::escapePhp($domain['robots']) . '", '
                    . ($domain['clang_start_hidden'] ? 'true' : 'false') . ','
                    . (int) $domain['id'] . ','
                    . ($domain['auto_redirect'] ? 'true' : 'false') . ','
                    . (int) $domain['auto_redirect_days'] . ','
                    . ($domain['clang_start_auto'] ? 'true' : 'false')
                    . '));';
            }
        }

        $aliasDomains = $gc->getArray('select * from ' . Core::getTable('yrewrite_alias') . ' order by domain_id');
        foreach ($aliasDomains as $domain) {
            if (!$domain['alias_domain'] || !$domain['domain_id']) {
                continue;
            }

            $content .= "\n" . '\\Yakamara\\YRewrite\\YRewrite::addAliasDomain("' . $domain['alias_domain'] . '", ' . ((int) $domain['domain_id']) . ', ' . (int) $domain['clang_start'] . ');';
        }

        File::put(self::$configfile, $content);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(self::$configfile);
        }
    }

    public static function readConfig(): void
    {
        if (!file_exists(self::$configfile)) {
            self::generateConfig();
        }
        include self::$configfile;
    }

    public static function readPathFile(): void
    {
        if (!file_exists(self::$pathfile)) {
            self::generatePathFile([]);
        }
        self::$paths = File::getCache(self::$pathfile);
    }

    public static function copyHtaccess(): bool
    {
        return File::copy(Path::addon('yrewrite', 'setup/.htaccess'), Path::frontend('.htaccess'));
    }

    public static function isHttps(): bool
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']) {
            return true;
        }
        return (isset($_SERVER['SERVER_PORT']) && 443 === (int) $_SERVER['SERVER_PORT']) || (isset($_SERVER['HTTPS']) && 'off' !== strtolower((string) $_SERVER['HTTPS']));
    }

    public static function deleteCache(): void
    {
        Addon::require('yrewrite')->clearCache();
    }

    public static function getFullPath(string $link = ''): string
    {
        $domain = self::getHost();
        $http = self::isHttps() ? 'https://' : 'http://';
        $subfolder = self::getSubPath();
        return $http . $domain . $subfolder . $link;
    }

    public static function getHost(): ?string
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            return (string) $_SERVER['HTTP_X_FORWARDED_HOST'];
        }
        return $_SERVER['HTTP_HOST'] ?? null;
    }

    private static function getSubPath(): string
    {
        $path = dirname((string) $_SERVER['SCRIPT_NAME']);
        if (Core::isBackend()) {
            $path = dirname($path);
        }

        return rtrim($path, DIRECTORY_SEPARATOR) . '/';
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function buildQuery(array $params, string $separator): string
    {
        return str_replace('&', $separator, Str::buildQuery($params));
    }

    private static function escapePhp(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES);
    }
}
