<?php


namespace Coffreo\CephOdm\Test\Functional;


use PHPUnit\Framework\TestCase;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Aws\S3\S3Client;
use Coffreo\CephOdm\Factory\ObjectManagerFactory;

abstract class AbstractFunctionalTestCase extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var S3Client
     */
    protected $client;

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

    private function clearDb(): void
    {
        $buckets = $this->client->listBuckets();
        foreach ($buckets['Buckets'] as $bucket) {
            do {
                $objects = $this->client->listObjects(['Bucket' => $bucket['Name']]);
                if (isset($objects['Contents'])) {
                    foreach ($objects['Contents'] as $object) {
                        $this->client->deleteObject(['Bucket' => $bucket['Name'], 'Key' => $object['Key']]);
                    }
                }
            } while(!empty($objects['IsTruncated']));
            $this->client->deleteBucket(['Bucket' => $bucket['Name']]);
        }
    }

    public function tearDown()
    {
        $this->clearDb();
    }
}