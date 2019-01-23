<?php


namespace Coffreo\CephOdm\Test\Functional;

use Aws\S3\S3Client;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Factory\ObjectManagerFactory;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

class BucketTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var S3Client
     */
    private $client;

    private $bucketNames = [
        'mybucket2',
        'mybucket4',
        'mybucket1',
        'mybucket5',
        'mybucket3'
    ];

    public function setUp()
    {
        $this->client = new S3Client([
            'region' => 'us-east-1',
            'version' => '2006-03-01',
            'endpoint' => sprintf('http://%s/', $_ENV['CEPHDEMO_IP']),
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => $_ENV['CEPHDEMO_USER'], 'secret' => $_ENV['CEPHDEMO_PASS']]
        ]);
        $this->objectManager = ObjectManagerFactory::create($this->client);

        $this->clearDb();
    }

    public function testInsertBucket(): void
    {
        $bucketToInsert = 'mybucket';

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

    public function testRemoveBucket(): void
    {
        $bucketToRemove = 'mybucket';
        $this->client->createBucket(['Bucket' => $bucketToRemove]);

        $this->objectManager->remove(new Bucket($bucketToRemove));
        $this->objectManager->flush();

        $this->assertFalse($this->doesBucketExist($bucketToRemove));
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

    private function clearDb(): void
    {
        $buckets = $this->client->listBuckets();
        foreach ($buckets['Buckets'] as $bucket) {
            $objects = $this->client->listObjects(['Bucket' => $bucket['Name']]);
            if (isset($objects['Contents'])) {
                foreach ($objects['Contents'] as $object) {
                    $this->client->deleteObject(['Bucket' => $bucket['Name'], 'Key' => $object['Key']]);
                }
            }

            $this->client->deleteBucket(['Bucket' => $bucket['Name']]);
        }
    }

    public function tearDown()
    {
        $this->clearDb();
    }
}