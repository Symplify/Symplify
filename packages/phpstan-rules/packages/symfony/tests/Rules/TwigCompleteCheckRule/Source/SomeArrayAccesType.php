<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Symfony\Tests\Rules\TwigCompleteCheckRule\Source;

final class SomeArrayAccesType implements  \ArrayAccess, \IteratorAggregate, \Countable
{
    public $children = [
        'some_child' => true,
    ];

    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        return $this->children[$name];
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($name)
    {
        return isset($this->children[$name]);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($name, $value)
    {
        throw new \BadMethodCallException('Not supported.');
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($name)
    {
        unset($this->children[$name]);
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->children);
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->children);
    }

    public function existingMethod()
    {
    }
}
