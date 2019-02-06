<?php


namespace Coffreo\CephOdm\DataRepository;


use Aws\S3\Exception\S3Exception;
use Coffreo\CephOdm\Entity\Bucket;
use Coffreo\CephOdm\EventListener\QueryTruncatedListener;

/**
 * Repository for Ceph file objects
 */
class CephFileDataRepository extends AbstractCephDataRepository
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

    public function findAll(): array
    {
        return $this->findBy([]);
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $continue = null): array
    {
        $this->checkLimit($limit);

        if (($limit || $continue) && !empty($criteria['id'])) {
            throw new \InvalidArgumentException(
                "limit and continue arguments can't be used if an id is defined as criteria"
            );
        }

        $fields = array_keys($criteria);
        $idCount = 0;
        foreach ($fields as $field) {
            if ($field == 'bucket' || $field == 'id') {
                $idCount++;
            } else {
                throw new \InvalidArgumentException(
                    sprintf("Allowed search criteria are only bucket and id (%s provided)", $field)
                );
            }
        }

        if (isset($criteria['bucket'])) {
            $criteria['bucket'] = $this->bucketToString($criteria['bucket']);
        }

        if ($idCount == 2) {
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
        if ($limit) {
            $clientCrit['MaxKeys'] = $limit;
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

            foreach ($objects['Contents'] as $object) {
                if (isset($criteria['id']) && $object['Key'] != $criteria['id']) {
                    continue;
                }

                $ret[] = $this->findByIdentifier(['bucket' => $bucketName, 'id' => $object['Key']]);
            }
        }

        if ($bucketsTruncated) {
            foreach ($this->listeners as $listener) {
                $listener->queryTruncated($bucketsTruncated);
            }
        }

        return $ret;
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
        $data = null;
        try {
            $objectData = $this->client->getObject(['Bucket' => $identifier['bucket'], 'Key' => $identifier['id']]);
            $data = [
                'Bucket' => $identifier['bucket'],
                'Key' => $identifier['id'],
                'Body' => $objectData['Body'],
                'Metadata' => $objectData['Metadata'] ?? []
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
}