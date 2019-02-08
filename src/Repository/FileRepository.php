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

    /**
     * @codeCoverageIgnore
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limitByBucket = null, $continue = null) : iterable
    {
        return parent::findBy($criteria, $orderBy, $limitByBucket, $continue);
    }

    public function findByFrom(array $criteria, $from, ?array $orderBy = null, ?int $limitByBucket = null): iterable
    {
        foreach ($this->findByFromCallListeners as $listener) {
            $listener->findByFromCalled($criteria, $from, $orderBy, $limitByBucket);
        }
        return $this->findBy($criteria, $orderBy, $limitByBucket, 1);
    }
}