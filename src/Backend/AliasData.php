<?php

namespace Yakamara\YRewrite\Backend;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Translation\Translatable;

/**
 * Form data of a row of `rex_yrewrite_alias` (backend page "Alias domains").
 *
 * @internal
 */
final class AliasData
{
    #[Text(new Translatable('yrewrite_alias_domain_refersto'), note: new Translatable('yrewrite_alias_domain_refersto_notice'), required: true)]
    public string $aliasDomain = '';

    #[Choice(new Translatable('yrewrite_domain_willbereferdto'), choices: self::domainChoices(...), required: true, placeholder: '')]
    public ?int $domainId = null;

    #[Choice(new Translatable('yrewrite_clang_start'), choices: new LanguageChoices())]
    public int $clangStart = 1;

    /** @return array<int, string> */
    public static function domainChoices(): array
    {
        $choices = [];
        foreach (Sql::factory()->getArray('SELECT id, domain FROM ' . Core::TABLE_PREFIX . 'yrewrite_domain ORDER BY domain') as $row) {
            $choices[(int) $row['id']] = (string) $row['domain'];
        }

        return $choices;
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = new self();
        $data->aliasDomain = (string) $row['alias_domain'];
        $data->domainId = (int) $row['domain_id'] ?: null;
        $data->clangStart = (int) $row['clang_start'] ?: 1;

        return $data;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'alias_domain' => $this->aliasDomain,
            'domain_id' => $this->domainId ?? 0,
            'clang_start' => $this->clangStart,
        ];
    }
}
