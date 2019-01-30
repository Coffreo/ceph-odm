<?php


namespace Coffreo\CephOdm\Test\Functional;

use Coffreo\CephOdm\Entity\Bucket;

class BucketTest extends AbstractFunctionalTestCase
{
    private $bucketNames = [
        'mybucket2',
        'mybucket4',
        'mybucket1',
        'mybucket5',
        'mybucket3'
    ];

    public function testInsertBucket(): void
    {
        $bucketToInsert = 'My.bucket-with_authorized-CHARACTERS';

        $this->objectManager->persist(new Bucket($bucketToInsert));
        $this->objectManager->flush();

        $this->assertTrue($this->doesBucketExist($bucketToInsert));
    }

    public function testFind(): void
    {
        foreach ($this->bucketNames as $bucketName) {
            $this->client->createBucket(['Bucket' => $bucketName]);
        }

        $sortedBuckets = [
            new Bucket('mybucket1'),
            new Bucket('mybucket2'),
            new Bucket('mybucket3'),
            new Bucket('mybucket4'),
            new Bucket('mybucket5')
        ];

        $rsortedBuckets = [
            new Bucket('mybucket5'),
            new Bucket('mybucket4'),
            new Bucket('mybucket3'),
            new Bucket('mybucket2'),
            new Bucket('mybucket1')
        ];

        $bucketToFind = $this->bucketNames[3];

        $repo = $this->objectManager->getRepository(Bucket::class);
        $expectedBucket = new Bucket($bucketToFind);

        $bucket = $repo->find($bucketToFind);
        $this->assertEquals($expectedBucket, $bucket);

        $bucket = $repo->findOneBy(['name' => $bucketToFind]);
        $this->assertEquals($expectedBucket, $bucket);

        $buckets = $repo->findAll();
        $this->assertEquals($sortedBuckets, $buckets);

        $buckets = $repo->findBy(['name' => $bucketToFind]);
        $this->assertEquals([$expectedBucket], $buckets);

        $buckets = $repo->findBy(['name' => 'mynonexistentbucket']);
        $this->assertEquals([], $buckets);

        $buckets = $repo->findBy(['name' => $bucketToFind], null, null, 1);
        $this->assertEquals([], $buckets);

        $buckets = $repo->findBy([], null, 3);
        $this->assertEquals(array_slice($sortedBuckets, 0, 3), $buckets);

        $buckets = $repo->findBy([], null, 3, 2);
        $this->assertEquals(array_slice($sortedBuckets, 2, 3), $buckets);

        $buckets = $repo->findBy([], null, 3, 3);
        $this->assertEquals(array_slice($sortedBuckets, 3), $buckets);

        $buckets = $repo->findBy([], ['name' => -1], 3);
        $this->assertEquals(array_slice($rsortedBuckets, 0, 3), $buckets);

        $buckets = $repo->findBy([], ['name' => -1], 3, 2);
        $this->assertEquals(array_slice($rsortedBuckets, 2, 3), $buckets);

        $buckets = $repo->findBy([], ['name' => -1], 3, 3);
        $this->assertEquals(array_slice($rsortedBuckets, 3), $buckets);
    }

    public function testFindWithNonExistentBucket(): void
    {
        $repo = $this->objectManager->getRepository(Bucket::class);
        $this->assertNull($repo->find(['mynonexistentbucket']));
    }

    public function testFindOneByWithNonExistentBucket(): void
    {
        $repo = $this->objectManager->getRepository(Bucket::class);
        $this->assertNull($repo->findOneBy(['name' => 'mynonexistentbucket']));
    }

    public function testRemoveBucket(): void
    {
        $bucketToRemove = 'mybucket';
        $this->client->createBucket(['Bucket' => $bucketToRemove]);

        $this->objectManager->remove(new Bucket($bucketToRemove));
        $this->objectManager->flush();

        $this->assertFalse($this->doesBucketExist($bucketToRemove));
    }

    /**
     * @expectedException \Coffreo\CephOdm\Exception\Exception
     * @expectedExceptionMessage Bucket mynonexistentbucket doesn't exist
     * @expectedExceptionCode \Coffreo\CephOdm\Exception\Exception::BUCKET_NOT_FOUND
     */
    public function testRemoveNonExistentBucketShouldThrowException(): void
    {
        $this->objectManager->remove(new Bucket('mynonexistentbucket'));
        $this->objectManager->flush();
    }

    private function doesBucketExist(string $bucketName): bool
    {
        $buckets = $this->client->listBuckets();

        $found = false;
        foreach ($buckets['Buckets'] as $bucket) {
            if ($bucket['Name'] == $bucketName) {
                $found = true;
                break;
            }
        }

        return $found;
    }
}