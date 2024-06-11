<?php

declare(strict_types=1);

namespace Doctrine\SkeletonMapper\ObjectRepository;

use Doctrine\Common\EventManager;
use Doctrine\SkeletonMapper\DataRepository\ObjectDataRepositoryInterface;
use Doctrine\SkeletonMapper\Hydrator\ObjectHydratorInterface;
use Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface;
use Doctrine\SkeletonMapper\ObjectFactory;
use Doctrine\SkeletonMapper\ObjectManagerInterface;
use InvalidArgumentException;

/**
 * Base class for object repositories to extend from.
 *
 * @template T of object
 * @template-implements ObjectRepositoryInterface<T>
 */
abstract class ObjectRepository implements ObjectRepositoryInterface
{
    /** @phpstan-var class-string<T> */
    protected $className;

    /** @var ClassMetadataInterface<T> */
    protected ClassMetadataInterface $class;

    /** @phpstan-param class-string<T> $className */
    public function __construct(
        protected ObjectManagerInterface $objectManager,
        protected ObjectDataRepositoryInterface $objectDataRepository,
        protected ObjectFactory $objectFactory,
        protected ObjectHydratorInterface $objectHydrator,
        protected EventManager $eventManager,
        string $className,
    ) {
        $this->setClassName($className);
    }

    /**
     * Returns the class name of the object managed by the repository.
     *
     * @phpstan-return class-string<T>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /** @phpstan-param class-string<T> $className */
    public function setClassName(string $className): void
    {
        $this->className = $className;
        $this->class     = $this->objectManager->getClassMetadata($this->className);
    }

    /**
     * Finds an object by its primary key / identifier.
     *
     * {@inheritDoc}
     *
     * @psalm-return T|null
     */
    public function find($id)
    {
        return $this->getOrCreateObject(
            $this->objectDataRepository->find($id),
        );
    }

   /**
    * Finds all objects in the repository.
    *
    * @return object[] The objects.
    */
    public function findAll(): array
    {
        $objectsData = $this->objectDataRepository->findAll();

        $objects = [];
        foreach ($objectsData as $objectData) {
            $object = $this->getOrCreateObject($objectData);

            if ($object === null) {
                throw new InvalidArgumentException('Could not create object.');
            }

            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * {@inheritDoc}
     */
    public function findBy(array $criteria, array|null $orderBy = null, int|null $limit = null, int|null $offset = null): array
    {
        $objectsData = $this->objectDataRepository->findBy(
            $criteria,
            $orderBy,
            $limit,
            $offset,
        );

        $objects = [];
        foreach ($objectsData as $objectData) {
            $object = $this->getOrCreateObject($objectData);

            if ($object === null) {
                throw new InvalidArgumentException('Could not create object.');
            }

            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * {@inheritDoc}
     */
    public function findOneBy(array $criteria)
    {
        return $this->getOrCreateObject(
            $this->objectDataRepository->findOneBy($criteria),
        );
    }

    public function refresh(object $object): void
    {
        $data = $this->objectDataRepository
            ->find($this->getObjectIdentifier($object));

        if ($data === null) {
            throw new InvalidArgumentException('Could not find object to refresh.');
        }

        $this->hydrate($object, $data);
    }

    /** @param mixed[] $data */
    public function hydrate(object $object, array $data): void
    {
        $this->objectHydrator->hydrate($object, $data);
    }

    /** @phpstan-param class-string $className */
    public function create(string $className): object
    {
        return $this->objectFactory->create($className);
    }

    /**
     * @param mixed[] $data
     *
     * @psalm-return T|null
     */
    protected function getOrCreateObject(array|null $data = null)
    {
        if ($data === null) {
            return null;
        }

        return $this->objectManager->getOrCreateObject(
            $this->getClassName(),
            $data,
        );
    }
}
