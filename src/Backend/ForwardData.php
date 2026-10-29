<?php

namespace Yakamara\YRewrite\Backend;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Form\Field\ArticlePicker;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\DatePicker;
use Redaxo\Core\Form\Field\MediaPicker;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/**
 * Form data of a row of `rex_yrewrite_forward` (backend page "Forwards").
 *
 * @internal
 */
final class ForwardData
{
    #[Choice(new Translatable('yrewrite_forward_status'), choices: [1 => new Translatable('yrewrite_forward_active'), 0 => new Translatable('yrewrite_forward_inactive')])]
    public int $status = 1;

    #[Choice(new Translatable('yrewrite_domain'), choices: AliasData::domainChoices(...), required: true, placeholder: '')]
    public ?int $domainId = null;

    #[Text(new Translatable('yrewrite_forward_url'), note: new Translatable('yrewrite_forward_url_info'), required: true, maxLength: 255)]
    public string $url = '';

    #[Choice(new Translatable('yrewrite_forward_move_method'), choices: [
        301 => new Translatable('yrewrite_forward_301'),
        302 => new Translatable('yrewrite_forward_302'),
        303 => new Translatable('yrewrite_forward_303'),
        307 => new Translatable('yrewrite_forward_307'),
    ])]
    public int $movetype = 303;

    #[Choice(new Translatable('yrewrite_forward_type'), choices: [
        'article' => new Translatable('yrewrite_forward_type_article'),
        'extern' => new Translatable('yrewrite_forward_type_extern'),
        'media' => new Translatable('yrewrite_forward_type_media'),
    ])]
    public string $type = 'article';

    #[ArticlePicker(new Translatable('yrewrite_forward_article_id'), key: 'article_id')]
    public ?Article $article = null;

    #[Choice(new Translatable('yrewrite_forward_clang'), choices: new LanguageChoices())]
    public int $clang = 1;

    #[Text(new Translatable('yrewrite_forward_extern'), maxLength: 512)]
    public string $extern = '';

    #[MediaPicker(new Translatable('yrewrite_forward_media'))]
    public ?Media $media = null;

    #[DatePicker(new Translatable('yrewrite_expiry_date'))]
    public ?string $expiryDate = null;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = new self();
        $data->status = (int) $row['status'];
        $data->domainId = (int) $row['domain_id'] ?: null;
        $data->url = (string) $row['url'];
        $data->movetype = (int) $row['movetype'] ?: 303;
        $data->type = (string) $row['type'] ?: 'article';
        $data->article = (int) $row['article_id'] > 0 ? Article::get((int) $row['article_id']) : null;
        $data->clang = (int) $row['clang'] ?: 1;
        $data->extern = (string) $row['extern'];
        $data->media = '' !== (string) $row['media'] ? Media::get((string) $row['media']) : null;
        $expiry = (string) ($row['expiry_date'] ?? '');
        $data->expiryDate = '' === $expiry || str_starts_with($expiry, '0000') ? null : $expiry;

        return $data;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'status' => $this->status,
            'domain_id' => $this->domainId ?? 0,
            'url' => $this->url,
            'movetype' => (string) $this->movetype,
            'type' => $this->type,
            'article_id' => $this->article?->id ?? 0,
            'clang' => $this->clang,
            'extern' => $this->extern,
            'media' => $this->media?->fileName ?? '',
            'expiry_date' => $this->expiryDate,
        ];
    }
}
