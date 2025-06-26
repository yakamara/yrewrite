Changelog
=========

Version 2.11.0 – 21.03.2025
---------------------------

### Neu

* Anbindung an API AddOn um Seo Daten (im Moment Titel und Description) lesen und schreiben zu können (@dergel)
* FileType .mjs in htaccess ergänzt (@skerbis)
* nonce für CSP ergänzt (@dergel)

### Bugs

* Wenn ein Media nicht vorhanden war, konnte ein Whoops in der Sitemap entstehen (@dergel/ @tbaddade)
* str_replace deprecated aufruf korrigiert (@MC-PMOE)
* Wenn htaccess nicht geschrieben werden konnte, gab es keine Fehler (@Koala)
* HTTP_AUTHORIZATION Header wird nun auch beachtet (@dergel)
* Fehlerhafter clang aufruf korrigiert (@tyrant88)
* Readme angepasst (@marcohanke)
* Fehlerhafte clang aufrufe korrigiert (@tbaddade)


Version 2.10.0 – 31.03.2023
---------------------------

### Neu

* Neue PHP-Mindestversion 8.1 (@gharlan)
* Eigene Seitentitel werden direkt genutzt, ohne Domain-Titelschema (@bitshiftersgmbh)
* hreflangs: `x-default` wird gesetzt bei Auto-Sprachweiterleitung (@gharlan)
* Weiterleitungen: Bei mehreren Matches, wird die Weiterleitung mit den meisten Params genutzt (@gharlan)
* Weiterleitungen: Params ohne Werte werden unterstützt (`?foo&bar`) (@gharlan)
* Sitemap: Vorbereitung für Videos (@TobiasKrais)
* Mimetypes in `.htaccess` aktualisiert (@tyrant88)
* Meta- und Link-Tags ohne schließenden Slash (@gharlan)
* Doku-Optimierungen/Erweiterungen (@alxndr-w, @geraldurbas, @madiko, @tyrant88)

### Bugfixes

* `og:url`-Tag korrigiert (@isospin)
* Weiterleitungsschleife beseitigt bei Aufrufen über `?article_ix=X` mit nicht existenter Artikel-ID (@TobiasKrais)
* hreflangs wurden nicht korrekt gesetzt bei sprachspezifischen Domains mit gleichem Mountpoint (@marcohanke)
* SEO-Tags: Auswahl `noindex, follow` wurde nicht korrekt beachtet (@gharlan)


Version 2.9.1 – 16.08.2022
--------------------------

### Bugfixes

* Umleitung bei Frontendaufrufen mit `?article_id=X`-Parameter nicht mehr bei API-Aufrufen und bei POST-Requests, um kompatibler zum Verhalten vor v2.9 zu sein (@gharlan)


Version 2.9.0 – 03.08.2022
--------------------------

### Neu

* SEO-Daten:
    - Bild kann hinterlegt werden (mit neuem Media-Manager-Effekt `yrewrite_seo_image`) (@TobiasKrais)
    - Neue Methode `getTags`, die alle Tags gemeinsam liefert (bisherige und zusätzliche bzgl. `og:` und `twitter:`); Anpassungen über EP `YREWRITE_SEO_TAGS` möglich (@tbaddade, @TobiasKrais)
    - Bisherige Einzelmethoden für die Tags (`getTitleTag` etc.) als deprecated gesetzt (@tbaddade)
* Weiterleitungen: 
    - Ziel wird als URL in der Liste angezeigt (@DanielWeitenauer)
    - Deaktivierungsdatum kann manuell gesetzt/geändert werden, das Datum wird formatiert ausgegeben und es wird der Wert "0000-00-00" vermieden (@gharlan)
* Bei Frontend-Aufruf über Parameter `?article_id=X&clang=Y` wird auf die Artikel-URL umgeleitet (@gharlan)
* YRewrite löscht nicht mehr den gesamten REDAXO-Cache, sondern nur den eigenen (@alxndr-w)
* Hilfe erweitert/optimiert (@alxndr-w, @skerbis, @TobiasKrais, @tbaddade)

### Bugfixes

