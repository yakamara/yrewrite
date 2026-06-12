# YRewrite — Development & REDAXO 6 porting principles

Architecture notes and hard-won gotchas from porting yrewrite to REDAXO 6. Read this when
**maintaining or extending** the addon (not just using it). Paths are relative to the project /
core package (`vendor/redaxo/core/...`).

## Addon architecture (REDAXO 6, composer-based)

- An addon is an **abstract `Redaxo\Core\Addon\Addon` subclass**, declared via
  `composer.json` → `extra.redaxo.addon-class`. Metadata (name, authors, version, license,
  homepage) comes from composer.json — **no `package.yml`** for composer addons.
- Integration happens through overridable hooks on the addon class:
  - `boot()` — runtime init on every request (register extensions, assets, permissions).
  - `install()` / `uninstall()` — schema/data; **must be idempotent** (runs on every
    `console migrate` and on (re)install).
  - `getPages(): iterable<Page>` — backend pages.
  - properties `public protected(set) LoadOrder $load` and `public protected(set) array $defaultConfig`.
- **`public protected(set)` is required** to match the base-class signature. PhpStorm reports
  *"'set' access level must be omitted"* as an ERROR — that is a **false positive**; PHP 8.5
  accepts it and the addon boots. Trust the runtime (`console migrate`), not the inspection.
- PSR-4: the **file name must equal the class name** (`YRewriteAddon.php` → `class YRewriteAddon`).
- `enlist()` auto-registers the addon's `lang/` (i18n) and `fragments/` dirs. Lang keys are
  prefixed with the addon name (`yrewrite_…`); `$addon->i18n('key')` tries `yrewrite_key` first
  then falls back to `key`.
- **Addon state** lives in `<project>/var/data/core/addons.json` with two arrays: `config`
  (per-addon `{class, state}`) and `order` (boot order). `boot()` runs for everything in `order`,
  not just activated addons — if you hand-edit state to `uninstalled`, also remove it from `order`,
  otherwise its `boot()` still runs and may hit missing tables.

## rex_* → namespaced core API (REDAXO 6)

Everything moved out of global `rex_*` classes. Key mappings used here:

| REDAXO 5 | REDAXO 6 |
|---|---|
| `rex` | `Redaxo\Core\Core` (`isBackend/isFrontend/getUser/getTable/getServerName/getProperty/setProperty/getEnvironment/getAccesskey`) |
| `rex_addon` | `Redaxo\Core\Addon\Addon` (`require/get/getConfig/setConfig/clearCache`) |
| `rex_extension(_point)` | `Redaxo\Core\ExtensionPoint\Extension` + `ExtensionPoint` + `ExtensionLevel` |
| `rex_article`/`rex_category`/`rex_structure_element` | `Redaxo\Core\Content\Article` / `Category` / `StructureElement` |
| `rex_clang` | `Redaxo\Core\Language\Language` |
| `rex_path`/`rex_url` | `Redaxo\Core\Filesystem\Path` / `Url` |
| `rex_sql` | `Redaxo\Core\Database\Sql`; `rex_sql_table/column/index` → `Database\Table/Column/Index` |
| `rex_request/get/post` | `Redaxo\Core\Http\Request::request/get/post` (+ `requestMethod()`) |
| `rex_response` | `Redaxo\Core\Http\Response` |
| `rex_i18n` | `Redaxo\Core\Translation\I18n` (`msg/rawMsg`) |
| `rex_view`/`rex_fragment` | `Redaxo\Core\View\View` (`title`), `View\Message`, `View\Asset`, `View\Fragment` |
| `rex_list`/`rex_form` | `Redaxo\Core\View\DataList` / `Redaxo\Core\Form\Form` |
| `rex_media`/`rex_media_manager` | `Redaxo\Core\MediaPool\Media` / `MediaManager\MediaManager` |
| `rex_perm::register` | `Redaxo\Core\Security\Permission::register` |
| `rex_csrf_token` | `Redaxo\Core\Security\CsrfToken` |
| `rex_escape()` | `function Redaxo\Core\View\escape()` |
| `rex_string::buildQuery` | `Redaxo\Core\Util\Str::buildQuery` (**no separator arg** — only the array) |

