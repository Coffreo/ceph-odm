<?php


namespace Coffreo\CephOdm\Test\Unit\EventListener;

use Aws\S3\S3Client;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;
use Coffreo\CephOdm\EventListener\FileLazyLoadListener;
use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface;

/**
 * @coversDefaultClass \Coffreo\CephOdm\EventListener\FileLazyLoadListener
 */
class FileLazyLoadListenerTest extends TestCase
{
    private $sut;
    private $client;
    private $classMetadataFactory;

    public function setUp()
    {
        $this->classMetadataFactory = $this->createMock(ClassMetadataFactory::class);
        $this->client = $this->createMock(DummyS3Client::class);
        $this->sut = new FileLazyLoadListener($this->client, $this->classMetadataFactory);
    }

    /**
     * @covers ::lazyLoadedPropertyGet
     */
    public function providerLazyLoadedPropertyGetWithNoFileInstanceShouldThrowException(): array
    {
        return [
            ['rere', "First argument of lazyLoadedPropertyGet must be a File instance; scalar given"],
            [new \stdClass(), "First argument of lazyLoadedPropertyGet must be a File instance; stdClass given"]
        ];
    }

    /**
     * @dataProvider providerLazyLoadedPropertyGetWithNoFileInstanceShouldThrowException
     * @expectedException \LogicException
     *
     * @covers ::lazyLoadedPropertyGet
     */
    public function testLazyLoadedPropertyGetWithNoFileInstanceShouldThrowException($object, string $expectedExceptionMessage): void
    {
        $this->expectExceptionMessage($expectedExceptionMessage);
        $this->sut->lazyLoadedPropertyGet($object, '');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage File bucket must be set
     *
     * @covers ::lazyLoadedPropertyGet
     */
    public function testLazyLoadedPropertyGetWithFileWithoutBucketShouldThrowException(): void
    {
        $this->sut->lazyLoadedPropertyGet(new File(), '');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage File id must be set
     *
     * @covers ::lazyLoadedPropertyGet
     */
    public function testLazyLoadedPropertyGetWithFileWithoutIdShouldThrowException(): void
    {
        $file = new File();
        $file->setBucket(new Bucket('mybucket'));

        $this->sut->lazyLoadedPropertyGet($file, '');
    }

    public function providerLazyLoadedPropertyGet(): array
    {
        return [
            [
                [],
                ['Body' => 'mydata', 'Metadata' => ['mymetadata' => 'myvalue']],
                ['bucket' => 'mybucket', 'id' => 'myid'],
                ['mymetadata' => 'myvalue']
            ],
            [
                ['bucket' => ['name' => 'Bucket'], 'id' => ['name' => 'Key']],
                ['Body' => 'mydata', 'Metadata' => ['mymetadata' => 'myvalue']],
                ['Bucket' => 'mybucket', 'Key' => 'myid'],
                ['mymetadata' => 'myvalue']
            ],
            [
                [],
                ['Body' => 'mydata'],
                ['bucket' => 'mybucket', 'id' => 'myid'],
                []
            ],
            [
                ['bucket' => ['name' => 'Bucket'], 'id' => ['name' => 'Key']],
                ['Body' => 'mydata'],
                ['Bucket' => 'mybucket', 'Key' => 'myid'],
                []
            ]
        ];
    }

    /**
     * @dataProvider providerLazyLoadedPropertyGet
     * @covers ::lazyLoadedPropertyGet
     */
    public function testLazyLoadedPropertyGet(array $mappings, array $getObjectReturn, array $expectedGetObjectArgs, array $expectedSetAllMetadataArg): void
    {
        $classMetadata = $this->createMock(ClassMetadataInterface::class);
        $classMetadata
            ->method('getFieldMappings')
            ->willReturn($mappings);

        $this->classMetadataFactory
            ->method('getMetadataFor')
            ->with(File::class)
            ->willReturn($classMetadata);

        $this->client
            ->expects($this->once())
            ->method('getObject')
            ->with($expectedGetObjectArgs)
            ->willReturn($getObjectReturn);

        $bucket = new Bucket('mybucket');

        $file = $this->createMock(File::class);
        $file
            ->method('getBucket')
            ->willReturn($bucket);

        $file
            ->method('getId')
            ->willReturn('myid');

        $file
            ->expects($this->once())
            ->method('setBin')
            ->with('mydata');

        $file
            ->expects($this->once())
            ->method('setAllMetadata')
            ->with($expectedSetAllMetadataArg);

        $this->sut->lazyLoadedPropertyGet($file, '');
    }
}

/**
 * Dummmy class to allow mocking
 */
class DummyS3Client extends S3Client
{
    public function getObject(array $args)
    {
        return parent::getObject();
    }
}