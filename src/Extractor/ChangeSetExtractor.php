<?php


namespace Coffreo\CephOdm\Extractor;

use Doctrine\SkeletonMapper\UnitOfWork\Change;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;

/**
 * Extract changed values from a ChangeSet object
 */
class ChangeSetExtractor implements ExtractorInterface
{
    public function extract($object, string $name)
    {
        if (!$object instanceof ChangeSet) {
            throw new \InvalidArgumentException("Object must be an instance of ChangeSet");
        }

        $changes = $object->getChanges();
        foreach ($changes as $change) {
            if (!$change instanceof Change) {
                throw new \InvalidArgumentException(sprintf("Change must be an instance of Change (actually %s)", is_object($change) ? get_class($change) : 'scalar'));
            }

            if ($change->getPropertyName() != $name) {
                continue;
            }

            return $change->getNewValue();
        }

        throw new \InvalidArgumentException(sprintf("Change %s not found", $name), 404);
    }


}