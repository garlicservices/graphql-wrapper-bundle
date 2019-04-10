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
    ->where('country = Ukraine');

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

### Querying external related objects using a deferred resolver
Using deferred resolvers allows to getting relate data by one query after receiving the data of the base query, which solve the N + 1 problem.

Suppose we have next GraphQL query:
```json
{
  wallet {
    WalletFind {
      items {
        id
        amount
        company {
          id
          title
        }
      }
    }
  }
}
```

Where _company_ object is part of an external service, wich we'd like to get by one query to the external service.
To solve this problem, we need:
1. Create CompanyType, which we'd like use in our schema:
   ```php
    <?php
    namespace App\GraphQL\DataSource\Type;
    
    use Youshido\GraphQL\Field\Field;
    use Youshido\GraphQL\Type\Object\AbstractObjectType;
    use Youshido\GraphQL\Type\Scalar\StringType;
    
    /**
     * Class CompanyType
     */
    class CompanyType extends AbstractObjectType
    {
     public function build($config): void
     {
   $config->addField(new Field(['name' => 'id', 'type' => new StringType()]))
    ->addField(new Field(['name' => 'title', 'type' => new StringType()]));
     }
    }
   ```
   
2. Create CompanyDataSource, which we'd like use to get external data:
    ```php
    <?php
    namespace App\GraphQL\DataSource;
    
    use \Garlic\Wrapper\Service\GraphQL\DataSource\AbstractDataSource;
    
    class CompanyDataSource extends AbstractDataSource
    {
        public function getQueryName(): string
        {
            return 'company.CompanyFind';
        }
    }
    ```
    We have to create _getQueryName()_ method that returns the name of the query to fetch the companies data.

3. Create WalletCompanyRelation that implements _Garlic\Wrapper\Service\GraphQL\DataSource\DataSourceRelationInterface_ which returns a description of the relation between CompanySource and WalletType.  
    ```php
    <?php
    
    namespace App\GraphQL\DataSource;
    
    use App\Entity\Wallet;
    use \Garlic\Wrapper\Service\GraphQL\DataSource\DataSourceRelationInterface;
    
    class WalletCompanyRelation implements DataSourceRelationInterface
    {
        /**
         * @param Wallet $entity
         *
         * @return array
         */
        public function relation($entity): array
        {
            return ['id' => $entity->getCompanyId()];
        }
    }
    ```
4. Add the Company field to WalletType:
    ```php
    <?php
    
    namespace App\GraphQL\Type;
    
    use App\GraphQL\DataSource\CompanyDataSource;
    use App\GraphQL\DataSource\WalletCompanyRelation;
    use Garlic\Wrapper\Service\GraphQL\DataSource\DataSourceResolver;
    use App\GraphQL\DataSource\Type\CompanyType;
    use Garlic\GraphQL\Type\TypeAbstract;
    use Youshido\GraphQL\Type\Scalar\FloatType;
    use Youshido\GraphQL\Type\Scalar\IdType;
    
    class WalletType extends TypeAbstract
    {
        public function build($builder)
        {
            $builder->addField('id', new IdType())
                ->addField('amount', new FloatType(), ['argument' => false])
                ->addField('company', new CompanyType(), [
                    'argument' => false,
                    'resolve' => DataSourceResolver::build(CompanyDataSource::class, WalletCompanyRelation::class),
                ]);
        }  
       //...
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