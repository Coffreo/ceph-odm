<?php


namespace Coffreo\CephOdm\Extractor;

/**
 * Interface for extractors
 * Extractors allow to extract a data of an object from a name
 */
interface ExtractorInterface
{
    public function extract($object, string $name);
}