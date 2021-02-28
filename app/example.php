<?php declare(strict_types=1);

require_once __DIR__.'/vendor/autoload.php';

$shmKey = ftok(__FILE__, 't');

$capacity = 1;
$shm = \shmop_open($shmKey, "c", 0644, PHP_INT_SIZE * 2 * $capacity);

try {
    $options = getopt('k:v:');

    if (!isset($options['k']) || !isset($options['v'])) {
        throw new \Exception('Please specify key and value as arguments');
    }

    $key = (int)$options['k'];
    $value = (int)$options['v'];

    $map = new \App\IntIntMap($shm, shmop_size($shm));

    echo "Putting ${value} to ${key}...\n\n";

    $previousValue = $map->put($key, $value) ?? 'not defined';

    echo "OK. Previous value is ${previousValue}\n\n";

    echo "Reading from ${key}...\n\n";

    $result = $map->get($key);

    echo "Result is: ${result}\n";
} catch (\Exception $e) {
    echo "An error has been occurred: {$e->getMessage()}\n";
} finally {
    \shmop_delete($shm);
}
