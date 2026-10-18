<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Infrastructure\Admin\AdminCredentials;
use App\Infrastructure\Admin\AdminLoginLimiter;
use App\Infrastructure\Database\ConnectionFactory;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Http\Session;

/** Single-admin authentication; no CMS write endpoints in this presenter. */
final class AdminPresenter extends Presenter
{
    private const SECTION = 'maringotka_admin_v2';

    public function __construct(
        private readonly ConnectionFactory $connectionFactory,
        private readonly Session $session,
    ) {
        parent::__construct();
    }

    protected function startup(): void
    {
        parent::startup();
        $this->getHttpResponse()->setHeader('Cache-Control', 'no-store, private');
        $this->getHttpResponse()->setHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->getHttpResponse()->setHeader('Referrer-Policy', 'no-referrer');
        $this->getHttpResponse()->setHeader('X-Content-Type-Options', 'nosniff');
        // Start before rendering: Nette Forms CSRF protection needs the session.
        $this->session->start();
    }

    public function actionDefault(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('login');
        }
    }

    public function actionLogin(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('default');
        }
    }

    private function isLoggedIn(): bool
    {
        return $this->session->getSection(self::SECTION)->get('authenticated') === true;
    }

    protected function createComponentLoginForm(): Form
    {
        $form = new Form();
        $form->addText('username', 'Používateľské meno')
            ->setRequired('Zadajte používateľské meno.')
            ->setMaxLength(64)
            ->setHtmlAttribute('autocomplete', 'username');
        $form->addPassword('password', 'Heslo')
            ->setRequired('Zadajte heslo.')
            ->setHtmlAttribute('autocomplete', 'current-password');
        $form->addSubmit('submit', 'Prihlásiť sa');
        $form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');

        $form->onSuccess[] = function (Form $form): void {
            $values = $form->getValues('array');
            $username = (string) $values['username'];
            $password = (string) $values['password'];
            $ipAddress = $this->getHttpRequest()->getRemoteAddress() ?? '';

            $pdo = $this->connectionFactory->create();
            $limiter = new AdminLoginLimiter($pdo);
            if ($limiter->isBlocked($ipAddress)) {
                $form->addError('Prihlásenie sa nepodarilo. Skúste to neskôr.');
                return;
            }

            $credentials = new AdminCredentials($pdo);
            if (!$credentials->verify($username, $password)) {
                $limiter->recordFailure($ipAddress);
                $form->addError('Nesprávne prihlasovacie údaje alebo dočasné obmedzenie prihlásenia.');
                return;
            }

            $limiter->clear($ipAddress);
            $this->session->regenerateId();
            $section = $this->session->getSection(self::SECTION);
            $section->set('authenticated', true);
            $section->setExpiration('30 minutes');
            $this->redirect('default');
        };

        return $form;
    }

    protected function createComponentLogoutForm(): Form
    {
        $form = new Form();
        $form->addSubmit('submit', 'Odhlásiť sa');
        $form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');
        $form->onSuccess[] = function (): void {
            $this->session->getSection(self::SECTION)->remove('authenticated');
            $this->session->regenerateId();
            $this->redirect('login');
        };

        return $form;
    }
}