* Artikel, die als Mountpoint/Startartikel/Fehlerartikel verwendet werden, können nicht mehr gelöscht werden (@TobiasKrais)
* Weiterleitungen mit URL-kodierten Zeichen wie `%20` funktionierten nicht (@gharlan)
* Artikel-Weiterleitung auf sich selbst wird verhindert (@gharlan)
* SEO-Daten: Default-Werte wurden teils nicht richtig berücksichtigt (@gharlan)
* Fehler, wenn der Client keinen `Host`-Header sendet, beseitigt (@gharlan)
* Warning in Sitemap beseitigt (@tyrant88)


Version 2.8.3 – 15.12.2021
--------------------------

### Bugfixes

* Im Release fehlte die `.htaccess`-Datei (@gharlan)


Version 2.8.2 – 07.12.2021
--------------------------

### Bugfixes

* Notice im path_resolver wird vermieden (@gharlan)


Version 2.8.1 – 06.12.2021
--------------------------

### Bugfixes

* Update/Installation schlug fehl wegen eines Unique-Keys auf eine TEXT-Spalte (@gharlan)


Version 2.8 – 05.12.2021
--------------------------

### Neu

- Installation unter PHP 8 und mit yform 4 ermöglicht (@alxndr-w, @TobiasKrais)
- Neue REX_VAR: `REX_YREWRITE_DOMAIN` (@dergel)
- Eigene URLs können Anker (`#foo`) enthalten (@tbaddade)
- Artikel-spezifische Weiterleitungen: Original-URL ist aufrufbar und wird umgeleitet (@gharlan)
- Weiterleitungen funktionieren nun ohne Berücksichtigung von Groß-/Kleinschreibung (@gharlan)
- Weiterleitungen: URL/Ziel-URL können mehr als 191 Zeichen enthalten (@tbaddade)
- Weiterleitungen werden standardmäßig absteigend nach Erstellung sortiert (@alxndr-w)
- Unique-Keys auf Datenbankebene (@alxndr-w, @tbaddade)
- Medien über Media Manager nutzen den Addonspezifischen Media-Type `yrewrite_default` (@gharlan)
- Mime-Type für `.wasm`-Extension ergänzt (@novinet-markusd)
- Setup-Page: Vorschaulinks öffnen in neuem Tab (@frood)
- Texte/Readme optimiert (@skerbis, @tbaddade, @alxndr-w, @dergel)
- Schwedische Übersetzung (@interweave-media)

### Bugfixes

* Anpassungen für neuere yform-Versionen (@marcohanke, @alxndr-w, @tbaddade)
* Domainänderungen wirkten sich wegen Opcache teils verzögert aus (@gharlan)
* Weiterleitungen konnten keine Umlaute enthalten (@gharlan)
* Offline-Sprachen werden bei automatischer Sprachumleitung und beim 404-Artikel nicht mehr berücksichtigt (@TobiasKrais)
* Es entstanden teils Redirects mit ungültiger URL (fehlender Slash zwischendrin) (@TobiasKrais, @gharlan)
* `rex_yrewrite::getFullPath` hat im Backend eine ungültige URL geliefert (@gharlan)


Version 2.7 – 18.09.2020
--------------------------

### Neu

- URL-Typ in Artikel auswählbar: "Automatisch", "Eigene URL", "Umleitung zu Artikel", "Umleitung zu URL"
- Optional können Unicode-URLs aktiviert werden, in denen dann auch Umlaute, chinesische/kyrillische Schriftzeichen etc. erhalten bleiben
- Sitemap Darstellung .xsl verbessert
- Diverse Erklärungen/Doku verbessert (Danke alexplusde,Hirbod)
- Diverse Übersetzungen ergänzt (Danke Jürgen Weiss, Yves Torres, Fernando Averanga)
- Auto-Redirects: Umgang mit Domains beschränkt auf einzelne Sprachen korrigiert
- Auch im Backend Domain-Aliase umleiten
- noindex, follow ergänzt
- Start-Clang optional automatisch gemäß Browsersprache
- Auch wenn nur eine einzelne Sprache vorhanden ist, kann diese nun in der URL auftauchen
- Bei Artikeln aus Default-Domain relative URLs erzeugen
- Weiterleitungen für URLs mit Query-Parametern können eingerichtet werden
- Bei Custom URLs werden die Varianten mit/ohne Slash automatisch umgeleitet

