<?php


namespace Coffreo\CephOdm\Test\Unit\Repository;


use Coffreo\CephOdm\Repository\BucketRepository;
use Doctrine\SkeletonMapper\ObjectRepository\BasicObjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BucketRepositoryTest extends TestCase
{
    /**
     * @var BasicObjectRepository|MockObject
     */
    private $basicObjectRepository;

    /**
     * @var BucketRepository
     */
    private $sut;

    public function setUp()
    {
        $this->basicObjectRepository = $this->createMock(BasicObjectRepository::class);
        $this->sut = new BucketRepository($this->basicObjectRepository);
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::getObjectIdentifier
     */
    public function testGetObjectIdentifier() : void
    {
        $arg = new \stdClass();
        $return = ['myvalue'];

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('getObjectIdentifier')
            ->with($arg)
            ->willReturn($return);

        $this->assertEquals($return, $this->sut->getObjectIdentifier($arg));
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::getObjectIdentifierFromData
     */
    public function testObjectIdentifierFromData() : void
    {
        $arg = ['myargvalue'];
        $return = ['myvalue'];

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('getObjectIdentifierFromData')
            ->with($arg)
            ->willReturn($return);

        $this->assertEquals($return, $this->sut->getObjectIdentifierFromData($arg));
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::merge
     */
    public function testMerge() : void
    {
        $arg = new \stdClass();

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('merge')
            ->with($arg);

        $this->sut->merge($arg);
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::hydrate
     */
    public function testHydrate() : void
    {
        $args = [new \stdClass(), ['myvalue']];

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('hydrate')
            ->with(...$args);

        $this->sut->hydrate(...$args);
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::create
     */
    public function testCreate() : void
    {
        $arg = 'myvalue';

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('create')
            ->with($arg);

        $this->sut->create($arg);
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::refresh
     */
    public function testRefresh() : void
    {
        $arg = new \stdClass();

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('refresh')
            ->with($arg);

        $this->sut->refresh($arg);
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::find
     */
    public function testFind() : void
    {
        $arg = 'myargvalue';
        $return = new \stdClass();

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('find')
            ->with($arg)
            ->willReturn($return);

        $this->assertEquals($return, $this->sut->find($arg));
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::findAll
     */
    public function testFindAll() : void
    {
        $return = [new \stdClass(), new \stdClass()];

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($return);

        $this->assertEquals(new \ArrayObject($return), $this->sut->findAll());
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::findBy
     */
    public function testFindBy() : void
    {
        $args = [['criteria' => 'value'], ['order' => 'value'], 10, 3];
        $return = [new \stdClass(), new \stdClass()];

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(...$args)
            ->willReturn($return);

        $this->assertEquals(new \ArrayObject($return), $this->sut->findBy(...$args));
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::findOneBy
     */
    public function testFindOneBy() : void
    {
        $arg = ['criteria' => 'value'];
        $return = new \stdClass();

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($arg)
            ->willReturn($return);

        $this->assertEquals($return, $this->sut->findOneBy($arg));
    }

    /**
     * @covers \Coffreo\CephOdm\Repository\AbstractRepositoryDecorator::getClassName
     */
    public function testGetClassName() : void
    {
        $return = 'myclassname';

        $this->basicObjectRepository
            ->expects($this->once())
            ->method('getClassName')
            ->willReturn($return);

        $this->assertEquals($return, $this->sut->getClassName());
    }
}