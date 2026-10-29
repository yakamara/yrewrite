<?php

namespace Yakamara\YRewrite\Backend;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Form\Field\ArticlePicker;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Field\Url;
use Redaxo\Core\Translation\Translatable;

/**
 * Form data of the URL panel in the article content sidebar (`yrewrite_url_type`, `yrewrite_url`,
 * `yrewrite_redirection` of `rex_article`).
 *
 * `yrewrite_redirection` holds either an article id (internal) or a URL (external), so it is edited via two fields.
 *
 * @internal
 */
final class UrlSettings
{
    #[Choice(new Translatable('yrewrite_url_type'), choices: [
        'AUTO' => new Translatable('yrewrite_url_type_auto'),
        'CUSTOM' => new Translatable('yrewrite_url_type_custom'),
        'REDIRECTION_INTERNAL' => new Translatable('yrewrite_url_type_redirection_internal'),
        'REDIRECTION_EXTERNAL' => new Translatable('yrewrite_url_type_redirection_external'),
    ])]
    public string $urlType = 'AUTO';

    #[Text(new Translatable('yrewrite_url_type_custom'), maxLength: 250)]
    public string $url = '';

    #[ArticlePicker(new Translatable('yrewrite_url_type_redirection_internal'))]
    public ?Article $redirectionInternal = null;

    #[Url(new Translatable('yrewrite_url_type_redirection_external'))]
    public ?string $redirectionExternal = null;

    public static function fromArticle(Article $article): self
    {
        $data = new self();
        $data->urlType = (string) ($article->getValue('yrewrite_url_type') ?: 'AUTO');
        $data->url = (string) ($article->getValue('yrewrite_url') ?? '');
        $redirection = (string) ($article->getValue('yrewrite_redirection') ?? '');
        if ('REDIRECTION_INTERNAL' === $data->urlType && is_numeric($redirection)) {
            $data->redirectionInternal = Article::get((int) $redirection);
        }
        if ('REDIRECTION_EXTERNAL' === $data->urlType && '' !== $redirection) {
            $data->redirectionExternal = $redirection;
        }

        return $data;
    }

    /** @return array<string, string> */
    public function toRow(): array
    {
        return [
            'yrewrite_url_type' => $this->urlType,
            'yrewrite_url' => 'CUSTOM' === $this->urlType ? $this->url : '',
            'yrewrite_redirection' => match ($this->urlType) {
                'REDIRECTION_INTERNAL' => (string) ($this->redirectionInternal?->id ?? ''),
                'REDIRECTION_EXTERNAL' => (string) $this->redirectionExternal,
                default => '',
            },
        ];
    }
}
