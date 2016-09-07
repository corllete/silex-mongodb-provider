<?php
/**
 * This file is part of the Silex MongoDB Service Provider package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Corllete\SilexMongoDB\Provider;

use MongoDB\Client;
use Pimple\ServiceProviderInterface;
use Pimple\Container;

/**
 * Integrate MongoDB via MongoDB PHP Library as Silex services
 *
 * @link   https://github.com/mongodb/mongo-php-library
 *
 * @author Miroslav Yovchev <m.yovchev@corllete.com>
 */
class MongoDBServiceProvider implements ServiceProviderInterface
{
    private $namespace;
    private $multiNamespace;

    /**
     * Client options documentation:
     *
     * @link http://mongodb.github.io/mongo-php-library/classes/client/
     *
     * Connection string
     * @link https://docs.mongodb.com/manual/reference/connection-string/
     *
     * Default 'typeMap' driver options
     * @link https://github.com/mongodb/mongo-php-library/blob/master/src/Client.php#L14-L18
     */
    public static $defaultOptions = [
        'uri'            => 'mongodb://localhost:27017',
        'host'           => null,
        'database'       => null,
        'username'       => null,
        'password'       => null,
        'uri_options'    => [],
        'driver_options' => [
            'type_map' => [],
        ],
    ];

    /**
     * Namespace for single and multi-connection mode
     *
     * @param string $namespace
     * @param string $multiNamespace
     * @throws \InvalidArgumentException
     */
    public function __construct($namespace = 'mongodb', $multiNamespace = 'mongodbs')
    {
        if (empty($namespace)) {
            throw new \InvalidArgumentException("Namespace can not be empty");
        }

        if (empty($multiNamespace)) {
            throw new \InvalidArgumentException("Namespace for connections collection can not be empty");
        }

        $this->namespace      = $namespace;
        $this->multiNamespace = $multiNamespace;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $app)
    {
        $ns  = $this->namespace;
        $mns = $this->multiNamespace;

        $app[$ns.'.default_options'] = isset($app[$ns.'.default_options']) ? $app[$ns.'.default_options'] : [];
        $app[$ns.'.default_options'] += self::$defaultOptions;

        $app[$ns.'.factory'] = $app->protect(
            function (array $options = []) use ($app, $ns) {
                $options += $app[$ns.'.default_options'];
                $options += self::$defaultOptions;

                if (empty($options['uri']) && !empty($options['host'])) {

                    $options['uri'] = $this->assembleUri(
                        $options['host'],
                        $options['port'],
                        $options['database'],
                        $options['username'],
                        $options['password']
                    );
                }

                return new Client($options['uri'], $options['uri_options'], $options['driver_options']);
            }
        );

        $app[$mns.'.options.init'] = $app->protect(
            function () use ($app, $ns, $mns) {
                static $done = false;

                if ($done) {
                    return;
                }
                $done = true;

                if (isset($app[$ns.'.options']) && isset($app[$mns.'.options'])) {
                    throw new \LogicException("Illegal configuration - choose either single or multi connection setup", 1);
                    
                }

                if (!isset($app[$mns.'.options'])) {
                    $singleOptions = isset($app[$ns.'.options']) ? $app[$ns.'.options'] : [];
                    $singleOptions += $app[$ns.'.default_options'];

                    $app[$mns.'.options'] = [
                        'default' => $singleOptions,
                    ];
                }

                if (isset($app[$mns.'.options']['default'])) {
                    $app[$mns.'.default'] = 'default';
                } elseif (!isset($app[$mns.'.default'])) {
                    $tmp = $app[$mns.'.options'];
                    reset($tmp);
                    $app[$mns.'.default'] = key($tmp);
                }
            }
        );

        $app[$mns] = function ($app) use ($ns, $mns) {
            $app[$mns.'.options.init']();

            $mongos = new Container();
            foreach ($app[$mns.'.options'] as $name => $options) {
                $mongos[$name] = function () use ($app, $options, $ns) {
                    return $app[$ns.'.factory']($options);
                };
            }

            if (!isset($mongos['default'])) {
                $mongos['default'] = $mongos[$app[$mns.'.default']];
            }

            return $mongos;
        };

        $app[$ns] = function ($app) use ($mns) {
            $dbs = $app[$mns];

            return $dbs[$app[$mns.'.default']];
        };
    }

    /**
     * @link http://php.net/manual/en/mongodb-driver-manager.construct.php
     *
     * @param string      $host
     * @param string      $port
     * @param string|null $db
     * @param string|null $username
     * @param string|null $password
     * @return string
     */
    private function assembleUri($host, $port, $db = null, $username = null, $password = null)
    {
        if (!empty($db)) {
            $db = '/'.$db;
        }

        $cred = '';
        if ($username !== null && $password !== null) {
            $cred = sprintf('%s:%s@', rawurlencode($username), rawurlencode($password));
        }

        return sprintf('mongodb://%s%s:%s%s', $cred, $host, $port, $db.'');
    }
}
