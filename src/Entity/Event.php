<?php

namespace Productively\Api\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Ramsey\Uuid\UuidInterface;

/**
 * @ApiResource(
 *     collectionOperations={"get","post"},
 *     itemOperations={"get"},
 *     normalizationContext={"groups"={"event:read"}},
 *     denormalizationContext={"groups"={"event:write"}}
 * )
 * @ORM\Entity(repositoryClass="Productively\Api\Repository\EventRepository")
 * @ORM\Table(indexes={@ORM\Index(name="group_datetime", columns={"event_group_id", "datetime"})})
 */
class Event
{
    public const TYPE_MESSAGE = "message";
    public const EPHEMERAL_TYPES = ["typing-start"];
    /**
     * @ORM\Id()
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     * @Groups({"event:read"})
     */
    protected UuidInterface $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"event:read"})
     */
    public string $userIdentifier;

    /**
     * @ORM\Column(type="datetime_immutable")
     * @Groups({"event:read"})
     */
    public \DateTimeImmutable $datetime;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"event:read", "event:write"})
     */
    public string $type;

    /**
     * @ORM\ManyToOne(targetEntity="Productively\Api\Entity\Group", inversedBy="events")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"event:read", "event:write"})
     * @ApiFilter(SearchFilter::class, properties={"eventGroup.id": "exact"})
     */
    protected Group $eventGroup;

    /**
     * @ORM\OneToOne(targetEntity="Productively\Api\Entity\MessageEventData", cascade={"persist", "remove"})
     * @Groups({"event:read", "event:write"})
     */
    protected ?MessageEventData $messageEventData;

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getEventGroup(): Group
    {
        return $this->eventGroup;
    }

    public function setEventGroup(Group $eventGroup): self
    {
        $this->eventGroup = $eventGroup;

        return $this;
    }

    public function getMessageEventData(): ?MessageEventData
    {
        return $this->messageEventData;
    }

    public function setMessageEventData(?MessageEventData $messageEventData): self
    {
        $this->messageEventData = $messageEventData;

        return $this;
    }

    public function isEphemeral(): bool
    {
        return in_array($this->type, self::EPHEMERAL_TYPES);
    }
}
