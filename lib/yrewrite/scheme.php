<?php

/**
 * YREWRITE Addon.
 *
 * @author gregor.harlan@redaxo.org
 *
 * @package redaxo\yrewrite
 */

class rex_yrewrite_scheme
{
    protected $suffix = '/';

    /**
     * @return string
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * @param string $suffix
     */
    public function setSuffix($suffix)
    {
        $this->suffix = $suffix;
    }

    /**
     * @param int $clang
     * @return string
     */
    public function getClang($clang, rex_yrewrite_domain $domain)
    {
        if ($domain->isStartClangHidden() && $clang == $domain->getStartClang()) {
            return '';
        }

        return '/' . $this->normalize(rex_clang::get($clang)->getCode(), $clang);
    }

    /**
     * @param string $path
     * @return string
     */
    public function appendCategory($path, rex_category $cat, rex_yrewrite_domain $domain)
    {
        return $path . '/' . $this->normalize($cat->getName(), $cat->getClangId());
    }

    /**
     * @param string $path
     * @return string
     */
    public function appendArticle($path, rex_article $art, rex_yrewrite_domain $domain)
    {
        if ($art->isStartArticle() && $domain->getMountId() != $art->getId()) {
            return $path . $this->suffix;
        }
        return $path . '/' . $this->normalize($art->getName(), $art->getClangId()) . $this->suffix;
    }

    /**
     * @return string|false
     */
    public function getCustomUrl(rex_article $art, rex_yrewrite_domain $domain)
    {
        if ($domain->getStartId() == $art->getId()) {
            if (!$domain->isStartClangAuto() && $domain->getStartClang() == $art->getClangId()) {
                return '/';
            }
            return $this->getClang($art->getClangId(), $domain) . $this->suffix;
        }
        if ($url = (string) $art->getValue('yrewrite_url')) {
            return $url;
        }
        return false;
    }

    /**
     * @return rex_structure_element|false
     */
    public function getRedirection(rex_article $art, rex_yrewrite_domain $domain)
    {
        return false;
    }

    /**
     * @param string $path
     * @return null|string|string[]
     */
    public function getAlternativeCandidates($path, rex_yrewrite_domain $domain)
    {
        if ('/' === substr($path, -1)) {
            return substr($path, 0, -1);
        }
        if ($this->suffix && substr($path, -strlen($this->suffix)) !== $this->suffix) {
            return $path . $this->suffix;
        }

        return null;
    }

    /**
     * @param string $string
     * @param int    $clang
     *
     * @return string
     */
    public function normalize($string, $clang = 1)
    {
        // do nothing if empty
        if (null === $string || '' === $string) {
            return '';
        }

        // deal with unicode urls
        if (rex_addon::get('yrewrite')->getConfig('unicode_urls')) {
            $string = str_replace(["'", '’', 'ʻ'], '', $string);
            $string = preg_replace('/[^\p{L&}\p{Lo}\p{M}\p{N}\p{Sc}]+/u', '-', $string);
            $string = mb_strtolower(trim($string, '-'));
            return $string;
        }
        
        // normalize
        $string = str_replace(
            ['Ä',  'Ö',  'Ü',  'ä',  'ö',  'ü',  'ß',  'À', 'à', 'Á', 'á', 'ç', 'È', 'è', 'É', 'é', 'ë', 'Ì', 'ì', 'Í', 'í', 'Ï', 'ï', 'Ò', 'ò', 'Ó', 'ó', 'ô', 'Ù', 'ù', 'Ú', 'ú', 'Č', 'č', 'Ł', 'ł', 'ž', '/', '®', '©', '™'],
            ['Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss', 'A', 'a', 'A', 'a', 'c', 'E', 'e', 'E', 'e', 'e', 'I', 'i', 'I', 'i', 'I', 'i', 'O', 'o', 'O', 'o', 'o', 'U', 'u', 'U', 'u', 'C', 'c', 'L', 'l', 'z', '-', '',  '',  ''],
            $string,
        );
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        $string = preg_replace('/[^\w -]+/', '', $string);
        $string = strtolower(trim($string));
        $string = urlencode($string);
        $string = preg_replace('/[+-]+/', '-', $string);
        return $string;
    }
}
