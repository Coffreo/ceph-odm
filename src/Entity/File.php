<?php


namespace Coffreo\CephOdm\Entity;


use Doctrine\Common\NotifyPropertyChanged;
use Doctrine\Common\PropertyChangedListener;
use Doctrine\SkeletonMapper\Hydrator\HydratableInterface;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface;
use Doctrine\SkeletonMapper\Mapping\LoadMetadataInterface;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use Doctrine\SkeletonMapper\Persister\IdentifiableInterface;
use Doctrine\SkeletonMapper\Persister\PersistableInterface;
use Doctrine\SkeletonMapper\UnitOfWork\Change;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;
use Ramsey\Uuid\Uuid;

/**
 * Ceph file
 */
class File implements HydratableInterface, IdentifiableInterface, LoadMetadataInterface, NotifyPropertyChanged, PersistableInterface
{
    /**
     * Ceph identifier
     *
     * @var string|null
     */
    private $id;

    /**
     * Filename
     *
     * @var string|null
     */
    private $filename;

    /**
     * Binary data
     *
     * @var string
     */
    private $bin;

    /**
     * Bucket
     *
     * @var Bucket
     */
    private $bucket;

    /**
     * Metadata
     *
     * @var array
     */
    private $metadata = [];

    /**
     * Property changed listeners
     *
     * @var PropertyChangedListener[]
     */
    private $listeners = [];

    /**
     * @codeCoverageIgnore
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): void
    {
        $this->onPropertyChanged('filename', $this->filename, $filename);
        $this->filename = $filename;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getBin(): string
    {
        return $this->bin;
    }

    public function setBin(string $bin): void
    {
        $this->onPropertyChanged('bin', $this->bin, $bin);
        $this->bin = $bin;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getBucket(): Bucket
    {
        return $this->bucket;
    }

    public function setBucket(Bucket $bucket): void
    {
        $this->onPropertyChanged('bucket', $this->bucket, $bucket);
        $this->bucket = $bucket;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    public function setAllMetadata(array $metadata): void
    {
        $this->onPropertyChanged('metadata', $this->metadata, $metadata);
        $this->metadata = $metadata;
    }

    /**
     * Add a metadata or replace it if the key already exists
     *
     * @param string $name name of the metadata
     * @param string $value value of the metadata
     */
    public function setMetadata(string $name, string $value): void
    {
        $new = $this->metadata;
        $new[$name] = $value;

        $this->onPropertyChanged('metadata', $this->metadata, $new);
        $this->metadata = $new;
    }

    /**
     * Remove a metadata
     *
     * @param string $name name of the metadata
     */
    public function removeMetadata(string $name): void
    {
        $new = $this->metadata;
        unset($new[$name]);

        $this->onPropertyChanged('metadata', $this->metadata, $new);
        $this->metadata = $new;
    }

    /**
     * Return a metadata or null if the metadata doesn't exist
     *
     * @param string $name name of the metadata
     *
     * @return string|null
     */
    public function getMetadata(string $name): ?string
    {
        if (isset($this->metadata[$name])) {
            return $this->metadata[$name];
        }

        return null;
    }

    public function hydrate(array $data, ObjectManagerInterface $objectManager): void
    {
        $this->id = $data['Key'];
        $this->filename = $data['Metadata']['filename'] ?? null;
        $this->bucket = new Bucket($data['Bucket']);
        $this->bin = $data['Body']->getContents();

        if (isset($data['Metadata'])) {
            unset($data['Metadata']['filename']);
            $this->metadata = $data['Metadata'];
        }
    }

    /**
     * @internal used by the library to assign Ceph id, this should not be used in application code.
     *
     * @codeCoverageIgnore
     */
    public function assignIdentifier(array $identifier): void
    {
        $this->id = (string)$identifier['Key'];
    }

    public static function loadMetadata(ClassMetadataInterface $metadata): void
    {
        $metadata->setIdentifier(['Bucket', 'Key']);
        $metadata->setIdentifierFieldNames(['bucket', 'id']);
        $metadata->mapField(['fieldName' => 'bucket', 'name' => 'Bucket']);
        $metadata->mapField(['fieldName' => 'id', 'name' => 'Key']);
        $metadata->mapField(['fieldName' => 'bin', 'name' => 'Body']);
        $metadata->mapField(['fieldName' => 'metadata', 'name' => 'Metadata']);
    }

    /**
     * @codeCoverageIgnore
     */
    public function addPropertyChangedListener(PropertyChangedListener $listener)
    {
        $this->listeners[] = $listener;
    }

    public function preparePersistChangeSet(): array
    {
        if (!$this->bucket) {
            throw new \RuntimeException("Bucket must be set before persisting");
        }

        $data = [
            'Bucket' => $this->bucket->getName(),
            'Key' => Uuid::uuid4(),
            'Body' => $this->bin
        ];

        $metadata = [];
        if ($this->metadata) {
            $metadata = $this->metadata;
        }

        if ($this->filename) {
            $metadata['filename'] = $this->filename;
        }

        if ($metadata) {
            $data['Metadata'] = $metadata;
        }

        return $data;
    }

    public function prepareUpdateChangeSet(ChangeSet $changeSet): array
    {
        if (!$this->bucket) {
            throw new \RuntimeException("Bucket must be set before updating");
        }

        $data = [
            'Bucket' => $this->bucket->getName(),
            'Key' => $this->id,
        ];
        $metadata = [];

        /** @var Change $change */
        foreach ($changeSet->getChanges() as $fieldName => $change) {
            $newValue = $change->getNewValue();
            switch ($fieldName) {
                case 'bin':
                    $data['Body'] = $newValue;
                    break;
                case 'filename':
                    if ($newValue) {
                        $metadata['filename'] = $newValue;
                    }
                    break;
                case 'metadata':
                    if ($newValue) {
                        $metadata = array_merge($metadata, $newValue);
                    }
                    break;
            }
        }

        if ($metadata) {
            $data['Metadata'] = $metadata;
        }

        return $data;
    }

    /**
     * @param mixed $oldValue
     * @param mixed $newValue
     */
    protected function onPropertyChanged(string $propName, $oldValue, $newValue) : void
    {
        if ($this->listeners === []) {
            return;
        }

        foreach ($this->listeners as $listener) {
            $listener->propertyChanged($this, $propName, $oldValue, $newValue);
        }
    }
}