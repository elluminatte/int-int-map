<?php declare(strict_types=1);

namespace App;

class IndexedNode
{
    private int $index;
    private MapNode $node;

    public function __construct(int $index, MapNode $node)
    {
        $this->index = $index;
        $this->node = $node;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getNode(): MapNode
    {
        return $this->node;
    }
}
