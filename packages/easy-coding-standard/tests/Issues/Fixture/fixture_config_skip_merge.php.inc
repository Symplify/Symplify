<?php

declare(strict_types=1);

use Symplify\Astral\ValueObject\AttributeKey;

if ($parent = $node->getAttribute(Attributekey::PARENT)) {
    $parent = $parent->getAttribute(AttributeKey::PARENT);
}