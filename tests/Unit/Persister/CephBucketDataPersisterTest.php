<?php


namespace Coffreo\CephOdm\Test\Unit\Persister;


use Aws\S3\S3Client;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Persister\CephBucketPersister;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;
use PHPUnit\Framework\TestCase;
use Coffreo\CephOdm\Test\DummyS3Client;

/**
 * @coversDefaultClass \Coffreo\CephOdm\Persister\CephBucketPersister
 */
class CephBucketDataPersisterTest extends TestCase
{
    /**
     * @var S3Client|MockObject
     */
    private $client;

    /**
     * @var CephFilePersister
     */
    private $sut;

    public function setUp()
    {
        $this->client = $this->createMock(DummyS3Client::class);
        $this->sut = new CephBucketPersister($this->client, $this->createMock(ObjectManagerInterface::class), '');
    }

    /**
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::persistObject
     * @covers ::saveCephData
     */
    public function testPersistObject(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket
            ->expects($this->once())
            ->method('preparePersistChangeSet')
            ->willReturn(['Bucket' => 'mybucketname']);

        $this->client
            ->expects($this->once())
            ->method('createBucket')
            ->with(['Bucket' => 'mybucketname']);

        $this->assertEquals(['Bucket' => 'mybucketname'], $this->sut->persistObject($bucket));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Missing bucket identifier
     *
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::persistObject
     * @covers ::saveCephData
     */
    public function testPersistObjectWithoutBucketDataShouldThrowException(): void
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket
            ->expects($this->once())
            ->method('preparePersistChangeSet')
            ->willReturn(['Anotherfield' => 'mybucketname']);

        $this->client
            ->expects($this->never())
            ->method('createBucket');

        $this->sut->persistObject($bucket);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage updateObject can't be called for a Bucket object
     *
     * @covers ::updateObject
     */
    public function testUpdateObjectShouldThrowException(): void
    {
        $this->sut->updateObject($this->createMock(Bucket::class), $this->createMock(ChangeSet::class));
    }

    /**
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::removeObject
     * @covers ::deleteCephIdentifier
     */
    public function testRemoveObject(): void
    {
        $bucket = $this->createMock(Bucket::class);

        $sut = $this
            ->getMockBuilder(CephBucketPersister::class)
            ->setConstructorArgs([$this->client, $this->createMock(ObjectManagerInterface::class), ''])
            ->setMethods(['getObjectIdentifier'])
            ->getMock();

        $sut
            ->method('getObjectIdentifier')
            ->with($bucket)
            ->willReturn(['myidentifier1' => 'myidentifier1value']);

        $this->client
            ->expects($this->once())
            ->method('deleteBucket')
            ->with(['myidentifier1' => 'myidentifier1value']);

        $sut->removeObject($bucket);
    }
}