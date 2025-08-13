<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;

class AlwaysQuoteStrategy extends DefaultQuoteStrategy
{
    public function getColumnName(string $fieldName, ClassMetadata $class, AbstractPlatform $platform): string
    {
        return $platform->quoteSingleIdentifier($class->fieldMappings[$fieldName]->columnName);
    }
}
