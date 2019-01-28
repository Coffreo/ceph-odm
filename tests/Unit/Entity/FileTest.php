<?php


namespace Coffreo\CephOdm\Test\Unit\Entity;

use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;
use Doctrine\Common\PropertyChangedListener;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface;
use Doctrine\SkeletonMapper\ObjectManager;
use Doctrine\SkeletonMapper\UnitOfWork\Change;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Ramsey\Uuid\Uuid;

/**
 * @coversDefaultClass \Coffreo\CephOdm\Entity\File
 */
class FileTest extends TestCase
{
    /**
     * @covers ::setFilename
     */
    public function testSetFilename(): void
    {
        $sut = $this->createSutForTestingSetter('filename', [null, 'myfilename', null]);

        $sut->setFilename('myfilename');
        $this->assertEquals('myfilename', $sut->getFilename());

        $sut->setFilename(null);
        $this->assertEquals(null, $sut->getFilename());
    }

    /**
     * @covers ::setBin
     */
    public function testSetBin(): void
    {
        $sut = $this->createSutForTestingSetter('bin', [null, 'mybinarycontent', 'myotherbinarycontent']);

        $sut->setBin('mybinarycontent');
        $this->assertEquals('mybinarycontent', $sut->getBin());

        $sut->setBin('myotherbinarycontent');
        $this->assertEquals('myotherbinarycontent', $sut->getBin());
    }

    /**
     * @covers ::setBucket
     */
    public function testSetBucket(): void
    {
        $bucket = new Bucket('mybucketname');
        $newBucket = new Bucket('myotherbucketname');

        $sut = $this->createSutForTestingSetter('bucket', [null, $bucket, $newBucket]);

        $sut->setBucket($bucket);
        $this->assertSame($bucket, $sut->getBucket());

        $sut->setBucket($newBucket);
        $this->assertSame($newBucket, $sut->getBucket());
    }

    /**
     * @covers ::setAllMetadata
     */
    public function testSetAllMetadata(): void
    {
        $sut = $this->createSutForTestingSetter(
            'metadata',
            [
                [],
                ['my-metadata1' => 'myvalue1'],
                ['mymetadata2' => 'myvalue2', 'mymetadata3' => 'myvalue3']
            ]
        );

        $sut->setAllMetadata(['my-metadata1' => 'myvalue1']);
        $this->assertEquals(['my-metadata1' => 'myvalue1'], $sut->getAllMetadata());

        $sut->setAllMetadata(['mymetadata2' => 'myvalue2', 'mymetadata3' => 'myvalue3']);
        $this->assertEquals(['mymetadata2' => 'myvalue2', 'mymetadata3' => 'myvalue3'], $sut->getAllMetadata());
    }

    /**
     * @covers ::setMetadata
     */
    public function testSetMetadata(): void
    {
        $sut = $this->createSutForTestingSetter(
            'metadata',
            [
                [],
                ['my.metadata1' => 'myvalue1'],
                ['my.metadata1' => 'myvalue1', 'mymetadata2' => 'myvalue2']
            ]
        );

        $sut->setMetadata('my.metadata1', 'myvalue1');
        $this->assertEquals(['my.metadata1' => 'myvalue1'], $sut->getAllMetadata());

        $sut->setMetadata('mymetadata2', 'myvalue2');
        $this->assertEquals(['my.metadata1' => 'myvalue1', 'mymetadata2' => 'myvalue2'], $sut->getAllMetadata());
    }

