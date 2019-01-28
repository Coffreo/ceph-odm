<?php


namespace Coffreo\CephOdm\Test\Repository;

use Aws\CommandInterface;
use Aws\S3\Exception\S3Exception;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Repository\CephFileDataRepository;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Coffreo\CephOdm\Test\DummyS3Client;

/**
 * @coversDefaultClass \Coffreo\CephOdm\Repository\CephFileDataRepository
 */
class CephFileDataRepositoryTest extends TestCase
{
    /**
     * @var CephFileDataRepository|MockObject
     */
    private $sut;

    private $data = [
        [
            'Bucket' => 'mybucket1',
            'Key' => 'myobject1',
            'Body' => 'mybinarycontent1',
            'Metadata' => ['mymetadata1' => 'myvalue1']
        ],
        [
            'Bucket' => 'mybucket1',
            'Key' => 'myobject2',
            'Body' => 'mybinarycontent2'
        ],
        [
            'Bucket' => 'mybucket3',
            'Key' => 'myobject1',
            'Body' => 'mybinarycontent3',
            'Metadata' => ['mymetadata1' => 'myvalue2', 'mymetadata2' => 'myvalue3']
        ],
        [
            'Bucket' => 'mybucket3',
            'Key' => 'myobject2',
            'Body' => 'mybinarycontent4',
            'Metadata' => ['mymetadata2' => 'myvalue4']
        ],
        [
            'Bucket' => 'mybucket3',
            'Key' => 'myobject3',
            'Body' => 'mybinarycontent5',
            'Metadata' => ['mymetadata1' => 'myvalue1', 'mymetadata2' => 'myvalue4']
        ]
    ];

    public function setUp()
    {
        $client = $this->createMock(DummyS3Client::class);

        $client
            ->method('listBuckets')
            ->willReturn([
                'Buckets' => [
                    ['Name' => 'mybucket1'],
                    ['Name' => 'mybucket2'],
                    ['Name' => 'mybucket3']
                ]
            ]);

        $client
            ->method('listObjects')
            ->willReturnMap([
                [['Bucket' => 'mybucket1'], ['Contents' => [
                    ['Key' => 'myobject1'],
                    ['Key' => 'myobject2']
                ]]],
                [['Bucket' => 'mybucket2'], ['Contents' => []]],
                [['Bucket' => 'mybucket3'], ['Contents' => [
                    ['Key' => 'myobject1'],
                    ['Key' => 'myobject2'],
                    ['Key' => 'myobject3']
                ]]],
            ]);

        $client
            ->method('getObject')
            ->willReturnCallback(function (array $args = []) {
                switch ($args) {
                    case ['Bucket' => 'mybucket1', 'Key' => 'myobject1']:
                        return $this->data[0];

                    case ['Bucket' => 'mybucket1', 'Key' => 'myobject2']:
                        return $this->data[1];

                    case ['Bucket' => 'mybucket3', 'Key' => 'myobject1']:
                        return $this->data[2];

                    case ['Bucket' => 'mybucket3', 'Key' => 'myobject2']:
                        return $this->data[3];

                    case ['Bucket' => 'mybucket3', 'Key' => 'myobject3']:
                        return $this->data[4];

                    case ['Bucket' => 'mybucketthatleadstoexception', 'Key' => 'myobject1']:
                        throw new S3Exception('myunexpectedexception',  $this->createMock(CommandInterface::class));

                    default:
                        if (!isset($args['Bucket']) || !in_array($args['Bucket'], ['mybucket1', 'mybucket2', 'mybucket3'])) {
                            throw new S3Exception('not found', $this->createMock(CommandInterface::class), ['code' => 'NoSuchBucket']);
                        }
                        throw new S3Exception('not found', $this->createMock(CommandInterface::class), ['code' => 'NoSuchKey']);
                }
            });

        $classMetadata = $this->createMock(ClassMetadataInterface::class);
        $classMetadata
            ->method('getIdentifierFieldNames')
            ->willReturn(['bucket', 'id']);

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager
            ->method('getClassMetadata')
            ->with('myclassname')
            ->willReturn($classMetadata);

        $this->sut = new CephFileDataRepository(
            $client,
            $objectManager,
            'myclassname'
        );
    }

    public function providerFindWithBadArgumentsShouldThrowException(): array
    {
        return [
            ['myid'],
            [['myid']],
            [['myid', 'myid2', 'myid3']]
        ];
    }

    /**
     * @dataProvider providerFindWithBadArgumentsShouldThrowException
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Method expect a two values array with bucket and id as argument
     *
     * @covers ::find
     */
    public function testFindWithBadArgumentsShouldThrowException($findArgument): void
    {
        $this->sut->find($findArgument);
    }

    /**
     * @covers ::find
     * @covers \Coffreo\CephOdm\Repository\AbstractCephDataRepository::getIdentifier
     * @covers ::findByIdentifier
     */
    public function testFind(): void
    {
        $ret = $this->sut->find(['mybucket3', 'myobject1']);
        $this->assertEquals([
            'Bucket' => 'mybucket3',
            'Key' => 'myobject1',
            'Body' => 'mybinarycontent3',
            'Metadata' => ['mymetadata1' => 'myvalue2', 'mymetadata2' => 'myvalue3']
        ], $ret);
    }

