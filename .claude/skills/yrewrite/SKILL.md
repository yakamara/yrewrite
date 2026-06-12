---
name: yrewrite
description: >-
  Use when working with the REDAXO 6 yrewrite addon (package yakamara/yrewrite, namespace
  Yakamara\YRewrite) — speaking URLs, multidomain setups, per-article custom URLs/redirects,
  SEO meta tags (Open Graph/Twitter), hreflang, canonical URLs, XML sitemap, robots.txt,
  manual forwards/redirects, alias domains, and custom URL schemes. Triggers on yrewrite,
  Yakamara\YRewrite, YRewrite::, getFullUrlByArticleId, getCurrentDomain, Seo::getTags,
  YREWRITE_SEO_TAGS, sitemap.xml / robots.txt in REDAXO, or the .htaccess rewrite setup.
---

# YRewrite (REDAXO 6)

URL rewriting, multidomain and SEO addon for REDAXO 6. Successor of the REDAXO 5 `yrewrite`
addon, fully namespaced. This addon converts `index.php?article_id=13&clang=1` into speaking
URLs like `/en/news/archive/`, supports multiple domains/languages, and provides SEO output.

> **Maintaining or extending the addon?** Read [`development.md`](development.md) — REDAXO 6 addon
> architecture, the full `rex_*` → namespaced API map, and the core `Form` / MySQL-strict-mode /
> `.htaccess` / content-sidebar gotchas learned while porting it.

## Key facts (REDAXO 6 vs 5)

- Composer package: `yakamara/yrewrite`; addon name: `yrewrite`; PSR-4 namespace: `Yakamara\YRewrite\`.
- Core classes are namespaced — there is **no** global `rex_yrewrite` class. Use:
  - `Yakamara\YRewrite\YRewrite` (main API, was `rex_yrewrite`)
  - `Yakamara\YRewrite\Domain`, `Seo`, `Scheme`, `Forward`, `PathResolver`, `PathGenerator`, `Settings`
- DB tables: `rex_yrewrite_domain`, `rex_yrewrite_alias`, `rex_yrewrite_forward`.
- Article columns: `yrewrite_url_type`, `yrewrite_url`, `yrewrite_redirection`, `yrewrite_title`,
  `yrewrite_description`, `yrewrite_image`, `yrewrite_changefreq`, `yrewrite_priority`,
  `yrewrite_index`, `yrewrite_canonical_url`.
- Frontend rewrite is driven by extension point `URL_REWRITE` (core) + the `.htaccess` rules; the
  addon resolves incoming requests in `PACKAGES_INCLUDED` (early).
- Backend page key is `yrewrite`, tabs: `domains`, `alias_domains`, `forward`, `setup`.
- Permissions: `yrewrite[url]`, `yrewrite[seo]`, `yrewrite[forward]`.

## Setup

1. Install + activate (backend, or `php bin/console addon:install yrewrite`).
2. Open the backend page **YRewrite → Setup** and click ".htaccess-Datei setzen". This copies the
   addon's `setup/.htaccess` into the public document root (`Path::frontend('.htaccess')`),
   adding the rewrite rules **plus** the `Require all granted` block needed for the REDAXO 6
   `public/` layout. On nginx, configure rewrites manually (see addon README/below) instead.
3. Create at least one domain under **YRewrite → Domains**. Without a configured domain a single
   `default` domain (current host, root mount, all languages, language prefix hidden when the
   installation has only one language) is used automatically.

`sitemap.xml` and `robots.txt` are generated ad hoc per domain (rewritten to
`index.php?rex_yrewrite_func=sitemap|robots`).

> The addon routes all `/media/...` requests through the media manager. Do not create a structure
> category named "Media" and do not put frontend assets (CSS/JS) under `/media/` — use `/assets/`.

## Backend tabs

- **Domains** — add/edit domains: host, mount point (article; empty = root), start article, 404
  article, languages, preferred language (+ "hidden in URL", + "auto by browser language"), title
  scheme (`%T` = SEO title or article name, `%SN` = server name), robots.txt, automatic 301
  redirects on rename/move (+ expiry days).
- **Alias Domains** — alias hosts pointing to a real domain (e.g. `meinedomain.de` → `meine-domain.de`).
- **Weiterleitungen (Forwards)** — manual redirects for URLs that do **not** exist in the structure
  (relaunch redirects). Target = article / external link / media; status code 301/302/303/307;
  optional expiry date. Also handy to redirect to other protocols (`tel:`, `mailto:`).
- **Setup** — `.htaccess` action, usage info, settings (unicode URLs, hide URL/SEO blocks),
  sitemap/robots overview.

## Template usage

### SEO meta tags (put into `<head>`)

```php
use Yakamara\YRewrite\Seo;

$seo = new Seo();
echo $seo->getTags();
```

Outputs `<title>`, `<meta name="description">`, robots, canonical, hreflang alternates, and
Open Graph / Twitter card tags. Do not add your own title/description tags. SEO values per article
come from the `yrewrite_*` columns, edited in the article's content sidebar.

### URLs

```php
use Yakamara\YRewrite\YRewrite;
use Redaxo\Core\Filesystem\Url;

