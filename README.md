# Garlic GraphQL wrapper

This bundle allow microservices communicate to each other using GraphQL query builder

## Installation

Just a couple things are necessary for this bundle works. 

### Add garlic/bus bundle to your composer.json

```bash
composer require garlic/graphql-wrapper
```

### GraphQL way to get result from service (several services)

**Important:** If you want to use GraphQL wrapper you have to install [garlicservices/graphql-bundle](https://github.com/garlicservices/graphql-bundle) on all the services you requiested in your queries.
To install bundle on application just type in console the command showed below
```bash
composer require garlic/grpahql-bundle
```
#### Easy way to use GraphQl query

Simple example of querying data from remote microservice

````php
$graphQLService = $this->get(GraphQLService::class);

$addressQuery = $graphQLService->createQuery('serviceName.QueryName');
$addressQuery
    ->select('id', 'city', 'zipcode')
    ->where('country = Ukraine')
    ->addHeader('Authorization', 'abc');

$result = $graphQLService->fetch();
````

#### Querying internal related objects

Example of querying data from related objects
```php
$graphQLService = $this->get(GraphQLService::class);

$apartmentQuery = $graphQLService->createQuery('serviceName.QueryName');
$apartmentQuery
    ->select('id', 'buildYear', 'address.id', 'address.city', 'address.country')
    ->where('size = 5');
    
$result = $graphQLService->fetch();    
```

#### Searching on internal related objects

Example of searching data on included objects
```php
$graphQLService = $this->get(GraphQLService::class);

$apartmentQuery = $graphQLService->createQuery('serviceName.QueryName');
$apartmentQuery
    ->select('id', 'buildYear', 'address.id', 'address.city', 'address.country')
    ->where('size = 5', 'address.country = Ukraine');
    
$result = $graphQLService->fetch();
```

#### Querying external related objects (stitchOne)

Example of query stitching to one another by using stitchOne() method (stitched result will be included as an object)

```php
$graphQLService = $this->get(GraphQLService::class);

$addressQuery = $graphQLService->createQuery('firstServiceName.QueryName');
$addressQuery
    ->select('id', 'city', 'country')
    ->where('country = Ukraine')
;

$apartmentQuery = $graphQLService->createQuery('secondServiceName.QueryName');
$apartmentQuery
    ->select('id', 'size', 'addressId')
    ->where('size = 5')
    ->stitchOne($addressQuery, 'address', 'addressId', 'id')
;

$result = $graphQLService->fetch();
```

#### Querying external related list of objects (stitchMany) 

Example of stitching queries to one another by using stitchMany() method (stitched result will be included as list of objects)

```php
$graphQLService = $this->get(GraphQLService::class);

$addressQuery = $graphQLService->createQuery('firstServiceName.QueryName');
$addressQuery
    ->select('id', 'city', 'country')
    ->where('country = Ukraine')
;

$apartmentQuery = $graphQLService->createQuery('secondServiceName.QueryName');
$apartmentQuery
    ->select('id', 'size', 'addressId')
    ->where('size = 5')
    ->stitchMany($addressQuery, 'address', 'addressId', 'id')
;

$result = $graphQLService->fetch();
```

#### Querying stitching by using internally included objects

Example of stitching queries with fields from internally included objects

```php
$graphQLService = $this->get(GraphQLService::class);

$addressQuery = $graphQLService->createQuery('firstServiceName.QueryName');
$addressQuery
    ->select('id', 'city', 'country')
    ->where('country = Ukraine')
;

$apartmentQuery = $graphQLService->createQuery('secondServiceName.QueryName');
$apartmentQuery
    ->select('id', 'size', 'address.id', 'address.city', 'address.country')
    ->where('size = 5')
    ->stitchOne($addressQuery, 'fullAddress', 'address.id', 'id')
;

$result = $graphQLService->fetch();
```

### GraphQL mutations

Mutation is the way to change service data by sending some kinds of query. What this queries are and how they could created read below.

#### Create new data with GraphQL mutation

Example of creating new data row on remote microservice. Method "set" put new fields data in a query and method "select" contains fields that will be returned after query done.   

```php
$graphQLService = $this->get(GraphQLService::class);

$apartmentMutation = $graphQLService->createNewMutation('ServiceName.CreateMutationName');
$apartmentMutation
    ->set('size = 3', 'buildYear = 2018')
    ->select('id');
    
$result = $graphQLService->fetch();    
```

#### Update data with GraphQL mutation

```php
$graphQLService = $this->get(GraphQLService::class);

$apartmentMutation = $graphQLService->createUpdateMutation('ServiceName.UpdateMutationName');
$apartmentMutation
    ->set('size = 3', 'buildYear = 2018')
    ->where('size = 5')
    ->select('id');
    
$result = $graphQLService->fetch();    
```

#### Delete data with GraphQL mutation

```php
$graphQLService = $this->get(GraphQLService::class);

$apartmentMutation = $graphQLService->createDeleteMutation('ServiceName.DeleteMutationName');
$apartmentMutation
    ->where('size = 5')
    ->select('id');
    
$result = $graphQLService->fetch();    
```

#### Making async batch request with parallel processing

```php
$graphQLService = $this->get(GraphQLService::class);

$addressMutation = $graphQLService->createNewMutation('template.AddressCreate');
$addressMutation
    ->select('id', 'country', 'city')
    ->set('country = Ukraine', 'city = Boyarka', 'street = Kyivska', 'zipcode = 20214', 'house = 1');

$apartmentQuery = $graphQLService->createQuery('template.AddressFind');
$apartmentQuery
    ->select('id')
    ->where(['id' => 123])
;

$result = $graphQLService->fetchAsync(); 
```

#### Query stitching in Mutation

Query stitching works the same way as in query mode. Just try, it's amazing!

Example of Create Mutation with next stitching to the query.

```php
$graphQLService = $this->get(GraphQLService::class);

$addressMutation = $graphQLService->createNewMutation('FirstServiceName.CreateMutationName');
$addressMutation
    ->set('city = Kyiv', 'country = Ukraine')
    ->select('id');
    
$apartmentQuery = $graphQLService->createQuery('SecondServiceName.QueryName');
$apartmentQuery
    ->select('id', 'size', 'address.id', 'address.city', 'address.country')
    ->where('size = 5')
    ->stitchOne($addressMutation, 'newAddress', 'address.country', 'country')
;    
    
$result = $graphQLService->fetch();    
```

You can use stitching with query and mutation and vise-versa. Even several mutation can be stitched to one another.

## Enjoy
