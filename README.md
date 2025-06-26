# YRewrite

## Übersicht

Das AddOn YRewrite für REDAXO erweitert das CMS um "sprechende" URLs und unterstützt den Betrieb mehrerer Domains.
REDAXO ohne Rewriter-AddOn erstellt Urls nach dem Schema `index.php?article_id=13&clang=1`zur Vefügung. YRewrite  bietet lesbare URLs wie z.B. `/de/news/archiv/` in verschiedenen Schemata.

Mehrere Domains können dann sinnvoll sein, wenn

* mehrere Websites eines Kunden in einer Installation verwaltet werden,
* verschiedene Sprachen (`clang`) einer Website unter unterschiedlichen Domains oder Subdomains erreichbar sind,
* oder beides.

> Tipp: Wir empfehlen im ersten Fall, für jede einzelne Domain in der Struktur auf der obersten Ebene eine Kategorie anzulegen.

## Features

* Mehrere Domains in einer Webseite verwaltbar
* Sprachabhängigkeiten von Domains zuweisbar
* Domain- und sprachabhängige ROBOTS.txt-Anweisungen und XML-Sitemaps
* Individuelle URL pro Artikel möglich
* Seitentitel-Schema definierbar / pro Domain/Sprache
* Alias Domains die auf die Hauptdomain verweisen
* Allgemeine Weiterleitungen. URLs zu internen Artikeln, Dateien, externen Artikeln, sogar Protokoll-Tausch in bspw. `tel:`, `mailto:` u.a.
* Canonical Urls

## Installation

Voraussetzung für die aktuelle Version von YRewrite: REDAXO >= 5.5

* Über das REDAXO-Backend installieren und aktivieren
* Setup ausführen

# Erste Schritte

## Setup

Unter dem Reiter `Setup` sind die `sitemap.xml` und `robots.txt` je eingerichteter Domain einsehbar. Außerdem lässt sich ein Setup durchführen, bei der die Apache-Konfiguration für YRewrite über eine `.htaccess`-Datei erstellt wird.

### Apache-Konfiguration für YRewrite

Das Setup ausführen, um eine `.htaccess`-Datei im Hauptverzeichnis zu erstellen, die für die Verwendung von YRewrite benötigt wird. Anschließend werden alle Frontend-URLs in suchmaschinenfreundliche URLs umgeschrieben ("Rewriteing").

> **Hinweis** Sollten nach Abeschluss des Setups die Frontend-URLs nicht funktionieren, bitte prüfen, ob es sich um einen Apache- oder NGINX-Server handelt (siehe unten). Außerdem prüfen, ob das Webhosting-Paket eigene `.htaccess`-Regeln erlaubt.

