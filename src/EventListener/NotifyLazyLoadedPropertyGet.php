<?php


namespace Coffreo\CephOdm\EventListener;

/**
 * Add a listener to be notified when a lazy loaded property need to be loaded
 */
interface NotifyLazyLoadedPropertyGet
{
    public function addLazyLoadedPropertyGetListener(LazyLoadedProperyGetListener $listener): void;
}