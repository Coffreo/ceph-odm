<?php


namespace Coffreo\CephOdm\Persister;

use Coffreo\CephOdm\Entity\Bucket;

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
        if (isset($identifier['Bucket']) && $identifier['Bucket'] instanceof Bucket) {
            $identifier['Bucket'] = $identifier['Bucket']->getName();
        }

        $this->client->deleteObject($identifier);
    }
}