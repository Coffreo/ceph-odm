<?php


namespace Coffreo\CephOdm\DataRepository;


use Aws\S3\Exception\S3Exception;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\Entity\File;
use Coffreo\CephOdm\EventListener\FindByFromCallListener;
use Coffreo\CephOdm\EventListener\QueryTruncatedListener;

/**
 * Repository for Ceph file objects
 */
class CephFileDataRepository extends AbstractCephDataRepository implements FindByFromCallListener
{
    /**
     * @var QueryTruncatedListener[]
     */
    private $listeners = [];

    /**
     * Last key retrieved by bucket on truncated queries
     *
     * @var string[]
     */
    private $continueKeys = [];

    /**
     * Set to true when the next query to call must use findByFrom
     *
     * @var bool
     */
    private $findByFormNextCall = false;

    public function findAll(): array
    {
        return $this->findBy([]);
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limitByBucket = null, ?int $continue = null): array
    {
        if ($this->findByFormNextCall) {
            $this->findByFormNextCall = false;

            if (!empty($criteria['id'])) {
                throw new \InvalidArgumentException(
                    "id can't be defined as criteria in findByFrom method"
                );
            }

            return $this->findBy($criteria, $orderBy, $limitByBucket, 1);
        }

        if (($limitByBucket || $continue) && !empty($criteria['id'])) {
            throw new \InvalidArgumentException(
                "limit and continue arguments can't be used if an id is defined as criteria"
            );
        }

        $this->checkLimit($limitByBucket);

        $fields = array_keys($criteria);
        foreach ($fields as $field) {
            if (!in_array($field, ['bucket', 'id', 'metadata'])) {
                throw new \InvalidArgumentException(
                    sprintf("Allowed search criteria are only bucket, id and metadata (%s provided)", $field)
                );
            }
        }

        if (isset($criteria['bucket'])) {
            $criteria['bucket'] = $this->bucketToString($criteria['bucket']);
        }

        if (isset($criteria['bucket']) && isset($criteria['id'])) {
            $data = $this->findByIdentifier($criteria);
            return $data ? [$data] : [];
        }

        $bucketNames = [];
        if (isset($criteria['bucket'])) {
            $bucketNames[] = $criteria['bucket'];
        } else {
            $buckets = $this->client->listBuckets();
            foreach ($buckets['Buckets'] as $bucket) {
                $bucketNames[] = $bucket['Name'];
            }
        }

        $clientCrit = [];
        if ($limitByBucket) {
            $clientCrit['MaxKeys'] = $limitByBucket;
        }

        $ret = [];
        $bucketsTruncated = [];
        foreach ($bucketNames as $bucketName) {
            if ($continue) {
                if (!$continueKey = $this->continueKeys[$bucketName] ?? null) {
                    continue;
                }

                $clientCrit['Marker'] = $continueKey;
            }
            unset($this->continueKeys[$bucketName]);

            $objects = $this->client->listObjects(array_merge(['Bucket' => $bucketName], $clientCrit));

            if (!empty($objects['IsTruncated'])) {
                $bucketsTruncated[] = $bucketName;
                $this->continueKeys[$bucketName] = $objects['NextMarker'];
            }

            $meta = $this->objectManager->getClassMetadata(File::class)->getFieldMappings();
            $idMapping = $meta['id']['name'];

            foreach ($objects['Contents'] as $object) {
                if (isset($criteria['id']) && $object[$idMapping] != $criteria['id']) {
                    continue;
                }

                $ret[] = $this->findByIdentifier(['bucket' => $bucketName, 'id' => $object[$idMapping]]);
            }
        }

        if (isset($criteria['metadata'])) {
            $this->filterByMetadata($ret, $criteria['metadata']);
        }

        if ($orderBy) {
            $this->sort($ret, $orderBy);
        }

        if ($bucketsTruncated) {
            foreach ($this->listeners as $listener) {
                $listener->queryTruncated($bucketsTruncated);
            }
        }

        return $ret;
    }

