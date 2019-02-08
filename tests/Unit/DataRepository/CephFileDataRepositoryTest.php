<?php


namespace Coffreo\CephOdm\Test\Unit\DataRepository;

use Aws\CommandInterface;
use Aws\S3\Exception\S3Exception;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\DataRepository\CephFileDataRepository;
use Coffreo\CephOdm\Entity\File;
use Coffreo\CephOdm\EventListener\QueryTruncatedListener;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Coffreo\CephOdm\Test\DummyS3Client;

/**
 * @coversDefaultClass \Coffreo\CephOdm\DataRepository\CephFileDataRepository
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
            ->willReturnCallback(function ($args): array {
                $result = [];
                if (isset($args['Bucket'])) {
                    switch ($args['Bucket']) {
                        case 'mybucket1':
                            $result = ['Contents' => [
                                ['Key' => 'myobject1'],
                                ['Key' => 'myobject2']
                            ]];
                            break;

                        case 'mybucket2':
                            $result = ['Contents' => []];
                            break;

                        case 'mybucket3':
                            $result = ['Contents' => [
                                ['Key' => 'myobject1'],
                                ['Key' => 'myobject2'],
                                ['Key' => 'myobject3']
                            ]];
                            break;
                    }
                }

                $foundKey = null;
                if (!empty($args['Marker'])) {
                    foreach ($result['Contents'] as $key => $data) {
                        if (current($data) == $args['Marker']) {
                            $foundKey = $key;
                            break;
                        }
                    }
                }

                if ($foundKey !== null) {
                    $result['Contents'] = array_slice($result['Contents'], $foundKey + 1);
                }

                if (!empty($args['MaxKeys']) && count($result['Contents']) > $args['MaxKeys']) {
                    $result['Contents'] = array_slice($result['Contents'], 0, $args['MaxKeys']);
                    $result['IsTruncated'] = true;
                    $result['NextMarker'] = current(current(array_slice($result['Contents'], -1)));
                }

                return $result;
            });

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
        $classMetadata
            ->method('getFieldMappings')
            ->willReturn([
                'id' => ['name' => 'Key'],
                'bucket' => ['name' => 'Bucket'],
                'bin' => ['name' => 'Body'],
                'metadata' => ['name' => 'Metadata']
            ]);

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager
            ->method('getClassMetadata')
            ->with(File::class)
            ->willReturn($classMetadata);

        $this->sut = new CephFileDataRepository(
            $client,
            $objectManager,
            File::class
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
     * @covers \Coffreo\CephOdm\DataRepository\AbstractCephDataRepository::getIdentifier
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
     * @covers \Coffreo\CephOdm\DataRepository\AbstractCephDataRepository::getIdentifier
     * @covers ::findByIdentifier
     */
    public function testFindWithUnknownBucketShouldReturnNothing(): void
    {
        $ret = $this->sut->find(['mybucket4', 'myobject1']);
        $this->assertEquals(null, $ret);
    }

    /**
     * @covers ::find
     * @covers \Coffreo\CephOdm\DataRepository\AbstractCephDataRepository::getIdentifier
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
     * @covers \Coffreo\CephOdm\DataRepository\AbstractCephDataRepository::getIdentifier
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

    public function providerFindByWithTruncatedQueryShouldNotifyListener(): array
    {
        return [
            ['mybucket3', 2, $this->once(), ['mybucket3']],
            ['mybucket3', null, $this->never(), []],
            [null, null, $this->never(), ['mybucket3']],
        ];
    }

    /**
     * @dataProvider providerFindByWithTruncatedQueryShouldNotifyListener
     * @codeCoverageIgnore ::findBy
     */
    public function testFindByWithTruncatedQueryShouldNotifyListener(?string $bucketName, ?int $limit, InvokedCount $invokedCount, array $expectedArgs): void
    {
        $listener1 = $this->createMock(QueryTruncatedListener::class);
        $listener1
            ->expects($invokedCount)
            ->method('queryTruncated')
            ->with($expectedArgs);

        $listener2 = $this->createMock(QueryTruncatedListener::class);
        $listener2
            ->expects(clone $invokedCount)
            ->method('queryTruncated')
            ->with(['mybucket3']);

        $this->sut->addQueryTruncatedListener($listener1);
        $this->sut->addQueryTruncatedListener($listener2);

        if ($bucketName === null) {
            $this->sut->findAll();
        } else {
            $this->sut->findBy(['bucket' => $bucketName], [], $limit);
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage limit 0 is not valid
     *
     * @covers \Coffreo\CephOdm\DataRepository\CephFileDataRepository::checkLimit
     */
    public function testFindByWithWrongLimitShouldThrowException(): void
    {
        $this->sut->findBy([], [], 0);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Allowed search criteria are only bucket, id and metadata (myotherfield provided)
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
            [[], null, false, $this->data],
            [[], 2, false, array_slice($this->data, 0, 4), array_slice($this->data, 4, 1)],
            [['bucket' => 'mybucket1'], null, false, array_slice($this->data, 0, 2), []],
            [['bucket' => 'mybucket1', 'metadata' => ['mymetadata1' => 'myvalue1']], null, false, [$this->data[0]], []],
            [['bucket' => new Bucket('mybucket1')], 1, false, array_slice($this->data, 0, 1), array_slice($this->data, 1, 1)],
            [['id' => 'myobject2'], null, false, [$this->data[1], $this->data[3]]],
            [['bucket' => new Bucket('mybucket3'), 'id' => 'myobject2'], null, false, [$this->data[3]]]
        ];
    }

    /**
     * @dataProvider providerFindBy
     * @covers ::findBy
     * @covers \Coffreo\CephOdm\DataRepository\CephFileDataRepository::checkLimit
     * @covers ::bucketToString
     * @covers ::filterByMetadata
     */
    public function testFindBy(array $criteria, ?int $limit, bool $continue, array $expectedResult, ?array $expectedContinueResult = null): void
    {
        $ret = $this->sut->findBy($criteria, null, $limit, (int)$continue);

        foreach ($expectedResult as &$data) {
            if (!isset($data['Metadata'])) {
                $data['Metadata'] = [];
            }
        }

        $this->assertEquals($ret, $expectedResult);

        if ($expectedContinueResult === null) {
            return;
        }

        $ret = $this->sut->findBy($criteria, null, $limit, 1);

        foreach ($expectedContinueResult as &$data) {
            if (!isset($data['Metadata'])) {
                $data['Metadata'] = [];
            }
        }

        $this->assertEquals($ret, $expectedContinueResult);
    }

    public function providerFindByWithIdAndContinueShouldThrowException(): array
    {
        return [
            [['bucket' => 'mybucket', 'id' => 'myid'], null, 1],
            [['bucket' => 'mybucket', 'id' => 'myid'], 1, 1],
            [['bucket' => 'mybucket', 'id' => 'myid'], 1, null],
            [['id' => 'myid'], null, 1],
            [['id' => 'myid'], 1, 1],
            [['id' => 'myid'], 1, null]
        ];
    }

    /**
     * @dataProvider providerFindByWithIdAndContinueShouldThrowException
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage limit and continue arguments can't be used if an id is defined as criteria
     *
     * @covers ::findBy
     */
    public function testFindByWithIdAndContinueShouldThrowException(array $criteria, ?int $limit, ?int $continue): void
    {
        $this->sut->findBy($criteria, [], $limit, $continue);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage  limit can't be over than 1000 (actually 1001)
     *
     * @covers ::checkLimit
     */
    public function testFindByWithOverThan1000LimitShouldThrowException(): void
    {
        $this->sut->findBy([], [], 1001);
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

    public function providerFindByFromWithIdShouldThrowException(): array
    {
        return [
            [['bucket' => 'mybucket', 'id' => 'myid'], 'myid'],
            [['id' => 'myid'], ['bucket' => 'myid']]
        ];
    }

    /**
     * @dataProvider providerFindByFromWithIdShouldThrowException
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage id can't be defined as criteria in findByFrom method
     *
     * @covers ::findBy
     */
    public function testFindByFromWithIdShouldThrowException(array $criteria, $from): void
    {
        $this->sut->findByFromCalled($criteria, $from, null, null);
        $this->sut->findBy($criteria);
    }

    public function providerFindByFromWithWrongFromFormatShouldThrowException(): array
    {
        return [
            ['myid'],
            [new \stdClass()]
        ];
    }

    /**
     * @dataProvider providerFindByFromWithWrongFromFormatShouldThrowException
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage from must be an array or a string if bucket is in criteria
     *
     * @covers ::findByFromCalled
     */
    public function testFindByFromWithWrongFromFormatShouldThrowException($from): void
    {
        $this->sut->findByFromCalled(['id' => 'myid'], $from, null, null);
        $this->sut->findBy(['id' => 'myid']);
    }

    public function providerFindByFrom(): array
    {
        return [
            [['bucket' => 'mybucket3'], 'myobject1', array_slice($this->data, 3, 2)],
            [[], ['mybucket1' => 'myobject1', 'mybucket3' => 'myobject2'], [$this->data[1], $this->data[4]]]
        ];
    }

    /**
     * @dataProvider providerFindByFrom
     *
     * @covers ::findByFromCalled
     * @covers ::findBy
     */
    public function testFindByFrom(array $criteria, $from, array $expectedResult): void
    {
        $this->sut->findByFromCalled($criteria, $from, null, null);
        $ret = $this->sut->findBy($criteria);

        foreach ($expectedResult as &$data) {
            if (!isset($data['Metadata'])) {
                $data['Metadata'] = [];
            }
        }

        $this->assertEquals($ret, $expectedResult);
    }
}