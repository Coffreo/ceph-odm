<?php


namespace Coffreo\CephOdm\Repository;

use Coffreo\CephOdm\ResultSet\BucketResultSet;

/**
 * AbstractRepositoryWrapper for BucketRepository
 */
class BucketRepository extends AbstractRepositoryDecorator
{
    protected function createResultSet(array $result) : \ArrayObject
    {
        return new BucketResultSet($result);
    }
}