<?php

namespace Garlic\Wrapper\Service\GraphQL\DataSource;

use Youshido\GraphQL\Execution\DeferredResolver;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Parser\Ast\Field;

class DataSourceResolver
{
    /**
     * @var DataSourceInterface|null
     */
    protected $dataSource;

    /**
     * @var ResolveInfo
     */
    protected $resolverInfo;

    protected $value;

    /**
     * @var DataSourceRelationInterface
     */
    private $relation;

    public static function build(string $dataSourceAlias, string $relationAlias): callable
    {
        return function ($value, $args, ResolveInfo $resolveInfo) use ($dataSourceAlias, $relationAlias) {
            $container = $resolveInfo->getContainer();

            return (new static($container->get($dataSourceAlias), $container->get($relationAlias)))(...func_get_args());
        };
    }

    /**
     * DataSourceResolver constructor.
     */
    public function __construct(DataSourceInterface $dataSource, DataSourceRelationInterface $relation)
    {
        $this->dataSource = $dataSource;
        $this->relation = $relation;
    }

    public function __invoke($value, $args, ResolveInfo $resolveInfo): DeferredResolver
    {
        $this->resolverInfo = $resolveInfo;
        $this->value = $value;

        $this->dataSource->enqueue($this->handleRelationCallback());

        return new DeferredResolver(function () {
            return $this->dataSource->resolve($this->handleRelationCallback(), $this->getFields());
        });
    }

    protected function handleRelationCallback(): array
    {
        return $this->relation->relation($this->value);
    }

    protected function getFields(): array
    {
        return array_map(function (Field $field) {
            return $field->getName();
        }, $this->resolverInfo->getFieldASTList());
    }
}
