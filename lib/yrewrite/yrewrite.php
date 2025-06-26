<?php

/**
 * YREWRITE Addon.
 *
 * @author jan.kristinus@yakamara.de
 * @author gregor.harlan@redaxo.org
 *
 * @package redaxo\yrewrite
 */

class rex_yrewrite
{
    /** @var array<int, array<int, rex_yrewrite_domain>> */
    private static $domainsByMountId = [];

    /** @var array<string, rex_yrewrite_domain> */
    private static $domainsByName = [];

    /** @var array<int, rex_yrewrite_domain> */
    private static $domainsById = [];

    /** @var array<string, array{domain: rex_yrewrite_domain, clang_start: int}> */
    private static $aliasDomains = [];

    /** @var string */
    private static $pathfile = '';

    /** @var string */
    private static $configfile = '';

    /** @var array{paths?: array<string, array<int, array<int, string>>>, redirections?: array<string, array<int, array<int, array>>>} */
    public static $paths = [];

    /** @var rex_yrewrite_scheme */
    private static $scheme;

    public static function init()
    {
        if (null === self::$scheme) {
            self::setScheme(new rex_yrewrite_scheme());
        }

        self::$domainsByMountId = [];
        self::$domainsByName = [];
        self::$aliasDomains = [];
        self::$paths = [];

        self::addDomain(new rex_yrewrite_domain('default', null, self::getSubPath(), 0, rex_article::getSiteStartArticleId(), rex_article::getNotfoundArticleId(), rex_clang::getAllIds(), rex_clang::getStartId(), '', '', '', rex_clang::count() <= 1));

        self::$pathfile = rex_path::addonCache('yrewrite', 'pathlist.json');
        self::$configfile = rex_path::addonCache('yrewrite', 'config.php');
        self::readConfig();
        self::readPathFile();
    }

    public static function getScheme()
    {
        return self::$scheme;
    }

    public static function setScheme(rex_yrewrite_scheme $scheme)
    {
        self::$scheme = $scheme;
    }

    // ----- domain

    public static function addDomain(rex_yrewrite_domain $domain)
    {
        foreach ($domain->getClangs() as $clang) {
            self::$domainsByMountId[$domain->getMountId()][$clang] = $domain;
        }
        self::$domainsByName[$domain->getName()] = $domain;

        if ($id = $domain->getId()) {
            self::$domainsById[$id] = $domain;
        }
    }

    /**
     * @param string $from_domain
     * @param int $to_domain_id
     * @param int $clang_start
     */
    public static function addAliasDomain($from_domain, $to_domain_id, $clang_start = 0)
    {
        if (isset(self::$domainsById[$to_domain_id])) {
            self::$aliasDomains[$from_domain] = [
                'domain' => self::$domainsById[$to_domain_id],
                'clang_start' => $clang_start,
            ];
        }
    }

    /**
     * @return rex_yrewrite_domain[]
     */
    public static function getDomains()
    {
        return self::$domainsByName;
    }

    /**
     * @param string $name
     * @return null|rex_yrewrite_domain
     */
    public static function getDomainByName($name)
    {
        if (isset(self::$domainsByName[$name])) {
            return self::$domainsByName[$name];
        }
        return null;
    }

    /**
     * @param int $id
     * @return null|rex_yrewrite_domain
     */
    public static function getDomainById($id)
    {
        if (isset(self::$domainsById[$id])) {
            return self::$domainsById[$id];
        }
        return null;
    }

    public static function getDefaultDomain()
    {
        return self::$domainsByName['default'];
    }

    // ----- article

    public static function getCurrentDomain()
    {
        $article_id = rex_article::getCurrent()->getId();
        $clang_id = rex_clang::getCurrent()->getId();

        foreach (self::$domainsByName as $name => $domain) {
            if (isset(self::$paths['paths'][$name][$article_id][$clang_id])) {
                return $domain;
            }
        }
        return null;
    }

    public static function getFullUrlByArticleId($article_id = null, $clang = null, array $parameters = [], $separator = '&amp;')
    {
        $params = [];
        $params['id'] = $article_id ?: rex_article::getCurrentId();
        $params['clang'] = $clang ?: rex_clang::getCurrentId();
        $params['params'] = $parameters;
        $params['separator'] = $separator;

        return self::rewrite($params, [], true);
    }

    public static function getDomainByArticleId($aid, $clang = null)
    {
        $clang = $clang ?: rex_clang::getCurrentId();

        foreach (self::$domainsByName as $name => $domain) {
            if (isset(self::$paths['paths'][$name][$aid][$clang]) || isset(self::$paths['redirections'][$name][$aid][$clang])) {
                return $domain;
            }
        }
        return self::$domainsByName['default'];
    }

