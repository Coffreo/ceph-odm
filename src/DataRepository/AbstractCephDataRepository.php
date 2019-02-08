<?php


namespace Coffreo\CephOdm\DataRepository;

use Doctrine\SkeletonMapper\DataRepository\BasicObjectDataRepository;
use Aws\S3\S3Client;
use Doctrine\SkeletonMapper\ObjectManagerInterface;

/**
 * Abtrsact repository for Ceph objects
 */
abstract class AbstractCephDataRepository extends BasicObjectDataRepository
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

    protected function getIdentifier() : array
    {
        return $this->objectManager
            ->getClassMetadata($this->getClassName())
            ->getIdentifierFieldNames();
    }
}