<?php


namespace Coffreo\CephOdm\Test\Functional;

use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;

class FileTest extends AbstractFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->client->createBucket(['Bucket' => 'mybucket']);
    }

    public function providerInsertFile(): array
    {
        return [
            ['myfilename.txt', null, ['filename' => 'myfilename.txt']],
            [null, null, []],
            [null, ['mymetadata' => 'myvalue'], ['mymetadata' => 'myvalue']],
            ['myfilename.txt', ['my.metadata-1' => 'myvalue'], ['filename' => 'myfilename.txt', 'my.metadata-1' => 'myvalue']],
        ];
    }

    /**
     * @dataProvider providerInsertFile
     */
    public function testInsertFile(?string $filename, ?array $metadata, array $expectedMetadata): void
    {
        $file = new File();
        $file->setBucket(new Bucket('mybucket'));
        $file->setBin('mydata');

        if ($filename) {
            $file->setFilename($filename);
        }

        if ($metadata) {
            $file->setAllMetadata($metadata);
        }

        $this->objectManager->persist($file);
        $this->objectManager->flush();

        $id = $file->getId();
        $this->assertNotEmpty($id);

        $object = $this->client->getObject(['Bucket' => 'mybucket', 'Key' => $id]);
        $this->assertEquals('mydata', $object['Body']);
        $this->assertEquals($expectedMetadata, $object['Metadata'] ?? null);
    }

    public function testUpdateFile(): void
    {
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'mykey', 'Body' => 'mydata']);

        $repo = $this->objectManager->getRepository(File::class);
        $file = $repo->find(['mybucket', 'mykey']);

        $file->setBin('mynewdata');
        $file->setMetadata('mymetadata', 'myvalue');
        $this->objectManager->flush();

        $object = $this->client->getObject(['Bucket' => 'mybucket', 'Key' => 'mykey']);
        $this->assertEquals('mynewdata', $object['Body']);
        $this->assertEquals(['mymetadata' => 'myvalue'], $object['Metadata']);
    }

    /**
     * @expectedException Aws\S3\Exception\S3Exception
     * @expectedExceptionMessage 404 Not Found
     */
    public function testRemoveFile(): void
    {
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'mykey', 'Body' => 'mydata']);

        $repo = $this->objectManager->getRepository(File::class);

        $file = $repo->find(['mybucket', 'mykey']);
        $this->assertInstanceOf(File::class, $file);

        $this->objectManager->remove($file);
        $this->objectManager->flush();

        $this->client->getObject(['Bucket' => 'mybucket', 'Key' => 'mykey']);
    }

    public function testFind(): void
    {
        $bucket = new Bucket('mybucket');
        $bucket2 = new Bucket('mybucket2');

        $this->client->createBucket(['Bucket' => 'mybucket2']);

        $expectedFile1 = new File();
        $expectedFile1->setBucket($bucket);
        $expectedFile1->assignIdentifier(['Key' => 'myid1']);
        $expectedFile1->setBin('mybody1');
        $expectedFile1->setAllMetadata(['mymetadata' => 'myvalue']);
        $expectedFile1->addPropertyChangedListener($this->objectManager->getUnitOfWork());

        $expectedFile2 = new File();
        $expectedFile2->setBucket($bucket);
        $expectedFile2->assignIdentifier(['Key' => 'myid2']);
        $expectedFile2->setBin('mybody2');
        $expectedFile2->addPropertyChangedListener($this->objectManager->getUnitOfWork());

        $expectedFile3 = new File();
        $expectedFile3->setBucket($bucket);
        $expectedFile3->assignIdentifier(['Key' => 'myid3']);
        $expectedFile3->setBin('mybody3');
        $expectedFile3->setFilename('myfile3.txt');
        $expectedFile3->setAllMetadata(['mymetadata' => 'myvalue2']);
        $expectedFile3->addPropertyChangedListener($this->objectManager->getUnitOfWork());

        $expectedFile4 = new File();
        $expectedFile4->setBucket($bucket2);
        $expectedFile4->assignIdentifier(['Key' => 'myid2']);
        $expectedFile4->setBin('mybody4');
        $expectedFile4->setFilename('myfile4.txt');
        $expectedFile4->addPropertyChangedListener($this->objectManager->getUnitOfWork());

        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'myid1', 'Body' => 'mybody1', 'Metadata' => ['mymetadata' => 'myvalue']]);
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'myid2', 'Body' => 'mybody2']);
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'myid3', 'Body' => 'mybody3', 'Metadata' => ['mymetadata' => 'myvalue2', 'filename' => 'myfile3.txt']]);
        $this->client->putObject(['Bucket' => 'mybucket2', 'Key' => 'myid2', 'Body' => 'mybody4', 'Metadata' => ['filename' => 'myfile4.txt']]);

        $repo = $this->objectManager->getRepository(File::class);

        $file = $repo->find(['mybucket', 'myid1']);
        $this->assertEquals($expectedFile1, $file);

        $file = $repo->findOneBy(['bucket' => 'mybucket', 'id' => 'myid2']);
        $this->assertEquals($expectedFile2, $file);

        $files = $repo->findAll();
        $this->assertEquals([$expectedFile1, $expectedFile2, $expectedFile3, $expectedFile4], $files);

        $files = $repo->findBy(['bucket' => 'mybucket']);
        $this->assertEquals([$expectedFile1, $expectedFile2, $expectedFile3], $files);

        $files = $repo->findBy(['id' => 'myid2']);
        $this->assertEquals([$expectedFile2, $expectedFile4], $files);

        $files = $repo->findBy(['bucket' => 'mybucket', 'id' => 'myid3']);
        $this->assertEquals([$expectedFile3], $files);

        $files = $repo->findBy(['bucket' => 'mybucket2', 'id' => 'myid3']);
        $this->assertEquals([], $files);

        $files = $repo->findBy(['bucket' => 'mybucket'], null, 2);
        $this->assertEquals([$expectedFile1, $expectedFile2], $files);

        $files = $repo->findBy(['bucket' => 'mybucket'], null, 2, 1);
        $this->assertEquals([$expectedFile2, $expectedFile3], $files);

        $files = $repo->findBy(['bucket' => 'mybucket'], null, 2, 2);
        $this->assertEquals([$expectedFile3], $files);

        $files = $repo->findBy(['bucket' => 'mybucket'], null, 2, 3);
        $this->assertEquals([], $files);
    }

    public function testFindWithInexistentFile(): void
    {
        $repo = $this->objectManager->getRepository(File::class);
        $this->assertNull($repo->find(['myinexistentbucket', 'myinexistentfile']));
        $this->assertNull($repo->find(['mybucket', 'myinexistentfile']));
        $this->assertNull($repo->find(['myinexistentbucket', 'myid1']));
    }

    public function testFindOneByWithInexistentFile(): void
    {
        $repo = $this->objectManager->getRepository(File::class);
        $this->assertNull($repo->findOneBy(['bucket' => 'myinexistentbucket', 'id' => 'myinexistentfile']));
        $this->assertNull($repo->findOneBy(['bucket' => 'mybucket', 'id' => 'myinexistentfile']));
        $this->assertNull($repo->findOneBy(['bucket' => 'myinexistentbucket', 'id' => 'myid1']));
    }
}