    public static function getArticleIdByUrl($domain, $url)
    {
        if ($domain instanceof rex_yrewrite_domain) {
            $domain = $domain->getName();
        }
        foreach (self::$paths['paths'][$domain] as $c_article_id => $c_o) {
            foreach ($c_o as $c_clang => $c_url) {
                if ($url == $c_url) {
                    return [$c_article_id => $c_clang];
                }
            }
        }
        return false;
    }

    public static function isDomainStartArticle($aid, $clang = null)
    {
        $clang = $clang ?: rex_clang::getCurrentId();

        foreach (self::$domainsByMountId as $d) {
            if (isset($d[$clang]) && $d[$clang]->getStartId() == $aid) {
                return true;
            }
        }

        return false;
    }

    public static function isDomainMountpoint($aid, $clang = null)
    {
        $clang = $clang ?: rex_clang::getCurrentId();

        return isset(self::$domainsByMountId[$aid][$clang]);
    }

    public static function isInCurrentDomain($aid)
    {
        return (self::getDomainByArticleId($aid)->getName() == self::getCurrentDomain()->getName()) ? true : false;
    }

    // ----- url

    public static function getPathsByDomain($domain)
    {
        return self::$paths['paths'][$domain];
    }

    public static function prepare()
    {
        if (rex::isFrontend() && 'get' === rex_request_method() && !rex_get('rex-api-call') && $articleId = rex_get('article_id', 'int')) {
            $params = $_GET;
            $article = rex_article::get((int) $params['article_id'], (int) ($params['clang'] ?? 0) ?: rex_clang::getCurrentId());
            if ($article instanceof rex_article) {
                unset($params['article_id']);
                unset($params['clang']);
                $url = self::getFullUrlByArticleId($articleId, null, $params, '&');
                rex_response::sendRedirect($url, rex_response::HTTP_MOVED_PERMANENTLY);
            }
        }

        if ($articleId = rex_request('article_id', 'int')) {
            $url = rex_getUrl($articleId);
        } else {
            if (!isset($_SERVER['REQUEST_URI'])) {
                $_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'], 1);
                if (!empty($_SERVER['QUERY_STRING'])) {
                    $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
                }
            }

            $url = urldecode($_SERVER['REQUEST_URI']);
        }

        $resolver = new rex_yrewrite_path_resolver(self::$domainsByName, self::$domainsByMountId, self::$aliasDomains, self::$paths['paths'] ?? [], self::$paths['redirections'] ?? []);
        $resolver->resolve($url);

        return true;
    }

    public static function rewrite($params = [], $yparams = [], $fullpath = false)
    {
        // Url wurde von einer anderen Extension bereits gesetzt
        if (isset($params['subject']) && '' != $params['subject']) {
            return $params['subject'];
        }

        $id = $params['id'];
        $clang = $params['clang'];

        foreach (self::$paths['redirections'] as $domain => $redirections) {
            if (isset($redirections[$id][$clang]['url'])) {
                return $redirections[$id][$clang]['url'];
            }

            if (isset($redirections[$id][$clang])) {
                $params['id'] = $redirections[$id][$clang]['id'];
                $params['clang'] = $redirections[$id][$clang]['clang'];
                return self::rewrite($params, $yparams, $fullpath);
            }
        }

        // $url = urldecode($_SERVER['REQUEST_URI']);
        $domainName = self::getHost();

        $path = '';

        // same domain id check
        if (!$fullpath && isset(self::$paths['paths'][$domainName][$id][$clang])) {
            $domain = self::getDomainByName($domainName);
            $path = $domain->getPath() . self::$paths['paths'][$domainName][$id][$clang];
            // if(rex::isBackend()) { $path = self::$paths['paths'][$domain][$id][$clang]; }
        }

        if ('' == $path) {
            foreach ((array) self::$paths['paths'] as $i_domain => $i_id) {
                if (isset(self::$paths['paths'][$i_domain][$id][$clang])) {
                    $domain = self::getDomainByName($i_domain);
                    $path = 'default' === $domain->getName() ? $domain->getPath() : $domain->getUrl();
                    $path .= self::$paths['paths'][$i_domain][$id][$clang];
                    break;
                }
            }
        }

        // params
        $urlparams = '';
        if (isset($params['params'])) {
            $urlparams = rex_string::buildQuery($params['params'], $params['separator']);
        }

        return $path . ($urlparams ? '?' . $urlparams : '');
    }

    public static function rewriteMedia(array $params)
    {
        $buster = '';
        if (isset($params['buster']) && $params['buster']) {
            $buster = '?buster='.$params['buster'];
        }

        return rex_url::frontend('media/'.$params['type'].'/'.$params['file'].$buster);
    }

