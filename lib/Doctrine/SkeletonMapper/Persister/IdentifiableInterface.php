<?php

declare(strict_types=1);

namespace Doctrine\SkeletonMapper\Persister;

interface IdentifiableInterface
{
    /**
     * Assign identifier to object.
     *
     * @param mixed[] $identifier
     */
    public function assignIdentifier(array $identifier): void;
}
