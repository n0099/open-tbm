<?php

namespace App\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\SqlWalker;

/** @see https://stackoverflow.com/questions/2095394/doctrine-how-to-print-out-the-real-sql-not-just-the-prepared-statement/79647754#79647754 */
class InterpolateParametersSQLOutputWalker extends SqlWalker
{
    /** {@inheritdoc} */
    public function walkInputParameter(AST\InputParameter $inputParam): string
    {
        $parameter = $this->getQuery()->getParameter($inputParam->name);
        if ($parameter === null) {
            return '?';
        }

        $value = $parameter->getValue();
        /** @var ParameterType|ArrayParameterType|int|string $typeName */
        /** @see \Doctrine\ORM\Query\ParameterTypeInferer::inferType() */
        $typeName = $parameter->getType();
        $platform = $this->getConnection()->getDatabasePlatform();
        $processParameterType = static fn(ParameterType $type) => static fn($value): string =>
            (match ($type) { /** @see Type::getBindingType() */
                ParameterType::NULL => 'NULL',
                ParameterType::INTEGER => $value,
                ParameterType::BOOLEAN => (new BooleanType())->convertToDatabaseValue($value, $platform),
                ParameterType::STRING, ParameterType::ASCII => $platform->quoteStringLiteral($value),
                default => throw new ValueNotConvertible($value, $type->name)
            });

        if (is_string($typeName) && Type::hasType($typeName)) {
            return Type::getType($typeName)->convertToDatabaseValue($value, $platform);
        }
        if ($typeName instanceof ParameterType) {
            return $processParameterType($typeName)($value);
        }
        if ($typeName instanceof ArrayParameterType && is_array($value)) {
            $type = ArrayParameterType::toElementParameterType($typeName);
            return implode(', ', array_map($processParameterType($type), $value));
        }

        throw new ValueNotConvertible($value, $typeName);
    }
}