**Property access, not getters:** `StructureElement`/`Language`/`Media` expose public readonly
properties: `$art->id`, `$art->clangId`, `$art->name`, `$art->updateDate`, `$art->categoryId`,
`$lang->id/->code/->name`, `$media->fileName/->type/->title/->width/->height`. `getValue('col')`
still works for meta columns; `getUrl()`, `isOnline()`, `isStartArticle()`, `getParentTree()` remain methods.

**Environment** is the `Redaxo\Core\Environment` enum (`Frontend`/`Backend`/`Console`), not a string.

## Frontend URL resolution

- The current article is selected via core properties: `Core::setProperty('article_id', …)`,
  `'start_article_id'`, `'notfound_article_id'` (set early in `boot/core.php`). yrewrite overrides
  them in a `PACKAGES_INCLUDED` listener at `ExtensionLevel::Early` (the resolver runs there).
- URL generation: `Url::article($id, $clang, $params)` dispatches the core `URL_REWRITE` EP, which
  yrewrite answers with the path list. `getFullUrlByArticleId()` builds the absolute variant.

## Extension points

- Register: `Extension::register('NAME', $cb, ExtensionLevel::Early|Normal|Late)`. String names
  still work; structure EPs keep the r5 names/params (`ART_UPDATED`/`CAT_MOVED`/… with `id`,
  `clang`, `parent_id`).
- Dispatch: `Extension::dispatch(new ExtensionPoint('NAME', $subject, $params, $readonly))`.
- In a listener read `$ep->subject`, `$ep->name`, `$ep->getParam()/getParams()`; **return** the
  modified value (or `$ep->setSubject()`), which updates the subject for the next listener.
- yrewrite's own EPs are `YREWRITE_*` (SEO_TAGS, CANONICAL_URL, HREFLANG_TAGS, SITEMAP,
  DOMAIN_SITEMAP, PREPARE).

## Core `Form` — principles & traps

Used for all backend forms here (yform is not available on r6).

- `Form::factory($table, $fieldset, $where, $method='post')`. The **form name is
  `md5($table.$where.$method)` — the fieldset is NOT part of it.** Two forms on the same page over
  the same table+where therefore share the submit marker `<name>_save` and collide. Fix: give them
  **different where strings** (e.g. `id=X AND clang_id=Y` vs `clang_id=Y AND id=X`) so the md5 differs.
- `save()` builds the UPDATE from **every added field name as a column** → you cannot add virtual
  (non-column) fields. For data that isn't a 1:1 column, use `addRawField('<input name=…>')` (raw
  fields are excluded from `getSaveElements()`) and persist it yourself in a `REX_FORM_SAVED` hook.
- **Checkboxes / multi-selects store the core "`|value|`" pipe notation** → back them with
  `varchar` columns, not `tinyint`. Read such columns with `preg_split('/[|,]+/', trim($v, '|, '))`.
- **Empty numeric pickers submit `""`**, which MySQL strict mode rejects for INT columns. Call
  `$field->setDefaultSaveValue(null)` on optional article/number fields so empty → `NULL`.
- **`setAttribute()` does not reach widget fields** (`ArticleField`/`MediaField` render their own
  markup). To toggle their visibility by JS, wrap each group in `addRawField('<div data-…>')` …
  `addRawField('</div>')` and toggle the wrapper divs.
- **`ArticleField` expects `?int`.** A dual-purpose `varchar` column (id *or* URL) crashes
  `LinkVar::getWidget()`. Render the article picker via `LinkVar::getWidget($id, $name, ?int $value)`
  inside a raw field and save the column manually instead.
- `get()` processes the POST and, on a successful save, **redirects to `setApplyUrl()`** via
  `header()+exit` (works because the backend buffers output). On the redisplay the message arrives
  as a list param. There is **no** `isFinished()` — don't branch on it.
