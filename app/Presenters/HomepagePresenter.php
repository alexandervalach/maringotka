<?php

declare(strict_types=1);

namespace App\Presenters;

use Contributte\Translation\Translator;
use Nette\Application\UI\Presenter;

final class HomepagePresenter extends Presenter
{
    public function __construct(private readonly Translator $translator)
    {
        parent::__construct();
    }

    public function renderDefault(string $locale = 'sk'): void
    {
        // URL routing is the authority for the language of this page.
        $locale = $locale === 'en' ? 'en' : 'sk';
        $this->translator->setLocale($locale);
        $this->template->setTranslator($this->translator, $locale);
        $this->template->add('locale', $locale);
        $this->template->add('title', 'Maringotka na Závadke');
    }
}
