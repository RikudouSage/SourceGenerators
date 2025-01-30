<?php

namespace App;

/**
 * @template T of object
 */
interface SerializationProvider
{
    /**
     * @param T $object
     */
    public function serialize(object $object): array;

    /**
     * @return T
     */
    public function deserialize(array $data): object;
}
