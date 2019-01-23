# Ceph ODM
[![Build Status](https://travis-ci.org/Coffreo/ceph-odm.svg?branch=master)](https://travis-ci.org/Coffreo/ceph-odm)
[![codecov](https://codecov.io/gh/Coffreo/ceph-odm/branch/master/graph/badge.svg)](https://codecov.io/gh/Coffreo/ceph-odm)

This repository presents a Ceph odm based on Doctrine mapper skeleton.

## Installation
The recommanded way to install this project is using composer:
```bash
$ composer require coffreo/ceph-odm
```

## Basic usage

First, you need to instanciate an `Amazon S3` client:
```php
$s3Client = new \Aws\S3\S3Client([
    'region' => '',
    'version' => '2006-03-01',
    'endpoint' => 'http://my-ceph-server/',
    'use_path_style_endpoint' => true,
    'credentials' => ['key' => 'userAccessKey', 'secret' => 'userSecretKey']
]);
```
`use_path_style_endpoint` is important, it allows to internally generate urls like `http://my-ceph-server/mybucket` instead of urls like `http://mybucket.my-ceph-server/`.

Once your client is instanciated, use it to create your `ObjectManager`:

```php
$objectManager =  \Coffreo\CephOdm\Factory\ObjectManagerFactory::create($s3Client);
```

Note that you can pass an `Doctrine\Common\EventManager` as `create` second argument if you have to deal with Doctrine events.

### Create a bucket
Before creating objects, you must create a bucket for storing them into:
```php
$objectManager->persist(new \Coffreo\CephOdm\Entity\Bucket('my-bucket'));
$objectManager->flush();
```

### Create a new object
```php
$object = new \Coffreo\CephOdm\Entity\File();
$object->setBucket(new \Coffreo\CephOdm\Entity\Bucket('my-bucket'));
$object->setFilename('test.txt');
$object->setBin('my-file-content');
$object->setAllMetadata(['my-metadata1' => 'my-value1', 'my-metadata2' => 'my-value2']);
$objectManager->persist($object);
$objectManager->flush();
$objectManager->clear();

echo $object->getId(); // e223fc11-8046-4a84-98e2-0de912d071e9 for instance since object is stored
```

*Be careful, only lowercase strings are accepted as metadata keys.*

### Update an object
```php
$object->setBin('my-content-updated);
$object->addMetadata('my-metadata2', 'my-new-metadata-value);
$objectManager->flush();
$objectManager->clear();
```

### Remove an object
```php
$objectManager->remove($object);
$objectManager->flush();
$objectManager->clear();
```

### Find an object by its identifiers
Bucket and id are the primary identifiers of objects.
```php
$fileRepository = $objectManager->getRepository(\Coffreo\CephOdm\Entity\File::class);
$object = $fileRepository->find([new \Coffreo\CephOdm\Entity\Bucket('my-bucket'), 'e223fc11-8046-4a84-98e2-0de912d071e9']);

echo $object->getFilename();    // test.txt
```
In repository find methods, you can use the bucket name or a bucket object in your criteria:
```php
$fileRepository->find([new \Coffreo\CephOdm\Entity\Bucket('my-bucket'), 'e223fc11-8046-4a84-98e2-0de912d071e9']);
```
Is the same thing as:
```php
$fileRepository->find('my-bucket', 'e223fc11-8046-4a84-98e2-0de912d071e9']);
```

### Other find methods
```php
$objects = $fileRepository->findAll();  // All objects of all buckets

$objects = $fileRepository->findBy(['bucket' => 'my-bucket']);  // All objects of the bucket
$objects = $fileRepository->findBy(['id' => 'e223fc11-8046-4a84-98e2-0de912d071e9']); // All objects in any bucket of the given id
```
The previous statements only return objects that the **logged user owns**. For now, you can only perform a search on bucket and/or id.