### Bugs

- Korrektur für WindowsSysteme mit 'default'-Domain (Danke norbert)
- MediaManager URLs werden nun auch im Backend umgeschrieben
- Domains mit expliziter Portangabe konnten nicht gespeichert werden
- Bei Domains mit Unterordner stimmte die Sitemap nicht (Danke Daniel Springer)
- Diverse Warnings/Notices behoben


Version 2.6 – 24.09.2019
--------------------------

### Neu

- Version braucht YForm 3.x, PHP 7.x
- Diverse Übersetzungen ergänzt (Danke Yves und Fernando)
- Mediamanager Urls wegen ab R5.7 richtig umgeschrieben
- QueryCheck verbessert (Danke Hirbod)
- Doppelter EP Aufruf entfernt
- PathGenerator ausgelagert
- Dateien mit Klassen passend verschoben und benannt
- Einführung utf8mb4. Inhalte werden entsprechend konvertiert
- .htaccesss angepasst. Nicht vorhandene Backenddateien leiten nicht mehr auf Frontpage
- Permissions für das URL- und SEO-Editieren hinzugefügt (Danke Daniel Weitenauer)
- Zugriff auf die Namen der SEO-Felder ermöglichen, um sie ändern zu können (Danke Daniel Weitenauer)
- Möglichkeit zum generellen Ein- und Ausblenden der SEO-Blöcke hinzufügen (Danke Daniel Weitenauer)
- Sitemap und XSL für eine nettere Darstellung erweitert (Danke Alex Platter)
- canonical URL um EP YREWRITE_CANONICAL_URL erweitert (Danke Alex Platter)
- Domains/Aliasdomain werden nun validiert, da sonst Fehler geworfen wurden
- Bei WeiterleitungsURLs können nun auch Kommas verwendet werden
- 404 Status wird nun auch bei Startseite als Fehlerseite gesetzt

### Bugs

- Kategorie verschieben Fehler behoben (Danke Alexander Walther)
- Kategorie/Artikel löschen Fehler behoben
- Artikelfunktionen (cat2art, art2cat, art2startarticle) erzeugen nun wieder richtige Pfade
- Warnungen entfernt (Danke Alex Platter)



Version 2.5.0 – 04.02.2019
--------------------------

### Neu

- MIT Lizenz ergänzt
- AutoRedirects ergänzt. (Danke Wolfgang Bund)
- Alternativ URLs werden nun mit Query_String weitergeleitet
- getFullUrlByArticleId nun auch ohne article id möglich
- Update/Install. SQL Umbau auf ensure Basis (rex_sql_table, rex_sql_column)
- Version nun auch bei YForm 3.x wie auch bei YForm 2.x installierbar
- Anpassungen Texte, Ansichten
- Metafeld angepasst. Wenn noindex, nun auch nofollow


Version 2.4.0 – 03.11.2018
--------------------------

### Bugs

- Startartikel in Nicht-Startsprachen: Korrekten Suffix nutzen
- htaccess Anpassungen dots angepasst (Alexander Walther / alexplusde)
- EP Aufrufe ergänzt (ART_MOVED, ART_COPIED, CAT_MOVED) (Alex Wenz)
- Not Found Artikel nun auch in der richtigen Sprache
- Warnings entfernt
- Beim löschen einer Sprachen kam es bei yrewrite zu einen Error (Tobias Kreis)

### Neu

- headerstatus um 302 bei Weiterleitungen ergänzt (Wolfgang Bund)
- Ermöglicht, nur ein Zeichen in der URL einzugeben, das nicht mit einem Slash beginnt. Slash am Ende wird dennoch geblockt. (Alexander Walther / alexplusde)
- Statuscode nun auch in der Weiterleitungsübersicht (Wolfgang Bund)
- HTTP_X_FORWARDED_SERVER wird nun beachtet, falls z.B. ein Loadbalancer vorhanden ist
- Sprachen ergänzt: spanisch, englisch, schwedisch
- neue Methode rex_yrewrite::isInCurrentDomain($ArticleId)

### Docs

- Diverse Anpassungen (Alexander Walther / alexplusde)



