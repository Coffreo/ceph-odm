<?php


namespace Coffreo\CephOdm\Extractor;

/**
 * Allow to extract an object property through a getter
 */
class GetterExtractor implements ExtractorInterface
{
    public function extract($object, string $name)
    {
        $getterName = sprintf('get%s', ucfirst($name));
        if (!method_exists($object, $getterName)) {
            throw new \LogicException(sprintf("Can't find %s for accessing %s property", $getterName, $name));
        }

        return $object->{$getterName}();
    }
}