<?php


namespace Coffreo\CephOdm\Test\Unit\EventListener;


use Coffreo\CephOdm\EventListener\AddObjectListenerListener;
use Coffreo\CephOdm\EventListener\NotifyIdentifierChanged;
use Coffreo\CephOdm\EventListener\ObjectIdentifierChangedListener;
use Doctrine\SkeletonMapper\Event\LifecycleEventArgs;
use Doctrine\SkeletonMapper\ObjectIdentityMap;
use Doctrine\SkeletonMapper\ObjectManager;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount;
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
        $object = $this->createMock(NotifyIdentifierChanged::class);
        $object
            ->expects($this->once())
            ->method('addIdentifierChangedListener')
            ->with(new ObjectIdentifierChangedListener($objectIdentifyMap));

        $lifeCycleEventArgs = new LifecycleEventArgs($object, $this->createMock(ObjectManager::class));

        $sut = new AddObjectListenerListener($objectIdentifyMap);
        $sut->postLoad($lifeCycleEventArgs);
    }
}