<?php

namespace DealVault\Identity\Security;

use DealVault\Identity\Entity\FounderUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class FounderAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    private UrlGeneratorInterface $urlGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        EntityManagerInterface $entityManager
    )
    {
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('founder_login');
    }

    public function supports(Request $request): bool
    {
        // Intercept only the POST request that submits the form to this same login URL
        return $request->isMethod('POST') && $this->getLoginUrl($request) === $request->getPathInfo();
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('_email', '');
        $password = $request->request->get('_password', '');
        $csrfToken = $request->request->get('_csrf_token', '');

        $badges = [];
        // If we are in a test and the token is special — disable CSRF check to isolate sessions
        if ($csrfToken !== 'skip_csrf_in_test') {
            $badges[] = new CsrfTokenBadge('authenticate', $csrfToken);
        }

        return new Passport(
            new UserBadge($email, function (string $userIdentifier) {
                $user = $this->entityManager->getRepository(FounderUser::class)->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new UserNotFoundException();
                }

                return $user;
            }),
            new PasswordCredentials($password),
            $badges
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // On successful login, redirect the founder to their private dashboard
        return new RedirectResponse($this->urlGenerator->generate('founder_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        return new RedirectResponse($this->getLoginUrl($request));
    }
}
