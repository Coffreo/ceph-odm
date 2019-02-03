<?php


namespace Coffreo\CephOdm\Test\Unit\EventListener;

use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;
use Coffreo\CephOdm\EventListener\ObjectIdentifierChangedListener;
use Doctrine\SkeletonMapper\ObjectIdentityMap;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Coffreo\CephOdm\EventListener\ObjectIdentifierChangedListener
 */
class ObjectIdentifierChangedListenerTest extends TestCase
{
    public function providerIdentifierChange(): array
    {
        $bucket = new Bucket('mybucketname');

        $file1 = new File();
        $file1->setBucket($bucket);
        $file1->assignIdentifier(['Key' => 'myidentifier']);

        $file2 = new File();
        $file2->setBucket($bucket);
        $file2->assignIdentifier(['Key' => 'myidentifier']);

        $file3 = new File();
        $file3->setBucket($bucket);
        $file3->assignIdentifier(['Key' => 'myidentifier']);

        return [
            'bucket_update' => [
                $bucket,
                'myidentifier',
                $file1,
                'bucket',
                $bucket,
                new Bucket('mynewvalue'),
                "File of bucket mybucketname id myidentifier must be detached before changing its identifiers"
            ],
            'identifier_update' => [
                $bucket,
                'myidentifier',
                $file2,
                'Key',
                'myidentifier',
                'mynewidentifier',
                "File of bucket mybucketname id myidentifier must be detached before changing its identifiers"
            ],
            'identifier_update_on_detached_sender' => [
                $bucket,
                'myidentifierinidentitymap',
                $file3,
                'Key',
                'myidentifier',
                'mynewidentifier',
                null
            ],
            'no_file_sender' => [
                $bucket,
                'myidentifier',
                new \stdClass(),
                null,
                null,
                null,
                null
            ],
            'no_identifier_key' => [
                $bucket,
                'myidentifier',
                new \stdClass(),
                null,
                null,
                null,
                null
            ],
            'first_identifier_assignation' => [
                $bucket,
                'myidentifier',
                $file1,
                'bucket',
                null,
                new Bucket('myfirstvalue'),
                null
            ],
            'no_value_change' => [
                $bucket,
                'myidentifier',
                $file1,
                'bucket',
                new Bucket('myfirstvalue'),
                new Bucket('myfirstvalue'),
                null
            ],
        ];
    }

    /**
     * @dataProvider providerIdentifierChange
     * @covers ::identifierChanged
     *
     * @param Bucket $identityMapBucket bucket of the object already in identityMap
     * @param string $identityMapId id of the object already in identityMap
     * @param object $sender object on which the identifier change is processed
     * @param string|null $propertyName name of the property which is being changed
     * @param mixed $oldValue old value of the property
     * @param mixed $newValue new value of the property
     * @param string|null $expectedExceptionMessage exception message if an exception should be thrown, null otherwise
     */
    public function testIdentifierChanged(
        Bucket $identityMapBucket,
        string $identityMapId,
        $sender,
        ?string $propertyName,
        $oldValue,
        $newValue,
        ?string $expectedExceptionMessage = null
    ): void
    {
        if ($expectedExceptionMessage !== null) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $objectIdentificationForIdentityMap = new File();
        $objectIdentificationForIdentityMap->setBucket($identityMapBucket);
        $objectIdentificationForIdentityMap->assignIdentifier(['Key' => $identityMapId]);

        $objectIdentityMap = $this->createMock(ObjectIdentityMap::class);
        $objectIdentityMap
            ->method('contains')
            ->willReturnCallback(function($object) use ($objectIdentificationForIdentityMap) {
                return $objectIdentificationForIdentityMap == $object;
            });

        $sut = new ObjectIdentifierChangedListener($objectIdentityMap);
        $sut->identifierChanged($sender, $propertyName, $oldValue, $newValue);

        // Since @doesNotPerformAssertions doesn't allow to perform code coverage
        $this->assertTrue(true);
    }
}