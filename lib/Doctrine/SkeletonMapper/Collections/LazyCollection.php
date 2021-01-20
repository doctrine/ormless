<?php

declare(strict_types=1);

namespace Doctrine\SkeletonMapper\Collections;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Collection;

use function call_user_func;

/**
 * @template-extends AbstractLazyCollection<mixed, mixed>
 */
class LazyCollection extends AbstractLazyCollection
{
    /** @var callable|null */
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return Collection<int, object>
     */
    public function getCollection(): Collection
    {
        $this->initialize();

        return $this->collection;
    }

    protected function doInitialize(): void
    {
        if ($this->callback === null) {
            return;
        }

        $this->collection = call_user_func($this->callback);
        $this->callback   = null;
    }
}
