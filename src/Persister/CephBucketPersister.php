<?php


namespace Coffreo\CephOdm\Persister;

use Coffreo\CephOdm\Entity\Bucket;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;

/**
 * Persister for Ceph buckets
 */
class CephBucketPersister extends AbstractCephPersister
{
    protected $requiredProperties = ['name'];

    protected function saveCephData(array $data): void
    {
        $meta = $this->objectManager->getClassMetadata(Bucket::class)->getFieldMappings();
        if (empty($data[$meta['name']['name']])) {
            throw new \InvalidArgumentException("Missing bucket identifier");
        }

        $this->client->createBucket($data);
    }

    public function updateObject($object, ChangeSet $changeSet): array
    {
        throw new \LogicException("updateObject can't be called for a Bucket object");
    }

    protected function deleteCephIdentifier(array $identifier): void
    {
        $this->client->deleteBucket($identifier);
    }

    protected function extractBucketName($object): ?string
    {
        return $object instanceof Bucket ? $object->getName() : null;
    }
}