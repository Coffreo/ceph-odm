<?php


namespace Coffreo\CephOdm\Repository;

/**
 * Repository for Ceph bucket objects
 */
class CephBucketDataRepository extends AbstractCephDataRepository
{
    public function findAll(): array
    {
        $buckets = $this->client->listBuckets();
        return $buckets['Buckets'];
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        if ($criteria && array_keys($criteria) != ['name']) {
            throw new \InvalidArgumentException("Search can only be performed by name for a Bucket");
        }

        $this->checkLimitAndOffset($limit, $offset);

        $result = $this->findAll();

        if (isset($criteria['name'])) {
            $result = array_filter($result, function (array $data) use ($criteria) { return $data['Name'] == $criteria['name']; });
            return !$offset ? $result : [];
        }

        if ($orderBy) {
            if (array_keys($orderBy) != ['name']) {
                throw new \InvalidArgumentException("Order by can only be performed on name for a Bucket");
            }

            if (isset($orderBy['name'])) {
                $order = $orderBy['name'] >= 0 ? 1 : -1;
                usort($result, function (array $data1, array $data2) use ($order) { return $order * strcmp($data1['Name'], $data2['Name']); });
            }
        }

        if ($limit !== null || $offset !== null) {
            if ($offset === null) {
                $offset = 0;
            }

            return array_slice($result, $offset, $limit);
        }

        return $result;
    }

    public function findOneBy(array $criteria): ?array
    {
        $result = $this->findBy($criteria);
        return $result ? current($result) : null;
    }

}