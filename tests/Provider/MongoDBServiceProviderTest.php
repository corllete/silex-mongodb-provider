<?php
/**
 * This file is part of the Silex MongoDB Service Provider package.
 *
 * (c) Miro Yovchev <m.yovchev@corllete.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Corllete\SilexMongoDB\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Corllete\SilexMongoDB\Provider\MongoDBServiceProvider;

/**
 * Class MongoDBServiceProviderTest
 *
 * @author Miroslav Yovchev <m.yovchev@corllete.com>
 * @coversDefaultClass \Corllete\SilexMongoDB\Provider\MongoDBServiceProvider
 */

class MongoDBServiceProviderTest extends TestCase
{
    /**
     * @covers ::register
     * @covers ::assembleUri
     */
    public function testOptionInit()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider());

        $this->assertEquals($app['mongodb.default_options']['host'], MongoDBServiceProvider::$defaultOptions['host']);
        $this->assertEquals($app['mongodb.default_options']['port'], MongoDBServiceProvider::$defaultOptions['port']);

        $db = $app['mongodb'];
        $this->assertEquals(
            sprintf(
                'mongodb://%s:%s',
                MongoDBServiceProvider::$defaultOptions['host'],
                MongoDBServiceProvider::$defaultOptions['port']
            ), 
            (string) $db
        );
    }

    /**
     * @covers ::register
     */
    public function testInitOnce()
    {
        $app = new Container();
        $service = new MongoDBServiceProvider();
        $service->register($app);

        /** @var $db \MongoDB\Client */
        $app['mongodb'];

        $this->assertTrue(isset($app['mongodbs.options']));
        unset($app['mongodbs.options']);

        $app['mongodbs.options.init']();
        $this->assertFalse(isset($app['mongodbs.options']));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Namespace can not be empty
     * @covers ::__construct
     */
    public function testEmptyNamespaceThrowsError()
    {
        new MongoDBServiceProvider('');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Namespace for connections collection can not be empty
     * @covers ::__construct
     */
    public function testEmptyMultiNamespaceThrowsError()
    {
        new MongoDBServiceProvider('mongodb', '');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessageRegex ^Illegal configuration
     * @covers ::register
     */
    public function testMixSingleAndMultiConfigurationIsIllegal()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [],
            'mongodbs.options' => [],
        ]);
        $app['mongodb'];
    }

    /**
     * @covers ::register
     */
    public function testSingleConnection()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'uri' => 'mongodb://example.com:27017',
            ]
        ]);

        /** @var $db \MongoDB\Client */
        $db = $app['mongodb'];

        $this->assertInstanceOf('\MongoDB\Client', $db);
        $this->assertSame($app['mongodbs']['default'], $db);
        $this->assertEquals('mongodb://example.com:27017', (string) $db);
    }

    /**
     * @covers ::register
     * @covers ::__construct
     */
    public function testChangeServiceNamespace()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider('db', 'dbs'));

        /** @var $db \MongoDB\Client */
        $db = $app['db'];

        $this->assertInstanceOf('\MongoDB\Client', $db);
        $this->assertSame($app['dbs']['default'], $db);
    }

    /**
     * @covers ::assembleUri
     * @covers ::register
     */
    public function testCanAssembleMinimalUri()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'host' => 'example.com',
                'port' => '27017',
            ]
        ]);

        /** @var $db \MongoDB\Client */
        $db = $app['mongodb'];

        $this->assertEquals('mongodb://example.com:27017', (string) $db);
    }
    /**
     * @covers ::register
     */
    public function testUriOptionHasHigherPrecendenceThanAssembleOptions()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'uri' => 'mongodb://example-uri.com:1',
                'host' => 'exmample-assemble.com',
                'port' => '2',
            ]
        ]);

        /** @var $db \MongoDB\Client */
        $db = $app['mongodb'];

        $this->assertEquals('mongodb://example-uri.com:1', (string) $db);
    }

    /**
     * @covers ::assembleUri
     * @covers ::register
     */
    public function testCanAssembleFullUri()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'host' => 'example.com',
                'port' => '27017',
                'username' => 'user',
                'password' => 'pwd',
                'database' => 'test',
            ]
        ]);

        $this->assertEquals('mongodb://user:pwd@example.com:27017/test', (string) $app['mongodb']);
    }

    /**
     * @covers ::assembleUri
     * @covers ::register
     */
    public function testCanAssemblePartialUri()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'host' => 'example.com',
                'port' => '27017',
                'database' => 'test',
            ]
        ]);

        $this->assertEquals('mongodb://example.com:27017/test', (string) $app['mongodb']);

        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'host' => 'example.com',
                'port' => '27017',
                'username' => 'user',
                'password' => 'pwd',
            ]
        ]);

        $this->assertEquals('mongodb://user:pwd@example.com:27017', (string) $app['mongodb']);
    }

    /**
     * @covers ::assembleUri
     * @covers ::register
     */
    public function testWontAddCredentialsWithEmptyUserOrPass()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'host' => 'example.com',
                'port' => '27017',
                'username' => 'user',
            ]
        ]);

        $this->assertEquals('mongodb://example.com:27017', (string) $app['mongodb']);

        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'host' => 'example.com',
                'port' => '27017',
                'password' => 'pwd',
            ]
        ]);

        $this->assertEquals('mongodb://example.com:27017', (string) $app['mongodb']);
    }

    /**
     * @covers ::assembleUri
     * @covers ::register
     */
    public function testUsernameAndPasswordAreEncoded()
    {
        $user = 'a user@';
        $pass = 'a pwd@';

        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'host' => 'example.com',
                'port' => '27017',
                'username' => $user,
                'password' => $pass,
            ]
        ]);

        $this->assertEquals(sprintf(
            'mongodb://%s:%s@example.com:27017',
            rawurlencode($user),
            rawurlencode($pass)
        ), (string) $app['mongodb']);
    }

    /**
     * @covers ::assembleUri
     * @covers ::register
     */
    public function testCanAssembleUriWithoutUriKey()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.options' => [
                'host' => 'example.com',
                'port' => '27017',
            ]
        ]);

        /** @var $db \MongoDB\Client */
        $db = $app['mongodb'];

        $this->assertEquals('mongodb://example.com:27017', (string) $db);
    }

    /**
     * @covers ::register
     */
    public function testHasDefaultOptions()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider());

        $this->assertEquals(MongoDBServiceProvider::$defaultOptions, $app['mongodb.default_options']);
    }

    /**
     * @covers ::register
     */
    public function testCanOverrideDefaultOptions()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodb.default_options' => [
                'uri' => 'mongodb://example.com:27017',
            ]
        ]);

        $this->assertEquals('mongodb://example.com:27017', (string) $app['mongodb']);
    }

    /**
     * @covers ::register
     */
    public function testMultiConnection()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodbs.options' => $this->getMultiConnectionOptions()
        ]);

        $this->assertInstanceOf('\MongoDB\Client', $app['mongodb']);
        $this->assertSame($app['mongodbs']['default'], $app['mongodb']);
        $this->assertSame($app['mongodbs']['default'], $app['mongodbs']['first']);
        $this->assertEquals('mongodb://first.com:27017', (string) $app['mongodbs']['first']);

        $this->assertInstanceOf('\MongoDB\Client', $app['mongodbs']['second']);
        $this->assertEquals('mongodb://second.com:27017', (string) $app['mongodbs']['second']);

        $this->assertInstanceOf('\MongoDB\Client', $app['mongodbs']['third']);
        $this->assertEquals('mongodb://third.com:27017', (string) $app['mongodbs']['third']);
    }

    /**
     * @covers ::register
     */
    public function testMultiExplicitDefaultConnectionViaReference()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodbs.options' => $this->getMultiConnectionOptions(),
            'mongodbs.default' => 'second',
        ]);

        $this->assertInstanceOf('\MongoDB\Client', $app['mongodbs']['second']);
        $this->assertEquals('mongodb://second.com:27017', (string) $app['mongodbs']['default']);
        $this->assertEquals('mongodb://second.com:27017', (string) $app['mongodb']);
        $this->assertSame($app['mongodb'], $app['mongodbs']['second']);
        $this->assertSame($app['mongodb'], $app['mongodbs']['default']);
    }

    /**
     * @covers ::register
     */
    public function testMultiExplicitDefaultConnectionViaLabel()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodbs.options' => $this->getMultiConnectionWithDefaultOptions(),
        ]);

        $this->assertInstanceOf('\MongoDB\Client', $app['mongodbs']['default']);
        $this->assertEquals('mongodb://second.com:27017', (string) $app['mongodbs']['default']);
        $this->assertEquals('mongodb://second.com:27017', (string) $app['mongodb']);
        $this->assertSame($app['mongodb'], $app['mongodbs']['default']);
    }


    /**
     * @covers ::register
     */
    public function testDefaultConnectionLabelHasHigherPrecedenceThanDefaultReference()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider(), [
            'mongodbs.options' => $this->getMultiConnectionWithDefaultOptions(),
            'mongodbs.default' => 'third',
        ]);

        $this->assertInstanceOf('\MongoDB\Client', $app['mongodbs']['default']);
        $this->assertEquals('mongodb://second.com:27017', (string) $app['mongodbs']['default']);
        $this->assertEquals('mongodb://second.com:27017', (string) $app['mongodb']);
        $this->assertSame($app['mongodb'], $app['mongodbs']['default']);
        $this->assertNotSame($app['mongodb'], $app['mongodbs']['third']);
    }

    /**
     * @covers ::register
     */
    public function testMultiConnectionCanHaveDifferentNamespace()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider('mongodb', 'dbs'), [
            'dbs.options' => $this->getMultiConnectionOptions()
        ]);

        $this->assertInstanceOf('\MongoDB\Client', $app['mongodb']);
        $this->assertSame($app['dbs']['default'], $app['mongodb']);
        $this->assertSame($app['dbs']['default'], $app['dbs']['first']);
        $this->assertEquals('mongodb://first.com:27017', (string) $app['dbs']['first']);

        $this->assertInstanceOf('\MongoDB\Client', $app['dbs']['second']);
        $this->assertEquals('mongodb://second.com:27017', (string) $app['dbs']['second']);

        $this->assertInstanceOf('\MongoDB\Client', $app['dbs']['third']);
        $this->assertEquals('mongodb://third.com:27017', (string) $app['dbs']['third']);
    }

    /**
     * @covers ::register
     */
    public function testHasConnectionFactory()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider());

        $db = $app['mongodb.factory']();
        $this->assertInstanceOf('\MongoDB\Client', $db);
    }

    /**
     * @covers ::register
     */
    public function testDefaultNamespaceServices()
    {
        $app = new Container();
        $app->register(new MongoDBServiceProvider());

        $this->assertTrue(is_array($app['mongodb.default_options']));
        $this->assertTrue(is_callable($app['mongodb.factory']));
        $this->assertInstanceOf('\Pimple\Container', $app['mongodbs']);
        $this->assertInstanceOf('\MongoDB\Client', $app['mongodb']);
        $this->assertInstanceOf('\MongoDB\Client', $app['mongodbs']['default']);

        $app = new Container();
        $app->register(new MongoDBServiceProvider('db', 'dbs'));

        $this->assertTrue(is_array($app['db.default_options']));
        $this->assertTrue(is_callable($app['db.factory']));
        $this->assertInstanceOf('\Pimple\Container', $app['dbs']);
        $this->assertInstanceOf('\MongoDB\Client', $app['db']);
        $this->assertInstanceOf('\MongoDB\Client', $app['dbs']['default']);
    }

    public function testYouDidntForgotToBumpVersionNumber()
    {
        $this->assertEquals('1.1.0', MongoDBServiceProvider::VERSION);
    }

    public function getMultiConnectionOptions()
    {
        return [
            'first' => [
                'uri' => 'mongodb://first.com:27017',
            ],
            'second' => [
                'uri' => 'mongodb://second.com:27017',
            ],
            'third' => [
                'uri' => 'mongodb://third.com:27017',
            ],
        ];
    }

    public function getMultiConnectionWithDefaultOptions()
    {
        return [
            'first' => [
                'uri' => 'mongodb://first.com:27017',
            ],
            'default' => [
                'uri' => 'mongodb://second.com:27017',
            ],
            'third' => [
                'uri' => 'mongodb://third.com:27017',
            ],
        ];
    }
}
