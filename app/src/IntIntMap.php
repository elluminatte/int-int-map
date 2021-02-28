<?php declare(strict_types=1);

namespace App;

/**
 * Требуется написать IntIntMap, который по произвольному int ключу хранит произвольное int значение
 * Важно: все данные (в том числе дополнительные, если их размер зависит от числа элементов) требуется хранить в выделенном заранее блоке в разделяемой памяти
 * для доступа к памяти напрямую необходимо (и достаточно) использовать следующие два метода:
 * \shmop_read и \shmop_write
 */
class IntIntMap
{
    private \Shmop $shm_id;
    private int $capacity;
    private int $itemSize;

    /**
     * IntIntMap constructor.
     * @param \Shmop $shm_id результат вызова \shmop_open
     * @param int $size размер зарезервированного блока в разделяемой памяти (~100GB)
     */
    public function __construct(\Shmop $shm_id, int $size)
    {
        if (\shmop_size($shm_id) !== $size) {
            throw new \RuntimeException('Passed memory block size does not match real size');
        }

        $this->shm_id = $shm_id;
        $this->itemSize = PHP_INT_SIZE * 2;
        $this->capacity = (int)floor($size / $this->itemSize);

        if ($this->capacity < 1) {
            throw new \RuntimeException('Not enough memory to keep at least one item');
        }
    }

    /**
     * Метод должен работать со сложностью O(1) при отсутствии коллизий, но может деградировать при их появлении
     * @param int $key произвольный ключ
     * @param int $value произвольное значение
     * @return int|null предыдущее значение
     */
    public function put(int $key, int $value): ?int
    {
        $this->validateKey($key);

        $indexedNode = $this->getIndexedNodeByKey($key);

        if (is_null($indexedNode)) {
            throw new \OverflowException('Capacity is over');
        }

        $node = $indexedNode->getNode();

        $previousValue = $node->getValue();

        $node->updateKey($this->normalizeKey($key));
        $node->updateValue($value);

        $this->writeIndexedNode($indexedNode);

        return $previousValue;
    }

    /**
     * Метод должен работать со сложностью O(1) при отсутствии коллизий, но может деградировать при их появлении
     * @param int $key ключ
     * @return int|null значение, сохраненное ранее по этому ключу
     */
    public function get(int $key): ?int
    {
        $this->validateKey($key);

        $indexedNode = $this->getIndexedNodeByKey($key);

        if (is_null($indexedNode)) {
            return null;
        }

        return $indexedNode->getNode()->getValue();
    }

    private function getIndexedNodeByKey(int $key): ?IndexedNode
    {
        $index = $this->getKeyHash($key);

        $normalizedKey = $this->normalizeKey($key);

        $indexCandidates = range($index, $this->capacity - 1);

        if ($index > 0) {
            array_push($indexCandidates, ...range(0, $index - 1));
        }

        foreach ($indexCandidates as $indexCandidate) {
            $node = $this->readNode($indexCandidate);

            if ($node->isEmpty()) {
                return new IndexedNode($indexCandidate, $node);
            }

            if ($node->getNormalizedKey() === $normalizedKey) {
                return new IndexedNode($indexCandidate, $node);
            }
        }

        return null;
    }

    private function getKeyHash(int $key): int
    {
        return abs($key) % $this->capacity;
    }

    private function packItem(MapNode $node): string
    {
        return pack($this->getPackFormat(), $node->getNormalizedKey(), $node->getValue());
    }

    private function unpackItem(string $binary): ?MapNode
    {
        $data = unpack($this->getUnpackFormat(), $binary);

        return new MapNode($data['key'], $data['value']);
    }

    private function normalizeKey(int $key): int
    {
        return $key >= 0 ? ++$key : $key;
    }

    private function getPackFormat(): string
    {
        switch (PHP_INT_SIZE) {
            case 4:
                return 'I2';
            case 8:
                return 'Q2';
            default:
                throw new \RuntimeException('Packing for platform int size is not implemented yet');
        }
    }

    private function getUnpackFormat(): string
    {
        switch (PHP_INT_SIZE) {
            case 4:
                return 'Ikey/Ivalue';
            case 8:
                return 'Qkey/Qvalue';
            default:
                throw new \RuntimeException('Unpacking for platform int size is not implemented yet');
        }
    }

    private function writeIndexedNode(IndexedNode $indexedNode): void
    {
        $offset = $this->getMemoryOffset($indexedNode->getIndex());

        $data = $this->packItem($indexedNode->getNode());

        $result = \shmop_write($this->shm_id, $data, $offset);

        if ($result !== $this->itemSize) {
            throw new \Exception('Error during node writing');
        }
    }

    private function readNode(int $index): MapNode
    {
        $offset = $this->getMemoryOffset($index);

        $data = \shmop_read($this->shm_id, $offset, $this->itemSize);

        if ($data === false) {
            throw new \Exception('Error during node reading');
        }

        return $this->unpackItem($data);
    }

    private function getMemoryOffset(int $index): int
    {
        return $index * $this->itemSize;
    }

    private function validateKey(int $key): void
    {
        if ($key === PHP_INT_MAX) {
            $maxKey = PHP_INT_MAX - 1;
            throw new \InvalidArgumentException("Max key is $maxKey");
        }

        if ($key === PHP_INT_MIN) {
            $minKey = PHP_INT_MIN + 1;
            throw new \InvalidArgumentException("Min key is $minKey");
        }
    }
}
