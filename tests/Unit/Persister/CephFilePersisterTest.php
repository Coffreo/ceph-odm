<?php


namespace Coffreo\CephOdm\Test\Unit\Persister;

use Aws\S3\S3Client;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Doctrine\SkeletonMapper\UnitOfWork\Change;
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
            ->method('getBucket')
            ->willReturn(new Bucket('mybucket'));

        $file
            ->method('getBin')
            ->willReturn('mydata');

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
            ->method('getBucket')
            ->willReturn(new Bucket('mybucket'));

        $file
            ->method('getBin')
            ->willReturn('mydata');

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
        $object1->setBin('mydata');

        $bucket = $this->createMock(Bucket::class);
        $object2 = new DummyFile($bucket);

        return [
            [$object1, new \RuntimeException('myexceptionmessage', 5), \RuntimeException::class, 'myexceptionmessage', 5],
            [$object1, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'mycode']), S3Exception::class, 'myS3exceptionmessage', 0],
            [$object1, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'NoSuchBucket']), Exception::class, "Bucket mynonexistentbucket doesn't exist", Exception::BUCKET_NOT_FOUND],
            [$object2, new S3Exception('myS3exceptionmessage', $cmd, ['code' => 'NoSuchBucket']), Exception::class, "Bucket [name not found] doesn't exist", Exception::BUCKET_NOT_FOUND]
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

    /**
     * @expectedException \Coffreo\CephOdm\Exception\Exception
     * @expectedExceptionMessage Empty required property bucket
     * @expectedExceptionCode \Coffreo\CephOdm\Exception\Exception::MISSING_REQUIRED_PROPERTY
     *
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::checkRequiredProperties
     */
    public function testPersistObjectWithEmptyBucketShouldThrowException(): void
    {
        $sut = $this
            ->getMockBuilder(CephFilePersister::class)
            ->disableOriginalConstructor()
            ->setMethods(['preparePersistChangeSet'])
            ->getMock();

        $sut->persistObject(new File());
    }

    /**
     * @expectedException \Coffreo\CephOdm\Exception\Exception
     * @expectedExceptionMessage Empty required property bin
     * @expectedExceptionCode \Coffreo\CephOdm\Exception\Exception::MISSING_REQUIRED_PROPERTY
     *
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::checkRequiredProperties
     */
    public function testPersistObjectWithEmptyBinShouldThrowException(): void
    {
        $sut = $this
            ->getMockBuilder(CephFilePersister::class)
            ->disableOriginalConstructor()
            ->setMethods(['preparePersistChangeSet'])
            ->getMock();

        $file = new File();
        $file->setBucket(new Bucket('mybucket'));
        $sut->persistObject($file);
    }

    /**
     * @expectedException \Coffreo\CephOdm\Exception\Exception
     * @expectedExceptionMessage Empty required property bucket
     * @expectedExceptionCode \Coffreo\CephOdm\Exception\Exception::MISSING_REQUIRED_PROPERTY
     *
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::checkRequiredProperties
     */
    public function testUpdateObjectWithEmptyBucketShouldThrowException(): void
    {
        $sut = $this
            ->getMockBuilder(CephFilePersister::class)
            ->disableOriginalConstructor()
            ->setMethods(['preparePersistChangeSet'])
            ->getMock();

        $bucket = new Bucket('mybucket');
        $file = new File();
        $file->setBucket($bucket);
        $file->setBin('mydata');

        $changeSet = new ChangeSet($file, [new Change('bucket', $bucket, null)]);

        $sut->updateObject($file, $changeSet);
    }

    /**
     * @expectedException \Coffreo\CephOdm\Exception\Exception
     * @expectedExceptionMessage Empty required property bin
     * @expectedExceptionCode \Coffreo\CephOdm\Exception\Exception::MISSING_REQUIRED_PROPERTY
     *
     * @covers \Coffreo\CephOdm\Persister\AbstractCephPersister::checkRequiredProperties
     */
    public function testUpdateObjectWithEmptyBinShouldThrowException(): void
    {
        $sut = $this
            ->getMockBuilder(CephFilePersister::class)
            ->disableOriginalConstructor()
            ->setMethods(['preparePersistChangeSet'])
            ->getMock();

        $file = new File();
        $file->setBucket(new Bucket('mybucket'));
        $file->setBin('mydata');

        $changeSet = new ChangeSet($file, [new Change('bin', 'mydata', null)]);

        $sut->updateObject($file, $changeSet);
    }

    public function providerUpdateWithChangeSetWithNoChangeInstanceShouldThrowException(): array
    {
        return [
            [new \stdClass(), "Change must be an instance of Change (actually stdClass)"]
        ];
    }

    /**
     * @dataProvider providerUpdateWithChangeSetWithNoChangeInstanceShouldThrowException
     *
     * @covers ::checkRequiredProperties
     */
    public function testUpdateWithChangeSetWithNoChangeInstanceShouldThrowException($change, string $expectedMessage): void
    {
        $sut = $this
            ->getMockBuilder(CephFilePersister::class)
            ->disableOriginalConstructor()
            ->setMethods(['prepareUpdateChangeSet'])
            ->getMock();


        $changeSet = $this->createMock(ChangeSet::class);
        $changeSet
            ->method('getChanges')
            ->willReturn([$change]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $file = new File();
        $file->setBucket(new Bucket('mybucket'));
        $file->setBin('mydata');
        $sut->updateObject($file, $changeSet);
    }
}

class DummyFile
{
    private $bucket;

    public function __construct(?Bucket $bucket = null)
    {
        $this->bucket = $bucket;
    }

    public function getBucket(): Bucket
    {
        return $this->bucket;
    }

    public function getBin(): string
    {
        return 'mydata';
    }
}