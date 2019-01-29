<?php


namespace Coffreo\CephOdm\Test\Unit\Persister;

use Aws\S3\S3Client;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use \Coffreo\CephOdm\Persister\CephFilePersister;
use Coffreo\CephOdm\Test\DummyS3Client;
use Aws\S3\Exception\S3Exception;
use Coffreo\CephOdm\Exception\Exception;
use Aws\CommandInterface;

/**
 * @coversDefaultClass \Coffreo\CephOdm\Persister\CephFilePersister
 */
class CephFilePersisterTest extends TestCase
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
        $this->sut = new CephFilePersister($this->client, $this->createMock(ObjectManagerInterface::class), '');
    }

    /**
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::persistObject
     * @covers ::saveCephData
     */
    public function testPersistObject(): void
    {
        $file = $this->createMock(File::class);
        $file
            ->expects($this->once())
            ->method('preparePersistChangeSet')
            ->willReturn(['mykey1' => 'myvalue1', 'mykey2' => 'myvalue2']);

        $this->client
            ->expects($this->once())
            ->method('putObject')
            ->with(['mykey1' => 'myvalue1', 'mykey2' => 'myvalue2']);

        $this->assertEquals(['mykey1' => 'myvalue1', 'mykey2' => 'myvalue2'], $this->sut->persistObject($file));
    }

    /**
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::updateObject
     * @covers ::saveCephData
     */
    public function testUpdateObject(): void
    {
        $changeSet = $this->createMock(ChangeSet::class);

        $file = $this->createMock(File::class);
        $file
            ->expects($this->once())
            ->method('prepareUpdateChangeSet')
            ->with($changeSet)
            ->willReturn(['mykey1' => 'myvalue1', 'mykey2' => 'myvalue2']);

        $this->client
            ->expects($this->once())
            ->method('putObject')
            ->with(['mykey1' => 'myvalue1', 'mykey2' => 'myvalue2']);

        $ret = $this->sut->updateObject($file, $changeSet);
        $this->assertEquals(['mykey1' => 'myvalue1', 'mykey2' => 'myvalue2'], $ret);
    }

    /**
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::removeObject
     * @covers ::deleteCephIdentifier
     */
    public function testRemoveObject(): void
    {
        $file = $this->createMock(File::class);

        $sut = $this
            ->getMockBuilder(CephFilePersister::class)
            ->setConstructorArgs([$this->client, $this->createMock(ObjectManagerInterface::class), ''])
            ->setMethods(['getObjectIdentifier'])
            ->getMock();

        $sut
            ->method('getObjectIdentifier')
            ->with($file)
            ->willReturn(['Bucket' => new Bucket('mybucket'), 'Key' => 'myid']);

        $this->client
            ->expects($this->once())
            ->method('deleteObject')
            ->with(['Bucket' => 'mybucket', 'Key' => 'myid']);

        $sut->removeObject($file);
    }

    public function providerActionObjectWithExceptionShouldThrowException(): array
    {
        $cmd = $this->createMock(CommandInterface::class);
        $object1 = new File();
        $object1->setBucket(new Bucket('mynonexistentbucket'));
        //$object2 = new File();
        $object3 = new File();
        $object3->setBucket(new Bucket(''));
        $object4 = new \stdClass();

        return [
            [$object1, new \RuntimeException('myexceptionmessage', 5), \RuntimeException::class, 'myexceptionmessage', 5],
            [$object1, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'mycode']), S3Exception::class, 'myS3exceptionmessage', 0],
            [$object1, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'NoSuchBucket']), Exception::class, "Bucket mynonexistentbucket doesn't exist", Exception::BUCKET_NOT_FOUND],
            //[$object2, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'NoSuchBucket']), Exception::class, "Bucket [name not found] doesn't exist", Exception::BUCKET_NOT_FOUND],
            [$object3, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'NoSuchBucket']), Exception::class, "Bucket [name not found] doesn't exist", Exception::BUCKET_NOT_FOUND],
            [$object4, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'NoSuchBucket']), Exception::class, "Bucket [name not found] doesn't exist", Exception::BUCKET_NOT_FOUND],
        ];
    }

    /**
     * @dataProvider providerActionObjectWithExceptionShouldThrowException
     *
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::persistObject
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::handleS3Exception
     * @covers ::extractBucketName
     */
    public function testPersistObjectWithExceptionShouldThrowException($object, \Exception $originalException, string $expectedExceptionClass, string $expectedExceptionMessage, $expectedCode): void
    {
        $sut = $this
            ->getMockBuilder(CephFilePersister::class)
            ->disableOriginalConstructor()
            ->setMethods(['preparePersistChangeSet', 'saveCephData'])
            ->getMock();

        $sut
            ->method('saveCephData')
            ->willThrowException($originalException);

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $this->expectExceptionCode($expectedCode);

        $sut->persistObject($object);
    }

    /**
     * @dataProvider providerActionObjectWithExceptionShouldThrowException
     *
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::updateObject
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::handleS3Exception
     * @covers ::extractBucketName
     */
    public function testUpdateObjectWithExceptionShouldThrowException($object, \Exception $originalException, string $expectedExceptionClass, string $expectedExceptionMessage, $expectedCode): void
    {
        $sut = $this
            ->getMockBuilder(CephFilePersister::class)
            ->disableOriginalConstructor()
            ->setMethods(['prepareUpdateChangeSet', 'saveCephData'])
            ->getMock();

        $sut
            ->method('saveCephData')
            ->willThrowException($originalException);

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $this->expectExceptionCode($expectedCode);

        $sut->updateObject($object, $this->createMock(ChangeSet::class));
    }

    /**
     * @dataProvider providerActionObjectWithExceptionShouldThrowException
     *
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::removeObject
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::handleS3Exception
     * @covers ::extractBucketName
     */
    public function testRemoveObjectWithExceptionShouldThrowException($object, \Exception $originalException, string $expectedExceptionClass, string $expectedExceptionMessage, $expectedCode): void
    {
        $sut = $this
            ->getMockBuilder(CephFilePersister::class)
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