    /**
     * @covers ::find
     * @covers \Coffreo\CephOdm\Repository\AbstractCephDataRepository::getIdentifier
     * @covers ::findByIdentifier
     */
    public function testFindWithUnknownBucketShouldReturnNothing(): void
    {
        $ret = $this->sut->find(['mybucket4', 'myobject1']);
        $this->assertEquals(null, $ret);
    }

    /**
     * @covers ::find
     * @covers \Coffreo\CephOdm\Repository\AbstractCephDataRepository::getIdentifier
     * @covers ::findByIdentifier
     */
    public function testFindWithUnknownIdShouldReturnNothing(): void
    {
        $ret = $this->sut->find(['mybucket1', 'myobject3']);
        $this->assertEquals(null, $ret);
    }

    /**
     * @expectedException \Aws\S3\Exception\S3Exception
     * @expectedExceptionMessage myunexpectedexception
     *
     * @covers ::find
     * @covers \Coffreo\CephOdm\Repository\AbstractCephDataRepository::getIdentifier
     * @covers ::findByIdentifier
     */
    public function testFindWithExceptionThrownByClientShouldThrowException(): void
    {
        $this->sut->find(['mybucketthatleadstoexception', 'myobject1']);
    }

    /**
     * @covers ::findAll
     */
    public function testFindAll(): void
    {
        $ret = $this->sut->findAll();

        $expected = $this->data;
        foreach ($expected as &$data) {
            if (!isset($data['Metadata'])) {
                $data['Metadata'] = [];
            }
        }

        $this->assertEquals($expected, $ret);
    }

    public function providerFindOneBy(): array
    {
        return [
            [
                ['bucket' => new Bucket('mybucket3'), 'id' => 'myobject2'],
                ['Bucket' => 'mybucket3', 'Key' => 'myobject2', 'Body' => 'mybinarycontent4', 'Metadata' => ['mymetadata2' => 'myvalue4']]
            ],
            [
                ['bucket' => 'mybucket1', 'id' => 'myobject3'],
                null
            ]
        ];
    }

    /**
     * @dataProvider providerFindOneBy
     * @covers ::findOneBy
     * @covers ::bucketToString
     */
    public function testFindOneBy(array $criteria, ?array $expectedReturn): void
    {
        $ret = $this->sut->findOneBy($criteria);
        $this->assertEquals($expectedReturn, $ret);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Limit 0 is not valid
     *
     * @covers \Coffreo\CephOdm\Repository\CephFileDataRepository::checkLimitAndOffset
     */
    public function testFindByWithWrongLimitShouldThrowException(): void
    {
        $this->sut->findBy([], [], 0);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Offset -1 is not valid
     *
     * @covers \Coffreo\CephOdm\Repository\CephFileDataRepository::checkLimitAndOffset
     */
    public function testFindByWithWrongOffsetShouldThrowException(): void
    {
        $this->sut->findBy([], [], null, -1);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Allowed search criteria are only bucket and id (myotherfield provided)
     *
     * @covers ::findBy
     */
    public function testFindByWithNoBucketOrIdFieldShouldThrowException(): void
    {
        $this->sut->findBy(['myotherfield' => 'myvalue']);
    }

    public function providerFindBy(): array
    {
        return [
            [[], null, null, $this->data],
            [[], 2, null, array_slice($this->data, 0, 2)],
            [[], 3, 1, array_slice($this->data, 1, 3)],
            [['bucket' => 'mybucket1'], null, null, array_slice($this->data, 0, 2)],
            [['bucket' => new Bucket('mybucket1')], 1, null, array_slice($this->data, 0, 1)],
            [['bucket' => 'mybucket1'], 1, 1, array_slice($this->data, 1, 1)],
            [['bucket' => new Bucket('mybucket1')], 1, 3, []],
            [['id' => 'myobject1'], null, null, [$this->data[0], $this->data[2]]],
            [['id' => 'myobject1'], 2, 1, [$this->data[2]]],
            [['bucket' => new Bucket('mybucket3'), 'id' => 'myobject2'], null, null, [$this->data[3]]],
            [['bucket' => 'mybucket3', 'id' => 'myobject2'], null, 1, []]
        ];
    }

    /**
     * @dataProvider providerFindBy
     * @covers ::findBy
     * @covers \Coffreo\CephOdm\Repository\CephFileDataRepository::checkLimitAndOffset
     * @covers ::bucketToString
     */
    public function testFindBy(array $criteria, ?int $limit, ?int $offset, array $expectedResult): void
    {
        $ret = $this->sut->findBy($criteria, null, $limit, $offset);

        foreach ($expectedResult as &$data) {
            if (!isset($data['Metadata'])) {
                $data['Metadata'] = [];
            }
        }

        $this->assertEquals($ret, $expectedResult);
    }

    public function providerFindWithBucketAtInvalidFormatShouldThrowException(): array
    {
        return [
            [null],
            [12],
            [[]],
            [new \stdClass()]
        ];
    }

    /**
     * @dataProvider providerFindWithBucketAtInvalidFormatShouldThrowException
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage bucket must be a string or an instance of Bucket
     *
     * @covers ::bucketToString
     */
    public function testFindWithBucketAtInvalidFormatShouldThrowException($bucket)
    {
        $this->sut->find([$bucket, 'myobjectid']);
    }
}