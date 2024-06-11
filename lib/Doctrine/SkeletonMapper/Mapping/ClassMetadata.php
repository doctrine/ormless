<?php

declare(strict_types=1);

namespace Doctrine\SkeletonMapper\Mapping;

use BadMethodCallException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;

use function array_keys;
use function assert;
use function call_user_func;
use function call_user_func_array;
use function in_array;
use function is_callable;
use function sprintf;

/**
 * Class used to hold metadata about mapped classes.
 *
 * @template-covariant T of object
 * @template-implements ClassMetadataInterface<T>
 */
class ClassMetadata implements ClassMetadataInterface
{
    /** @var class-string<T> */
    public $name;

    /** @var mixed[] */
    public array $identifier = [];

    /** @var string[] */
    public array $identifierFieldNames = [];

    /** @var string[][] */
    public array $fieldMappings = [];

    /** var array<string, array{targetObject: class-string|null, type: string, fieldName: string}> */
    /** @var mixed[][] */
    public array $associationMappings = [];

    /** @var string[][] */
    public array $lifecycleCallbacks = [];

    /** @var ReflectionClass<object> */
    public ReflectionClass $reflClass;

    /** @var ReflectionProperty[] */
    public array $reflFields = [];

    /** @phpstan-param class-string<T> $className */
    public function __construct(string $className)
    {
        $this->name      = $className;
        $this->reflClass = new ReflectionClass($className);
    }

    /** @param mixed[] $identifier */
    public function setIdentifier(array $identifier): void
    {
        $this->identifier = $identifier;
    }

    /** @param string[] $identifierFieldNames */
    public function setIdentifierFieldNames(array $identifierFieldNames): void
    {
        $this->identifierFieldNames = $identifierFieldNames;
    }

    /**
     * {@inheritDoc}
     */
    public function mapField(array $mapping): void
    {
        if (! isset($mapping['name'])) {
            $mapping['name'] = $mapping['fieldName'];
        }

        if (isset($mapping['type']) && isset($mapping['targetObject'])) {
            $this->associationMappings[$mapping['fieldName']] = $mapping;
        } else {
            $this->fieldMappings[$mapping['fieldName']] = $mapping;
        }

        $this->initReflField($mapping);
    }

    /**
     * Gets the fully-qualified class name of this persistent class.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the mapped identifier field name.
     *
     * The returned structure is an array of the identifier field names.
     *
     * {@inheritDoc}
     */
    public function getIdentifier(): array
    {
        return $this->identifier;
    }

    /**
     * Gets the ReflectionClass instance for this mapped class.
     *
     * @phpstan-return ReflectionClass<object>
     */
    public function getReflectionClass(): ReflectionClass
    {
        return $this->reflClass;
    }

    public function isIdentifier(string $fieldName): bool
    {
        return in_array($fieldName, $this->getIdentifierFieldNames(), true);
    }

