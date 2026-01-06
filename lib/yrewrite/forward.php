<?php

/**
 * YREWRITE Addon.
 *
 * @author jan.kristinus@yakamara.de
 *
 * @package redaxo\yrewrite
 */

class rex_yrewrite_forward
{
    public static $pathfile = '';
    public static $paths = [];

    /** @var array<int, string> */
    public static $movetypes = [
        301 => '301 - Moved Permanently',
        302 => '302 - Found',
        303 => '303 - See Other',
        307 => '307 - Temporary Redirect',
    ];

    public static function init(): void
    {
        self::$pathfile = rex_path::addonCache('yrewrite', 'forward_pathlist.json');
        self::readPathFile();
    }

    // ------------------------------

    public static function getForward($params)
    {
        // Url wurde von einer anderen Extension bereits gesetzt
        if (isset($params['subject']) && '' != $params['subject']) {
            return $params['subject'];
        }

        self::init();

        /** @var rex_yrewrite_domain $domain */
        $domain = $params['domain'];
        $url = mb_strtolower($params['url']);

        $forward_url = '';
        $matchingParams = -1;
        foreach (self::$paths as $p) {
            $forwardDomain = rex_yrewrite::getDomainById($p['domain_id']);

            if (!$forwardDomain || $forwardDomain !== $domain) {
                continue;
            }

            $pUrl = urldecode($p['url']);
            /** @psalm-suppress RedundantCondition https://github.com/vimeo/psalm/issues/8125 */
            if ($pUrl !== $url && $pUrl . '/' !== $url) {
                continue;
            }

            if (count($p['params'] ?? []) <= $matchingParams) {
                continue;
            }

            foreach ($p['params'] ?? [] as $key => $value) {
                if (rex_get($key, 'string', null) !== $value) {
                    continue 2;
                }
            }

            if ('article' == $p['type'] && ($art = rex_article::get($p['article_id'], $p['clang']))) {
                $forward_url = rex_getUrl($p['article_id'], $p['clang']);
            } elseif ('media' == $p['type'] && ($media = rex_media::get($p['media']))) {
                $forward_url = rex_url::media($p['media']);
            } elseif ('extern' == $p['type'] && '' != $p['extern']) {
                $forward_url = $p['extern'];
            }

            if ('' != $forward_url) {
                $matchingParams = count($p['params'] ?? []);
            }
        }

        if ('' != $forward_url) {
            header('HTTP/1.1 ' . self::$movetypes[$p['movetype']]);
            header('Location: ' . $forward_url);
            exit;
        }

        return false;
    }

    // ------------------------------

    public static function readPathFile(): void
    {
        if (!file_exists(self::$pathfile)) {
            self::generatePathFile();
        } else {
            self::$paths = rex_file::getCache(self::$pathfile);
        }
    }

    public static function generatePathFile(): void
    {
        $gc = rex_sql::factory();
        $content = $gc->getArray('select * from ' . rex::getTable('yrewrite_forward'));

        foreach ($content as &$row) {
            $url = explode('?', (string) $row['url'], 2);
            $row['url'] = mb_strtolower($url[0]);

            if (isset($url[1])) {
                /** @phpstan-ignore-next-line */
                parse_str($url[1], $row['params']);
            }
        }

        rex_file::put(self::$pathfile, json_encode($content));
    }
}