Version 2.3.0 – 30.01.2018
--------------------------

### Info

- REDAXO 5.5 ist Vorraussetzung

### New
- Testlink in Übersicht gebaut
- Domain werden nun sortiert aufgelistet
- Fremdpages werden nun richtig in die Subnavi eingebunden
- Sprachen ergänzt und angepasst Danke ynamite, Ferando Averanga, Tina Soares, Jürgen Weiss
- Alte ComAuth Abfrage entfernt
- unbekannte Zeichen werden über iconv entfernt, bekannte Zeichen erweitert Danke Tobias Kreis
- Formularname festgelegt
- CSRF Protection eingebaut
- Dokumentation ergänzt. Danke Alex Walther und Thomas Skerbis
- Texte an diversen Stellen gekürzt und angepasst
- Sitemapausgabe hat nun einen cleanOutputBuffer
- URL Umbruch verbesser in der Artikel-URL-Ansicht
- Bei Metadescription werden nun die Inhalte ge-strip_taged
- Aufruf von media mit mediatypes in .htaccess
- Artikel-SEO: Placeholder wird bei Title angezeigt


### Bugs
- Installationsbug behoben
- Offlinesprache wird nun aus der Sitemap entfernt Danke Alex Platter
- Bei URL Generierung werden nun nur aktive Sprache unterstützt
- Warnings bei Konsolenaufrufen entfernt
- Forwarded Protokoll wird nun beachtet (Load Balancer Problem)
- Diverse Warnings entfernt


Version 2.2.0 – 15.09.2016
--------------------------

### Bugs
- Weiterleitungen funktionierten unter bestimmten Situationen nicht
- Änderungen an Domainnamen werden nun konsequent referenziert.


Version 2.1.0 – 25.08.2016
--------------------------

### Neu
- Diverse Beschreibung/Erklärungen ergänzt
- Methode getCurrentDomain ergänzt
- Diverse Sonderzeichen bei Ersetzungen ergänzt
- Schwedische Sprache ergänzt
- HreflangTag geändert
- extension point "YREWRITE_HREFLANG_TAGS" hinzugefügt
- robots.txt darf nun auch leer sein
- Custom-Urls nun auch mit "/" am Ende erlaubt
- Optional kann nun bei der Startsprache der language slug ausgeschaltet werden
- rex::getServer() wird nicht mehr überschrieben
- www. und http/s werden je nach konfigurierter Domain automatisch umgeleitet
- getFullUrlByArticleId um params und separator wie bei rex_getUrl ergänzt

### Bugs
- Startseitenerkennung korrigiert.
- Automatische Weiterleitung bei fehlendem Suffix
- Sitemap wird nun auch bei der default Domain ausgegeben
- Default clang immer auf aktuelle gesetzt
- ETag deaktiviert
- htaccess. files Ordner auf media geändert
- "Doppelter Slash"-Problem korrigiert
- Sitemap enthält keine 404 Seiten mehr
- Pfadaufruf für Windows angepasst
- Bestimmte Weiterleitung hatten nicht funktioniert.
- Weiterleitung auf Dateien ist korrigiert
- Umgang mit der default-Domain verbessert

### Info
- Textänderung: Startpunkt -> Mountpoint
- Diverse fehlende Texte ergänzt


Version 2.0.1 – 11.02.2016
--------------------------

- Texte ergänzt / deutsch und englisch
- rex_yrewrite_scheme ergänzt um getSuffix() und aufruf von normalize() erlaubt und getScheme() ergänzt
- Fehlerinfo wenn noch keine Domain vorhanden ist.

### Bugs

- Caching in der htaccess angepasst. Ist zu unkontrolliert und allgemein. Deswegen erstmal draußen
- Workaround für RewriteBase, allgemeingültig gemacht


Version 2.0.0 – 02.02.2016
--------------------------

- Portierung zu REDAXO 5
- Unterordner sind nun möglich
- http oder https, wird direkt in der Domainbezeichnung festgelegt
- Meta langhref ergänzt
- Fürs Verständnis aus "undefined" -> "default" gemacht.
- Standard-URL-Schema geändert .. aus .html -> /
- Canonical Urls ergänzt