    /*
    *
    *  function: generatePathFile
    *  - updates or generates the file-domain-path filelist
    *  -
    *
    */

    public static function generatePathFile($params)
    {
        $old_paths = self::$paths;

        $generator = new rex_yrewrite_path_generator(self::$scheme, self::$domainsByMountId, self::$paths['paths'] ?? [], self::$paths['redirections'] ?? []);

        $ep = $params['extension_point'] ?? '';
        switch ($ep) {
            // clang and id specific update
            case 'CAT_DELETED':
            case 'ART_DELETED':
                $generator->removeArticle($params['id'], $params['clang']);

                if ($params['parent_id'] > 0) {
                    $generator->generate(rex_article::get($params['parent_id'], $params['clang']));
                }

                break;
            case 'CAT_MOVED':
            case 'ART_MOVED':
                // workaround for R<5.8: https://github.com/redaxo/redaxo/pull/2843
                $clangId = $params['clang'] ?? $params['clang_id'];

                $generator->removeArticle($params['id'], $clangId);
                $generator->generate(rex_article::get($params['id'], $params['clang']));

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
                // TODO: Is this really needed anymore?
                rex_article_cache::delete($params['id']);

                $generator->generate(rex_article::get($params['id'], $params['clang']));

                break;
                // update all
            case 'CLANG_DELETED':
            case 'CLANG_ADDED':
            case 'CLANG_UPDATED':
                // case 'ALL_GENERATED':
            default:
                $generator->generateAll();
                break;
        }

        self::$paths = [
            'paths' => $generator->getPaths(),
            'redirections' => $generator->getRedirections(),
        ];

        $sql = rex_sql::factory()
//                ->setDebug()
                ->setTable(rex::getTable('yrewrite_forward'));

        // Alte Einträge ausschalten

        $sql->setWhere('expiry_date > "0000-00-00" AND expiry_date < :date', ['date' => date('Y-m-d')]);
        $sql->setValue('status', 0);
        $sql->update();

        // vergleicht alle Einträge aus old_paths mit der aktuellen path Liste.
        // nur ausführen, wenn es old_paths überhaupt gibt
        if ($old_paths) {
            foreach ($old_paths['paths'] as $domain_name => $old_article_paths) {
                $domain = self::getDomainByName($domain_name);
                $domain_id = $domain->getId();
                $expiry_date = null;
                if ($domain->getAutoRedirectDays()) {
                    $expiry_date = date('Y-m-d', time() + $domain->getAutoRedirectDays() * 24 * 60 * 60);
                }

                // Autoredirect nicht setzen, wenn autoredirect für diese Domain nicht eingeschaltet ist
                if (!$domain->getAutoRedirect()) {
                    continue;
                }
                foreach ($old_article_paths as $art_id => $old_paths) {
                    foreach (rex_clang::getAllIds() as $clang_id) {
                        if (!isset(self::$paths['paths'][$domain_name][$art_id][$clang_id]) || !isset($old_paths[$clang_id])) {
                            continue;
                        }

                        // Wenn es eine Abweichung im Pfad gibt, wird ein neuer Eintrag eingefügt
                        if (self::$paths['paths'][$domain_name][$art_id][$clang_id] != $old_paths[$clang_id]) {
                            if ('CAT_DELETED' == $ep || 'ART_DELETED' == $ep) {
                                $params = [
                                    'article_id' => $art_id,
                                ];
                                $sql->setTable(rex::getTable('yrewrite_forward'));
                                $sql->setWhere($params);
                                $sql->delete();
                            } elseif ('CLANG_DELETED' == $ep) {
                                $params = [
                                    'clang' => $clang_id,
                                ];
                                $sql->setTable(rex::getTable('yrewrite_forward'));
                                $sql->setWhere($params);
                                $sql->delete();
                            } elseif ('CAT_MOVED' == $ep || 'CAT_UPDATED' == $ep || 'ART_MOVED' == $ep || 'ART_UPDATED' == $ep || 'ART_META_UPDATED' == $ep) {
                                $params = [
                                    'article_id' => $art_id,
                                    'clang' => $clang_id,
                                    'type' => 'article',
                                    'domain_id' => $domain_id,
                                    'url' => trim($old_paths[$clang_id], '/'),
                                    'movetype' => '301',
                                    'status' => 1,
                                    'expiry_date' => $expiry_date,
                                ];
                                $sql->setTable(rex::getTable('yrewrite_forward'));
                                $sql->setValues($params);
                                $sql->insert();

                                // alte Redirects löschen wenn die URL der neuen URL des Artikels entspricht
                                $params = [
                                    'url' => trim(substr(rex_getUrl($art_id, $clang_id), strpos(rex_getUrl($art_id, $clang_id), $domain_name) + strlen($domain_name)), '/'),
                                ];
                                $sql->setTable(rex::getTable('yrewrite_forward'));
                                $sql->setValues([]);
                                $sql->setWhere($params);
                                $sql->delete();
                            }
                        }
                    }
                }
            }
        }

        rex_yrewrite_forward::init();
        rex_yrewrite_forward::generatePathFile();
        rex_file::putCache(self::$pathfile, self::$paths);
    }

