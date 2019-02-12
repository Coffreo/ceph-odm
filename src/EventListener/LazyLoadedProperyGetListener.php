<?php


namespace Coffreo\CephOdm\EventListener;


use Coffreo\CephOdm\Entity\File;

interface LazyLoadedProperyGetListener
{
    public function lazyLoadedPropertyGet($object, string $propertyName): void;
}