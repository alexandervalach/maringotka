<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Router\PublicRoutes;
use Contributte\Translation\Translator;
use Nette\Application\UI\Presenter;

final class HomepagePresenter extends Presenter
{
    public function __construct(private readonly Translator $translator)
    {
        parent::__construct();
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();
        $locale = $this->getParameter('locale') === 'en' ? 'en' : 'sk';
        $this->translator->setLocale($locale);
        $this->template->setTranslator($this->translator, $locale);
        $this->template->add('locale', $locale);
        $this->template->add('paths', PublicRoutes::forLocale($locale));
        $action = $this->getAction();
        $this->template->add('languageUrls', [
            'sk' => '/' . PublicRoutes::PATHS[$action]['sk'],
            'en' => '/' . PublicRoutes::PATHS[$action]['en'],
        ]);
        $titleKey = $action === 'default' ? 'hero.headline' : 'navigation.' . $action;
        $this->template->add('title', $this->translator->translate('messages.' . $titleKey));
    }
}
