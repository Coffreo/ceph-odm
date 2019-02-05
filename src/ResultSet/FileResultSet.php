<?php


namespace Coffreo\CephOdm\ResultSet;


class FileResultSet extends \ArrayObject
{
    private $bucketsTruncated;

    /**
     * @codeCoverageIgnore
     */
    public function __construct($input = array(), array $bucketsTruncated = [], int $flags = 0, string $iterator_class = "ArrayIterator")
    {
        parent::__construct($input, $flags, $iterator_class);
        $this->bucketsTruncated = $bucketsTruncated;
    }

    /**
     * Return the names of the bucket which file retrieving was truncated
     *
     * @codeCoverageIgnore
     */
    public function getBucketsTruncated(): array
    {
        return $this->bucketsTruncated;
    }
}