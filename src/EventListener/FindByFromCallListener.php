<?php


namespace Coffreo\CephOdm\EventListener;


interface FindByFromCallListener
{
    /**
     * Called when the FileRepository findByFrom method is called
     */
    public function findByFromCalled(array $criteria, $from, ?array $orderBy, ?int $limitByBucket): void;
}