<?php

namespace EventStreamApi\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use EventStreamApi\Repository\TransportRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     collectionOperations={"get"},
 *     itemOperations={"get"},
 *     normalizationContext={
 *         "groups"={"transport:read"}
 *     }
 * )
 * @ORM\Entity(repositoryClass=TransportRepository::class)
 */
class Transport
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @Groups({"transport:read", "event:read", "subscription:read", "subscription:write"})
     */
    public string $id;

    /**
     * Pem formatted public key that corresponds to the transport's private key it will sign return messages with.
     * @ORM\Column(type="text", nullable=true)
     */
    public ?string $publicKey;

    /**
     * @var bool Whether or not to subscribe users to the transport when the transport creates an event on behalf of a user.
     */
    public bool $autoSubscribeOnEventCreate = false;

    public function __construct(string $id, ?string $publicKey = null, bool $autoSubscribeOnEventCreate = false)
    {
        $this->id = $id;
        $this->publicKey = $publicKey;
        $this->autoSubscribeOnEventCreate = $autoSubscribeOnEventCreate;
    }
}
