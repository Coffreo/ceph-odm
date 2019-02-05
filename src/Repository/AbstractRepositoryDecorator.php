<?php


namespace Coffreo\CephOdm\Repository;

use Doctrine\SkeletonMapper\ObjectRepository\BasicObjectRepository;
use Doctrine\SkeletonMapper\ObjectRepository\ObjectRepositoryInterface;

/**
 * Decorator for repositories with ceph odm implementation special features
 */
abstract class AbstractRepositoryDecorator implements ObjectRepositoryInterface
{
    private $basicObjectRepository;

    /**
     * @codeCoverageIgnore
     */
    public function __construct(BasicObjectRepository $basicObjectRepository)
    {
        $this->basicObjectRepository = $basicObjectRepository;
    }

    public function getObjectIdentifier($object) : array
    {
        return $this->basicObjectRepository->getObjectIdentifier($object);
    }

    public function getObjectIdentifierFromData(array $data) : array
    {
        return $this->basicObjectRepository->getObjectIdentifierFromData($data);
    }

    public function merge($object) : void
    {
        $this->basicObjectRepository->merge($object);
    }

    public function hydrate($object, array $data) : void
    {
        $this->basicObjectRepository->hydrate($object, $data);
    }

    public function create(string $className)
    {
        return $this->basicObjectRepository->create($className);
    }

    public function refresh($object) : void
    {
        $this->basicObjectRepository->refresh($object);
    }

    public function find($id)
    {
        return $this->basicObjectRepository->find($id);
    }

    /**
     * Wrap ObjectRepository findAll method and return an ArrayObject instead of an array
     */
    public function findAll() : iterable
    {
        $result = $this->basicObjectRepository->findAll();
        return $this->createResultSet($result);
    }

    /**
     * Wrap ObjectRepository findBy method and return an ArrayObject instead of an array
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null) : iterable
    {
        $result = $this->basicObjectRepository->findBy($criteria, $orderBy, $limit, $offset);
        return $this->createResultSet($result);
    }

    public function findOneBy(array $criteria)
    {
        return $this->basicObjectRepository->findOneBy($criteria);
    }

    public function getClassName()
    {
        return $this->basicObjectRepository->getClassName();
    }

    /**
     * Create type specific ResultSet
     */
    abstract protected function createResultSet(array $result) : \ArrayObject;
}