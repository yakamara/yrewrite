<?php

namespace Yakamara\YRewrite;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Environment;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;

/**
 * Resolves an incoming request URL to a domain, article and language.
 *
 * Port of REDAXO 5 `rex_yrewrite_path_resolver`.
 *
 * @internal
 */
class PathResolver
{
    /**
     * @param array<string, Domain> $domainsByName
     * @param array<int, array<int, Domain>> $domainsByMountId
     * @param array<string, array{domain: Domain, clang_start: int}> $aliasDomains
     * @param array<string, array<int, array<int, string>>> $paths
     * @param array<string, array<int, array<int, array<string, mixed>>>> $redirections
     */
    public function __construct(
        private array $domainsByName,
        private array $domainsByMountId,
        private array $aliasDomains,
        private array $paths,
        private array $redirections,
    ) {}

    public function resolve(string $url): void
    {
        [$url, $params] = $this->normalizeAndSplitUrl($url);

        $host = YRewrite::getHost();

        if (null === $host) {
            $domain = $this->domainsByName['default'];
        } else {
            $domain = $this->resolveDomain($host, $url, $params);
        }

        $currentScheme = YRewrite::isHttps() ? 'https' : 'http';
        $domainScheme = $domain->getScheme();
        if ($domainScheme && $domainScheme !== $currentScheme && !$this->coreForcesScheme()) {
            $this->redirect($domainScheme . '://' . $host, $url, $params);
        }

        if (Core::isBackend()) {
            return;
        }

        if (str_starts_with($url, $domain->getPath())) {
            $url = substr($url, strlen($domain->getPath()));
        }

        $url = ltrim($url, '/');

        if ('' === $url && $domain->isStartClangAuto()) {
            $startClang = $this->resolveAutoStartClang($domain);

            $hreflangs = [];
            foreach ((new Seo())->getHrefLangs() as $lang => $href) {
                $hreflangs[] = "<$href>;  rel=\"alternate\"; hreflang=\"$lang\"";
            }
            header('Link: ' . implode(', ', $hreflangs));

            $this->redirect($currentScheme . '://' . $host, Url::article($domain->getStartId(), $startClang), $params, Response::HTTP_MOVED_TEMPORARILY);
        }

        Core::setProperty('start_article_id', $domain->getStartId());
        Core::setProperty('notfound_article_id', $domain->getNotfoundId());

        // if no path -> startarticle
        if ('' === $url) {
            Core::setProperty('article_id', $domain->getStartId());
            Language::setCurrentId($domain->getStartClang());
            return;
        }

        // normal exact check
        if ($result = $this->searchPath($domain, $url)) {
            Core::setProperty('article_id', $result['article_id']);
            Language::setCurrentId($result['clang_id']);
            return;
        }

        $this->resolveRedirectionPath($domain, $url, $params);

        $candidates = YRewrite::getScheme()->getAlternativeCandidates($url, $domain);
        if ($candidates) {
            foreach ((array) $candidates as $candidate) {
                if ($this->searchPath($domain, $candidate)) {
                    $this->redirect($domain->getUrl(), $candidate, $params);
                }

                $this->resolveRedirectionPath($domain, $candidate, $params);
            }
        }

        /** @var string|array{article_id?: int, clang?: int} $epParams */
        $epParams = '';
        $epParams = Extension::dispatch(new ExtensionPoint('YREWRITE_PREPARE', $epParams, ['url' => $url, 'domain' => $domain]));

        if (is_array($epParams) && isset($epParams['article_id']) && $epParams['article_id'] > 0) {
            if (isset($epParams['clang']) && $epParams['clang'] > 0) {
                $clang = $epParams['clang'];
            } else {
                $clang = Language::getCurrentId();
            }

            if (Article::get($epParams['article_id'], $clang)) {
                Core::setProperty('article_id', (int) $epParams['article_id']);
                Language::setCurrentId($clang);
                return;
            }
        }

        // no article found -> domain not found article
        Core::setProperty('article_id', $domain->getNotfoundId());
        Language::setCurrentId($domain->getStartClang());
        Response::setStatus(Response::HTTP_NOT_FOUND);
        foreach ($this->paths[$domain->getName()][$domain->getStartId()] ?? [] as $clang => $clangUrl) {
            $lang = Language::get($clang);
            if ($clang !== $domain->getStartClang() && '' !== $clangUrl && $lang && $lang->isOnline() && str_starts_with($url, $clangUrl)) {
                Language::setCurrentId($clang);
                return;
            }
        }
    }

