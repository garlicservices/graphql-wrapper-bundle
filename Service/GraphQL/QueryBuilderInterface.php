<?php
    
namespace Garlic\Wrapper\Service\GraphQL;

use Dflydev\DotAccessData\Data;

interface QueryBuilderInterface
{
    /**
     * Returns built GraphQL query as string
     *
     * @return mixed
     */
    public function getQuery(): string;
}