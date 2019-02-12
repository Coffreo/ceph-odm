<?php


namespace Coffreo\CephOdm\EventListener;


use Aws\S3\S3Client;
use Coffreo\CephOdm\Entity\File;
use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;

class FileLazyLoadListener implements LazyLoadedProperyGetListener
{
    private $client;
    private $metadataFactory;

    /**
     * @codeCoverageIgnore
     */
    public function __construct(S3Client $client, ClassMetadataFactory $metadataFactory)
    {
        $this->client = $client;
        $this->metadataFactory = $metadataFactory;
    }

    public function lazyLoadedPropertyGet($object, string $propertyName): void
    {
        if (!$object instanceof File) {
            throw new \LogicException(sprintf(
                "First argument of lazyLoadedPropertyGet must be a File instance; %s given",
                is_object($object) ? get_class($object) : 'scalar'
            ));
        }

        if (!$bucket = $object->getBucket()) {
            throw new \RuntimeException("File bucket must be set");
        }

        if (!$id = $object->getId()) {
            throw new \RuntimeException("File id must be set");
        }

        $mappings = $this->metadataFactory->getMetadataFor(File::class)->getFieldMappings();
        $bucketMapping = empty($mappings['bucket']['name']) ? 'bucket' : $mappings['bucket']['name'];
        $idMapping = empty($mappings['id']['name']) ? 'id' : $mappings['id']['name'];

        $objectData = $this->client->getObject([$bucketMapping => $bucket->getName(), $idMapping => $object->getId()]);
        $object->setBin($objectData['Body']);
        $object->setAllMetadata(empty($objectData['Metadata']) ? [] : $objectData['Metadata']);
    }
}