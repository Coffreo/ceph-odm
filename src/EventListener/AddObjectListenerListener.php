<?php


namespace Coffreo\CephOdm\EventListener;

use Doctrine\Common\EventArgs;
use Doctrine\Common\NotifyPropertyChanged;
use Doctrine\SkeletonMapper\Event\LifecycleEventArgs;
use Doctrine\SkeletonMapper\ObjectIdentityMap;

/**
 * Add listener to an object
 */
class AddObjectListenerListener
{
    private $objectIdentifyMap;

    /**
     * @codeCoverageIgnore
     */
    public function __construct(ObjectIdentityMap $objectIdentityMap)
    {
        $this->objectIdentifyMap = $objectIdentityMap;
    }

    /**
     * Add IdentifierPropertyChangedListener to an object when it is loaded
     */
    public function postLoad(LifecycleEventArgs $lifeCycleEventArgs): void
    {
         $object = $lifeCycleEventArgs->getObject();

        if ($object instanceof NotifyIdentifierChanged) {
            $object->addIdentifierChangedListener(new ObjectIdentifierChangedListener($this->objectIdentifyMap));
        }
    }
}