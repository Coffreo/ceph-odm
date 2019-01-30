<?php


namespace Coffreo\CephOdm\Persister;


use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Coffreo\CephOdm\Exception\Exception;
use Coffreo\CephOdm\Extractor\ChangeSetExtractor;
use Coffreo\CephOdm\Extractor\ExtractorInterface;
use Coffreo\CephOdm\Extractor\GetterExtractor;
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
     * Object properties that must not be empty to insert/update the object
     *
     * @var array
     */
    protected $requiredProperties = [];

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

        $this->checkRequiredProperties($object, new GetterExtractor());

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

        $this->checkRequiredProperties($changeSet, new ChangeSetExtractor());

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

    /**
     * Check that properties defined in requiredProperties are not empty for provided object
     */
    private function checkRequiredProperties($object, ExtractorInterface $extractor): void
    {
        foreach ($this->requiredProperties as $propertyName) {
            try {
                $extracted = $extractor->extract($object, $propertyName);
            } catch (\InvalidArgumentException $e) {
                if ($e->getCode() == 404) {
                    continue;
                }

                throw $e;
            }

            if (empty($extracted)) {
                throw new Exception(sprintf("Empty required property %s", $propertyName), Exception::MISSING_REQUIRED_PROPERTY);
            }
        }
    }

    abstract protected function saveCephData(array $data): void;
    abstract protected function deleteCephIdentifier(array $identifier): void;

    /**
     * Extract bucket name from an object
     */
    abstract protected function extractBucketName($object): ?string;
}