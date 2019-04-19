# Ceph ODM
[![Build Status](https://travis-ci.org/Coffreo/ceph-odm.svg?branch=master)](https://travis-ci.org/Coffreo/ceph-odm)
[![codecov](https://codecov.io/gh/Coffreo/ceph-odm/branch/master/graph/badge.svg)](https://codecov.io/gh/Coffreo/ceph-odm)
[![packagist](https://img.shields.io/packagist/v/coffreo/ceph-odm.svg)](https://github.com/Coffreo/ceph-odm)

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

### Duplicate an object
You can easyly clone an object by persisting it again. The only thing to keep in mind is to detach the entity:
```php
$object = $fileRepository->find(/* ... */);
$objectManager->detach($object);

// You can update (or not) the object properties before saving it
$object->setBin('my-other-content');

$objectManager->persist();
$objectManager->flush();
```
The object will be saved with a new id. You can also save it to another bucket:
```php
$object = $fileRepository->find(/* ... */);
$objectManager->detach($object);

$object->setBucket(new \Coffreo\CephOdm\Entity\Bucket('my-bucket-2));
// You can update (or not) the object properties before saving it
$object->setBin('my-other-content');

$objectManager->persist();
$objectManager->flush();
```

### Find an object by its identifiers
Bucket and id are the primary identifiers of objects.
```php
$fileRepository = $objectManager->getRepository(\Coffreo\CephOdm\Entity\File::class);
$object = $fileRepository->find([new \Coffreo\CephOdm\Entity\Bucket('my-bucket'), 'e223fc11-8046-4a84-98e2-0de912d071e9']);

echo $object->getFilename();    // test.txt
```
In repository find methods, you must use the bucket name or a bucket object in your criteria:
```php
$object = $fileRepository->find([new \Coffreo\CephOdm\Entity\Bucket('my-bucket'), 'e223fc11-8046-4a84-98e2-0de912d071e9']);
```
Is the same thing as:
```php
$object = $fileRepository->find(['my-bucket', 'e223fc11-8046-4a84-98e2-0de912d071e9']);
```

### Other find methods
```php
$objects = $fileRepository->findAll();  // All objects of all buckets

$objects = $fileRepository->findBy(['bucket' => 'my-bucket']);  // All objects of the bucket
$objects = $fileRepository->findBy(['id' => 'e223fc11-8046-4a84-98e2-0de912d071e9']); // All objects in any bucket of the given id
```
The previous statements only return objects that the **logged user owns**. For now, you can only perform a search on bucket and/or id.

### Filter results by metadata
You can also use metadata as filter
```php
$objects = $fileRepository->findBy(['bucket' => 'my-bucket', 'metadata' => ['mymetadata' => 'myvalue']]);
```
Be careful, it's only a filter. It's not native, all files are retrieved, filtering is done after. Furthermore the criteria `metadata => []` won't return all files without metadata. It means no metadata filter, so all the files will be returned according by the possible other criteria.

### Sort results
The results can be sorted but it's not a database sort. The sort is done programmatically so it's not optimized and it's applyed after the bucket limit. By default, the results are ordered by bucket name and id. For ordering a query by a filename metadata (desc) and by id (asc):
```php
$objects = $fileRepository->findBy([], ['metadata' => ['filename' => -1], 'id' => 1]);
```

### Truncated results
For the find methods which return many files (`findBy` and `findAll`), if there is too many results (more than the limit you specified or 1000 by default), the names of the buckets where all the files couldn't be returned are returned by `getBucketsTruncated`:
```php
// Let's set the limit to 10
$objects = $fileRepository->findBy(['bucket' => 'mybucket'], [], 10);
foreach ($objects->getBucketsTruncated() as $bucketName) {
    // some files of the bucket $bucketName ('mybucket' in our case) was not returned
}
```

### Resume truncated queries
You can use the `continue` parameter to resume a previously truncated query. For instance for retrieving the files of `mybucket` that was not retrieved by the query above:
```php
// It may be necessary to do this call many times. Do this call in a loop until $objects->getBucketsTruncated() returns an empty array.
$objects = $fileRepository->findBy(['bucket' => 'mybucket'], [], null, 1);
```
For making this possible, the repository keeps a pointer on the last file returned by bucket. Note that this pointer is modified when another query is done on the bucket; the calls bellow update the pointer for bucket `mybucket`:
* `findBy(['bucket' => 'mybucket'])`
* `findOneBy(['id' => 'myid'])`
* `findBy([])`
* `findAll`  
Only `find` never modify the internal pointer. 


This is another example for retrieving all files of the connected user:
```php
$truncated = []
do {
   $objects = $fileRepository->findBy([], [], null, $truncated ? 1 : 0);
   // Do something with objects
   $truncated = $objects->getBucketsTruncated();
} while ($truncated);
```
Note that you can use `findAll` on the first call too.

Finally, the `findByFrom` method returns files starting **after** the given identifier:
```php
$objects = $fileRepository->findByFrom(['bucket' => 'mybucket'], ['mybucket' => 'myid3']);
// Returns files myid4, myid5, myid6... but not myid3
// Since the criteria specifies the bucket, you can even simplify by: findByFrom(['bucket' => 'mybucket'], 'myid3')
```

## Lazy load
When queries that return multiple results are used (i.e. queries which don't specify bucket and id), `bin` and `metadata` are not loaded directly since getting them requires to perform another specific server call per result. This library uses in these cases a lazy load strategy and retrieves bin and metadata only when `getBin`, `getAllMetadata`, `getMetadata` or `setMetadata` is called. You won't normally have to worry about it but it could be useful to be aware about it.