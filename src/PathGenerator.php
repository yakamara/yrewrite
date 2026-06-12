<?php

namespace Yakamara\YRewrite;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructureElement;
use Redaxo\Core\Language\Language;

use function is_string;

/**
 * Generates the path- and redirection-lists for all domains.
 *
 * Port of REDAXO 5 `rex_yrewrite_path_generator`.
 *
 * @internal
 */
class PathGenerator
{
    /**
     * @param array<int, array<int, Domain>> $domains
     * @param array<string, array<int, array<int, string>>> $paths
     * @param array<string, array<int, array<int, array<string, mixed>>>> $redirections
     */
    public function __construct(
        private Scheme $scheme,
        private array $domains,
        private array $paths,
        private array $redirections,
    ) {}

    /** @return array<string, array<int, array<int, string>>> */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /** @return array<string, array<int, array<int, array<string, mixed>>>> */
    public function getRedirections(): array
    {
        return $this->redirections;
    }

    public function generateAll(): void
    {
        $this->paths = [];
        $this->redirections = [];

        foreach (Language::getAllIds() as $clangId) {
            $domain = $this->domains[0][$clangId];
            $path = $this->scheme->getClang($clangId, $domain);

            foreach (Category::getRootCategories(false, $clangId) as $cat) {
                $this->generatePaths($domain, $path, $cat);
            }

            foreach (Article::getRootArticles(false, $clangId) as $art) {
                $this->setPath($art, $domain, $path);
            }
        }
    }

    public function generate(Article $article): void
    {
        $clangId = $article->clangId;

        $domain = $this->domains[0][$clangId];
        $path = $this->scheme->getClang($clangId, $domain);

        $tree = $article->getParentTree();
        $category = null;

        if ($article->isStartArticle()) {
            $category = array_pop($tree);
        }

        foreach ($tree as $parent) {
            $path = $this->scheme->appendCategory($path, $parent, $domain);

            [$domain, $path] = $this->setDomain($parent, $domain, $path);

            $parentArticle = Article::get($parent->id, $clangId);
            if ($parentArticle) {
                $this->setPath($parentArticle, $domain, $path);
            }
        }

        if ($article->isStartArticle() && $category instanceof Category) {
            $this->generatePaths($domain, $path, $category);
        } else {
            $this->setPath($article, $domain, $path);
        }
    }

    public function removeArticle(int $articleId, int $clangId): void
    {
        foreach ($this->paths as $domain => $c) {
            unset($this->paths[$domain][$articleId][$clangId]);

            if (empty($this->paths[$domain][$articleId])) {
                unset($this->paths[$domain][$articleId]);
            }
        }

        foreach ($this->redirections as $domain => $_) {
            unset($this->redirections[$domain][$articleId][$clangId]);

            if (empty($this->redirections[$domain][$articleId])) {
                unset($this->redirections[$domain][$articleId]);
            }
            if (empty($this->redirections[$domain])) {
                unset($this->redirections[$domain]);
            }
        }
    }

    /** @return array{Domain, string} */
    private function setDomain(StructureElement $element, Domain $domain, string $path): array
    {
        $id = $element->id;
        $clang = $element->clangId;

        if (isset($this->domains[$id][$clang])) {
            $domain = $this->domains[$id][$clang];
            $path = $this->scheme->getClang($clang, $domain);
        }

        return [$domain, $path];
    }

    private function setPath(Article $article, Domain $domain, string $path): void
    {
        [$domain, $path] = $this->setDomain($article, $domain, $path);

        $domainName = $domain->getName();
        $articleId = $article->id;
        $clangId = $article->clangId;

        $url = $this->scheme->getCustomUrl($article, $domain);

        if (!is_string($url)) {
            $url = $this->scheme->appendArticle($path, $article, $domain);
        }

        $url = ltrim($url, '/');

        $urlType = $article->getValue('yrewrite_url_type');

        if ('REDIRECTION_EXTERNAL' === $urlType) {
            $this->redirections[$domainName][$articleId][$clangId] = [
                'url' => $article->getValue('yrewrite_redirection'),
                'path' => $url,
            ];

            unset($this->paths[$domainName][$articleId][$clangId]);

            return;
        }

        if ('REDIRECTION_INTERNAL' === $urlType) {
            $redirection = Article::get((int) $article->getValue('yrewrite_redirection'), $clangId);
        } else {
            $redirection = $this->scheme->getRedirection($article, $domain);
        }

        if ($redirection instanceof StructureElement) {
            $this->redirections[$domainName][$articleId][$clangId] = [
                'id' => $redirection->id,
                'clang' => $redirection->clangId,
                'path' => $url,
            ];

            unset($this->paths[$domainName][$articleId][$clangId]);

            return;
        }

        $this->paths[$domainName][$articleId][$clangId] = $url;

        unset($this->redirections[$domainName][$articleId][$clangId]);
    }

    private function generatePaths(Domain $domain, string $path, Category $category): void
    {
        $path = $this->scheme->appendCategory($path, $category, $domain);

        [$domain, $path] = $this->setDomain($category, $domain, $path);

        foreach ($category->getChildren() as $child) {
            $this->generatePaths($domain, $path, $child);
        }

        foreach ($category->getArticles() as $article) {
            $this->setPath($article, $domain, $path);
        }
    }
}
