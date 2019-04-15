<?php
    
namespace Garlic\Wrapper\Service\GraphQL;


use Garlic\Wrapper\Service\GraphQL\Exceptions\GraphQLQueryException;

class QueryHelper
{
    /**
     * Parse query name
     *
     * @param string $from
     * @return array
     * @throws GraphQLQueryException
     */
    public function parsQueryName(string $from): array 
    {
        $meta = explode('.', $from);
        if(count($meta) < 2) {
            throw new GraphQLQueryException('From part of query must contains service and query name separated by dot');
        }
        
        return [
            'service' => $meta[0],
            'query' => $meta[1],
        ];
    }
    
    /**
     * Check result is object
     *
     * @param $result
     * @return bool
     */
    public function checkResultIsObject($result)
    {
        return array_keys($result) !== range(0, count($result) - 1);
    }

    /**
     * @param $requests
     * @return array
     */
    public function mergeHeaders($requests)
    {
        $headers = [];
        
        foreach ($requests as $request) {
            $headers = array_merge($headers, $request->getHeaders());
        }
        
        return $headers;
    }
}
