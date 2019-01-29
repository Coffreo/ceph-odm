<?php


namespace Coffreo\CephOdm\Persister;


use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Coffreo\CephOdm\Exception\Exception;
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

        try {
            $this->saveCephData($data);
        } catch (S3Exception $e) {
            $this->handleS3Exception($object, $e);
        }

        return $data;
    }

    public function updateObject($object, ChangeSet $changeSet): array
    {
        $data = $this->prepareUpdateChangeSet($object, $changeSet);

        try {
            $this->saveCephData($data);
        } catch (S3Exception $e) {
            $this->handleS3Exception($object, $e);
        }

        return $data;
    }

    public function removeObject($object): void
    {
        $identifier = $this->getObjectIdentifier($object);

        try {
            $this->deleteCephIdentifier($identifier);
        } catch (S3Exception $e) {
            $this->handleS3Exception($object, $e);
        }
    }

    /**
     * Transform S3Exception into library dedicated exception in some cases
     */
    private function handleS3Exception($object, S3Exception $e): void
    {
        if ($e->getAwsErrorCode() == 'NoSuchBucket') {
            throw new Exception(
                sprintf("Bucket %s doesn't exist", $this->extractBucketName($object) ?: '[name not found]'),
                Exception::BUCKET_NOT_FOUND,
                $e
            );
        }

        throw $e;
    }

    abstract protected function saveCephData(array $data): void;
    abstract protected function deleteCephIdentifier(array $identifier): void;

    /**
     * Extract bucket name from an object
     */
    abstract protected function extractBucketName($object): ?string;
}