<?php declare(strict_types=1);

namespace App\Tests\Unit;

use App\IntIntMap;
use PHPUnit\Framework\TestCase;

class IntIntMapTest extends TestCase
{
    private const CAPACITY = 10;

    private \Shmop $shm;

    public function setUp(): void
    {
        $shmKey = ftok(__FILE__, 't');

        $this->shm = \shmop_open($shmKey, "c", 0644, PHP_INT_SIZE * 2 * self::CAPACITY);
    }

    public function tearDown(): void
    {
        \shmop_delete($this->shm);
    }

    public function testPassedSizeMatch(): void
    {
        $fakeSize = 1;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Passed memory block size does not match real size');

        new IntIntMap($this->shm, $fakeSize);
    }

    public function testGetWhenSignCollision(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $positiveKey = 1;
        $positiveValue = 2;

        $negativeKey = -$positiveValue;
        $negativeValue = 3;

        $map->put($positiveKey, $positiveValue);
        $map->put($negativeKey, $negativeValue);

        $positiveResult = $map->get($positiveKey);
        $negativeResult = $map->get($negativeKey);

        $this->assertEquals($positiveValue, $positiveResult);
        $this->assertEquals($negativeValue, $negativeResult);
    }

    public function testPutWhenSignCollision(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $positiveKey = 1;
        $positiveValue = 2;

        $negativeKey = -$positiveValue;
        $negativeValue = 3;

        $positiveResult = $map->put($positiveKey, $positiveValue);
        $negativeResult = $map->put($negativeKey, $negativeValue);

        $positivePutValue = $map->get($positiveKey);
        $negativePutValue = $map->get($negativeKey);

        $this->assertEquals(null, $positiveResult);
        $this->assertEquals(null, $negativeResult);
        $this->assertEquals($positiveValue, $positivePutValue);
        $this->assertEquals($negativeValue, $negativePutValue);
    }

    public function testGetWhenModCollision(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $firstKey = 0;
        $firstValue = 2;

        $secondKey = 10;
        $secondValue = 3;

        $map->put($firstKey, $firstValue);
        $map->put($secondKey, $secondValue);

        $firstResult = $map->get($firstKey);
        $secondResult = $map->get($secondKey);

        $this->assertEquals($firstValue, $firstResult);
        $this->assertEquals($secondValue, $secondResult);
    }

    public function testPutWhenModCollision(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $firstKey = 0;
        $firstValue = 2;

        $secondKey = 10;
        $secondValue = 3;

        $firstResult = $map->put($firstKey, $firstValue);
        $secondResult = $map->put($secondKey, $secondValue);

        $firstPutValue = $map->get($firstKey);
        $secondPutValue = $map->get($secondKey);

        $this->assertEquals(null, $firstResult);
        $this->assertEquals(null, $secondResult);
        $this->assertEquals($firstValue, $firstPutValue);
        $this->assertEquals($secondValue, $secondPutValue);
    }

    public function testMemoryOverflow(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $this->expectException(\OverflowException::class);
        $this->expectExceptionMessage('Capacity is over');

        for ($i = 0; $i <= self::CAPACITY; $i++) {
            $map->put($i, $i);
        }
    }

    public function testPutWhenThereIsNoPreviousValue(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 1;
        $value = 2;

        $result = $map->put($key, $value);

        $putValue = $map->get($key);

        $this->assertEquals(null, $result);
        $this->assertEquals($value, $putValue);
    }

    public function testPutWhenPreviousValueExists(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 1;
        $previousValue = 2;
        $newValue = 3;

        $map->put($key, $previousValue);

        $result = $map->put($key, $newValue);

        $putValue = $map->get($key);

        $this->assertEquals($previousValue, $result);
        $this->assertEquals($newValue, $putValue);
    }

    public function testGetWhenNoValueWasNotPut(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $result = $map->get(1);

        $this->assertEquals(null, $result);
    }

