<?php


namespace Coffreo\CephOdm\Persister;

use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;

/**
 * Persister for Ceph file objects
 */
class CephFilePersister extends AbstractCephPersister
{
    protected $requiredProperties = ['bucket', 'bin'];

    protected function saveCephData(array $data): void
    {
        if (isset($data['Bucket']) && $data['Bucket'] instanceof Bucket) {
            $data['Bucket'] = $data['Bucket']->getName();
        }

        $this->client->putObject($data);
    }

    protected function deleteCephIdentifier(array $identifier): void
    {
        if (isset($identifier['Bucket']) && $identifier['Bucket'] instanceof Bucket) {
            $identifier['Bucket'] = $identifier['Bucket']->getName();
        }

        $this->client->deleteObject($identifier);
    }

    protected function extractBucketName($object): ?string
    {
        if ($object instanceof File) {
            if ($bucket = $object->getBucket()) {
                return $bucket->getName();
            }
        }

        return null;
    }
}