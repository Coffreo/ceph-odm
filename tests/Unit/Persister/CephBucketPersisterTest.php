<?php


namespace Coffreo\CephOdm\Test\Unit\Persister;


use Aws\CommandInterface;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Exception\Exception;
use Coffreo\CephOdm\Persister\CephBucketPersister;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;
use PHPUnit\Framework\TestCase;
use Coffreo\CephOdm\Test\DummyS3Client;

/**
 * @coversDefaultClass \Coffreo\CephOdm\Persister\CephBucketPersister
 */
class CephBucketPersisterTest extends TestCase
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

    public function providerRemoveObjectWithExceptionShouldThrowException(): array
    {
        $cmd = $this->createMock(CommandInterface::class);
        $object1 = new Bucket('mynonexistentbucket');
        $object2 = new Bucket('');
        $object3 = new \stdClass();

        return [
            [$object1, new \RuntimeException('myexceptionmessage', 5), \RuntimeException::class, 'myexceptionmessage', 5],
            [$object1, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'mycode']), S3Exception::class, 'myS3exceptionmessage', 0],
            [$object1, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'NoSuchBucket']), Exception::class, "Bucket mynonexistentbucket doesn't exist", Exception::BUCKET_NOT_FOUND],
            [$object2, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'NoSuchBucket']), Exception::class, "Bucket [name not found] doesn't exist", Exception::BUCKET_NOT_FOUND],
            [$object3, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'NoSuchBucket']), Exception::class, "Bucket [name not found] doesn't exist", Exception::BUCKET_NOT_FOUND]
        ];
    }

    /**
     * @dataProvider providerRemoveObjectWithExceptionShouldThrowException
     *
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::removeObject
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::handleS3Exception
     * @covers ::extractBucketName
     */
    public function testRemoveObjectWithExceptionShouldThrowException($object, \Exception $originalException, string $expectedExceptionClass, string $expectedExceptionMessage, $expectedCode): void
    {
        $sut = $this
            ->getMockBuilder(CephBucketPersister::class)
            ->disableOriginalConstructor()
            ->setMethods(['getObjectIdentifier', 'deleteCephIdentifier'])
            ->getMock();

        $sut
            ->method('deleteCephIdentifier')
            ->willThrowException($originalException);

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $this->expectExceptionCode($expectedCode);

        $sut->removeObject($object);
    }
}