<?php

namespace Garlic\Wrapper\Service\GraphQL\Mutation;

use Garlic\Wrapper\Service\GraphQL\AbstractQueryBuilder;
use Garlic\Wrapper\Service\GraphQL\Exceptions\GraphQLQueryException;
use Garlic\Wrapper\Service\GraphQL\QueryBuilderInterface;
use Garlic\Wrapper\Service\GraphQL\SetQueryTrait;

class CreateMutationBuilder extends AbstractQueryBuilder implements QueryBuilderInterface
{
    use SetQueryTrait;
    
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
        
        if(count($this->values) <= 0) {
            throw new GraphQLQueryException('Updates mutation must contains all required fields. Use method set() to set them.');
        }
        
        return "mutation {".$this->query." (".$this->createArguments($this->values).") {".$this->createFields($this->fields)."}}";
    }
}
