<?php


namespace Coffreo\CephOdm\Repository;

use Coffreo\CephOdm\EventListener\FindByFromCallListener;
use Coffreo\CephOdm\EventListener\QueryTruncatedListener;
use Coffreo\CephOdm\ResultSet\FileResultSet;

/**
 * AbstractRepositoryWrapper for FileRepository
 */
class FileRepository extends AbstractRepositoryDecorator implements QueryTruncatedListener
{
    private $bucketNames = [];

    /**
     * @var FindByFromCallListener[]
     */
    private $findByFromCallListeners = [];

    protected function createResultSet(array $result): \ArrayObject
    {
        $bucketNames = $this->bucketNames;
        $this->bucketNames = [];

        return new FileResultSet($result, $bucketNames);
    }

    /**
     * @codeCoverageIgnore
     */
    public function queryTruncated(array $bucketNames)
    {
        $this->bucketNames = $bucketNames;
    }

    public function addFindByFromCallListener(FindByFromCallListener $listener): void
    {
        $this->findByFromCallListeners[] = $listener;
    }

    public function findByFrom(array $criteria, $from, ?array $orderBy = null, ?int $limit = null): iterable
    {
        foreach ($this->findByFromCallListeners as $listener) {
            $listener->findByFromCalled($criteria, $from, $orderBy, $limit);
        }
        return $this->findBy($criteria, $orderBy, $limit, 1);
    }
}