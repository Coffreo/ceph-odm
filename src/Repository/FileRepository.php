<?php


namespace Coffreo\CephOdm\Repository;

use Coffreo\CephOdm\ResultSet\FileResultSet;

/**
 * AbstractRepositoryWrapper for FileRepository
 */
class FileRepository extends AbstractRepositoryDecorator
{
    protected function createResultSet(array $result) : \ArrayObject
    {
        return new FileResultSet($result);
    }

}