# silex-mongodb-provider

[![Build Status](https://travis-ci.org/corllete/silex-mongodb-provider.svg?branch=master)](https://travis-ci.org/corllete/silex-mongodb-provider)
[![codecov](https://codecov.io/gh/corllete/silex-mongodb-provider/branch/master/graph/badge.svg)](https://codecov.io/gh/corllete/silex-mongodb-provider)
[![Scrutinizer Code
Quality](https://scrutinizer-ci.com/g/corllete/silex-mongodb-provider/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/corllete/silex-mongodb-provider/?branch=master)

Silex MongoDB Service Provider - integrates [Mongo PHP Library](https://github.com/mongodb/mongo-php-library)
with [Silex 2.x](https://github.com/silexphp/Silex)

## Requirements
- PHP 5.6+ || 7.0+
- php-mongodb (ext-mongodb)
- Silex 2.x

## Installation

```
$ composer require "corllete/silex-mongodb-provider=^1.0.0"
```

## Configuration & Usage

Connection uri defaults to `mongodb://localhost:27017`. For additional resources about configuration/setup options and library usage, take a look at the [Resources section](#resources).

### Zero Configuration

**Container setup**
```php
use Corllete\SilexMongoDB\Provider\MongoDBServiceProvider;

// connection uri: mongodb://localhost:27017
$app->register(new MongoDBServiceProvider());

```

> NOTE!
>
> I omit `use Corllete\SilexMongoDB\Provider\MongoDBServiceProvider;` line in all of the examples below for a sake of simplicity.

**Usage**
```php
/** @var $mongo \MongoDB\Client */
$mongo = $app['mongodb'];

// Instance of MongoDB\Database
$database = $mongo->some_db;

// Instance of MongoDB\Collection
$collection = $mongo->some_collection;

// OR the short version
$collection = $app['mongodb']->some_db->some_collection

// Instance of MongoDB\InsertOneResult
$result = $collection->insertOne([
    'name' => 'Gandalf The White'
]);

printf("Inserted %d document(s)\n", $result->getInsertedCount());
// Outputs: Inserted 1 document(s)

// Instance of MongoDB\BSON\ObjectID
$insertedId = $result->getInsertedId();

// Instance of MongoDB\Model\BSONDocument
$document = $collection->findOne(['_id' => $insertedId]);

echo $document['name'];
// 'Gandalf The White'

// Do your ninja magic here!
```

### Provide some options

**Container setup**
```php
// connection uri: mongodb://example.com:27017
$app->register(new MongoDBServiceProvider(), [
    'mongodb.options' => [
        'uri' => 'mongodb://example.com:27017',
        'uri_options'    => [...],
        'driver_options' => [
            'type_map' => [...],
        ],
    ],
]);
```

**Usage**
```php
/** @var $collection \MongoDB\Collection */
$collection = $app['mongodb']->db->collection;
```

### Multiple Connections

**Container setup**
```php
// `first` is the default connection, living in $app['mongodb'] namespace
$app->register(new MongoDBServiceProvider(), [
    'mongodbs.options' => [
        'first' => [
            'uri' => 'mongodb://first.com:27017',
        ],
        'second' => [
            'uri' => 'mongodb://user:pass@second.com:27017/some_db',
        ],
        'third' => [
            'uri' => 'mongodb://third.com:27017,mongodb://third.com:27018?replicaSet=myReplica',
        ],
    ],
]);
```
> **WARNING!**
>
> You have to apply `rawurlencode()` username and password when used directly in the uri string

**Usage**
```php
// mongodb://first.com:27017
$first = $app['mongodb'];

// OR
$first = $app['mongodbs']['first'];

// OR even
$first = $app['mongodbs']['default'];

// 'second' connection
$second = $app['mongodbs']['second'];

// 'third' connection
$second = $app['mongodbs']['third'];

// Do your ninja magic here!
```

### Explicit set default connection via connection name reference

**Container setup**
```php
// `second` is the default connection, living in $app['mongodb'] namespace
$app->register(new MongoDBServiceProvider(), [
    'mongodbs.options' => [
        'first' => [
            'uri' => 'mongodb://first.com:27017',
        ],
        'second' => [
            'uri' => 'mongodb://user:pass@second.com:27017/some_db',
        ],
        'third' => [
            'uri' => 'mongodb://third.com:27017,mongodb://third.com:27018?replicaSet=myReplica',
        ],
    ],
    'mongodbs.default' => 'second',
]);
```

**Usage**
```php
// 'second' is default now
$second = $app['mongodb'];

// OR
$second = $app['mongodbs']['second'];

// OR even
$second = $app['mongodbs']['default'];
```

### Explicit set default connection - connection label

**Container setup**
```php
// `second` is the default connection, living in $app['mongodb'] namespace
$app->register(new MongoDBServiceProvider(), [
    'mongodbs.options' => [
        'first' => [
            'uri' => 'mongodb://first.com:27017',
        ],
        'default' => [
            'uri' => 'mongodb://user:pass@second.com:27017/some_db',
        ],
        'third' => [
            'uri' => 'mongodb://third.com:27017,mongodb://third.com:27018?replicaSet=myReplica',
        ],
    ],
]);
```

**Usage**
```php
// 'default' is... well, default
$second = $app['mongodb'];

// OR
$second = $app['mongodbs']['default'];
```

### Assemble `uri`

**Container setup**
```php
// resulting in uri: 'mongodb://username:password@example.com:27017/some_db'
$app->register(new MongoDBServiceProvider(), [
    'mongodb.options' => [
        'host' => 'example.com',
        'port' => '27017',
        'username' => 'user',
        'password' => 'pass',
        'database' => 'some_db',
    ],
]);
```
Few things to keep in mind here:
- `host` and `port` are required in this configuration scenario, no defaults fallback
- if you provide `username`, `password` is required
- `database` is optional in all cases

### Container namespace

By default, this service provider is registered in `mongodb.*`  and `mongodbs.*` container namespace. While reserved by the [core parameters](http://silex.sensiolabs.org/doc/master/services.html#core-parameters) and [core services namespace](http://silex.sensiolabs.org/doc/master/services.html#core-services) is something everyone must live with, I don't feel occupying namespace (e.g. `mongodb`) from thrid party service providers is a good practice. Exactly this bad feeling made me implement feature (that I personally call) `service provider namespace`. The logic behind it is extremely simple - you provide single and multi namespace values (following the logic of core `DoctrineServiceProvider` which takes `db` and `dbs` namespaces) to the service provider constructor as respectively first and second argument.

It's a 'behind the scenes' feature, you may or may not use it but most important - you have a choice.

**Example - occupy `db` and `dbs` namespaces - given there is no registered `DoctrineServiceProvider`, no space for RDBMS!**
```php
$app->register(new MongoDBServiceProvider('db', 'dbs'), [
    'db.options' => [
        'uri' => 'mongodb://example.com:27017',
    ],
]);
```

**Usage**
```php
// namespace now is `db`
$mongo = $app['db'];

// ... and `dbs`
$mongo = $app['dbs']['default'];
```

**Example - override only single connection namespace to `db`**
```php
$app->register(new MongoDBServiceProvider('db'), [
    'db.options' => [
        'uri' => 'mongodb://example.com:27017',
    ],
]);
```

**Usage**
```php
// namespace now is `db`
$mongo = $app['db'];

// but multi connection namespace is still `mongodbs`
$mongo = $app['mongodbs']['default'];
```

## MongoDB Client service factory

If you, for any reason (unknown to me!) decide you need to manually create `\MongoDB\Client` instance, you may use the registered with `MongoDBServiceProvider` factory callable.

```php
$app->register(new MongoDBServiceProvider());

// somewhere else
$app['myStrangeMongoDBClient'] = function ($app) {
    return $app['mongodb.factory']()
}
```

Factory callable accepts one argument - array of connection configuration options, as they are passed via `mongodb.options` to the service provider. If no configuration options are passed, `$app['mongodb.default_options']` will be used (and you may override it!).

## Configuration options, service parameters and services reference

### Configuration options

The following describes the options passed to the `register()` container method (see examples section).

**Single connection options**

`mongodb` is the single connection configuration namespace, it may be overridden (see Namespace section). If this happens, you have to substitute it in the examples below.

- `mongodb.default_options` (`array`) - options used for zero configuration (or the factory service) in same format as `mongodb.options`. You may override it directly in your bootstrap code to change default behaviour. The default value of this option:
```php
'mongodb.default_options' => [
    'uri'            => null,
    'host'           => 'localhost',
    'port'           => 27017,
    'database'       => null,
    'username'       => null,
    'password'       => null,
    'uri_options'    => [],
    'driver_options' => [
        'type_map' => [],
    ],
];

```
- `mongodb.options` (`array`) - same format as `mongodb.default_options`, set your single connection options - more details about `uri` format, uri and driver options in [MongoDB PHP library \MongoDB\Client documentation][mongo-client] and [Official MongoDB `conection uri` documentation][mongo-connection-uri].

**Multi connection options**

`mongodbs` is the multi connection configuration namespace, it may be overridden (see Namespace section). If this happens, you have to substitute it in the examples below.

- `mongodbs.options` (`array`) - array of `mongodb.options` in format `LABEL => mongodb.options`. More details in the examples section.
- `mongodbs.default` (`string`) - connection label, defined in `mongodbs.options` to be set as default (thus retrieved with `$app['mongodb']`).

### Services

Retrievable in your code via `$app['SERVICE_NAME']`;

- `mongodb` (`\MongoDB\Client`) - default mongodb client. The name of this service depends on the single connection configuration namespace.
- `mongodbs` (`Pimple\Container`) - contains all mongodb clients registered with the service provider. Retrieve them by label. The name of this service depends on the multi connection configuration namespace.

**Internal**
- `mongodb.factory` (`callable`) - `\MongoDB\Client` factory service. The name of this service depends on the single connection configuration namespace.
- `mongodb.options.init` (`callable`) - used internally to one-time prepare the configuration array. The name of this service depends on the single connection configuration namespace.

### Parameters

Retrieve or override in your code via `$app['PARAMETER_NAME']`;

- `mongodb.default_options` (`array`) - set to override zero configuration setup & mongodb client factory default behaviour. More details in `Configuration options` section.  The name of this parameter depends on the single connection configuration namespace.

## Tests

Run `phpunit` test after composer install

```
$ composer install && bin/phpunit
```

You may also print the coverage
```
bin/phpunit --coverage-text
```

## Gotchas

- `s` is important! Be sure not to mess up `mongodb.options` and `mongodbs.options`
- It seems your service provider configuration is not found? In most cases you did the following - you have changed the [service provider namespace](#container-namespace), but you have provided the default namespace configuration options (e.g. `mongodb.options` instead `yourNamespace.options`)
- If you see `\LogicException` after you call the mongodb client in your code, check your service provider configuration - most probably you have provided both `mongodb.options` and `mongodbs.options` configuration parameters (which is clearly illegal).
- You have set 'default' connection in multi connection setup, but it is NOT the default one! Well, hardly, but you may double check if you are using both methods of setting default connection - connection labeled 'default' and `mongodbs.default = connection_name`. Connection label has higher precedence, so in the previous scenario, 'default' labeled connection will be the one returned by `$app['mongodb']` and `$app['mongodbs']['default']`

## Contribute

Fork me and open a Pull Request. Please don't provide code contribution without test coverage. Single commit Pull Request preffered.

## Resources

- [Official MongoDB PHP library documentation][mongo-lib]
- [Official MongoDB documentation][mongo-docs]
- [Official MongoDB PHP extension (the new `mongodb` driver) documentation][mongo-driver]
- [MongoDB\Client - PHP library documentation][mongo-client]
- [MongoDB Connection - official MongoDB documentation][mongo-connection-uri]
- [Silex Micro-framework][silex]
- [Google - everything else!](https://google.com/)

[mongo-connection-uri]: https://docs.mongodb.com/manual/reference/connection-string/
[mongo-client]: http://mongodb.github.io/mongo-php-library/classes/client/
[mongo-driver]: http://php.net/manual/en/set.mongodb.php
[mongo-docs]: https://docs.mongodb.com/manual/
[mongo-lib]: http://mongodb.github.io/mongo-php-library/
[silex]: http://silex.sensiolabs.org/

## License

This package is licensed under [MIT License](https://github.com/corllete/silex-mongodb-provider/blob/master/LICENSE)

(c) [Corllete](http://corllete.com/)
