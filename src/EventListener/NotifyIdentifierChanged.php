<?php


namespace Coffreo\CephOdm\EventListener;

/**
 * Add a listener to be notified where an identifier changes
 */
interface NotifyIdentifierChanged
{
    /**
     * Adds a listener that wants to be notified about identifier changes.
     *
     * @param IdentifierChangedListener $listener
     */
    public function addIdentifierChangedListener(IdentifierChangedListener $listener): void;
}