<?php

namespace Bencode\SnowflakeOdbc;

use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Builder;

class SnowflakeOdbcQueryGrammar extends Grammar
{    
    protected function compileAggregate(Builder $query, $aggregate)
{
    $column = $this->columnize($aggregate['columns']);

    return 'select ' . $aggregate['function'] . '(' . $column . ') as "aggregate"';
}

}
