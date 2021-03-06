<?php


namespace Coffreo\CephOdm\Test\Unit\EventListener;


use Aws\S3\S3Client;
use Coffreo\CephOdm\EventListener\AddObjectListenerListener;
use Coffreo\CephOdm\EventListener\FileLazyLoadListener;
use Coffreo\CephOdm\EventListener\NotifyIdentifierChanged;
use Coffreo\CephOdm\EventListener\NotifyLazyLoadedPropertyGet;
use Coffreo\CephOdm\EventListener\ObjectIdentifierChangedListener;
use Doctrine\SkeletonMapper\Event\LifecycleEventArgs;
use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\SkeletonMapper\ObjectIdentityMap;
use Doctrine\SkeletonMapper\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Coffreo\CephOdm\EventListener\AddObjectListenerListener
 */
class AddObjectListenerListenerTest extends TestCase
{
    /**
     * @covers ::postLoad
     */
    public function testPostLoad(): void
    {
        $objectIdentifyMap = $this->createMock(ObjectIdentityMap::class);
        $client = $this->createMock(S3Client::class);
        $metadataClass = $this->createMock(ClassMetadataFactory::class);
        $object = $this->createMock([NotifyIdentifierChanged::class, NotifyLazyLoadedPropertyGet::class]);
        $object
            ->expects($this->once())
            ->method('addIdentifierChangedListener')
            ->with(new ObjectIdentifierChangedListener($objectIdentifyMap));

        $object
            ->expects($this->once())
            ->method('addLazyLoadedPropertyGetListener')
            ->with(new FileLazyLoadListener($client, $metadataClass));

        $lifeCycleEventArgs = new LifecycleEventArgs($object, $this->createMock(ObjectManager::class));

        $sut = new AddObjectListenerListener($objectIdentifyMap, $client, $metadataClass);
        $sut->postLoad($lifeCycleEventArgs);
    }
}