<?php


namespace Coffreo\CephOdm\Test;

use Aws\S3\S3Client;


/**
 * Allow to get magic methods availables for mock
 */
class DummyS3Client extends S3Client
{
    public function putObject(array $args = [])
    {
        parent::putObject($args);
    }

    public function deleteObject(array $args = [])
    {
        parent::deleteObject($args);
    }

    public function createBucket(array $args = [])
    {
        parent::createBucket($args);
    }

    public function deleteBucket(array $args = [])
    {
        parent::deleteBucket($args);
    }

    public function listBuckets(array $args = [])
    {
        return parent::listBuckets($args);
    }

    public function listObjects(array $args = [])
    {
        parent::listObjects($args);
    }

    public function getObject(array $args = [])
    {
        parent::getObject($args);
    }
}