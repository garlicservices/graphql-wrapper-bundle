<?php

namespace Garlic\Wrapper\Service\GraphQL\DataSource;

interface DataSourceRelationInterface
{
    public function relation($entity): array;
}