    public function testGetWhenNoValueWasPut(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 1;
        $value = 2;

        $map->put($key, $value);
        $result = $map->get(1);

        $this->assertEquals($value, $result);
    }

    public function testPutWithTooBigKey(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = PHP_INT_MAX;
        $value = 1;

        $this->expectException(\InvalidArgumentException::class);
        $maxKey = PHP_INT_MAX - 1;
        $this->expectExceptionMessage("Max key is $maxKey");

        $map->put($key, $value);
    }

    public function testPutWithBigKey(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = PHP_INT_MAX - 1;
        $value = 2;

        $result = $map->put($key, $value);

        $putValue = $map->get($key);

        $this->assertEquals(null, $result);
        $this->assertEquals($value, $putValue);
    }

    public function testPutWithTooSmallKey(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = PHP_INT_MIN;
        $value = 2;

        $this->expectException(\InvalidArgumentException::class);
        $minKey = PHP_INT_MIN + 1;
        $this->expectExceptionMessage("Min key is $minKey");

        $map->put($key, $value);
    }

    public function testPutWithBigValue(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 1;
        $value = PHP_INT_MAX;

        $result = $map->put($key, $value);

        $putValue = $map->get($key);

        $this->assertEquals(null, $result);
        $this->assertEquals($value, $putValue);
    }

    public function testPutWithSmallValue(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 1;
        $value = PHP_INT_MIN;

        $result = $map->put($key, $value);

        $putValue = $map->get($key);

        $this->assertEquals(null, $result);
        $this->assertEquals($value, $putValue);
    }

    public function testGetWithTooBigKey(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = PHP_INT_MAX;

        $this->expectException(\InvalidArgumentException::class);
        $maxKey = PHP_INT_MAX - 1;
        $this->expectExceptionMessage("Max key is $maxKey");

        $map->get($key);
    }

    public function testGetWithTooSmallKey(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = PHP_INT_MIN;

        $this->expectException(\InvalidArgumentException::class);
        $minKey = PHP_INT_MIN + 1;
        $this->expectExceptionMessage("Min key is $minKey");

        $map->get($key);
    }

    public function testGetWithBigValue(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 1;
        $value = PHP_INT_MAX;

        $map->put($key, $value);

        $result = $map->get($key);

        $this->assertEquals($value, $result);
    }

    public function testGetWithSmallValue(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 1;
        $value = PHP_INT_MIN;

        $map->put($key, $value);

        $result = $map->get($key);

        $this->assertEquals($value, $result);
    }

    public function testPutWithZeroKey(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 0;
        $value = 2;

        $result = $map->put($key, $value);

        $putValue = $map->get($key);

        $this->assertEquals(null, $result);
        $this->assertEquals($value, $putValue);
    }

    public function testPutWithZeroValue(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 1;
        $value = 0;

        $result = $map->put($key, $value);

        $putValue = $map->get($key);

        $this->assertEquals(null, $result);
        $this->assertEquals($value, $putValue);
    }

    public function testPutWithZeroKeyAndValue(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 0;
        $value = 0;

        $result = $map->put($key, $value);

        $putValue = $map->get($key);

        $this->assertEquals(null, $result);
        $this->assertEquals($value, $putValue);
    }

    public function testGetWithZeroKey(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 0;
        $value = 1;

        $map->put($key, $value);

        $result = $map->get($key);

        $this->assertEquals($value, $result);
    }

    public function testGetWithZeroValue(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 1;
        $value = 0;

        $map->put($key, $value);

        $result = $map->get($key);

        $this->assertEquals($value, $result);
    }

    public function testGetWithZeroKeyAndValue(): void
    {
        $map = new IntIntMap($this->shm, shmop_size($this->shm));

        $key = 0;
        $value = 0;

        $map->put($key, $value);

        $result = $map->get($key);

        $this->assertEquals($value, $result);
    }
}
