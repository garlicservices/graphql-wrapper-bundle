<?php

namespace Garlic\Wrapper\Service\GraphQL\DataSource;

interface DataSourceInterface
{
    public function enqueue(array $args);

    public function resolve(array $args, array $fields);
}