    // ----- func

    public static function checkUrl($url)
    {
        if (!preg_match('/^[%_\.+\-\/a-zA-Z0-9]+$/', $url)) {
            return false;
        }
        return true;
    }

    // ----- generate

    public static function generateConfig()
    {
        $content = '<?php ' . "\n";

        $gc = rex_sql::factory();

        $domains = $gc->getArray('select * from '.rex::getTable('yrewrite_domain').' order by mount_id, clangs');
        foreach ($domains as $domain) {
            if (!$domain['domain']) {
                continue;
            }

            $name = (string) $domain['domain'];
            if (!str_contains($name, '//')) {
                $name = '//'.$name;
            }
            $parts = parse_url($name);
            $name = $parts['host'];
            if (isset($parts['port'])) {
                $name .= ':'.$parts['port'];
            }
            $path = '/';
            if (isset($parts['path'])) {
                $path = rtrim($parts['path'], '/') . '/';
            }

            if ($domain['start_id'] > 0 && $domain['notfound_id'] > 0) {
                $content .= "\n" . 'rex_yrewrite::addDomain(new rex_yrewrite_domain('
                    . '"' . $name . '", '
                    . (isset($parts['scheme']) ? '"'.$parts['scheme'].'"' : 'null') . ', '
                    . '"' . $path . '", '
                    . $domain['mount_id'] . ', '
                    . $domain['start_id'] . ', '
                    . $domain['notfound_id'] . ', '
                    . (strlen(trim((string) $domain['clangs'])) ? 'array(' . $domain['clangs'] . ')' : 'null') . ', '
                    . $domain['clang_start'] . ', '
                    . '"' . rex_escape($domain['title_scheme']) . '", '
                    . '"' . rex_escape($domain['description']) . '", '
                    . '"' . rex_escape($domain['robots']) . '", '
                    . ($domain['clang_start_hidden'] ? 'true' : 'false') . ','
                    . $domain['id'] . ','
                    . $domain['auto_redirect'] . ','
                    . $domain['auto_redirect_days'] . ','
                    . ($domain['clang_start_auto'] ? 'true' : 'false')
                    . '));';
            }
        }

        $domains = $gc->getArray('select * from '.rex::getTable('yrewrite_alias').' order by domain_id');
        foreach ($domains as $domain) {
            if (!$domain['alias_domain'] || !$domain['domain_id']) {
                continue;
            }

            $content .= "\n" . 'rex_yrewrite::addAliasDomain("' . $domain['alias_domain'] . '", ' . ((int) $domain['domain_id']) . ', ' . $domain['clang_start'] . ');';
        }

        rex_file::put(self::$configfile, $content);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(self::$configfile);
        }
    }

    public static function readConfig()
    {
        if (!file_exists(self::$configfile)) {
            self::generateConfig();
        }
        include self::$configfile;
    }

    public static function readPathFile()
    {
        if (!file_exists(self::$pathfile)) {
            self::generatePathFile([]);
        }
        self::$paths = rex_file::getCache(self::$pathfile);
    }

    public static function copyHtaccess()
    {
        return rex_file::copy(rex_path::addon('yrewrite', 'setup/.htaccess'), rex_path::frontend('.htaccess'));
    }

    public static function isHttps()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) {
            return true;
        }
        return (isset($_SERVER['SERVER_PORT']) && 443 == $_SERVER['SERVER_PORT']) || (isset($_SERVER['HTTPS']) && 'off' != strtolower($_SERVER['HTTPS']));
    }

    public static function deleteCache()
    {
        rex_package::require('yrewrite')->clearCache();
    }

    public static function getFullPath($link = '')
    {
        $domain = self::getHost();
        $http = 'http://';
        $subfolder = self::getSubPath();
        if (self::isHttps()) {
            $http = 'https://';
        }
        return $http . $domain . $subfolder . $link;
    }

    public static function getHost()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            return $_SERVER['HTTP_X_FORWARDED_HOST'];
        }
        return @$_SERVER['HTTP_HOST'];
    }

    private static function getSubPath(): string
    {
        $path = dirname($_SERVER['SCRIPT_NAME']);
        if (rex::isBackend()) {
            $path = dirname($path);
        }

        return rtrim($path, DIRECTORY_SEPARATOR) . '/';
    }
}
