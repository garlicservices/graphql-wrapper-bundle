<?php

namespace Garlic\Wrapper\Service;

use Dflydev\DotAccessData\Data;
use Enqueue\Rpc\Promise;
use Garlic\Wrapper\Service\GraphQL\Exceptions\GraphQLQueryException;
use Garlic\Wrapper\Service\GraphQL\Mutation\CreateMutationBuilder;
use Garlic\Wrapper\Service\GraphQL\Mutation\DeleteMutationBuilder;
use Garlic\Wrapper\Service\GraphQL\Mutation\MutationBuilder;
use Garlic\Wrapper\Service\GraphQL\Mutation\UpdateMutationBuilder;
use Garlic\Wrapper\Service\GraphQL\Query\QueryBuilder;
use Garlic\Wrapper\Service\GraphQL\QueryHelper;
use Garlic\Wrapper\Service\GraphQL\QueryRelation;
use Garlic\Bus\Service\Pool\CommunicationPoolService;
use Garlic\Bus\Service\CommunicatorService;
use Interop\Amqp\Impl\AmqpMessage;

class GraphQLService extends QueryHelper
{
    /** @var array */
    private $requests = [];

    /** @var CommunicatorService CommunicatorService */
    private $communicatorService;

    /** @var string */
    private $jwtToken;

    /**
     * GraphQLService constructor.
     * @param CommunicatorService $communicatorService
     */
    public function __construct(
        CommunicatorService $communicatorService
    ) {
        $this->communicatorService = $communicatorService;
    }

    /**
     * Create query builder
     *
     * @param string $from
     * @return QueryBuilder
     * @throws GraphQLQueryException
     */
    public function createQuery(string $from): QueryBuilder
    {
        $meta = $this->parsQueryName($from);
        $this->requests[$meta['service']][$meta['query']] = new QueryBuilder($meta['query']);

        return $this->requests[$meta['service']][$meta['query']];
    }

    /**
     * Create mutation builder
     *
     * @param string $from
     * @return MutationBuilder
     * @throws GraphQLQueryException
     */
    public function createMutation(string $from): MutationBuilder
    {
        $meta = $this->parsQueryName($from);
        $this->requests[$meta['service']][$meta['query']] = new MutationBuilder($meta['query']);

        return $this->requests[$meta['service']][$meta['query']];
    }

    /**
     * Create mutation builder for inserting new row
     *
     * @param string $from
     * @return CreateMutationBuilder
     * @throws GraphQLQueryException
     */
    public function createNewMutation(string $from): CreateMutationBuilder
    {
        $meta = $this->parsQueryName($from);
        $this->requests[$meta['service']][$meta['query']] = new CreateMutationBuilder($meta['query']);

        return $this->requests[$meta['service']][$meta['query']];
    }

    /**
     * Create mutation builder for updating rows
     *
     * @param string $from
     * @return UpdateMutationBuilder
     * @throws GraphQLQueryException
     */
    public function createUpdateMutation(string $from): UpdateMutationBuilder
    {
        $meta = $this->parsQueryName($from);
        $this->requests[$meta['service']][$meta['query']] = new UpdateMutationBuilder($meta['query']);

        return $this->requests[$meta['service']][$meta['query']];
    }

    /**
     * Create mutation builder for deleting rows
     *
     * @param string $from
     * @return DeleteMutationBuilder
     * @throws GraphQLQueryException
     */
    public function createDeleteMutation(string $from): DeleteMutationBuilder
    {
        $meta = $this->parsQueryName($from);
        $this->requests[$meta['service']][$meta['query']] = new DeleteMutationBuilder($meta['query']);

        return $this->requests[$meta['service']][$meta['query']];
    }

