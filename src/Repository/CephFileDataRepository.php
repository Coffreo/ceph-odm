<?php


namespace Coffreo\CephOdm\Repository;


use Aws\S3\Exception\S3Exception;
use Coffreo\CephOdm\Entity\Bucket;

/**
 * Repository for Ceph file objects
 */
class CephFileDataRepository extends AbstractCephDataRepository
{
    public function findAll(): array
    {
        return $this->findBy([]);
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $this->checkLimitAndOffset($limit, $offset);

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
            // check $offset since no result shoud be returned if offset is not equal to 0 or null
            if (!$offset && $data = $this->findByIdentifier($criteria)) {
                return [$data];
            }

            return [];
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

        $ret = [];
        $count = 0;
        foreach ($bucketNames as $bucketName) {
            $objects = $this->client->listObjects(['Bucket' => $bucketName]);
            foreach ($objects['Contents'] as $object) {
                if (isset($criteria['id']) && $object['Key'] != $criteria['id']) {
                    continue;
                }

                if ($count++ < $offset) {
                    continue;
                }

                $ret[] = $this->findByIdentifier(['bucket' => $bucketName, 'id' => $object['Key']]);
                if ($count == $limit + $offset) {
                    break(2);
                }
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
        $data = $this->findBy($criteria, null, 1);

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
}