    /** @return array{string, string} */
    private function normalizeAndSplitUrl(string $url): array
    {
        // because of server differences
        if (!str_starts_with($url, '/')) {
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

    private function resolveDomain(string $host, string $url, string $params): Domain
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

        if (str_starts_with($host, 'www.')) {
            $alternativeHost = substr($host, 4);
        } else {
            $alternativeHost = 'www.' . $host;
        }
        if (isset($this->domainsByName[$alternativeHost])) {
            $this->redirect($this->domainsByName[$alternativeHost]->getUrl(), $url, $params);
        }

        // no domain, no alias, domain with root mountpoint ?
        $clang = Language::getCurrentId();
        if (isset($this->domainsByMountId[0][$clang])) {
            return $this->domainsByMountId[0][$clang];
        }

        // no root domain -> default
        return $this->domainsByName['default'];
    }

    private function resolveAutoStartClang(Domain $domain): int
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return $domain->getStartClang();
        }

        $startClang = null;
        $startClangFallback = $domain->getStartClang();

        foreach (explode(',', (string) $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $code) {
            $code = trim(explode(';', $code, 2)[0]);
            $code = str_replace('-', '_', mb_strtolower($code));

            foreach ($domain->getClangs() as $clangId) {
                $clang = Language::get($clangId);
                if (!$clang || !$clang->isOnline()) {
                    continue;
                }
                $clangCode = str_replace('-', '_', mb_strtolower($clang->code));
                if ($code === $clangCode) {
                    $startClang = $clang->id;
                    break 2;
                }

                if (str_starts_with($code, $clangCode . '_')) {
                    $startClangFallback = $clang->id;
                }
            }
        }

        return $startClang ?? $startClangFallback;
    }

    /** @return array{article_id: int, clang_id: int}|null */
    private function searchPath(Domain $domain, string $url): ?array
    {
        $clangIds = Language::getAllIds();

        foreach ($this->paths[$domain->getName()] ?? [] as $articleId => $clangPaths) {
            foreach ($clangIds as $clangId) {
                if (isset($clangPaths[$clangId]) && $clangPaths[$clangId] === $url) {
                    return ['article_id' => (int) $articleId, 'clang_id' => $clangId];
                }
            }
        }

        return null;
    }

    private function resolveRedirectionPath(Domain $domain, string $url, string $params): void
    {
        $clangIds = Language::getAllIds();

        foreach ($this->redirections[$domain->getName()] ?? [] as $clangRedirections) {
            foreach ($clangIds as $clangId) {
                $redirection = $clangRedirections[$clangId] ?? null;

                if (!isset($redirection['path']) || $redirection['path'] !== $url) {
                    continue;
                }

                if (isset($redirection['url'])) {
                    $url = $redirection['url'];
                } else {
                    $url = YRewrite::getFullUrlByArticleId($redirection['id'], $redirection['clang']) . $params;
                }

                header('HTTP/1.1 ' . Response::HTTP_MOVED_PERMANENTLY);
                header('Location: ' . $url);
                exit;
            }
        }
    }

    /** @return never */
    private function redirect(string $host, string $url, string $params, string $status = Response::HTTP_MOVED_PERMANENTLY): void
    {
        header('HTTP/1.1 ' . $status);
        header('Location: ' . rtrim($host, '/') . '/' . ltrim($url, '/') . $params);
        exit;
    }

    private function coreForcesScheme(): bool
    {
        $useHttps = Core::getProperty('use_https');

        return true === $useHttps
            || (Environment::Frontend === Core::getEnvironment() && 'frontend' === $useHttps)
            || (Environment::Backend === Core::getEnvironment() && 'backend' === $useHttps);
    }
}
