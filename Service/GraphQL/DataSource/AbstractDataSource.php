<?php

namespace Garlic\Wrapper\Service\GraphQL\DataSource;

use Garlic\Helpers\ArrayHelper;
use Garlic\Wrapper\Service\GraphQLService;

abstract class AbstractDataSource implements DataSourceInterface
{
    /**
     * @var array
     */
    protected $buffer = [];

    /**
     * @var array|null
     */
    protected $result;

    /**
     * @var string
     */
    protected $glueKey = '.';

    /**
     * @var GraphQLService
     */
    private $graphQLService;

    /**
     * CompanyDataSource constructor.
     */
    public function __construct(GraphQLService $graphQLService)
    {
        $this->graphQLService = $graphQLService;
    }

    public function enqueue(array $args): self
    {
        if ($this->result !== null) {
            $this->clear();
        }

        $this->buffer[] = $args;

        return $this;
    }

    public function resolve(array $args, array $fields)
    {
        if ($this->result === null) {
            try {
                $this->result = $this->applyMap($this->fetch($fields));
            } catch (\Exception $exception) {
                $this->result = [];
            }
        }

        return $this->result[$this->makePKByArgs($args)] ?? null;
    }

    abstract public function getQueryName(): string;

    protected function clear(): void
    {
        $this->buffer = [];
        $this->result = null;
    }

    protected function getPrimaryKey(): array
    {
        return ['id'];
    }

    protected function getRelatedFields(array $fields): array
    {
        return array_values(array_unique(array_merge($fields, $this->getPrimaryKey())));
    }

    protected function getRelatedConditions(): array
    {
        return array_map(function ($item) {
            return array_values(array_unique(ArrayHelper::wrap($item)));
        }, array_merge_recursive(...$this->buffer));
    }

    protected function getResponseRootPath(): string
    {
        return $this->packPath(['data', $this->getQueryName()]);
    }

    protected function fetch(array $fields): array
    {
        $this->graphQLService->createQuery($this->getQueryName())
            ->select($this->getRelatedFields($fields))
            ->where($this->getRelatedConditions());

        try {
            return $this->graphQLService->fetch();
        } catch (\Exception $exception) {
            /*
             * todo[egrik]: add logging exception and retry request
             */
        }

        return [];
    }

    protected function makePKByArgs(array $args): string
    {
        $data = ArrayHelper::mapByKeys($args, $this->getPrimaryKey());

        return implode($this->glueKey, array_map(function (...$items) {
            return implode($this->glueKey, $items);
        }, array_keys($data), $data));
    }

    protected function applyMap(array $result): array
    {
        $rootItem = ArrayHelper::get($result, $this->getResponseRootPath(), []);
        $columns = array_map([$this, 'makePKByArgs'], $rootItem);

        return array_map($callback = function ($array) use (&$callback) {
            if (is_array($array)) {
                return (object)array_map($callback, $array);
            }

            return $array;
        }, array_combine($columns, $rootItem));
    }

    protected function packPath($items): string
    {
        return implode($this->glueKey, $items);
    }
}
