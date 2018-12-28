<?php

namespace Garlic\Wrapper\Service\GraphQL\Query;

use Dflydev\DotAccessData\Data;
use Garlic\Wrapper\Service\GraphQL\AbstractQueryBuilder;
use Garlic\Wrapper\Service\GraphQL\Exceptions\GraphQLQueryException;
use Garlic\Wrapper\Service\GraphQL\QueryBuilderInterface;
use Garlic\Wrapper\Service\GraphQL\WhereQueryTrait;

class QueryBuilder extends AbstractQueryBuilder implements QueryBuilderInterface
{
    use WhereQueryTrait;
    
    /**
     * Create query as a string
     *
     * @return string
     * @throws GraphQLQueryException
     */
    public function getQuery(): string
    {
        if(empty($this->query)) {
            throw new GraphQLQueryException('Select section not found in the query.');
        }
        
        if(count($this->fields) <= 0) {
            throw new GraphQLQueryException('Query must contains at least one selected field. Use method select() to set it.');
        }
    
        $arguments = '';
        if(count($this->arguments) > 0) {
            $arguments = "(".$this->createArguments($this->arguments).")";
        }
    
        return "{".$this->query." $arguments {".$this->createFields($this->fields)."}}";
    }
}