    public function hasField(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]);
    }

    /**
     * A numerically indexed list of field names of this persistent class.
     *
     * This array includes identifier fields if present on this class.
     *
     * {@inheritDoc}
     */
    public function getFieldNames(): array
    {
        return array_keys($this->fieldMappings);
    }

    /**
     * An array of field mappings for this persistent class indexed by field name.
     *
     * @return mixed[][]
     */
    public function getFieldMappings(): array
    {
        return $this->fieldMappings;
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationNames(): array
    {
        return array_keys($this->associationMappings);
    }

    public function getTypeOfField(string $fieldName): string
    {
        return $this->fieldMappings[$fieldName]['type'] ?? '';
    }

    public function getAssociationTargetClass(string $assocName): string|null
    {
        if (! isset($this->associationMappings[$assocName])) {
            throw new InvalidArgumentException(
                sprintf("Association name expected, '%s' is not an association.", $assocName),
            );
        }

        return $this->associationMappings[$assocName]['targetObject'];
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifierValues(object $object): array
    {
        $identifier = [];
        foreach ($this->identifierFieldNames as $identifierFieldName) {
            $identifier[$this->fieldMappings[$identifierFieldName]['name']] = $this->reflFields[$identifierFieldName]->getValue($object);
        }

        return $identifier;
    }

    /**
     * {@inheritDoc}
     *
     * Checks whether the class has a mapped association (embed or reference) with the given field name.
     */
    public function hasAssociation(string $fieldName): bool
    {
        return isset($this->associationMappings[$fieldName]);
    }

    /**
     * {@inheritDoc}
     *
     * Checks whether the class has a mapped reference or embed for the specified field and
     * is a single valued association.
     */
    public function isSingleValuedAssociation(string $fieldName): bool
    {
        return isset($this->associationMappings[$fieldName]['type']) &&
            $this->associationMappings[$fieldName]['type'] === 'one';
    }

    /**
     * {@inheritDoc}
     *
     * Checks whether the class has a mapped reference or embed for the specified field and
     * is a collection valued association.
     */
    public function isCollectionValuedAssociation(string $fieldName): bool
    {
        return isset($this->associationMappings[$fieldName]['type']) &&
            $this->associationMappings[$fieldName]['type'] === 'many';
    }

    public function invokeLifecycleCallbacks(string $event, object $object, array|null $arguments = null): void
    {
        if (! $object instanceof $this->name) {
            throw new InvalidArgumentException(
                sprintf('Expected class "%s"; found: "%s"', $this->name, $object::class),
            );
        }

        foreach ($this->lifecycleCallbacks[$event] as $callback) {
            if ($arguments !== null) {
                $callable = [$object, $callback];
                assert(is_callable($callable));

                call_user_func_array($callable, $arguments);
            } else {
                $callable = [$object, $callback];
                assert(is_callable($callable));

                call_user_func($callable);
            }
        }
    }

    /**
     * Checks whether the class has callbacks registered for a lifecycle event.
     *
     * @param string $event Lifecycle event
     */
    public function hasLifecycleCallbacks(string $event): bool
    {
        return isset($this->lifecycleCallbacks[$event]);
    }

    /**
     * Gets the registered lifecycle callbacks for an event.
     *
     * @return string[]
     */
    public function getLifecycleCallbacks(string $event): array
    {
        return $this->lifecycleCallbacks[$event] ?? [];
    }

    /**
     * Adds a lifecycle callback for objects of this class.
     *
     * If the callback is already registered, this is a NOOP.
     */
    public function addLifecycleCallback(string $callback, string $event): void
    {
        if (isset($this->lifecycleCallbacks[$event]) && in_array($callback, $this->lifecycleCallbacks[$event], true)) {
            return;
        }

        $this->lifecycleCallbacks[$event][] = $callback;
    }

    /**
     * Sets the lifecycle callbacks for objects of this class.
     *
     * Any previously registered callbacks are overwritten.
     *
     * @param string[][] $callbacks
     */
    public function setLifecycleCallbacks(array $callbacks): void
    {
        $this->lifecycleCallbacks = $callbacks;
    }

    /**
     * Returns an array of identifier field names numerically indexed.
     *
     * {@inheritDoc}
     */
    public function getIdentifierFieldNames(): array
    {
        return $this->identifierFieldNames;
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationMappedByTargetField(string $fieldName)
    {
        throw new BadMethodCallException(__METHOD__ . '() is not implemented yet.');
    }

    /**
     * {@inheritDoc}
     */
    public function isAssociationInverseSide(string $fieldName)
    {
        throw new BadMethodCallException(__METHOD__ . '() is not implemented yet.');
    }

    /** @param mixed[] $mapping */
    private function initReflField(array $mapping): void
    {
        if (! $this->reflClass->hasProperty($mapping['fieldName'])) {
            return;
        }

        $reflProp = $this->reflClass->getProperty($mapping['fieldName']);
        $reflProp->setAccessible(true);
        $this->reflFields[$mapping['fieldName']] = $reflProp;
    }
}
