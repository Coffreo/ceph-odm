<?php


namespace Coffreo\CephOdm\Factory;


use Aws\S3\S3Client;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;
use Coffreo\CephOdm\EventListener\AddObjectListenerListener;
use Coffreo\CephOdm\Persister\CephBucketPersister;
use Coffreo\CephOdm\Persister\CephFilePersister;
use Coffreo\CephOdm\DataRepository\CephBucketDataRepository;
use Coffreo\CephOdm\DataRepository\CephFileDataRepository;
use Coffreo\CephOdm\Repository\BucketRepository;
use Coffreo\CephOdm\Repository\FileRepository;
use Doctrine\Common\EventManager;
use Doctrine\SkeletonMapper\Events;
use Doctrine\SkeletonMapper\Hydrator\BasicObjectHydrator;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataFactory;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInstantiator;
use Doctrine\SkeletonMapper\ObjectFactory;
use Doctrine\SkeletonMapper\ObjectIdentityMap;
use Doctrine\SkeletonMapper\ObjectManager;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Doctrine\SkeletonMapper\ObjectRepository\BasicObjectRepository;
use Doctrine\SkeletonMapper\ObjectRepository\ObjectRepositoryFactory;
use Doctrine\SkeletonMapper\Persister\ObjectPersisterFactory;

/**
 * Factory for ObjectManager creation
 */
class ObjectManagerFactory
{
    public static function create(S3Client $client, EventManager $eventManager = null): ObjectManagerInterface
    {
        if ($eventManager === null) {
            $eventManager = new EventManager();
        }

        $classMetadataFactory = new ClassMetadataFactory(new ClassMetadataInstantiator());
        $objectFactory = new ObjectFactory();
        $objectRepositoryFactory = new ObjectRepositoryFactory();
        $objectPersisterFactory = new ObjectPersisterFactory();
        $objectIdentityMap = new ObjectIdentityMap($objectRepositoryFactory);

        $objectManager = new ObjectManager(
            $objectRepositoryFactory,
            $objectPersisterFactory,
            $objectIdentityMap,
            $classMetadataFactory,
            $eventManager
        );
        $eventManager->addEventListener(Events::postLoad, new AddObjectListenerListener($objectIdentityMap));

        $fileDataRepository = new CephFileDataRepository($client, $objectManager, File::class);
        $bucketDataRepository = new CephBucketDataRepository($client, $objectManager, Bucket::class);

        $filePersister = new CephFilePersister($client, $objectManager, File::class);
        $bucketPersister = new CephBucketPersister($client, $objectManager, Bucket::class);

        $hydrator = new BasicObjectHydrator($objectManager);
        $fileRepository = new BasicObjectRepository(
            $objectManager,
            $fileDataRepository,
            $objectFactory,
            $hydrator,
            $eventManager,
            File::class
        );

        $bucketRepository = new BasicObjectRepository(
            $objectManager,
            $bucketDataRepository,
            $objectFactory,
            $hydrator,
            $eventManager,
            Bucket::class
        );

        $objectRepositoryFactory->addObjectRepository(File::class, new FileRepository($fileRepository));
        $objectRepositoryFactory->addObjectRepository(Bucket::class, new BucketRepository($bucketRepository));

        $objectPersisterFactory->addObjectPersister(File::class, $filePersister);
        $objectPersisterFactory->addObjectPersister(Bucket::class, $bucketPersister);

        return $objectManager;
    }
}