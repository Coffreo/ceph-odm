<?php


namespace Coffreo\CephOdm\EventListener;

use Aws\S3\S3Client;
use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\SkeletonMapper\Event\LifecycleEventArgs;
use Doctrine\SkeletonMapper\ObjectIdentityMap;

/**
 * Add listener to an object
 */
class AddObjectListenerListener
{
    private $objectIdentifyMap;
    private $client;
    private $metadataFactory;

    /**
     * @codeCoverageIgnore
     */
    public function __construct(ObjectIdentityMap $objectIdentityMap, S3Client $client, ClassMetadataFactory $metadataFactory)
    {
        $this->objectIdentifyMap = $objectIdentityMap;
        $this->client = $client;
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * Add listeners to an object when it is loaded
     */
    public function postLoad(LifecycleEventArgs $lifeCycleEventArgs): void
    {
         $object = $lifeCycleEventArgs->getObject();

        if ($object instanceof NotifyIdentifierChanged) {
            $object->addIdentifierChangedListener(new ObjectIdentifierChangedListener($this->objectIdentifyMap));
        }

        if ($object instanceof NotifyLazyLoadedPropertyGet) {
            $object->addLazyLoadedPropertyGetListener(new FileLazyLoadListener($this->client, $this->metadataFactory));
        }
    }
}