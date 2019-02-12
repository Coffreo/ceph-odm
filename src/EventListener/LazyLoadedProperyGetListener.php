<?php


namespace Coffreo\CephOdm\EventListener;

interface LazyLoadedProperyGetListener
{
    public function lazyLoadedPropertyGet($object, string $propertyName): void;
}