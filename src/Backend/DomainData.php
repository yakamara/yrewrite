<?php

namespace Yakamara\YRewrite\Backend;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Form\Field\ArticlePicker;
use Redaxo\Core\Form\Field\Checkbox;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\Number;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Field\Textarea;
use Redaxo\Core\Translation\Translatable;
use Yakamara\YRewrite\Seo;

use function is_array;

/**
 * Form data of a row of `rex_yrewrite_domain` (backend page "Domains").
 *
 * The pipe notation of the `clangs` and checkbox columns is kept, as the config generator reads it.
 *
 * @internal
 */
final class DomainData
{
    #[Text(new Translatable('yrewrite_domain'), note: new Translatable('yrewrite_domain_info'), required: true)]
    public string $domain = '';

    #[ArticlePicker(new Translatable('yrewrite_mount_id'), note: new Translatable('yrewrite_mount_info'), key: 'mount_id')]
    public ?Article $mount = null;

    #[ArticlePicker(new Translatable('yrewrite_start_id'), note: new Translatable('yrewrite_start_info'), required: true, key: 'start_id')]
    public ?Article $start = null;

    #[ArticlePicker(new Translatable('yrewrite_notfound_id'), note: new Translatable('yrewrite_notfound_info'), required: true, key: 'notfound_id')]
    public ?Article $notfound = null;

    /** @var list<int> */
    #[Choice(new Translatable('yrewrite_clangs'), choices: new LanguageChoices(), multiple: true, note: new Translatable('yrewrite_clangs_info'))]
    public array $clangs = [];

    #[Choice(new Translatable('yrewrite_clang_start'), choices: new LanguageChoices(), note: new Translatable('yrewrite_clang_start_info'))]
    public int $clangStart = 1;

    #[Checkbox(new Translatable('yrewrite_clang_start_hidden'))]
    public bool $clangStartHidden = false;

    #[Checkbox(new Translatable('yrewrite_clang_start_auto'))]
    public bool $clangStartAuto = false;

    #[Text(new Translatable('yrewrite_domain_title_scheme'), note: new Translatable('yrewrite_domain_title_scheme_info'))]
    public string $titleScheme = '';

    #[Text(new Translatable('yrewrite_domain_description'))]
    public string $description = '';

    #[Checkbox(new Translatable('yrewrite_auto_redirect'))]
    public bool $autoRedirect = false;

    #[Number(new Translatable('yrewrite_auto_redirect_days'), note: new Translatable('yrewrite_auto_redirect_days_info'), min: 0)]
    public int $autoRedirectDays = 0;

    #[Textarea(new Translatable('yrewrite_domain_robots'))]
    public string $robots = '';

    public static function defaults(): self
    {
        $data = new self();
        $data->titleScheme = Seo::$titleSchemeDefault;
        $data->robots = Seo::$robotsDefault;

        return $data;
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = new self();
        $data->domain = (string) $row['domain'];
        $data->mount = (int) $row['mount_id'] > 0 ? Article::get((int) $row['mount_id']) : null;
        $data->start = Article::get((int) $row['start_id']);
        $data->notfound = Article::get((int) $row['notfound_id']);
        $data->clangs = self::idList($row['clangs'] ?? '');
        $data->clangStart = (int) $row['clang_start'] ?: 1;
        $data->clangStartHidden = self::flag($row['clang_start_hidden'] ?? '');
        $data->clangStartAuto = self::flag($row['clang_start_auto'] ?? '');
        $data->titleScheme = (string) $row['title_scheme'];
        $data->description = (string) $row['description'];
        $data->autoRedirect = self::flag($row['auto_redirect'] ?? '');
        $data->autoRedirectDays = (int) $row['auto_redirect_days'];
        $data->robots = (string) $row['robots'];

        return $data;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'domain' => $this->domain,
            'mount_id' => $this->mount?->id,
            'start_id' => $this->start?->id ?? 0,
            'notfound_id' => $this->notfound?->id ?? 0,
            'clangs' => [] === $this->clangs ? '' : '|' . implode('|', $this->clangs) . '|',
            'clang_start' => $this->clangStart,
            'clang_start_hidden' => $this->clangStartHidden ? '|1|' : '',
            'clang_start_auto' => $this->clangStartAuto ? '|1|' : '',
            'title_scheme' => $this->titleScheme,
            'description' => $this->description,
            'auto_redirect' => $this->autoRedirect ? '|1|' : '',
            'auto_redirect_days' => $this->autoRedirectDays,
            'robots' => $this->robots,
        ];
    }

    /** @return list<int> */
    private static function idList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('intval', $value));
        }
        $value = trim((string) $value, '|, ');

        return '' === $value ? [] : array_values(array_map('intval', (array) preg_split('/[|,]+/', $value)));
    }

    private static function flag(mixed $value): bool
    {
        return '' !== trim((string) $value, '| ') && '0' !== trim((string) $value, '| ');
    }
}
