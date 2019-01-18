<?php


namespace Coffreo\CephOdm\Persister;

use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;

/**
 * Persister for Ceph buckets
 */
class CephBucketPersister extends AbstractCephPersister
{
    protected function saveCephData(array $data)
    {
        if (empty($data['Bucket'])) {
            throw new \InvalidArgumentException("Missing bucket identifier");
        }

        $this->client->createBucket($data);
    }

    public function updateObject($object, ChangeSet $changeSet): array
    {
        throw new \LogicException("updateObject can't be called for a Bucket object");
    }

    protected function deleteCephIdentifier(array $identifier)
    {
        $this->client->deleteBucket($identifier);
    }
}