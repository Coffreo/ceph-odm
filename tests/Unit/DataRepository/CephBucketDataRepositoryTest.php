<?php


namespace Coffreo\CephOdm\Test\Unit\DataRepository;


use Coffreo\CephOdm\DataRepository\CephBucketDataRepository;
use Coffreo\CephOdm\Test\DummyS3Client;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Coffreo\CephOdm\DataRepository\CephBucketDataRepository
 */
class CephBucketDataRepositoryTest extends TestCase
{
    /**
     * @var CephBucketDataRepository
     */
    private $sut;

    private $data = [
        ['Name' => 'mybucket2'],
        ['Name' => 'mybucket3'],
        ['Name' => 'mybucket1']
    ];

    public function setUp()
    {
        $client = $this->createMock(DummyS3Client::class);
        $client
            ->method('listBuckets')
            ->willReturn([
                'Buckets' => $this->data
            ]);

        $classMetadata = $this->createMock(ClassMetadataInterface::class);
        $classMetadata
            ->method('getIdentifierFieldNames')
            ->willReturn(['name']);

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager
            ->method('getClassMetadata')
            ->with('myclassname')
            ->willReturn($classMetadata);

        $this->sut = new CephBucketDataRepository($client, $objectManager, 'myclassname');
    }

    /**
     * @covers ::findAll
     */
    public function testFindAll(): void
    {
        $this->assertEquals($this->data, $this->sut->findAll());
    }

    /**
     * @covers ::find
     * @covers \Coffreo\CephOdm\DataRepository\AbstractCephDataRepository::getIdentifier
     */
    public function testFind(): void
    {
        $this->assertEquals(['Name' => 'mybucket3'], $this->sut->find('mybucket3'));
    }

    /**
     * @covers ::find
     * @covers \Coffreo\CephOdm\DataRepository\AbstractCephDataRepository::getIdentifier
     */
    public function testFindWithUnknownIdentifierShouldReturnNothing(): void
    {
        $ret = $this->sut->find(['myunknownbucket']);
        $this->assertEquals(null, $ret);
    }

    public function providerFindOneBy()
    {
        return [
            [[], $this->data[0]],
            [['name' => 'mybucket3'], $this->data[1]],
            [['name' => 'myunknownbucket'], null]
        ];
    }

    /**
     * @dataProvider providerFindOneBy
     * @covers ::findOneBy
     */
    public function testFindOneBy(array $criteria, ?array $expectedResult): void
    {
        $this->assertEquals($expectedResult, $this->sut->findOneBy($criteria));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Limit 0 is not valid
     *
     * @covers \Coffreo\CephOdm\DataRepository\CephFileDataRepository::checkLimitAndOffset
     */
    public function testFindByWithWrongLimitShouldThrowException(): void
    {
        $this->sut->findBy([], [], 0);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Offset -1 is not valid
     *
     * @covers \Coffreo\CephOdm\DataRepository\CephFileDataRepository::checkLimitAndOffset
     */
    public function testFindByWithWrongOffsetShouldThrowException(): void
    {
        $this->sut->findBy([], [], null, -1);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Search can only be performed by name for a Bucket
     *
     * @covers ::findBy
     */
    public function testFindByWithNoNameShouldThrowException(): void
    {
        $this->sut->findBy(['myotherfield' => 'myvalue']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Order by can only be performed on name for a Bucket
     *
     * @covers ::findBy
     */
    public function testFindByWithNoNameSortShouldThrowException(): void
    {
        $this->sut->findBy([], ['myotherfield' => 'myvalue']);
    }

    public function providerFindBy(): array
    {
        return [
            [[], null, null, null, $this->data],
            [[], ['name' => 1], null, null, [['Name' => 'mybucket1'], ['Name' => 'mybucket2'], ['Name' => 'mybucket3']]],
            [[], ['name' => -1], null, null, [['Name' => 'mybucket3'], ['Name' => 'mybucket2'], ['Name' => 'mybucket1']]],
            [[], ['name' => -1], 2, null, [['Name' => 'mybucket3'], ['Name' => 'mybucket2']]],
            [[], ['name' => -1], 3, 1, [['Name' => 'mybucket2'], ['Name' => 'mybucket1']]],
            [[], null, 1, 2, [['Name' => 'mybucket1']]],
            [['name' => 'mybucket2'], null, null, null, [['Name' => 'mybucket2']]],
            [['name' => 'mybucket2'], null, null, 1, []]
        ];
    }

    /**
     * @dataProvider providerFindBy
     * @covers ::findBy
     */
    public function testFindBy(array $criteria, ?array $orderBy, ?int $limit, ?int $offset, array $expectedResult): void
    {
        $this->assertEquals($expectedResult, $this->sut->findBy($criteria, $orderBy, $limit, $offset));
    }
}