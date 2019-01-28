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
}