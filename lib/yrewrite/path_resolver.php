<?php

/**
 * @internal
 */
class rex_yrewrite_path_resolver
{
    /** @var array<string, rex_yrewrite_domain> */
    private $domainsByName;

    /** @var array<int, array<int, rex_yrewrite_domain>> */
    private $domainsByMountId;

    /** @var array<string, array{domain: rex_yrewrite_domain, clang_start: int}> */
    private $aliasDomains;

    /** @var array<string, array<int, array<int, string>>> */
    private $paths;

    /** @var array<string, array<int, array<int, array>>> */
    private $redirections;

    /**
     * @param array<string, rex_yrewrite_domain> $domainsByName
     * @param array<int, array<int, rex_yrewrite_domain>> $domainsByMountId
     * @param array<string, array{domain: rex_yrewrite_domain, clang_start: int}> $aliasDomains
     * @param array<string, array<int, array<int, string>>> $paths
     * @param array<string, array<int, array<int, array>>> $redirections
     */
    public function __construct(array $domainsByName, array $domainsByMountId, array $aliasDomains, array $paths, array $redirections)
    {
        $this->domainsByName = $domainsByName;
        $this->domainsByMountId = $domainsByMountId;
        $this->aliasDomains = $aliasDomains;
        $this->paths = $paths;
        $this->redirections = $redirections;
    }

    public function resolve(string $url): void
    {
        [$url, $params] = $this->normalizeAndSplitUrl($url);

        $host = rex_yrewrite::getHost();

        if (null === $host) {
            $domain = $this->domainsByName['default'];
        } else {
            $domain = $this->resolveDomain($host, $url, $params);
        }

        $currentScheme = rex_yrewrite::isHttps() ? 'https' : 'http';
        $domainScheme = $domain->getScheme();
        $coreUseHttps = rex::getProperty('use_https');
        if (
            $domainScheme && $domainScheme !== $currentScheme
            && true !== $coreUseHttps && rex::getEnvironment() !== $coreUseHttps
        ) {
            $this->redirect($domainScheme . '://' . $host, $url, $params);
        }

        if (rex::isBackend()) {
            return;
        }

        // force lowercase urls (seo duplicate content normalization)
        // media (/media/...) and asset (/assets/...) requests never reach this resolver:
        // media_manager intercepts rex_media_type on PACKAGES_INCLUDED (EARLY) and exits before
        // rex_yrewrite::prepare() runs, and existing files are served directly by the web server.
        // so media filenames (which are case-sensitive) are never lowercased here.
        if ($domain->isForceLowercase()) {
            // detect uppercase in a percent-encoding-safe way: strip %XX octets first, so encoded
            // umlauts (e.g. "%C3%BC") do not trigger a spurious redirect.
            $pathWithoutEscapes = preg_replace('/%[0-9A-Fa-f]{2}/', '', $url);
            if (preg_match('/[A-Z]/', $pathWithoutEscapes)) {
                // lowercase real ascii letters only, leave %XX escapes untouched.
                $lowerUrl = preg_replace_callback('/%[0-9A-Fa-f]{2}|[A-Z]+/', static function ($m) {
                    return '%' === $m[0][0] ? $m[0] : strtolower($m[0]);
                }, $url);
                $this->redirect($currentScheme . '://' . $host, $lowerUrl, $params);
            }
        }

        if (str_starts_with($url, $domain->getPath())) {
            $url = substr($url, strlen($domain->getPath()));
        }

        $url = ltrim($url, '/');

        if ('' === $url && $domain->isStartClangAuto()) {
            $startClang = $this->resolveAutoStartClang($domain);

            $hreflangs = [];
            foreach ((new rex_yrewrite_seo())->getHrefLangs() as $lang => $href) {
                $hreflangs[] = "<$href>;  rel=\"alternate\"; hreflang=\"$lang\"";
            }
            header('Link: ' . implode(', ', $hreflangs));

            $this->redirect($currentScheme . '://' . $host, rex_getUrl($domain->getStartId(), $startClang), $params, '302 Found');
        }

        $structureAddon = rex_addon::get('structure');
        $structureAddon->setProperty('start_article_id', $domain->getStartId());
        $structureAddon->setProperty('notfound_article_id', $domain->getNotfoundId());

        // if no path -> startarticle
        if ('' === $url) {
            $structureAddon->setProperty('article_id', $domain->getStartId());
            rex_clang::setCurrentId($domain->getStartClang());
            return;
        }

        // normal exact check
        if ($result = $this->searchPath($domain, $url)) {
            $structureAddon->setProperty('article_id', (int) $result['article_id']);
            rex_clang::setCurrentId($result['clang_id']);
            return;
        }

        $this->resolveRedirectionPath($domain, $url, $params);

        $candidates = rex_yrewrite::getScheme()->getAlternativeCandidates($url, $domain);
        if ($candidates) {
            foreach ((array) $candidates as $candidate) {
                if ($this->searchPath($domain, $candidate)) {
                    $this->redirect($domain->getUrl(), $candidate, $params);
                }

                $this->resolveRedirectionPath($domain, $candidate, $params);
            }
        }

        /** @var string|array{article_id?: int, clang?: int} $params */
        $params = '';
        $params = rex_extension::registerPoint(new rex_extension_point('YREWRITE_PREPARE', $params, ['url' => $url, 'domain' => $domain]));

        if (isset($params['article_id']) && $params['article_id'] > 0) {
            if (isset($params['clang']) && $params['clang'] > 0) {
                $clang = $params['clang'];
            } else {
                $clang = rex_clang::getCurrentId();
            }

            if (rex_article::get($params['article_id'], $clang)) {
                $structureAddon->setProperty('article_id', (int) $params['article_id']);
                rex_clang::setCurrentId($clang);
                return;
            }
        }

        // no article found -> domain not found article
        $structureAddon->setProperty('article_id', $domain->getNotfoundId());
        rex_clang::setCurrentId($domain->getStartClang());
        rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
        foreach ($this->paths[$domain->getName()][$domain->getStartId()] ?? [] as $clang => $clangUrl) {
            $rex_clang = rex_clang::get($clang);
            if ($clang != $domain->getStartClang() && '' != $clangUrl && $rex_clang->isOnline() && str_starts_with($url, $clangUrl)) {
                rex_clang::setCurrentId($clang);
                return;
            }
        }
    }

