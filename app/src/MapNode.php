<?php declare(strict_types=1);

namespace App;

class MapNode
{
    private int $normalizedKey;
    private int $value;

    public function __construct(int $normalizedKey, int $value)
    {
        $this->normalizedKey = $normalizedKey;
        $this->value = $value;
    }

    public function getNormalizedKey(): int
    {
        return $this->normalizedKey;
    }

    public function getValue(): ?int
    {
        if ($this->isEmpty()) {
            return null;
        }

        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->normalizedKey === 0;
    }

    public function updateKey(int $normalizedKey): void
    {
        $this->normalizedKey = $normalizedKey;
    }

    public function updateValue(int $value): void
    {
        $this->value = $value;
    }
}
