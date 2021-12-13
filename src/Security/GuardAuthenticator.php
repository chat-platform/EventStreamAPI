<?php
namespace EventStreamApi\Security;

use EventStreamApi\Entity\User;
use EventStreamApi\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Doctrine\Persistence\ManagerRegistry;

class GuardAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private TokenVerifier $tokenVerifier,
        private ManagerRegistry $managerRegistry,
        private UserRepository $userRepository
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization') &&
            str_starts_with($request->headers->get('Authorization'), 'Bearer');
    }

    public function authenticate(Request $request): Passport
    {
        $token = str_replace("Bearer ", "", $request->headers->get('Authorization'));

        try {
            $validatedToken = $this->tokenVerifier->verify($token);
        } catch (\Throwable $exception) {
            throw new CustomUserMessageAuthenticationException("Unable to validate JWT: " . $exception->getMessage(), [], $exception->getCode(), $exception);
        }

        return new SelfValidatingPassport(new UserBadge($validatedToken["sub"], function($userId) {
            $user = $this->userRepository->find($userId);

            if(!$user) {
                $user = $this->createUserFromRemoteUser($userId);
            }

            return $user;
        }));
    }

    private function createUserFromRemoteUser(string $tokenSubject): User
    {
        $entityManager = $this->managerRegistry->getManagerForClass(User::class);

        if(!$entityManager) {
            //This shouldn't happen
            throw new \RuntimeException("Internal server error.");
        }

        $user = new User($tokenSubject);
        $entityManager->persist($user);

        $entityManager->flush();
        $entityManager->refresh($user);

        return $user;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}