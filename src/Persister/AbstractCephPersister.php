<?php


namespace Coffreo\CephOdm\Persister;


use Aws\S3\S3Client;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Doctrine\SkeletonMapper\Persister\BasicObjectPersister;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;

/**
 * Abstract persister for Ceph objects
 */
abstract class AbstractCephPersister extends BasicObjectPersister
{
    /**
     * Amazon S3 Client
     *
     * @var S3Client
     */
    protected $client;

    /**
     * @codeCoverageIgnore
     */
    public function __construct(S3Client $client, ObjectManagerInterface $objectManager, string $className)
    {
        parent::__construct($objectManager, $className);

        $this->client = $client;
    }

    public function persistObject($object): array
    {
        $data = $this->preparePersistChangeSet($object);

        $this->saveCephData($data);

        return $data;
    }

    public function updateObject($object, ChangeSet $changeSet): array
    {
        $data = $this->prepareUpdateChangeSet($object, $changeSet);

        $this->saveCephData($data);

        return $data;
    }

    public function removeObject($object): void
    {
        $identifier = $this->getObjectIdentifier($object);
        $this->deleteCephIdentifier($identifier);
    }

    abstract protected function saveCephData(array $data);
    abstract protected function deleteCephIdentifier(array $identifier);
}