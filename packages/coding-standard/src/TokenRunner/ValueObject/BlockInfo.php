<?php

declare(strict_types=1);

namespace Symplify\CodingStandard\TokenRunner\ValueObject;

final class BlockInfo
{
    public function __construct(
        private int $start,
        private int $end
    ) {
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function contains(int $position): bool
    {
        if ($position < $this->start) {
            return false;
        }

        return $position <= $this->end;
    }
}
