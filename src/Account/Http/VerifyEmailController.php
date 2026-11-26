<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\AccountUrls;
use FourStreet\Account\EmailVerification;
use FourStreet\Account\Form\ConfirmEmailForm;
use FourStreet\Account\Notice;
use FourStreet\Account\Session\SessionManager;
use FourStreet\Account\Session\Visitor;
use FourStreet\Account\VerificationResult;
use FourStreet\Account\VerificationStatus;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;

/**
 * /account/verifica/?token=…: il link dell'email di registrazione. Aperto nel browser della registrazione
 * conferma l'email, apre la sessione e porta alla destinazione (quella chiesta alla registrazione, oppure la
 * pagina dell'account). Aperto altrove mostra la pagina della password: il link non cambia nulla finché
 * non arriva la password giusta (POST, con il token nel campo nascosto). Il token non resta nell'indirizzo
 * dopo la conferma, e la pagina non lo manda come Referer a nessuno.
 */
final readonly class VerifyEmailController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private EmailVerification $verification,
        private SessionManager $sessions,
        private SignupBrowser $signup,
    ) {}

    public function __invoke(Request $request): Response
    {
        // HEAD lo mandano i programmi che controllano i link: non deve cambiare nulla.
        if ($request->method === 'HEAD') {
            return Response::html('');
        }

        $submitted = $request->method === 'POST';
        $token = ($submitted ? $request->bodyParam(AccountUrls::TOKEN_PARAMETER) : $request->queryParam(AccountUrls::TOKEN_PARAMETER)) ?? '';
        $result = $submitted
            ? $this->verification->verifyWithPassword($token, ConfirmEmailForm::password($request), $request->remoteAddress)
            : $this->verification->verify($token, $this->signup->hash($request));

        return match ($result->status) {
            VerificationStatus::Verified => $this->verified($result, $request),
            VerificationStatus::PasswordRequired => $submitted
                ? $this->passwordPage($request, $result, $token, ConfirmEmailForm::missingPassword(), 422)
                : $this->passwordPage($request, $result, $token, ConfirmEmailForm::blank()),
            VerificationStatus::WrongPassword => $this->passwordPage($request, $result, $token, ConfirmEmailForm::wrongPassword(), 422),
            VerificationStatus::Throttled => $this->pages->tooManyAttempts(
                'verify-password',
                $this->passwordData($request, $result, $token, ConfirmEmailForm::blank()),
                $result->retryAfter,
            ),
            VerificationStatus::AlreadyVerified => Visitor::of($request)->isLoggedIn()
                ? Response::seeOther(AccountUrls::HOME)
                : $this->pages->render('verify-done'),
            VerificationStatus::Expired => $this->pages->render('verify-failed', ['expired' => true], 410),
            VerificationStatus::Invalid => $this->pages->render('verify-failed', ['expired' => false], 404),
        };
    }

    private function verified(VerificationResult $result, Request $request): Response
    {
        $location = $result->returnTo->isDefault()
            ? AccountUrls::to(AccountUrls::HOME, notice: Notice::EmailVerified)
            : $result->returnTo->value;

        return Response::seeOther($location)
            ->withCookie($this->sessions->start($result->user(), $request))
            ->withCookie($this->signup->forget());
    }

    private function passwordPage(Request $request, VerificationResult $result, string $token, ConfirmEmailForm $form, int $status = 200): Response
    {
        return $this->pages->render('verify-password', $this->passwordData($request, $result, $token, $form), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function passwordData(Request $request, VerificationResult $result, string $token, ConfirmEmailForm $form): array
    {
        return [
            'csrf' => CsrfToken::of($request)->value,
            'form' => $form,
            'token' => $token,
            'email' => $result->user()->email->value,
            'returnTo' => $result->returnTo,
            'alert' => null,
        ];
    }
}
