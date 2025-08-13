<?php

namespace App\Doctrine;

use Doctrine\ORM\Query\SqlOutputWalker;
use Doctrine\ORM\Query\AST;

class PrefixParameterNameSqlOutputWalker extends SqlOutputWalker
{
    public const string HINT_PARAMETER_PREFIX = 'tbm.dql.parameter.prefix';

    /** {@inheritdoc} */
    public function walkInputParameter(AST\InputParameter $inputParam): string
    {
        $query = $this->getQuery();
        $parameter = $query->getParameter($inputParam->name);
        return $parameter === null
            ? '?'
            : ':' . $query->getHint(self::HINT_PARAMETER_PREFIX) . $inputParam->name;
    }
}