    /**
     * @covers ::removeMetadata
     */
    public function testRemoveMetadata()
    {
        $sut = $this->createSutForTestingSetter(
            'metadata',
            [
                [],
                ['mymetadata1' => 'myvalue1', 'mymetadata2' => 'myvalue2'],
                ['mymetadata2' => 'myvalue2'],
                ['mymetadata2' => 'myvalue2'],
                [],
                []
            ]
        );

        $sut->setAllMetadata(['mymetadata1' => 'myvalue1', 'mymetadata2' => 'myvalue2']);
        $sut->removeMetadata('mymetadata1');
        $this->assertEquals(['mymetadata2' => 'myvalue2'], $sut->getAllMetadata());
        $sut->removeMetadata('mymetadata1');
        $this->assertEquals(['mymetadata2' => 'myvalue2'], $sut->getAllMetadata());
        $sut->removeMetadata('mymetadata2');
        $this->assertEquals([], $sut->getAllMetadata());
        $sut->removeMetadata('mymetadata2');
        $this->assertEquals([], $sut->getAllMetadata());
    }

    /**
     * Create sut for setter testing
     *
     * @param string $fieldName name of the field matching to the setter
     * @param array $consecutiveValues consecutive values that the field should take
     *
     * @return MockObject|File
     */
    private function createSutForTestingSetter(string $fieldName, array $consecutiveValues): MockObject
    {
        $sut = $this
            ->getMockBuilder(File::class)
            ->setMethods(['onPropertyChanged'])
            ->getMock();

        $expectedCallArgs = [];
        $lastValue = array_shift($consecutiveValues);
        foreach ($consecutiveValues as $key => $value) {
            $expectedCallArgs[] = [$fieldName, $lastValue, $value];
            $lastValue = $value;
        }

        $sut
            ->expects($this->exactly(count($expectedCallArgs)))
            ->method('onPropertyChanged')
            ->withConsecutive(...$expectedCallArgs);

        return $sut;
    }

    /**
     * @covers ::getMetadata
     */
    public function testGetMetadata(): void
    {
        $sut = new File();
        $sut->setAllMetadata(['mymetadata1' => 'myvalue1']);

        $this->assertEquals('myvalue1', $sut->getMetadata('mymetadata1'));
        $this->assertEquals(null, $sut->getMetadata('mymetadata2'));
    }

    public function providerHydrate(): array
    {
        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn('mybinarycontent');

        return [
            [
                ['Key' => 'myid', 'Bucket' => 'mybucketname', 'Body' => $streamMock],
                'myid',
                null,
                'mybucketname',
                'mybinarycontent',
                []
            ],
            [
                ['Key' => 'myid', 'Bucket' => 'mybucketname', 'Body' => $streamMock, 'Metadata' => ['mymetadata1' => 'myvalue1']],
                'myid',
                null,
                'mybucketname',
                'mybinarycontent',
                ['mymetadata1' => 'myvalue1']
            ],
            [
                ['Key' => 'myid', 'Bucket' => 'mybucketname', 'Body' => $streamMock, 'Metadata' => ['mymetadata1' => 'myvalue1', 'filename' => 'myfilename']],
                'myid',
                'myfilename',
                'mybucketname',
                'mybinarycontent',
                ['mymetadata1' => 'myvalue1']
            ],
            [
                ['Key' => 'myid', 'Bucket' => 'mybucketname', 'Body' => $streamMock, 'Metadata' => ['filename' => 'myfilename']],
                'myid',
                'myfilename',
                'mybucketname',
                'mybinarycontent',
                []
            ]
        ];
    }

    /**
     * @dataProvider providerHydrate
     * @covers ::hydrate
     */
    public function testHydrate(
        array $data,
        string $expectedId,
        ?string $expectedFilename,
        string $expectedBucketName,
        string $expectedBin,
        array $expectedMetadata
    ): void
    {
        $sut = new File();
        $sut->hydrate($data, $this->createMock(ObjectManager::class));

        $this->assertEquals($expectedId, $sut->getId());
        $this->assertEquals($expectedFilename, $sut->getFilename());
        $this->assertEquals($expectedBucketName, $sut->getBucket()->getName());
        $this->assertEquals($expectedBin, $sut->getBin());
        $this->assertEquals($expectedMetadata, $sut->getAllMetadata());
    }

