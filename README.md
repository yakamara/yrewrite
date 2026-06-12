# YRewrite für REDAXO 6

YRewrite erzeugt sprechende URLs und verwaltet mehrere Domains in einer REDAXO-Installation.
Aus `index.php?article_id=13&clang=1` wird zum Beispiel `/de/news/archiv/`. Zusätzlich liefert
das AddOn die SEO-Ausgabe (Meta-Tags, Open Graph, Canonical, hreflang), eine `sitemap.xml` und
eine `robots.txt` je Domain.

Das ist yrewrite für REDAXO 6 (Composer-Paket `yakamara/yrewrite`, Namespace `Yakamara\YRewrite`,
Branch `3.x`).

## Funktionen

- Mehrere Domains in einer Installation, optional pro Sprache (`clang`)
- Sprechende, suchmaschinenfreundliche URLs in verschiedenen Schemata
- Eigene URL pro Artikel sowie Umleitungen auf einen Artikel oder eine externe Adresse
- Allgemeine Weiterleitungen (301/302/303/307) für URLs, die es in der Struktur nicht gibt –
  praktisch beim Relaunch
- Alias-Domains, die auf eine Hauptdomain weiterleiten
- SEO-Daten pro Artikel: Titel, Description, Vorschaubild, Indexierung, Canonical-URL
- `sitemap.xml` und `robots.txt`, domain- und sprachabhängig
- Seitentitel-Schema pro Domain
- Erweiterbar über eigene URL-Schemes und Extension Points

## Voraussetzungen

- REDAXO 6
- PHP 8.5 oder neuer
- Apache mit `mod_rewrite` (für NGINX siehe Hinweis bei der Installation)

## Installation

YRewrite wird in REDAXO 6 über Composer eingebunden.

1. Paket im Projektverzeichnis installieren:

   ```bash
   composer require yakamara/yrewrite
   ```

2. AddOn installieren und aktivieren – im Backend unter *AddOns* oder über die Konsole:

   ```bash
   php bin/console addon:install yrewrite
   ```

3. `.htaccess` einrichten: im Backend unter **YRewrite → Setup** auf *.htaccess-Datei setzen*
   klicken. Damit wird die Datei im öffentlichen Verzeichnis (`public/`) erstellt bzw. ersetzt,
   die für das Rewriting nötig ist. Eine bereits vorhandene `.htaccess` vorher sichern.

   > Auf NGINX greift keine `.htaccess`. Dort die Rewrite-Regeln direkt im Server-Block
   > hinterlegen (siehe `.claude/skills/yrewrite/SKILL.md`).

4. Mindestens eine Domain anlegen: **YRewrite → Domains → +**. Host, Startartikel und
   404-Artikel angeben. Ohne konfigurierte Domain nutzt YRewrite automatisch den aktuellen Host.

Danach werden alle Frontend-URLs umgeschrieben.

## Verwendung

SEO-Meta-Tags im `<head>` des Templates ausgeben:

```php
use Yakamara\YRewrite\Seo;

$seo = new Seo();
echo $seo->getTags();
```

URL und aktuelle Domain ermitteln:

```php
use Yakamara\YRewrite\YRewrite;
use Redaxo\Core\Filesystem\Url;

echo Url::article(42, 1);                  // sprechende URL eines Artikels
echo YRewrite::getCurrentDomain()?->getName();
```

## Backend

Das AddOn legt unter *AddOns → YRewrite* folgende Reiter an:

- **Domains** – Domains anlegen und verwalten
- **Alias Domains** – weiterleitende Hosts
- **Weiterleitungen** – manuelle Redirects auf Artikel, Medien oder externe Links
- **Setup** – `.htaccess` einrichten, Einstellungen, Übersicht von `sitemap.xml`/`robots.txt`

URL und SEO-Daten lassen sich zusätzlich direkt im Artikel-Editor in der Seitenleiste pflegen.

## Dokumentation

Eine ausführliche Referenz zur Verwendung (Templates, Extension Points, eigene URL-Schemes,
NGINX-Konfiguration) liegt im Skill unter `.claude/skills/yrewrite/SKILL.md`. Architektur- und
Entwicklungshinweise zur REDAXO-6-Portierung stehen in `.claude/skills/yrewrite/development.md`.

## Lizenz

MIT. Siehe Angaben in der `composer.json`.
