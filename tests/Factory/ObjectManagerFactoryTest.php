<?php


namespace Coffreo\CephOdm\Test\Factory;


use Aws\S3\S3Client;
use Coffreo\CephOdm\Factory\ObjectManagerFactory;
use Doctrine\Common\EventManager;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Coffreo\CephOdm\Factory\ObjectManagerFactory
 */
class ObjectManagerFactoryTest extends TestCase
{
    public function providerCreate(): array
    {
        return [
            [null],
            [new EventManager()]
        ];
    }

    /**
     * @dataProvider providerCreate
     * @covers ::create
     */
    public function testCreate(?EventManager $eventManager): void
    {
        $client = $this->createMock(S3Client::class);
        $objectManager = ObjectManagerFactory::create($client, $eventManager);

        $this->assertInstanceOf(ObjectManagerInterface::class, $objectManager);
    }
}