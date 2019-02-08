<?php


namespace Coffreo\CephOdm\Test\Functional;

use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;
use Coffreo\CephOdm\ResultSet\FileResultSet;

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
            [null, []],
            [['filename' => 'myfilename.txt'], ['filename' => 'myfilename.txt']],
            [['mymetadata' => 'myvalue'], ['mymetadata' => 'myvalue']],
            [['my.metadata-1' => 'myvalue', 'filename' => 'myfilename.txt'], ['filename' => 'myfilename.txt', 'my.metadata-1' => 'myvalue']],
        ];
    }

    /**
     * @dataProvider providerInsertFile
     */
    public function testInsertFile(?array $metadata, array $expectedMetadata): void
    {
        $file = new File();
        $file->setBucket(new Bucket('mybucket'));
        $file->setBin('mydata');

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

    // TODO: uncoment when can be optimized (and add test with findAll if possible and usefull
//    public function testResultTruncated(): void
//    {
//        $repo = $this->objectManager->getRepository(File::class);
//
//        for ($i=1; $i <= 1005; $i++) {
//            $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'mykey'.$i, 'Body' => 'mydata'.$i]);
//
//            if ($i == 999) {
//                $all = $repo->findAll();
//                $this->assertEquals([], $all->getBucketsTruncated());
//            }
//        }
//
//        $all = $repo->findAll();
//        $this->assertEquals(['mybucket'], $all->getBucketsTruncated());
//    }

    public function testUpdateFile(): void
    {
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'mykey', 'Body' => 'mydata']);

        $repo = $this->objectManager->getRepository(File::class);
        $file = $repo->find(['mybucket', 'mykey']);

        $file->setBin('mynewdata');
        $file->setMetadata('filename', 'myfilename.txt');
        $this->objectManager->flush();

        $object = $this->client->getObject(['Bucket' => 'mybucket', 'Key' => 'mykey']);
        $this->assertEquals('mynewdata', $object['Body']);
        $this->assertEquals(['filename' => 'myfilename.txt'], $object['Metadata']);
    }

    public function testPersistWithUpdateFileBucketShouldCreateANewFile(): void
    {
        $this->client->createBucket(['Bucket' => 'mybucket2']);
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'mykey', 'Body' => 'mydata']);

        $repo = $this->objectManager->getRepository(File::class);
        $file = $repo->find(['mybucket', 'mykey']);
        $this->objectManager->detach($file);

        $file->setBucket(new Bucket('mybucket2'));
        $file->setBin('mydata2');
        $file->setMetadata('mymeta', 'myvalue');
        $this->objectManager->persist($file);
        $this->objectManager->flush();

        $object1 = $this->client->getObject(['Bucket' => 'mybucket', 'Key' => 'mykey']);
        $this->assertEquals([], $object1['Metadata']);

        $object2 = $this->client->getObject(['Bucket' => 'mybucket2', 'Key' => $file->getId()]);
        $this->assertEquals('myvalue', $object2['Metadata']['mymeta']);
        $this->assertEquals('mydata2', (string)$object2['Body']);
    }

    public function testPersistWithUpdateFileIdShouldCreateANewFile(): void
    {
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'mykey', 'Body' => 'mydata']);

        $repo = $this->objectManager->getRepository(File::class);
        $file = $repo->find(['mybucket', 'mykey']);
        $this->objectManager->detach($file);

        $file->setBin('mydata2');
        $file->setMetadata('mymeta', 'myvalue');
        $this->objectManager->persist($file);
        $this->objectManager->flush();

        $object1 = $this->client->getObject(['Bucket' => 'mybucket', 'Key' => 'mykey']);
        $this->assertEquals([], $object1['Metadata']);

        $object2 = $this->client->getObject(['Bucket' => 'mybucket', 'Key' => $file->getId()]);
        $this->assertEquals('myvalue', $object2['Metadata']['mymeta']);
        $this->assertEquals('mydata2', (string)$object2['Body']);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage File of bucket mybucket id mykey must be detached before changing its identifiers
     */
    public function testPersistWithUpdateBucketAndNonDetachedObjectShouldThrowException(): void
    {
        $this->client->createBucket(['Bucket' => 'mybucket2']);
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'mykey', 'Body' => 'mydata']);

        $repo = $this->objectManager->getRepository(File::class);
        $file = $repo->find(['mybucket', 'mykey']);

        $file->setBucket(new Bucket('mybucket2'));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage File of bucket mybucket id mykey must be detached before changing its identifiers
     */
    public function testPersistWithUpdateFileIdAndNonDetachedObjectShouldThrowException(): void
    {
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'mykey', 'Body' => 'mydata']);

        $repo = $this->objectManager->getRepository(File::class);
        $file = $repo->find(['mybucket', 'mykey']);

        $file->setBin('mydata2');
        $file->setMetadata('mymeta', 'myvalue');
        $this->objectManager->persist($file);
        $this->objectManager->flush();
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

    private function createFindTestsData(): array
    {
        $bucket = new Bucket('mybucket');
        $bucket2 = new Bucket('mybucket2');

        $this->client->createBucket(['Bucket' => 'mybucket2']);

        $expectedFile1 = new File();
        $expectedFile1->assignIdentifier(['Bucket' => $bucket, 'Key' => 'myid1']);
        $expectedFile1->setBin('mybody1');
        $expectedFile1->setAllMetadata(['mymetadata' => 'myvalue']);

        $expectedFile2 = new File();
        $expectedFile2->assignIdentifier(['Bucket' => $bucket, 'Key' => 'myid2']);
        $expectedFile2->setBin('mybody2');

        $expectedFile3 = new File();
        $expectedFile3->assignIdentifier(['Bucket' => $bucket, 'Key' => 'myid3']);
        $expectedFile3->setBin('mybody3');
        $expectedFile3->setAllMetadata(['filename' => 'myfile3.txt', 'mymetadata' => 'myvalue2']);

        $expectedFile4 = new File();
        $expectedFile4->assignIdentifier(['Bucket' => $bucket2, 'Key' => 'myid2']);
        $expectedFile4->setBin('mybody4');
        $expectedFile4->setMetadata('filename', 'myfile4.txt');

        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'myid1', 'Body' => 'mybody1', 'Metadata' => ['mymetadata' => 'myvalue']]);
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'myid2', 'Body' => 'mybody2']);
        $this->client->putObject(['Bucket' => 'mybucket', 'Key' => 'myid3', 'Body' => 'mybody3', 'Metadata' => ['mymetadata' => 'myvalue2', 'filename' => 'myfile3.txt']]);
        $this->client->putObject(['Bucket' => 'mybucket2', 'Key' => 'myid2', 'Body' => 'mybody4', 'Metadata' => ['filename' => 'myfile4.txt']]);

        return [$expectedFile1, $expectedFile2, $expectedFile3, $expectedFile4];
    }

    public function testFind(): void
    {
        list($expectedFile1, $expectedFile2, $expectedFile3, $expectedFile4) = $this->createFindTestsData();

        $repo = $this->objectManager->getRepository(File::class);

        $file = $repo->find(['mybucket', 'myid1']);
        $this->compareFiles([$expectedFile1], [$file]);

        $file = $repo->findOneBy(['bucket' => 'mybucket', 'id' => 'myid2']);
        $this->compareFiles([$expectedFile2], [$file]);

        $files = $repo->findAll();
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->compareFiles([$expectedFile1, $expectedFile2, $expectedFile3, $expectedFile4], $files);

        $files = $repo->findBy(['bucket' => 'mybucket']);
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->assertEquals([], $files->getBucketsTruncated());
        $this->compareFiles([$expectedFile1, $expectedFile2, $expectedFile3], $files);

        $files = $repo->findBy(['id' => 'myid2']);
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->compareFiles([$expectedFile2, $expectedFile4], $files);

        $files = $repo->findBy(['bucket' => 'mybucket', 'id' => 'myid3']);
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->compareFiles([$expectedFile3], $files);

        $files = $repo->findBy(['bucket' => 'mybucket2', 'id' => 'myid3']);
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->compareFiles([], $files);
    }

    public function testFindByWithLimit(): void
    {
        list($expectedFile1, $expectedFile2, $expectedFile3) = $this->createFindTestsData();

        $repo = $this->objectManager->getRepository(File::class);

        $files = $repo->findBy(['bucket' => 'mybucket'], null, 2);
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->assertEquals(['mybucket'], $files->getBucketsTruncated());
        $this->compareFiles([$expectedFile1, $expectedFile2], $files);

        $files = $repo->findBy(['bucket' => 'mybucket'], null, 2, 1);
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->compareFiles([$expectedFile3], $files);

        $files = $repo->findBy(['bucket' => 'mybucket'], null, 2, 1);
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->compareFiles([], $files);

        $files = $repo->findBy(['bucket' => 'mybucket'], null, 1);
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->compareFiles([$expectedFile1], $files);

        $files = $repo->findBy(['bucket' => 'mybucket'], null, 2);
        $this->assertInstanceOf(FileResultSet::class, $files);
        $this->assertEquals(['mybucket'], $files->getBucketsTruncated());
        $this->compareFiles([$expectedFile1, $expectedFile2], $files);
    }

    public function testFindByFrom(): void
    {
        list(, $expectedFile2, $expectedFile3) = $this->createFindTestsData();
        $repo = $this->objectManager->getRepository(File::class);

        $files = $repo->findByFrom(['bucket' => 'mybucket'], 'myid1');
        $this->compareFiles([$expectedFile2, $expectedFile3], $files);

        $files = $repo->findByFrom(['bucket' => 'mybucket'], 'myid1', [], 1);
        $this->compareFiles([$expectedFile2], $files);

        $files = $repo->findByFrom([], ['mybucket' => 'myid1']);
        $this->compareFiles([$expectedFile2, $expectedFile3], $files);
    }

    /**
     * @param File[] $expectedFiles
     * @param File[] $actualFiles
     */
    private function compareFiles(array $expectedFiles, iterable $actualFiles): void
    {
        $this->assertCount(count($expectedFiles), $actualFiles);

        foreach ($expectedFiles as $key => $expectedFile) {
            $actualFile = $actualFiles[$key];
            $this->assertEquals($expectedFile->getBucket(), $actualFile->getBucket());
            $this->assertEquals($expectedFile->getId(), $actualFile->getId());
            $this->assertEquals($expectedFile->getBin(), $actualFile->getBin());
            $this->assertEquals($expectedFile->getAllMetadata(), $actualFile->getAllMetadata());
        }
    }

    public function testFindWithNonExistentFile(): void
    {
        $repo = $this->objectManager->getRepository(File::class);
        $this->assertNull($repo->find(['mynonexistentbucket', 'mynonexistentfile']));
        $this->assertNull($repo->find(['mybucket', 'mynonexistentfile']));
        $this->assertNull($repo->find(['mynonexistentbucket', 'myid1']));
    }

    public function testFindOneByWithNonExistentFile(): void
    {
        $repo = $this->objectManager->getRepository(File::class);
        $this->assertNull($repo->findOneBy(['bucket' => 'mynonexistentbucket', 'id' => 'mynonexistentfile']));
        $this->assertNull($repo->findOneBy(['bucket' => 'mybucket', 'id' => 'mynonexistentfile']));
        $this->assertNull($repo->findOneBy(['bucket' => 'mynonexistentbucket', 'id' => 'myid1']));
    }

    /**
     * @expectedException \Coffreo\CephOdm\Exception\Exception
     * @expectedExceptionMessage Bucket mynonexistentbucket doesn't exist
     * @expectedExceptionCode \Coffreo\CephOdm\Exception\Exception::BUCKET_NOT_FOUND
     */
    public function testPersistInNonExistentBucketShouldThrowException(): void
    {
        $file = new File();
        $file->assignIdentifier(['Bucket' => new Bucket('mynonexistentbucket'), 'Key' => 'mykey']);
        $file->setBin('mydata');

        $this->objectManager->persist($file);
        $this->objectManager->flush();
    }

    /**
     * @expectedException \Coffreo\CephOdm\Exception\Exception
     * @expectedExceptionMessage Bucket mynonexistentbucket doesn't exist
     * @expectedExceptionCode \Coffreo\CephOdm\Exception\Exception::BUCKET_NOT_FOUND
     */
    public function testRemoveInNonExistentBucketShouldThrowException(): void
    {
        $file = new File();
        $file->assignIdentifier(['Bucket' => new Bucket('mynonexistentbucket'), 'Key' => 'mykey']);

        $this->objectManager->remove($file);
        $this->objectManager->flush();
    }
}