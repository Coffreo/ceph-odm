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
        $this->replaceBucketObjectByBucketName($data);
        $this->client->putObject($data);
    }

    protected function deleteCephIdentifier(array $identifier): void
    {
        $this->replaceBucketObjectByBucketName($identifier);
        $this->client->deleteObject($identifier);
    }

    private function replaceBucketObjectByBucketName(&$data): void
    {
        $meta = $this->objectManager->getClassMetadata(File::class)->getFieldMappings();
        $bucketMapping = $meta['bucket']['name'];

        if (isset($data[$bucketMapping]) && $data[$bucketMapping] instanceof Bucket) {
            $data[$bucketMapping] = $data[$bucketMapping]->getName();
        }
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