    private function sort(array &$result, $orderBy): void
    {
        $sortByArrayCriteria = function ($a, $b, array $orderBy, array $mappings) use (&$sortByArrayCriteria): int
        {
            foreach ($orderBy as $key => $direction) {
                $mappedKey = empty($mappings[$key]['name']) ? $key : $mappings[$key]['name'];
                if (is_array($direction)) {
                    $valA = isset($a[$mappedKey]) && is_array($a[$mappedKey]) ? $a[$mappedKey] : [];
                    $valB = isset($b[$mappedKey]) && is_array($b[$mappedKey]) ? $b[$mappedKey] : [];

                    $res = $sortByArrayCriteria($valA, $valB, $direction, []);
                } else {
                    $valA = array_key_exists($mappedKey, $a) ? $a[$mappedKey] : null;
                    $valB = array_key_exists($mappedKey, $b) ? $b[$mappedKey] : null;

                    $res = strcmp($valA, $valB) * ($direction >= 0 ? 1 : -1);
                }

                if ($res != 0) {
                    return $res;
                }
            }

            return 0;
        };

        $mappings = $this->objectManager->getClassMetadata(File::class)->getFieldMappings();
        usort($result, function ($a, $b) use ($orderBy, $sortByArrayCriteria, $mappings): int {
            return $sortByArrayCriteria($a, $b, $orderBy, $mappings);
        });
    }

    private function filterByMetadata(array &$result, array $criteriaMetadata): void
    {
        foreach ($result as $key => $res) {
            foreach ($criteriaMetadata as $metadataName => $value) {
                if (!isset($res['Metadata'][$metadataName]) || $res['Metadata'][$metadataName] != $value) {
                    unset($result[$key]);
                    break;
                }
            }
        }
    }

    public function find($id): ?array
    {
        if (!is_array($id) || count($id) != 2) {
            throw new \InvalidArgumentException("Method expect a two values array with bucket and id as argument");
        }

        $id[0] = $this->bucketToString($id[0]);

        return parent::find($id);
    }

    public function findOneBy(array $criteria): ?array
    {
        $data = $this->findBy($criteria);

        return $data[0] ?? null;
    }

    /**
     * Try to find a record with primary identifers
     *
     * @param array $identifier array with bucket and id of the searched record
     *
     * @return array|null
     */
    private function findByIdentifier(array $identifier): ?array
    {
        $meta = $this->objectManager->getClassMetadata(File::class)->getFieldMappings();
        $bucketMapping = $meta['bucket']['name'];
        $idMapping = $meta['id']['name'];

        $data = null;
        try {
            $objectData = $this->client->getObject([$bucketMapping => $identifier['bucket'], $idMapping => $identifier['id']]);
            $data = [
                $bucketMapping => $identifier['bucket'],
                $idMapping => $identifier['id'],
                $meta['bin']['name'] => $objectData['Body'],
                $meta['metadata']['name'] => $objectData['Metadata'] ?? []
            ];
        } catch (S3Exception $e) {
            if (!in_array($e->getAwsErrorCode(), ['NoSuchBucket', 'NoSuchKey'])) {
                throw $e;
            }
        }

        return $data;
    }

    private function bucketToString($bucket): string
    {
        if ($bucket instanceof Bucket) {
            return $bucket->getName();
        }

        if (is_string($bucket)) {
            return $bucket;
        }

        throw new \InvalidArgumentException("bucket must be a string or an instance of Bucket");
    }

    /**
     * @codeCoverageIgnore
     */
    public function addQueryTruncatedListener(QueryTruncatedListener $listener): void
    {
        $this->listeners[] = $listener;
    }

    private function checkLimit(?int $limit): void
    {
        if ($limit !== null && $limit < 1) {
            throw new \InvalidArgumentException(sprintf("limit %d is not valid", $limit));
        }

        if ($limit > 1000) {
            throw new \InvalidArgumentException(sprintf("limit can't be over than 1000 (actually %d)", $limit));
        }
    }

    public function findByFromCalled(array $criteria, $from, ?array $orderBy, ?int $limitByBucket)
    {
        $this->findByFormNextCall = true;

        if (is_string($from) && !empty($criteria['bucket'])) {
            $from = [$criteria['bucket'] => $from];
        }

        if (!is_array($from)) {
            throw new \InvalidArgumentException("from must be an array or a string if bucket is in criteria");
        }

        foreach ($from as $bucketName => $continueKey) {
            $this->continueKeys[$bucketName] = $continueKey;
        }
    }
}