    /**
     * Execute async queries and returns received data
     *
     * @return mixed
     */
    public function fetchAsync()
    {
        foreach ($this->requests as $serviceName => $request) {
            /** @var QueryBuilder $query */
            foreach ($request as $queryName => $query) {
                $this->communicatorService->pool($serviceName, 'graphql', [], ['query' => (string)$query]);
            }
        }
        $result = $this->communicatorService->fetch();

        foreach ($this->requests as $serviceName => $request) {
            /** @var QueryBuilder $query */
            foreach ($request as $queryName => $query) {
                $data = $result[$serviceName]->getData();
                $query->setResult(
                    isset($data['data'][$queryName]) ? $data['data'][$queryName] : null
                );
            }
        }
        return $this->stitchQueries();
    }

    /**
     * Execute queries and returns received data
     *
     * @return array
     */
    public function fetch($asAdmin = false): array
    {
        $bearer = $asAdmin ? $this->getAdminJwtToken() : false;

        foreach ($this->requests as $serviceName => $request) {
            $result = $this->communicatorService
                ->request($serviceName)
                /** @var CommunicatorService::__call('graphql'), ... */
                ->graphql([], ['query' => implode("\n", $request)], $asAdmin ? ['Authorization' => $bearer] : [])
                ->getData();

            /** @var QueryBuilder $query */
            foreach ($request as $queryName => $query) {
                $query->setResult((!empty($result['data'])) ? $result['data'][$queryName] : null);
            }
        }

        return $this->stitchQueries();
    }

    /**
     * Stitch queries to each other by stitch rules
     *
     * @return mixed
     */
    private function stitchQueries()
    {
        $result = [];
        foreach ($this->requests as $serviceName => $request) {
            /** @var QueryBuilder $query */
            foreach ($request as $queryName => $query) {
                if (count($query->getStitched()) > 0) {
                    $this->bindRelations($query);
                }

                $result['data'][$serviceName][$queryName] = $query->getArrayResult();
            }
        }

        $this->requests = [];

        return $result;
    }

    /**
     * Bind relation value to the query
     *
     * @param QueryBuilder $query
     * @return mixed
     */
    private function bindRelations(QueryBuilder $query)
    {
        $queryDataResults = $query->getResult();
        $queryArrayResults = $query->getArrayResult();
        if ($this->checkResultIsObject($queryArrayResults)) {
            $queryArrayResults = [$queryArrayResults];
        }

        /** @var QueryRelation $relation */
        foreach ($query->getStitched() as $relation) {
            $relationDataResults = $relation->getQuery()->getResult();
            $relationArrayResults = $relation->getQuery()->getArrayResult();
            if ($this->checkResultIsObject($relationArrayResults)) {
                $relationArrayResults = [$relationArrayResults];
            }

            foreach ($queryArrayResults as $queryKey => $queryArrayResult) {
                $queryRelationValue = $queryDataResults->get($queryKey.'.'.$relation->getCurrent());

                foreach ($relationArrayResults as $relationKey => $relationArrayResult) {
                    $relationValue = $relationDataResults->get($relationKey.'.'.$relation->getTarget());

                    $type = ($relation->getType() == QueryRelation::TYPE_ONE) ? "set" : "append";
                    if ($queryRelationValue == $relationValue) {
                        $queryDataResults->{$type}(
                            $queryKey.'.'.$relation->getAlias(),
                            $relationArrayResult
                        );
                    }
                }

            }
        }

        $query->setResult($queryDataResults->export());

        return $query->getResult()->export();
    }

    /**
     * @return string
     */
    public function getAdminJwtToken()
    {
        if (empty($this->jwtToken)) {
            $meta = $this->parsQueryName('user.UserLogin');
            $builder = new QueryBuilder($meta['query']);
            $builder->select(['jwtToken'])
                ->where(['email' => getenv('ADMIN_EMAIL'), 'plainPassword' => getenv('ADMIN_PASSWORD')]);

            $result = $this->communicatorService
                ->request('user')
                /** @var CommunicatorService::__call('graphql'), ... */
                ->graphql([], ['query' => $builder->getQuery()])
                ->getData();

            $this->jwtToken = $result['data']['UserLogin'][0]['jwtToken'];
        }

        return $this->jwtToken;
    }
}