> **Hinweis:** Das Addon leitet alle Anfragen von `/media/` über das Media-Manager-AddOn. Stelle daher sicher, dass es weder eine Struktur-Kategorie "Media" gibt, noch, dass sich keine deiner Dateien fürs Frontend, bspw. CSS- oder JS-Dateien, darin befinden. Gute Orte hierfür sind die Ordner `/assets/` oder die Verwendung des Theme-AddOns. Sollte es notwendig sein, eine Kategorie namens "Media" zu verwenden, dann müssen [die entsprechenden Zeilen in der .htaccess-Datei](https://github.com/yakamara/redaxo_yrewrite/blob/b519622a3be135f1380e35bf85783cc33e71664f/setup/.htaccess#L96-L97) auskommentiert oder umbenannt werden und diese fortan genutzt werden, wenn Medien aus dem Medien Manager verwendet werden. Dies hat weitere Auswirkungen, z.B. auf geschützte Dateien mit YCom - das Auskommentieren und Umbenennen sollte daher nur von erfahrenen REDAXO-Entwicklern vorgenommen werden.

### NGINX-Konfiguration für YRewrite

Eine vollständige nginx config für YRewrite.

> Hinweis für PLESK-Websites: Die Direktiven können unter ***Einstellungen für Apache & nginx*** der gewünschten Domain im Abschnitt ***Zusätzliche nginx-Anweisungen*** hinterlegt werden.

```nginx
charset utf-8;

location / {
  try_files $uri $uri/ /index.php$is_args$args;
}

rewrite ^/sitemap\.xml$                           /index.php?rex_yrewrite_func=sitemap last;
rewrite ^/robots\.txt$                            /index.php?rex_yrewrite_func=robots last;
rewrite ^/media[0-9]*/imagetypes/([^/]*)/([^/]*)  /index.php?rex_media_type=$1&rex_media_file=$2&$args;
rewrite ^/media/([^/]*)/([^/]*)                   /index.php?rex_media_type=$1&rex_media_file=$2&$args;
rewrite ^/media/(.*)                              /index.php?rex_media_type=yrewrite_default&rex_media_file=$1&$query_string;
rewrite ^/images/([^/]*)/([^/]*)                  /index.php?rex_media_type=$1&rex_media_file=$2&$args;
rewrite ^/imagetypes/([^/]*)/([^/]*)              /index.php?rex_media_type=$1&rex_media_file=$2;

// !!! WICHTIG !!! Falls Let's Encrypt fehlschlägt, diese Zeile auskommentieren (sollte jedoch funktionieren)
location ~ /\. { deny  all; }

// Zugriff auf diese Verzeichnisse verbieten
location ^~ /redaxo/src { deny  all; }
location ^~ /redaxo/data { deny  all; }
location ^~ /redaxo/cache { deny  all; }
location ^~ /redaxo/bin { deny  all; }


// In einigen Fällen könnte folgende Anweisung zusätlich sinnvoll sein.

location ~ /\.(ttf|eot|woff|woff2)$ {
  add_header Access-Control-Allow-Origin *;
  expires 604800s;
}
```


## Domain hinzufügen

1. In "YRewrite" unter "Domains" Auf das +-Zeichen klicken.
2. Domain eintragen, bspw. `https://www.meine-domain.de/`.
3. Mountpoint auswählen (optional). Das ist der Startartikel einer Kategorie, in der sich YRewrite einklinken soll. Alle Artikel unterhalb des Mount-Artikels sind dann über die Domain aufrufbar. Wird kein Artikel ausgewählt, sind alle Ebenen dieser Domain zugeordnet (Standard).
4. Startseiten-Artikel auswählen. Das kann der Mount-Artikel sein oder eine separate Artikelseite. Diese wird als Startseite der Domain aufgerufen.
5. Fehlerseiten-Artikel auswählen. Das ist der Artikel, der mit einem 404-Fehlercode ausgegeben wird, z.B., wenn eine Seite nicht gefunden werden kann oder ein Tippfehler in der Adresse vorliegt.
6. Spracheinstellungen: Hier können Sprachen ausgewählt werden, die mit der Domain verknüpft werden. So lassen sich bspw. unterschiedliche Domains pro Sprache umsetzen.
7. Titelschema eintragen, bspw. `%T - Meine Domain`. Dieses Titelschema kann dann im Website-Template ausgegeben werden.
8. robots.txt-Einstellungen hinzufügen. Siehe Tipp unten.
9. Domain hinzufügen.

Diese Vorgehensweise für alle gewünschten Domains wiederholen.

> **Tipp:** Um die Installation während der Entwicklung zuverlässig gegen ein Crawling von Bots und Suchmaschinen zu schützen, genügt die `robots.txt` nicht. Dazu gibt es das `maintanance`-Addon von https://friendsofREDAXO.github.io

> **Tipp:** Die Domain auch in der Google Search Console hinterlegen und die `sitemap.xml` dort hinzufügen, um das Crawling zu beschleunigen. Die Domain sollte in allen vier Variationen hinterlegt werden, also mit/ohne `https` und mit/ohne `www.`. Die `sitemap.xml` jedoch nur in der Hauptdomain, am besten mit `https://` und `www.`

> **Hinweis:** Domains mit Umlauten bitte derzeit decodiert eintragen. Umwandlung bspw. mit https://www.punycoder.com

> **Hinweis:** Informationen zu Best Practice zu Domains und mehrsprachigen Websites auch unter [Multiregionale und mehrsprachige Websites verwalten](https://developers.google.com/search/docs/advanced/crawling/managing-multi-regional-sites?hl=de) aus der Google Search Central Dokumentation.

## Alias-Domain hinzufügen

Alias-Domains werden nur dann benötigt, wenn mehrere Domains auf den selben Ordner im Server zeigen, aber keine separaten Websites aufrufen. z.B. `www.meinedomain.de` und `www.meine-domain.de`.

Alias-Domains müssen nicht eingetragen werden, wenn die Domain nicht auf das Serververzeichnis zeigt. Einige Hoster bieten bspw. von sich aus die Möglichkeit, per Redirect von `www.meinedomain.de` auf `www.meine-domain.de` weiterzuleiten. Dann wird die Einstellung nicht benötigt.

1. In "YRewrite" unter "Domains" Auf das +-Zeichen klicken
2. Alias-Domain eintragen, bspw. `https://www.meine-domain.de/`
3. Ziel-Domain aus YRewrite auswählen
4. Alias-Domain hinzufügen

## Weiterleitungen

Unter Weiterleitungen können URLs definiert werden, die dann auf einen bestimmten Artikel oder eine andere Adresse umgeleitet werden.

> **Hinweis:** Mit dieser Einstellung können nicht bereits vorhandene Artikel / URLs umgeleitet werden, sondern nur URLs, die in der REDAXO-Installation nicht vorhanden sind. Das ist bspw. bei einem Relaunch der Fall, wenn alte URLs auf eine neue Zielseite umgeleitet werden sollen.

> **Tipp**: Damit lässt sich auch ein Artikel oder eine Kategorie zu einem gänzlich anderen URI-Protokoll ändern, bspw. `tel:`, `mailto:` u.a. Diese werden auch an anderer Stelle, bspw. von der `rex_navigation::factory()`, berücksichtigt.

## Weitere Schritte

Die `sitemap.xml` kann pro Domain bspw. in der Google Search Console eingetragen werden, um die korrekte Indexierung der Domain(s) und deren Seiten zu überprüfen.

# Klassen-Referenz

## YRewrite-Objekt

Siehe auch: https://github.com/yakamara/redaxo_yrewrite/blob/main/lib/yrewrite/yrewrite.php

```
    $yrewrite = new rex_yrewrite;
    // dump($yrewrite); // optional alle Eigenschaften und Methoden anzeigen
```

**Methoden**

```
```

## YRewrite-Domain-Objekt

Siehe auch: https://github.com/yakamara/redaxo_yrewrite/blob/main/lib/yrewrite/domain.php

```
$domain = rex_yrewrite::getCurrentDomain();
dump($domain); // optional alle Eigenschaften und Methoden anzeigen
```

**Methoden**
```
init()
getScheme()
setScheme(rex_yrewrite_scheme $scheme)
addDomain(rex_yrewrite_domain $domain)
addAliasDomain($from_domain, $to_domain_id, $clang_start = 0)
getDomains()
getDomainByName($name)
getDomainById($id)
getDefaultDomain()
getCurrentDomain()
getFullUrlByArticleId($id, $clang = null, array $parameters = [], $separator = \'&amp;\')
getDomainByArticleId($aid, $clang = null)
getArticleIdByUrl($domain, $url)
isDomainStartArticle($aid, $clang = null)
isDomainMountpoint($aid, $clang = null)
getPathsByDomain($domain)
prepare()
rewrite($params = [], $yparams = [], $fullpath = false)
generatePathFile($params)
checkUrl($url)
generateConfig()
readConfig()
readPathFile()
copyHtaccess()
isHttps()
deleteCache()
getFullPath($link = \'\')
getHost()
```
## YRewrite-SEO-Objekt

Siehe auch: https://github.com/yakamara/redaxo_yrewrite/blob/main/lib/yrewrite/seo.php


```
$seo = new rex_yrewrite_seo();
dump($seo); // optional alle Eigenschaften und Methoden anzeigen
```

**Methoden**

```
```

# Beispiele

## ID der aktuellen Domain in YRewrite

```
rex_yrewrite::getCurrentDomain()->getId();

```

Beispiel-Rückgabewert: `1`

## Mount-ID der Domain

```
rex_yrewrite::getCurrentDomain()->getMountId();
```

Beispiel-Rückgabewert: `5`


## Startartikel-ID der Domain

```
rex_yrewrite::getCurrentDomain()->getStartId();
```

Beispiel-Rückgabewert: `42`

## Fehler-Artikel-ID der Domain

```
rex_yrewrite::getCurrentDomain()->getNotfoundId();
```

Beispiel-Rückgabewert: `43`

## Name der aktuellen Domain

```
rex_yrewrite::getCurrentDomain()->getName();
```

Beispiel-Rückgabewert: `meine-domain.de`

## vollständige URL eines Artikels

```
rex_yrewrite::getFullUrlByArticleId(42);
```

Beispiel-Rückgabewert: `https://www.meine-domain.de/meine-kategorie/mein-artikel.html`

## Zu welcher Domain gehört der aktuelle Artikel?

```
rex_yrewrite::getDomainByArticleId(REX_ARTICLE_ID)->getName();
```

Beispiel-Rückgabewert: `meine-domain.de`

## Meta-Tags auslesen (`description`, `title`, `image` usw.)

Diesen Codeabschnitt in den `<head>`-Bereich des Templates kopieren:

```php
$seo = new rex_yrewrite_seo();
echo $seo->getTags();
```

Dies erzeugt folgende Ausgabe:

```html
<meta name="description" content="Der Text aus dem Beschreibungs-Feld">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://example.org/de/">
<link rel="alternate" hreflang="de" href="https://example.org/de/">
<link rel="alternate" hreflang="en" href="https://example.org/en/">
<meta property="og:title" content="Artikelname / Websitetitel">
<meta property="og:description" content="Der Text aus dem Beschreibungs-Feld">
<meta property="og:image" content="https://example.org/media/yrewrite_seo_image/seo-image.jpg">
<meta property="og:image:alt" content="Der Bildtitel aus dem Medienpool">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:url" content="https://example.org/de/">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="Artikelname / Websitetitel">
<meta name="twitter:description" content="Der Text aus dem Beschreibungs-Feld">
<meta name="twitter:url" content="https://example.org/de/">
<meta name="twitter:image" content="https://example.org/media/yrewrite_seo_image/seo-image.jpg">';
<meta name="twitter:image:alt" content="Der Bildtitel aus dem Medienpool">
```

## Meta-Tags erweitern / ändern

```php
rex_extension::register('YREWRITE_SEO_TAGS', function(rex_extension_point $ep) {
    $tags = $ep->getSubject();
    
    // title-Tag ändern
    $title = rex_escape('Ein geänderter Titel');
    $tags['title'] = '<title>'.$title.'</title>';
    $tags['og:title'] = '<meta property="og:title" content="'.$title.'">';
    $tags['twitter:title'] = '<meta name="twitter:title" content="'.$title.'">';
    
    // favicon-Tags hinzufügen
    $tags['favicon'] = '
            <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon/apple-touch-icon.png">
            <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon/favicon-32x32.png">
            <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon/favicon-16x16.png">
            <link rel="manifest" href="/assets/favicon/site.webmanifest">
            <link rel="mask-icon" href="/assets/favicon/safari-pinned-tab.svg" color="#5bbad5">
            <link rel="shortcut icon" href="/assets/favicon/favicon.ico">
            <meta name="msapplication-TileColor" content="#ffffff">
            <meta name="msapplication-config" content="/assets/favicon/browserconfig.xml">
            <meta name="theme-color" content="#ffffff">';
    $ep->setSubject($tags);
});

```

## Navigation Factory in Abhängigkeit der gewählten Domain

Weitere Informaionen zur Navigation Factory des REDAXO-Cores in der API-Dokumentation unter https://REDAXO.org/api/master/ und bei den Tricks von FriendsOfREDAXO: https://github.com/friendsofREDAXO/tricks/

```
$nav = rex_navigation::factory();
echo $nav->get(rex_yrewrite::getCurrentDomain()->getMountId(), 1, TRUE, TRUE);
```

## Übersicht aller Domains ausgeben

```
$domains = array_filter(rex_sql::factory()->setDebug(0)->query(\'SELECT * FROM rex_yrewrite_domain\')
foreach($domains as $domain) {
    dump($domain);
}
```

# URL-Schemes für YRewrite

## Übersicht

YRewrite kann durch Schemes erweitert werden.

**Installation**
- Als Datei im `lib`-Ordner des __project-AddOns__ ablegen.
- Dateiname: `eigene_rewrite_class.php`
- In die `boot.php` des project-AddOns einsetzen:

```php
<?php
if (rex_addon::get(\'yrewrite\')->isAvailable()) {
    rex_yrewrite::setScheme(new eigene_rewrite_class());
}
```

Nachfolgend listen wir hier ein paar Beispiele.

## Endung auf .html setzen

```php
<?php
class rex_yrewrite_scheme_mysuffix extends rex_yrewrite_scheme
{
    protected $suffix = \'.html\';
}
```

## Trailing Slash entfernen

```php
<?php
class rex_yrewrite_scheme_mysuffix extends rex_yrewrite_scheme
{
    protected $suffix = Null;
}
```

## URL-Replacer

Ersetzt URLs leerer Elternkategorien mit den URLs der nächsten mit inhalt versehenen (online-)Kindkategorie.

> Basiert auf: https://gist.github.com/gharlan/a70704b1c309cb1281c1

### Weiterleitung egal ob Inhalt in Startartikel der Elternkategorie

```php
<?php
class rex_yrewrite_scheme_gh extends rex_yrewrite_scheme
{
    protected $suffix = \'/\';

    public function getRedirection(rex_article $art, rex_yrewrite_domain $domain)
    {

        if ($art->isStartArticle() && ($cats = $art->getCategory()->getChildren(true))) {
            return $cats[0];
        }

        return false;
    }
}
```

### Weiterleitung nur wenn kein Inhalt im Startartikel der Elternkategorie

```php
<?php
class rex_yrewrite_scheme_gh extends rex_yrewrite_scheme
{
    protected $suffix = \'/\';

    public function getRedirection(rex_article $art, rex_yrewrite_domain $domain)
    {

        if ($art->isStartArticle() && ($cats = $art->getCategory()->getChildren(true)) && !rex_article_slice::getFirstSliceForCtype(1, $art->getId(), rex_clang::getCurrentId())) {
            return $cats[0];
        }

    return false;
    }
}
```

## URL manipulieren, hier mit dem AddOn Sprog

So kann als Kategoriename ein Platzhalter wie {{contact}} verwendet werden und durch die in Sprog hinterlegten Sprachvarianten ersetzt werden.

One Level, Kategoriename-Ersetzung durch Sprog.

```php
<?php
class translate_url_with_sprog extends rex_yrewrite_scheme
{

    public function appendCategory($path, rex_category $cat, rex_yrewrite_domain $domain)
    {
        return $path;
    }

    public function appendArticle($path, rex_article $art, rex_yrewrite_domain $domain)
    {
        return $path . \'/\' . $this->normalize(sprogdown($art->getName(), $art->getClangId()), $art->getClangId()) . \'/\';
    }
}
```

Multilevel, Kategoriename-Ersetzung durch Sprog.

```php
<?php
class translate_url_with_sprog extends rex_yrewrite_scheme
{
    public function appendCategory($path, rex_category $cat, rex_yrewrite_domain $domain)
    {
        return $path . \'/\' . $this->normalize(sprogdown($cat->getName(), $cat->getClangId()), $cat->getClangId());
    }
}
```

## Ersetzungsmuster mit einem eigenen Schema verändern

Die Ersetzungsmuster können mit eigenen Schema verändert werden. In diesem Beispiel wird `&` durch `und` getauscht.

1. Datei in den lib-Ordner des Project-AddOns anlegen

```
<?php

class rex_project_rewrite_scheme extends rex_yrewrite_scheme
{
    /**
     * @param string $string
     * @param int $clang
     *
     * @return string
     */
    public function normalize($string, $clang = 1)
    {
        $string = str_replace(
            ['&'],
            ['und'],
            $string
        );

        // Id 2 = ungarisch
        if ($clang == 2) {
            $string = str_replace(
                ['ő', 'ű'],
                ['oe', 'ue'],
                $string
            );
        }
        return parent::normalize($string, $clang);
    }
}
```

2. In der `boot.php`-Datei des `project`-AddOns diesen Code einfügen:

`rex_yrewrite::setScheme(new rex_project_rewrite_scheme());`

## Addons, die eigene Schemes mitbringen:

- YRewrite scheme: https://github.com/FriendsOfREDAXO/yrewrite_scheme


# Weitere Unterstützung

* Bug melden via GitHub: https://github.com/yakamara/redaxo_yrewrite/issues/
* Hilfe via REDAXO Slack-Channel: https://friendsofREDAXO.slack.com/
* Tricks via FriendsOfREDAXO: https://friendsofredaxo.github.io/tricks/ bei Addons > YRewrite
