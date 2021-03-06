<?php
namespace EventStreamApi\MessageHandler;

use Doctrine\Persistence\ManagerRegistry;
use EventStreamApi\Entity\Subscription;
use EventStreamApi\Entity\User;
use EventStreamApi\Repository\EventRepository;
use EventStreamApi\Repository\StreamRepository;
use EventStreamApi\Repository\TransportRepository;
use EventStreamApi\Repository\UserRepository;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class TransportEventHandler implements MessageHandlerInterface
{
    public function __construct(
        private TransportRepository $transportRepository,
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private StreamRepository $streamRepository,
        private ManagerRegistry $managerRegistry
    ) { }

    public function __invoke(TransportEvent $transportEvent): void
    {
        if (!($eventTransport = $transportEvent->getEvent()->getTransport())) {
            // Ignore events missing a transport (misbehaving transport).
            return;
        }

        if (!($transport = $this->transportRepository->find($eventTransport->getName()))) {
            // Ignore events with an invalid transport (misbehaving transport).
            return;
        }

        if (!openssl_verify(
            $transportEvent->getEvent()->getId(),
            $transportEvent->getSignature(),
            $transport->publicKey
        )) {
            // Ignore events with an invalid signature (malicious transport).
            return;
        }

        if ($this->eventRepository->find($transportEvent->getEvent()->getId())) {
            // Ignore events that already exist (replay attack?)
            return;
        }

        if (!$transportEvent->getEvent()->getUser()) {
            // Ignore events without a user (invalid data)
            return;
        }

        if (!($stream = $this->streamRepository->find($transportEvent->getEvent()->getStream()->getId()))) {
            // TODO: Handle events in streams that don't exist
            return;
        }

        if (!($user = $this->userRepository->find($transportEvent->getEvent()->getUser()->getId()))
        ) {
            // Create user if transport says it exists and we don't know about it (eventual consistency)
            $subscriptionManager = $this->managerRegistry->getManagerForClass(User::class);

            if(!$subscriptionManager) {
                // This shouldn't happen
                throw new \RuntimeException("Internal server error.");
            }

            $user = new User($transportEvent->getEvent()->getUser()->getId());
            $subscriptionManager->persist($user);
            $subscriptionManager->flush();
            $transportEvent->getEvent()->setUser($user);
        }

        if (!$stream->hasUser($user)) {
            // TODO: Handle events in streams that the user doesn't belong to
            return;
        }

        // Auto subscribe user. Useful for when transport creates a user.
        if ($transport->autoSubscribeOnEventCreate &&
            ($streamUser = $user->getStreamUserForStream($stream)) &&
            !$streamUser->isSubscribed($transport->getName())
        ) {
            $subscriptionManager = $this->managerRegistry->getManagerForClass(User::class);

            if(!$subscriptionManager) {
                // This shouldn't happen
                throw new \RuntimeException("Internal server error.");
            }

            $subscription = new Subscription($transport, $streamUser);

            $subscriptionManager->persist($subscription);
            $subscriptionManager->flush();
        }

        $eventManager = $this->managerRegistry->getManagerForClass(User::class);

        if(!$eventManager) {
            // This shouldn't happen
            throw new \RuntimeException("Internal server error.");
        }

        $eventManager->persist($transportEvent->getEvent());

        $eventManager->flush();
    }
}