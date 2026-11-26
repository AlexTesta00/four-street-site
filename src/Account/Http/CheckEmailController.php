<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\AccountUrls;
use FourStreet\Account\Notice;
use FourStreet\Account\ReturnPath;
use FourStreet\Account\Token\TokenPurpose;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * "Controlla la posta": la pagina dopo la registrazione (o la richiesta di un link di conferma nuovo)
 * e dopo la richiesta di una nuova password. Non dice mai a quale indirizzo è partita l'email,
 * né se è partita: l'indirizzo non passa dall'URL.
 */
final readonly class CheckEmailController implements Controller
{
    /**
     * @param TokenPurpose $purpose quale email aspetta la persona
     */
    public function __construct(
        private AccountPages $pages,
        private TokenPurpose $purpose,
    ) {}

    public function __invoke(Request $request): Response
    {
        $returnTo = ReturnPath::fromQuery($request);

        return $this->pages->render('check-email', [
            'purpose' => $this->purpose,
            'alert' => Notice::fromRequest($request)?->alert(),
            'retryUrl' => match ($this->purpose) {
                TokenPurpose::EmailVerification => AccountUrls::to(AccountUrls::VERIFY_RESEND, $returnTo),
                TokenPurpose::PasswordReset => AccountUrls::to(AccountUrls::FORGOT_PASSWORD, $returnTo),
            },
            'loginUrl' => AccountUrls::to(AccountUrls::LOGIN, $returnTo),
        ]);
    }
}
