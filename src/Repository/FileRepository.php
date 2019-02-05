<?php


namespace Coffreo\CephOdm\Repository;

use Coffreo\CephOdm\EventListener\QueryTruncatedListener;
use Coffreo\CephOdm\ResultSet\FileResultSet;

/**
 * AbstractRepositoryWrapper for FileRepository
 */
class FileRepository extends AbstractRepositoryDecorator implements QueryTruncatedListener
{
    private $bucketNames = [];

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
}