<?php


namespace Coffreo\CephOdm\Entity;

use Doctrine\SkeletonMapper\Hydrator\HydratableInterface;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface;
use Doctrine\SkeletonMapper\Mapping\LoadMetadataInterface;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Doctrine\SkeletonMapper\Persister\IdentifiableInterface;
use Doctrine\SkeletonMapper\Persister\PersistableInterface;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;

/**
 * Container for Ceph files
 */
class Bucket implements HydratableInterface, IdentifiableInterface, LoadMetadataInterface, PersistableInterface
{
    /**
     * Bucket name
     *
     * @var string
     */
    private $name;

    /**
     * @codeCoverageIgnore
     */
    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function hydrate(array $data, ObjectManagerInterface $objectManager): void
    {
        $this->name = $data['Name'];
    }

    public static function loadMetadata(ClassMetadataInterface $metadata): void
    {
        $metadata->setIdentifier(['Name']);
        $metadata->setIdentifierFieldNames(['name']);
        $metadata->mapField(['fieldName' => 'name', 'name' => 'Bucket']);
    }

    public function preparePersistChangeSet(): array
    {
        return ['Bucket' => $this->name, 'Name' => $this->name];
    }

    public function prepareUpdateChangeSet(ChangeSet $changeSet): array
    {
        throw new \LogicException("prepareUpdateChangeSet can't be called for a Bucket object");
    }

    /**
     * @codeCoverageIgnore
     */
    public function assignIdentifier(array $identifier): void
    {
        $this->name = $identifier['Name'];
    }
}