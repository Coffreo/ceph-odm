<?php


namespace Coffreo\CephOdm\EventListener;

use Coffreo\CephOdm\Entity\File;
use Doctrine\Common\PropertyChangedListener;
use Doctrine\SkeletonMapper\ObjectIdentityMap;

/**
 * Listen identifier property changes
 */
class ObjectIdentifierChangedListener implements IdentifierChangedListener
{
    private $objectIdentifyMap;

    /**
     * @codeCoverageIgnore
     */
    public function __construct(ObjectIdentityMap $objectIdentifyMap)
    {
        $this->objectIdentifyMap = $objectIdentifyMap;
    }

    /**
     * Throw exception when trying to change a non detached object identifier
     */
    public function identifierChanged($sender, $propertyName, $oldValue, $newValue)
    {
        if (!$sender instanceof File || empty($oldValue) || $oldValue == $newValue) {
            return;
        }

        if ($this->objectIdentifyMap->contains($sender)) {
            throw new \RuntimeException(sprintf(
                "File of bucket %s id %s must be detached before changing its identifiers",
                $sender->getBucket()->getName(),
                $sender->getId()
            ));
        }
    }
}