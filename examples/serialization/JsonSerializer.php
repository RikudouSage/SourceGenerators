<?php

namespace App;

use LogicException;
use Rikudou\SourceGenerators\Attribute\PartialClass;
use Rikudou\SourceGenerators\Attribute\PartialMethod;
use RuntimeException;

#[PartialClass]
final class JsonSerializer
{
    public function serialize(object $object): array
    {
        foreach ($this->getProviders() as $providerClass => $provider) {
            if (is_a($object, $providerClass, true)) {
                return $provider->serialize($object);
            }
        }

        throw new RuntimeException('Object of type ' . $object::class . ' does not have any provider registered, please mark the class with #[JsonSerializable].');
    }

    public function deserialize(array $json, string $class): object
    {
        foreach ($this->getProviders() as $providerClass => $provider) {
            if (is_a($class, $providerClass, true)) {
                return $provider->deserialize($json);
            }
        }

        throw new RuntimeException('Object of type ' . $class . ' does not have any provider registered, please mark the class with #[JsonSerializable].');
    }

    /**
     * @return iterable<string, SerializationProvider<object>>
     */
    #[PartialMethod]
    private function getProviders(): iterable
    {
        throw new LogicException('This method should have been implemented by a source generator.');
    }
}
