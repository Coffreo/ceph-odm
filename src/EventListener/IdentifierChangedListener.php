<?php


namespace Coffreo\CephOdm\EventListener;

/**
 * Notify when an identifier changes
 */
interface IdentifierChangedListener
{
    /**
     * Collect information about an identifier change.
     *
     * @param object $sender       The object on which the identifier changed.
     * @param string $propertyName The name of the identifier that changed.
     * @param mixed  $oldValue     The old value of the identifier that changed.
     * @param mixed  $newValue     The new value of the identifier that changed.
     */
    public function identifierChanged($sender, $propertyName, $oldValue, $newValue): void;
}