Url::article(42, 1);                              // pretty URL (dispatches URL_REWRITE)
YRewrite::getFullUrlByArticleId(42, 1);           // full absolute URL incl. domain
YRewrite::getFullUrlByArticleId(42, 1, ['p' => 1]); // with query params
```

### Current domain

```php
use Yakamara\YRewrite\YRewrite;

$domain = YRewrite::getCurrentDomain();           // ?Yakamara\YRewrite\Domain
$domain?->getId();
$domain?->getName();        // e.g. "meine-domain.de"
$domain?->getHost();
$domain?->getMountId();
$domain?->getStartId();
$domain?->getNotfoundId();
$domain?->getUrl();         // scheme://host/path
$domain?->getStartClang();

YRewrite::getDomainByArticleId(REX_ARTICLE_ID)->getName();
YRewrite::getDomainByName('meine-domain.de');
YRewrite::getDomainById(1);
YRewrite::getDomains();     // array<string, Domain>
```

### Navigation scoped to the current domain's mount point

```php
use Redaxo\Core\Content\Navigation;
use Yakamara\YRewrite\YRewrite;

$nav = Navigation::factory();
echo $nav->get(YRewrite::getCurrentDomain()->getMountId(), 1, true, true);
```

## Extension points

Register with `Redaxo\Core\ExtensionPoint\Extension::register('NAME', fn)`. Read the value with
`$ep->subject` and return the modified value (or `$ep->setSubject(...)`).

- `YREWRITE_SEO_TAGS` — modify the array of `<head>` tags (key => html string).
- `YREWRITE_CANONICAL_URL` — adjust the canonical URL (param `article`).
- `YREWRITE_HREFLANG_TAGS` — adjust the hreflang map (`langCode => url`).
- `YREWRITE_SITEMAP` / `YREWRITE_DOMAIN_SITEMAP` — modify sitemap entries.
- `YREWRITE_PREPARE` — last-chance resolver: return `['article_id' => int, 'clang' => int]` to map
  an otherwise-unresolved URL to an article (params `url`, `domain`).

```php
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

use function Redaxo\Core\View\escape;

Extension::register('YREWRITE_SEO_TAGS', static function (ExtensionPoint $ep) {
    $tags = $ep->subject;
    $title = escape('A changed title');
    $tags['title'] = '<title>' . $title . '</title>';
    $tags['og:title'] = '<meta property="og:title" content="' . $title . '">';
    $tags['favicon'] = '<link rel="icon" href="/assets/favicon.ico">';
    return $tags;
});
```

## Custom URL schemes

Subclass `Yakamara\YRewrite\Scheme` and register it in the `project` addon's `boot()`:

```php
use Redaxo\Core\Addon\Addon;
use Yakamara\YRewrite\YRewrite;

if (Addon::get('yrewrite')?->isActivated()) {
    YRewrite::setScheme(new \Project\MyRewriteScheme());
}
```

Common overrides:

```php
namespace Project;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructureElement;
use Yakamara\YRewrite\Domain;
use Yakamara\YRewrite\Scheme;

class MyRewriteScheme extends Scheme
{
    protected string $suffix = '.html';   // or '' to drop the trailing slash

    // redirect empty parent categories to their first child
    public function getRedirection(Article $art, Domain $domain): StructureElement|false
    {
        if ($art->isStartArticle() && ($cats = $art->getCategory()?->getChildren(true))) {
            return $cats[0];
        }
        return false;
    }

    // change URL normalization (e.g. "&" => "und")
    public function normalize(string $string, int $clang = 1): string
    {
        $string = str_replace(['&'], ['und'], $string);
        return parent::normalize($string, $clang);
    }
}
```

Note r6 property access: `$art->name`, `$art->id`, `$art->clangId`, `$cat->name` (public readonly
properties, not getters). `getValue('yrewrite_url')` etc. still work for the meta columns.

## nginx (instead of .htaccess)

```nginx
location / { try_files $uri $uri/ /index.php$is_args$args; }
rewrite ^/sitemap\.xml$  /index.php?rex_yrewrite_func=sitemap last;
rewrite ^/robots\.txt$   /index.php?rex_yrewrite_func=robots last;
rewrite ^/media/([^/]*)/([^/]*)  /index.php?rex_media_type=$1&rex_media_file=$2&$args;
rewrite ^/media/(.*)             /index.php?rex_media_type=yrewrite_default&rex_media_file=$1&$query_string;
```

## Gotchas

- Settings under core *System* (website URL, start/error article) are ignored while yrewrite is active.
- With a single configured domain, leave the "mount point" empty.
- Do not use a `<base>` tag in templates — URLs may include the domain depending on context.
- After custom changes that desync URLs, clear the addon cache (regenerates config + path files).
- Backend forms use the core `Form` class; optional domain/forward columns are nullable and empty
  numeric pickers are stored as `NULL` (MySQL strict mode).
