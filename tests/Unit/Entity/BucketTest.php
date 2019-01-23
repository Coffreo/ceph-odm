<?php


namespace Coffreo\CephOdm\Test\Unit\Entity;


use Coffreo\CephOdm\Entity\Bucket;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Coffreo\CephOdm\Entity\Bucket
 */
class BucketTest extends TestCase
{
    /**
     * @covers ::hydrate
     */
    public function testHydrate(): void
    {
        $sut = new Bucket();
        $sut->hydrate(['Name' => 'mybucketname'], $this->createMock(ObjectManagerInterface::class));

        $this->assertEquals('mybucketname', $sut->getName());
    }

    /**
     * @covers ::loadMetadata
     */
    public function testLoadMetadata(): void
    {
        $mock = $this->createMock(ClassMetadataInterface::class);

        $mock->expects($this->once())->method('setIdentifier')->with(['Name']);
        $mock->expects($this->once())->method('setIdentifierFieldNames')->with(['name']);
        $mock->expects($this->once())->method('mapField')->with(['fieldName' => 'name', 'name' => 'Bucket']);

        Bucket::loadMetadata($mock);
    }

    /**
     * @covers ::preparePersistChangeSet
     */
    public function testPreparePersistChangeSet(): void
    {
        $sut = new Bucket('mybucketname');
        $this->assertEquals(['Bucket' => 'mybucketname', 'Name' => 'mybucketname'], $sut->preparePersistChangeSet());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage  prepareUpdateChangeSet can't be called for a Bucket object
     *
     * @covers ::prepareUpdateChangeSet
     */
    public function testPrepareUpdateChangeSetShouldThrowException(): void
    {
        $sut = new Bucket();
        $sut->prepareUpdateChangeSet($this->createMock(ChangeSet::class));
    }
}