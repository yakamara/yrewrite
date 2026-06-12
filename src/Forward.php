<?php

namespace Yakamara\YRewrite;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaPool\Media;

/**
 * Manual forwards (redirects) configured in the backend.
 *
 * Port of REDAXO 5 `rex_yrewrite_forward`.
 */
class Forward
{
    public static string $pathfile = '';

    /** @var list<array<string, mixed>> */
    public static array $paths = [];

    /** @var array<int, string> */
    public static array $movetypes = [
        301 => '301 - Moved Permanently',
        302 => '302 - Found',
        303 => '303 - See Other',
        307 => '307 - Temporary Redirect',
    ];

    public static function init(): void
    {
        self::$pathfile = Path::addonCache('yrewrite', 'forward_pathlist.json');
        self::readPathFile();
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function getForward(array $params): false
    {
        // Url wurde von einer anderen Extension bereits gesetzt
        if (isset($params['subject']) && '' != $params['subject']) {
            return false;
        }

        self::init();

        /** @var Domain $domain */
        $domain = $params['domain'];
        $url = mb_strtolower((string) $params['url']);

        $forwardUrl = '';
        $matchingParams = -1;
        $matched = null;
        foreach (self::$paths as $p) {
            $forwardDomain = YRewrite::getDomainById((int) $p['domain_id']);

            if (!$forwardDomain || $forwardDomain !== $domain) {
                continue;
            }

            $pUrl = urldecode((string) $p['url']);
            if ($pUrl !== $url && $pUrl . '/' !== $url) {
                continue;
            }

            if (count($p['params'] ?? []) <= $matchingParams) {
                continue;
            }

            foreach ($p['params'] ?? [] as $key => $value) {
                if (Request::get($key, 'string', null) !== $value) {
                    continue 2;
                }
            }

            if ('article' === $p['type'] && Article::get((int) $p['article_id'], (int) $p['clang'])) {
                $forwardUrl = Url::article((int) $p['article_id'], (int) $p['clang']);
            } elseif ('media' === $p['type'] && Media::get((string) $p['media'])) {
                $forwardUrl = Url::media((string) $p['media']);
            } elseif ('extern' === $p['type'] && '' != $p['extern']) {
                $forwardUrl = (string) $p['extern'];
            }

            if ('' != $forwardUrl) {
                $matchingParams = count($p['params'] ?? []);
                $matched = $p;
            }
        }

        if ('' != $forwardUrl && null !== $matched) {
            header('HTTP/1.1 ' . self::$movetypes[(int) $matched['movetype']]);
            header('Location: ' . $forwardUrl);
            exit;
        }

        return false;
    }

    public static function readPathFile(): void
    {
        if (!file_exists(self::$pathfile)) {
            self::generatePathFile();
        } else {
            self::$paths = File::getCache(self::$pathfile);
        }
    }

    public static function generatePathFile(): void
    {
        $gc = Sql::factory();
        $content = $gc->getArray('select * from ' . Core::getTable('yrewrite_forward'));

        foreach ($content as &$row) {
            $url = explode('?', (string) $row['url'], 2);
            $row['url'] = mb_strtolower($url[0]);

            if (isset($url[1])) {
                parse_str($url[1], $row['params']);
            }
        }
        unset($row);

        File::put(self::$pathfile, (string) json_encode($content));
    }
}
