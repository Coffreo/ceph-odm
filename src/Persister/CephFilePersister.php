<?php


namespace Coffreo\CephOdm\Persister;

/**
 * Persister for Ceph file objects
 */
class CephFilePersister extends AbstractCephPersister
{
    protected function saveCephData(array $data)
    {
        $this->client->putObject($data);
    }

    protected function deleteCephIdentifier(array $identifier)
    {
        $this->client->deleteObject($identifier);
    }
}