    /** @return array{string, string} */
    private function normalizeAndSplitUrl(string $url): array
    {
        // because of server differences
        if ('/' !== substr($url, 0, 1)) {
            $url = '/' . $url;
        }

        // delete params
        $params = '';
        if (($pos = strpos($url, '?')) !== false) {
            $params = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        // delete anker
        if (($pos = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $pos);
        }

        return [$url, $params];
    }

    private function resolveDomain(string $host, string $url, string $params): rex_yrewrite_domain
    {
        if (isset($this->domainsByName[$host])) {
            return $this->domainsByName[$host];
        }

        // check for aliases
        if (isset($this->aliasDomains[$host])) {
            $domain = $this->aliasDomains[$host]['domain'];

            if (!$url && isset($this->paths[$domain->getName()][$domain->getStartId()][$this->aliasDomains[$host]['clang_start']])) {
                $url = $this->paths[$domain->getName()][$domain->getStartId()][$this->aliasDomains[$host]['clang_start']];
            }
            // forward to original domain permanent move 301

            if (str_starts_with($url, $domain->getPath())) {
                $url = substr($url, strlen($domain->getPath()));
            }

            $this->redirect($domain->getUrl(), $url, $params);
        }

        if ('www.' === substr($host, 0, 4)) {
            $alternativeHost = substr($host, 4);
        } else {
            $alternativeHost = 'www.' . $host;
        }
        if (isset($this->domainsByName[$alternativeHost])) {
            $this->redirect($this->domainsByName[$alternativeHost]->getUrl(), $url, $params);
        }

        // no domain, no alias, domain with root mountpoint ?
        $clang = rex_clang::getCurrentId();
        if (isset($this->domainsByMountId[0][$clang])) {
            return $this->domainsByMountId[0][$clang];
        }

        // no root domain -> default
        return $this->domainsByName['default'];
    }

    private function resolveAutoStartClang(rex_yrewrite_domain $domain): int
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return $domain->getStartClang();
        }

        $startClang = null;
        $startClangFallback = $domain->getStartClang();

        foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $code) {
            $code = trim(explode(';', $code, 2)[0]);
            $code = str_replace('-', '_', mb_strtolower($code));

            foreach ($domain->getClangs() as $clangId) {
                $clang = rex_clang::get($clangId);
                if (!$clang->isOnline()) {
                    continue;
                }
                $clangCode = str_replace('-', '_', mb_strtolower($clang->getCode()));
                if ($code === $clangCode) {
                    $startClang = $clang->getId();
                    break 2;
                }

                if (str_starts_with($code, $clangCode . '_')) {
                    $startClangFallback = $clang->getId();
                }
            }
        }

        return $startClang ?? $startClangFallback;
    }

    /** @return array{article_id: int, clang_id: int}|null */
    private function searchPath(rex_yrewrite_domain $domain, string $url): ?array
    {
        $clangIds = rex_clang::getAllIds();

        foreach ($this->paths[$domain->getName()] ?? [] as $articleId => $clangPaths) {
            foreach ($clangIds as $clangId) {
                if (isset($clangPaths[$clangId]) && $clangPaths[$clangId] == $url) {
                    return ['article_id' => $articleId, 'clang_id' => $clangId];
                }
            }
        }

        return null;
    }

    private function resolveRedirectionPath(rex_yrewrite_domain $domain, string $url, string $params): void
    {
        $clangIds = rex_clang::getAllIds();

        foreach ($this->redirections[$domain->getName()] ?? [] as $clangRedirections) {
            foreach ($clangIds as $clangId) {
                $redirection = $clangRedirections[$clangId] ?? null;

                if (!isset($redirection['path']) || $redirection['path'] !== $url) {
                    continue;
                }

                if (isset($redirection['url'])) {
                    $url = $redirection['url'];
                } else {
                    $url = rex_yrewrite::getFullUrlByArticleId($redirection['id'], $redirection['clang']) . $params;
                }

                header('HTTP/1.1 ' . rex_response::HTTP_MOVED_PERMANENTLY);
                header('Location: ' . $url);
                exit;
            }
        }
    }

    /** @return never */
    private function redirect(string $host, string $url, string $params, string $status = rex_response::HTTP_MOVED_PERMANENTLY)
    {
        header('HTTP/1.1 ' . $status);
        header('Location: ' . rtrim($host, '/') . '/' . ltrim($url, '/') . $params);
        exit;
    }
}