    /**
     * @covers ::loadMetadata
     */
    public function testLoadMetadata():void
    {
        $mock = $this->createMock(ClassMetadataInterface::class);

        $mock->expects($this->once())->method('setIdentifier')->with(['Bucket', 'Key']);
        $mock->expects($this->once())->method('setIdentifierFieldNames')->with(['bucket', 'id']);
        $mock->method('mapField')->withConsecutive(
            [['fieldName' => 'bucket', 'name' => 'Bucket']],
            [['fieldName' => 'id', 'name' => 'Key']],
            [['fieldName' => 'bin', 'name' => 'Body']],
            [['fieldName' => 'metadata', 'name' => 'Metadata']]
        );

        File::loadMetadata($mock);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Bucket must be set before persisting
     *
     * @covers ::preparePersistChangeSet
     */
    public function testPreparePersistChangeSetWithoutBucketShouldThrowException(): void
    {
        $sut = new File();
        $sut->preparePersistChangeSet();
    }

    public function providerPreparePersistChangeSet(): array
    {
        return [
            ['mybucketname', 'myid', 'mybinarycontent', 'myfilename', [], ['Bucket' => 'mybucketname', 'Key' => 'myid', 'Body' => 'mybinarycontent', 'Metadata' => ['filename' => 'myfilename']]],
            ['mybucketname', 'myid', 'mybinarycontent', null, [], ['Bucket' => 'mybucketname', 'Key' => 'myid', 'Body' => 'mybinarycontent']],
            ['mybucketname', 'myid', 'mybinarycontent', 'myfilename', ['mymetadata1' => 'myvalue1'], ['Bucket' => 'mybucketname', 'Key' => 'myid', 'Body' => 'mybinarycontent', 'Metadata' => ['filename' => 'myfilename', 'mymetadata1' => 'myvalue1']]],
            ['mybucketname', 'myid', 'mybinarycontent', null, ['mymetadata1' => 'myvalue1'], ['Bucket' => 'mybucketname', 'Key' => 'myid', 'Body' => 'mybinarycontent', 'Metadata' => ['mymetadata1' => 'myvalue1']]]
        ];
    }

    /**
     * @dataProvider providerPreparePersistChangeSet
     * @covers ::preparePersistChangeSet
     */
    public function testPreparePersistChangeSet(
        string $bucketName,
        string $id,
        string $bin,
        ?string $filename,
        array $metadata,
        array $expectedReturn
    ): void
    {
        $sut = new File();
        $sut->setBucket(new Bucket($bucketName));
        $sut->setBin($bin);
        $sut->setFilename($filename);
        $sut->setAllMetadata($metadata);

        $actualReturn = $sut->preparePersistChangeSet();
        $this->assertEquals(array_merge($expectedReturn, ['Key' => $actualReturn['Key']]), $actualReturn);
        $this->assertInstanceOf(Uuid::class, $actualReturn['Key']);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Bucket must be set before updating
     *
     * @covers ::prepareUpdateChangeSet
     */
    public function testPrepareUpdateChangeSetWithoutBucketShouldThrowException(): void
    {
        $sut = new File();
        $sut->prepareUpdateChangeSet($this->createMock(ChangeSet::class));
    }

    public function providerPrepareUpdateChangeSet(): array
    {
        $binChange = $this->createMock(Change::class);
        $binChange->method('getNewValue')->willReturn('mynewbinarycontent');
        $filenameChange = $this->createMock(Change::class);
        $filenameChange->method('getNewValue')->willReturn('mynewfilename');
        $metadataChange = $this->createMock(Change::class);
        $metadataChange->method('getNewValue')->willReturn(['mymetadata1' => 'mynewvalue']);

        return [
            [['bin' => $binChange], ['Bucket' => 'mybucketname', 'Key' => 'myid', 'Body' => 'mynewbinarycontent']],
            [['filename' => $filenameChange], ['Bucket' => 'mybucketname', 'Key' => 'myid', 'Metadata' => ['filename' => 'mynewfilename']]],
            [['metadata' => $metadataChange], ['Bucket' => 'mybucketname', 'Key' => 'myid', 'Metadata' => ['mymetadata1' => 'mynewvalue']]],
            [['filename' => $filenameChange], ['Bucket' => 'mybucketname', 'Key' => 'myid', 'Metadata' => ['filename' => 'mynewfilename']]],
            [['bin' => $binChange, 'metadata' => $metadataChange, 'filename' => $filenameChange], ['Bucket' => 'mybucketname', 'Key' => 'myid', 'Body' => 'mynewbinarycontent', 'Metadata' => ['filename' => 'mynewfilename', 'mymetadata1' => 'mynewvalue']]]
        ];
    }

    /**
     * @dataProvider providerPrepareUpdateChangeSet
     * @covers ::prepareUpdateChangeSet
     */
    public function testPrepareUpdateChangeSet(array $changes, array $expectedReturn): void
    {
        $changeset = $this->createMock(ChangeSet::class);
        $changeset->method('getChanges')->willReturn($changes);

        $sut = new File();
        $sut->setBucket(new Bucket('mybucketname'));
        $sut->assignIdentifier(['Key' => 'myid']);

        $this->assertEquals($expectedReturn, $sut->prepareUpdateChangeSet($changeset));
    }

    public function providerOnPropertyChanged(): array
    {
        $sut1 = new File();
        $sut2 = new File();
        $sut3 = new File();

        return [
            [
                $sut1,
                [],
                []
            ],
            [
                $sut2,
                [$this->createMock(PropertyChangedListener::class)],
                [
                    [$sut2, 'bin', null, 'mybinarycontent'],
                    [$sut2, 'bin', 'mybinarycontent', 'myotherbinarycontent']
                ]
            ],
            [
                $sut3,
                [$this->createMock(PropertyChangedListener::class), $this->createMock(PropertyChangedListener::class)],
                [
                    [$sut3, 'bin', null, 'mybinarycontent'],
                    [$sut3, 'bin', 'mybinarycontent', 'myotherbinarycontent']
                ]
            ]
        ];
    }

    /**
     * @dataProvider providerOnPropertyChanged
     * @covers ::onPropertyChanged
     */
    public function testOnPropertyChanged(File $sut, array $listeners, array $expectedCallArgs): void
    {
        /** @var MockObject $listener */
        foreach ($listeners as $listener) {
            $sut->addPropertyChangedListener($listener);
            $listener
                ->expects($this->exactly(count($expectedCallArgs)))
                ->method('propertyChanged')
                ->withConsecutive(...$expectedCallArgs);
        }

        $sut->setBin('mybinarycontent');
        $sut->setBin('myotherbinarycontent');

        // Since @doesNotPerformAssertions doesn't allow to perform code coverage
        $this->assertTrue(true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Valid characters for metadata name are lowercase letters, digits, dot and hyphen
     *
     * @covers ::checkMetadataName
     */
    public function testSetMetadataWithInvalidNameShouldThrowException(): void
    {
        $sut = new File();
        $sut->setMetadata('myName', 'myvalue');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Valid characters for metadata name are lowercase letters, digits, dot and hyphen
     *
     * @covers ::checkMetadataName
     */
    public function testGetMetadataWithInvalidNameShouldThrowException(): void
    {
        $sut = new File();
        $sut->getMetadata('my_name');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Valid characters for metadata name are lowercase letters, digits, dot and hyphen
     *
     * @covers ::checkMetadataName
     */
    public function testRemoveMetadataWithInvalidNameShouldThrowException(): void
    {
        $sut = new File();
        $sut->removeMetadata('my,name');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Valid characters for metadata name are lowercase letters, digits, dot and hyphen
     *
     * @covers ::checkMetadataName
     */
    public function testsetAllMetadataWithInvalidNameShouldThrowException(): void
    {
        $sut = new File();
        $sut->setAllMetadata(['myname' => 'myvalue', 'my:name' => 'myvalue']);
    }
}