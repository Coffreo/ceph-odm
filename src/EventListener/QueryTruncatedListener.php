<?php


namespace Coffreo\CephOdm\EventListener;


interface QueryTruncatedListener
{
    /**
     * Called when the previous query coundn't return all results
     *
     * @param string[] $bucketNames buckets for which the query was truncated
     */
    public function queryTruncated(array $bucketNames): void;
}