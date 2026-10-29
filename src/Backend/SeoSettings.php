<?php

namespace Yakamara\YRewrite\Backend;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\MediaPicker;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Field\Textarea;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;
use Yakamara\YRewrite\Seo;

/**
 * Form data of the SEO panel in the article content sidebar (`yrewrite_*` columns of `rex_article`).
 *
 * @internal
 */
final class SeoSettings
{
    #[Text(new Translatable('yrewrite_seotitle'), maxLength: 191)]
    public string $title = '';

    #[Textarea(new Translatable('yrewrite_seodescription'), rows: 3)]
    public string $description = '';

    #[MediaPicker(new Translatable('yrewrite_seoimage'), types: ['jpg', 'jpeg', 'png', 'webp', 'gif'], preview: true)]
    public ?Media $image = null;

    #[Choice(new Translatable('yrewrite_changefreq'), choices: self::changefreqChoices(...))]
    public string $changefreq = 'weekly';

    #[Choice(new Translatable('yrewrite_priority'), choices: self::priorityChoices(...), placeholder: new Translatable('yrewrite_priority_auto'))]
    public ?string $priority = null;

    #[Choice(new Translatable('yrewrite_index'), choices: [
        0 => new Translatable('yrewrite_index_status'),
        1 => new Translatable('yrewrite_index_index'),
        -1 => new Translatable('yrewrite_index_noindex'),
        2 => new Translatable('yrewrite_index_noindex_follow'),
    ])]
    public int $index = 0;

    #[Text(new Translatable('yrewrite_canonical_url'))]
    public string $canonicalUrl = '';

    /** @return array<string, Translatable> */
    public static function changefreqChoices(): array
    {
        $choices = [];
        foreach (Seo::$changefreq as $changefreq) {
            $choices[$changefreq] = new Translatable('yrewrite_changefreq_' . $changefreq);
        }

        return $choices;
    }

    /** @return array<string, Translatable> */
    public static function priorityChoices(): array
    {
        $choices = [];
        foreach (Seo::$priority as $priority) {
            $choices[$priority] = new Translatable('yrewrite_priority_' . str_replace('.', '_', $priority));
        }

        return $choices;
    }

    public static function fromArticle(Article $article): self
    {
        $data = new self();
        $data->title = (string) ($article->getValue('yrewrite_title') ?? '');
        $data->description = (string) ($article->getValue('yrewrite_description') ?? '');
        $image = (string) ($article->getValue('yrewrite_image') ?? '');
        $data->image = '' !== $image ? Media::get($image) : null;
        $changefreq = (string) ($article->getValue('yrewrite_changefreq') ?? '');
        $data->changefreq = in_array($changefreq, Seo::$changefreq, true) ? $changefreq : Seo::$changefreqDefault;
        $priority = (string) ($article->getValue('yrewrite_priority') ?? '');
        $data->priority = in_array($priority, Seo::$priority, true) ? $priority : null;
        $data->index = (int) ($article->getValue('yrewrite_index') ?? 0);
        $data->canonicalUrl = (string) ($article->getValue('yrewrite_canonical_url') ?? '');

        return $data;
    }

    /** @return array<string, string|int> */
    public function toRow(): array
    {
        return [
            'yrewrite_title' => $this->title,
            'yrewrite_description' => $this->description,
            'yrewrite_image' => $this->image?->fileName ?? '',
            'yrewrite_changefreq' => $this->changefreq,
            'yrewrite_priority' => (string) $this->priority,
            'yrewrite_index' => $this->index,
            'yrewrite_canonical_url' => $this->canonicalUrl,
        ];
    }
}
