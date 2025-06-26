<?php

/**
 * @psalm-scope-this rex_addon
 * @var rex_addon $this
 */

$readme_i18n = rex_path::addon($this->getName()).'/docs/README.'.rex::getUser()->getLanguage().'.md';

if (rex::getUser()->getLanguage() && file_exists($readme_i18n)) {
    $readme = file_get_contents($readme_i18n);
} else {
    $readme = file_get_contents(rex_path::addon($this->getName()).'README.md');
}
$readme = preg_replace('/<a name=".*"\>\<\/a\>/', '', $readme); // manuelle Navigations-Anker entfernen
$readme = preg_replace('/http.*\/assets\//', '/assets/addons/'.$this->getName().'/', $readme); // Bilder lokal laden
$h2_chapter = explode("\n# ", "\n".$readme);

$readme_chapters = [];
$navi_list = [];
$docs_chapter_active = rex_request('docs_chapter_active', 'string', false);

foreach ($h2_chapter as $h2_index => $h2_content) {
    preg_match('/(.*)\n^(?:.|\n(?!#))*/m', $h2_content, $headline);

    $h2_index = (isset($headline[0])) ? rex_string::normalize($headline[0]) : '';
    preg_match_all('/(?!## )*^## (.*)\n((?:.|\n(?!## ))*)/m', $h2_content, $matches);
    if (isset($headline[1]) && count(array_filter($matches))) {
        if ($docs_chapter_active && $docs_chapter_active == $h2_index) {
            $class = 'panel-primary';
            $navi_list[] = '<div class="panel '.$class.'"><div class="panel-heading"><strong>'.$headline[0].'</strong></div><div class="list-group">';
        } else {
            $class = 'panel-default';
            $navi_list[] = '<div class="panel '.$class.'"><div class="panel-heading"><a class="" href="index.php?page='.$this->getName().'/docs&amp;docs_chapter_active='.$h2_index.'"><strong>'.$headline[0].'</strong></a></div><div class="list-group">';
        }
        $readme_h2_content = '# '.$headline[0];
        $navi_elements = $matches[1];
        $blocks = $matches[2];
        $readme_chapters[$h2_index] = $readme_h2_content;
        foreach ($navi_elements as $h3_index => $navi_element) {
            $navi_list[] = '<a class="list-group-item" href="index.php?page='.$this->getName().'/docs&amp;docs_chapter_active='.$h2_index.'#'.rex_string::normalize($navi_element).'">'.$navi_element.'</a>';
            $readme_chapters[$h2_index] .= '<a id="'.rex_string::normalize($navi_element).'"></a>'."\n## ".$navi_element.$blocks[$h3_index]."\n";
        }
        $navi_list[] = '</div></div>';
    }
}
reset($readme_chapters);
$docs_chapter_active = rex_request('docs_chapter_active', 'string', key($readme_chapters));

if (!isset($readme_chapters[$docs_chapter_active])) {
    $docs_chapter_active = key($readme_chapters);
}
$navi_view = implode("\n", $navi_list);
$blocks_view = $readme_chapters[$docs_chapter_active];

$miu = rex_markdown::factory();

// Navigation

$blocks_view = $miu->parse($blocks_view);
$fragment = new rex_fragment();
$fragment->setVar('title', $this->i18n('docs').' [ <a target="_blank" href="'.$this->getSupportPage().'blob/main/README.md">bearbeiten</a> ]', false);
$fragment->setVar('body', $blocks_view, false);
$content = $fragment->parse('core/page/section.php');

$name = $this->getPackageId();
$version = $this->getVersion();
$author = $this->getAuthor();

$navi_view .= 'Credits: ' . $author;

echo '<section class="rex-docs">
    <div class="row">
        <div class="col-md-4 docs-nav">'.$navi_view.'</div>
        <div class="col-md-8 docs-content">'.$content.'</div>
    </div>
</section>';
// Dirty image overflow fix
echo '<style nonce="' . rex_response::getNonce() . '"> .rex-docs img { max-width: 100%; } </style>';
