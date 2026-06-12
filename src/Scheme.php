<?php

namespace Yakamara\YRewrite;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructureElement;
use Redaxo\Core\Language\Language;

use function is_string;

/**
 * Builds the URL path scheme for articles and categories.
 *
 * Port of REDAXO 5 `rex_yrewrite_scheme`. May be subclassed and registered via
 * {@see YRewrite::setScheme()} to customize URL generation.
 */
class Scheme
{
    protected string $suffix = '/';

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function setSuffix(string $suffix): void
    {
        $this->suffix = $suffix;
    }

    public function getClang(int $clang, Domain $domain): string
    {
        if ($domain->isStartClangHidden() && $clang === $domain->getStartClang()) {
            return '';
        }

        return '/' . $this->normalize(Language::require($clang)->code, $clang);
    }

    public function appendCategory(string $path, Category $cat, Domain $domain): string
    {
        return $path . '/' . $this->normalize($cat->name, $cat->clangId);
    }

    public function appendArticle(string $path, Article $art, Domain $domain): string
    {
        if ($art->isStartArticle() && $domain->getMountId() !== $art->id) {
            return $path . $this->suffix;
        }
        return $path . '/' . $this->normalize($art->name, $art->clangId) . $this->suffix;
    }

    public function getCustomUrl(Article $art, Domain $domain): string|false
    {
        if ($domain->getStartId() === $art->id) {
            if (!$domain->isStartClangAuto() && $domain->getStartClang() === $art->clangId) {
                return '/';
            }
            return $this->getClang($art->clangId, $domain) . $this->suffix;
        }
        if ($url = (string) $art->getValue('yrewrite_url')) {
            return $url;
        }
        return false;
    }

    public function getRedirection(Article $art, Domain $domain): StructureElement|false
    {
        return false;
    }

    /** @return null|string|list<string> */
    public function getAlternativeCandidates(string $path, Domain $domain): array|string|null
    {
        if (str_ends_with($path, '/')) {
            return substr($path, 0, -1);
        }
        if ('' !== $this->suffix && !str_ends_with($path, $this->suffix)) {
            return $path . $this->suffix;
        }

        return null;
    }

    public function normalize(string $string, int $clang = 1): string
    {
        if (YRewriteAddon::get('yrewrite')?->getConfig('unicode_urls')) {
            $string = str_replace(["'", '’', 'ʻ'], '', $string);
            $string = (string) preg_replace('/[^\p{L&}\p{Lo}\p{M}\p{N}\p{Sc}]+/u', '-', $string);
            return mb_strtolower(trim($string, '-'));
        }

        $string = str_replace(
            ['Ä',  'Ö',  'Ü',  'ä',  'ö',  'ü',  'ß',  'À', 'à', 'Á', 'á', 'ç', 'È', 'è', 'É', 'é', 'ë', 'Ì', 'ì', 'Í', 'í', 'Ï', 'ï', 'Ò', 'ò', 'Ó', 'ó', 'ô', 'Ù', 'ù', 'Ú', 'ú', 'Č', 'č', 'Ł', 'ł', 'ž', '/', '®', '©', '™'],
            ['Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss', 'A', 'a', 'A', 'a', 'c', 'E', 'e', 'E', 'e', 'e', 'I', 'i', 'I', 'i', 'I', 'i', 'O', 'o', 'O', 'o', 'o', 'U', 'u', 'U', 'u', 'C', 'c', 'L', 'l', 'z', '-', '',  '',  ''],
            $string,
        );
        $string = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        $string = (string) preg_replace('/[^\w -]+/', '', $string);
        $string = strtolower(trim($string));
        $string = urlencode($string);
        return (string) preg_replace('/[+-]+/', '-', $string);
    }
}
