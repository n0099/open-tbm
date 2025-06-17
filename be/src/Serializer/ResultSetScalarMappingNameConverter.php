<?php

namespace App\Serializer;

use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class ResultSetScalarMappingNameConverter implements NameConverterInterface
{
    private array $flippedScalarMappings;

    public function __construct(private ResultSetMapping $resultSetMapping)
    {
        $this->flippedScalarMappings = array_flip($resultSetMapping->scalarMappings);
    }

    public function normalize(string $propertyName): string
    {
        return $this->flippedScalarMappings[$propertyName];
    }

    public function denormalize(string $propertyName): string
    {
        return $this->resultSetMapping->scalarMappings[$propertyName];
    }
}