- Post-save side effects: register `REX_FORM_SAVED` and **identify the form by instance identity**
  (`$ep->getParam('form') === $form`) — `getFieldsetName()` is `protected` and not callable from
  the page. Clear caches / regenerate path files there.
- `ValidationRule`: `NOT_EMPTY`, `MIN/MAX_LENGTH`, `MIN/MAX`, `URL`, `EMAIL`, `MATCH`/`NOT_MATCH`,
  `VALUES`, `CUSTOM`. There is **no UNIQUE rule** (rely on a DB unique index). `MATCH` fails on an
  empty value — if the field may be empty, use a regex with `*` (e.g. `/^[…]*$/`) not `+`.

## MySQL strict mode

REDAXO 6 runs MySQL in strict mode. A `NOT NULL` column without a default that the form omits
throws `1364 Field 'x' doesn't have a default value`. Make every optional column **nullable** in
`install()` (`new Column($name, $type, true)`); keep only the truly required ones `NOT NULL`.

## Backend pages

- `getPages()` returns a `Redaxo\Core\Backend\MainPage($block, $key, $title)` (block `'addons'`)
  with `Page` subpages: `->setRequiredPermissions(...)` and `->setSubPath($this->getPath('pages/x.php'))`.
- The main page key equal to the addon name resolves to `pages/index.php` by convention; it usually
  contains `echo View::title(...); Controller::includeCurrentPageSubPath();`.
- In an included page file, **`$this` is the addon instance** (core includes it via
  `Addon::includeFile()`), so `$this->i18n(...)`, `$this->getConfig(...)` work, and `$params` is the
  passed context.

## Content sidebar panels (per-article editing)

- The core dispatches `STRUCTURE_CONTENT_SIDEBAR` (in `pages/structure/content.php`) with params
  `article_id`, `clang`, `ctype`, `function`, `slice_id`, `category_id`, … Its subject is the
  sidebar HTML string; append your panel and return it.
- Build the panel as a `Form` over the `article` table (`where = id/clang`), set
  `setApplyUrl(Url::backendPage('content/edit', [...]))`, `addParam('page','content/edit')` + the
  article params so the form posts back to the editor. Wrap the result in a
  `core/page/section.php` fragment (collapsible).
- Two panels (URL + SEO) on the same article: see the md5-name collision note above — use distinct
  where orderings.

## .htaccess setup (REDAXO 6 `public/` layout)

- Doc root is `public/`. The **project root** ships a `.htaccess` with `Require all denied`;
  `public/.htaccess` re-grants with `Require all granted`. yrewrite's `setup/.htaccess` **replaces**
  `public/.htaccess` (`Path::frontend('.htaccess')`), so it **must contain the `Require all granted`
  block** — otherwise the project-root deny applies and the whole site returns 403.
- Speaking URLs only resolve once these rewrite rules are in place; the `Setup` tab's button copies
  the file. `sitemap.xml` / `robots.txt` are rewritten to `index.php?rex_yrewrite_func=sitemap|robots`.
- This is Apache-specific; on nginx configure rewrites in the server block (see `SKILL.md`).

## Mass rename principle

`yrewrite_` contains the substring `rewrite_`, so sequential search-replaces double up
(`rewrite_x` → `yrewrite_x` → `yyrewrite_x`). Rename with a **single left-to-right pass and a
longest-match-first alternation** (one Perl `s///` built from a map sorted by key length desc), then
verify with `grep` for stragglers (`Redaxo\Rewrite`, bare `Rewrite`, `yyrewrite`, leftover lowercase
`rewrite_`). Handle string-literal namespaces (`\\Redaxo\\Rewrite`) as separate map entries.

## Verification workflow

- `php -l` every touched file.
- `php bin/console addon:install|migrate|cache:clear` to validate boot + schema at runtime — this,
  not PhpStorm, is the source of truth for the `protected(set)` question and class loading.
- Validate behaviour with real requests: backend pages in the browser, and `curl` for the frontend
  (`?article_id=N` → 301 to the pretty URL, pretty URL → 200, `?rex_yrewrite_func=sitemap|robots`).
- Never overwrite a live document-root `.htaccess` without a backup; the Setup button is destructive.
