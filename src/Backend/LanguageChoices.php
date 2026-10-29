<?php

namespace Yakamara\YRewrite\Backend;

use Override;
use Redaxo\Core\Form\ChoiceProvider;
use Redaxo\Core\Language\Language;

/** All languages as choices (`id => name`), usable in field attributes: `choices: new LanguageChoices()`. */
final readonly class LanguageChoices implements ChoiceProvider
{
    #[Override]
    public function choices(): array
    {
        $choices = [];
        foreach (Language::getAll() as $language) {
            $choices[$language->id] = $language->name;
        }

        return $choices;
    }
}
