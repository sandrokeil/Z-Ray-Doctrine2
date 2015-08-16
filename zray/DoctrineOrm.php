<?php
/**
 * Sake
 *
 * @link      http://github.com/sandrokeil/Z-Ray-Doctrine2 for the canonical source repository
 * @copyright Copyright (c) 2015 Sandro Keil
 * @license   http://github.com/sandrokeil/Z-Ray-Doctrine2/blob/master/LICENSE.txt New BSD License
 */

namespace Sake\ZRayDoctrine2;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class DoctrineOrm
 *
 * Collects information about Doctrine ORM and DBAL.
 */
class DoctrineOrm
{
    /**
     * Entities
     *
     * @var array
     */
    protected $entities = [];

    /**
     * Queries
     *
     * @var array
     */
    protected $queries = [];

    /**
     * Query number
     *
     * @var int
     */
    protected $queryNumber = 0;

    /**
     * Cache settings
     */
    protected $cache = [
        'metadata' => [
            'name' => 'Metadata Cache',
            'status' => 'none used'
        ],
        'query' => [
            'name' => 'Query Cache',
            'status' => 'none used'
        ],
        'result' => [
            'name' => 'Result Cache',
            'status' => 'none used'
        ],
        'hydration' => [
            'name' => 'Hydration Cache',
            'status' => 'none used'
        ],
        'secondLevel' => [
            'name' => 'Second Level Cache',
            'status' => 'none used'
        ],
    ];

    /**
     * Events
     */
    protected $events = [];

    /**
     * Collects queries from \Doctrine\ORM\Persisters\Entity\BasicEntityPersister
     *
     * @param array $context
     * @param array $storage
     */
    public function cache($context, &$storage)
    {
        // dont break z-ray
        if (empty($context['functionArgs'][0])
            || (!$context['functionArgs'][0] instanceof Cache && !is_object($context['returnValue']))
        ) {
            return;
        }

        if ($context['functionArgs'][0] instanceof Cache) {
            $class = get_class($context['functionArgs'][0]);
        } else {
            $class = get_class($context['returnValue']);
        }

        switch ($class) {
            case 'Doctrine\Common\Cache\PhpFileCache':
                $status = 'File cache used';
                break;
            case 'Doctrine\Common\Cache\FilesystemCache':
                $status = 'File cache used';
                break;
            case '\Doctrine\Common\Cache\ApcCache':
                $status = 'APC cache used';
                break;
            case 'Doctrine\Common\Cache\ArrayCache':
                $status = 'Array cache used';
                break;
            case 'Doctrine\Common\Cache\MemcacheCache':
                $status = 'Memcache cache used';
                break;
            case 'Doctrine\Common\Cache\MemcachedCache':
                $status = 'Memcached cache used';
                break;
            case 'Doctrine\Common\Cache\MongoDBCache':
                $status = 'MongoDB cache used';
                break;
            case 'Doctrine\Common\Cache\PredisCache':
                $status = 'Predis cache used';
                break;
            case 'Doctrine\Common\Cache\RedisCache':
                $status = 'Redis cache used';
                break;
            case 'Doctrine\Common\Cache\RiakCache':
                $status = 'Riak cache used';
                break;
            case 'Doctrine\Common\Cache\SQLite3Cache':
                $status = 'SQLite3 cache used';
                break;
            case 'Doctrine\Common\Cache\VoidCache':
                $status = 'Void cache used';
                break;
            case 'Doctrine\Common\Cache\WinCacheCache':
                $status = 'Win Cache used';
                break;
            case 'Doctrine\Common\Cache\XcacheCache':
                $status = 'Xcache used';
                break;
            case 'Doctrine\Common\Cache\ZendDataCache':
                $status = 'Zend Data cache used';
                break;
            default:
                $status = $class . ' used';
                break;
        }

        switch ($context['functionName']) {
            case 'Doctrine\ORM\Configuration::setMetadataCacheImpl':
                $this->cache['metadata']['status'] = $status;
                break;
            case 'Doctrine\ORM\Configuration::setSecondLevelCacheEnabled':
                $this->cache['secondLevel']['status'] = $status;
                break;
            case 'Doctrine\DBAL\Configuration::setResultCacheImpl':
                $this->cache['result']['status'] = $status;
                break;
            case 'Doctrine\ORM\Configuration::setQueryCacheImpl':
                $this->cache['query']['status'] = $status;
                break;
            case 'Doctrine\ORM\Configuration::setHydrationCacheImpl':
                $this->cache['hydration']['status'] = $status;
                break;
            default:
                break;
        }
    }

    /**
     * Collects all queries from Doctrine\DBAL\Connection
     *
     * @param array $context
     * @param array $storage
     */
    public function connection($context, &$storage)
    {
        // dont break z-ray
        if (empty($context['functionArgs'][0])) {
            return;
        }
        $query = trim($context['functionArgs'][0]);
        $queryWithParams = $query;

        // parameters detected
        if (!empty($context['functionArgs'][1])) {
            # doctrine does it right
            list($queryWithParams, $params, $types) = \Doctrine\DBAL\SQLParserUtils::expandListParameters(
                $query,
                $context['functionArgs'][1],
                empty($context['functionArgs'][2]) ? [] : $context['functionArgs'][2]
            );
            $queryWithParams = $this->formatQuery($queryWithParams, $params);
        }

        $queryId = $this->getQueryId($queryWithParams);

        if (!isset($this->queries[$queryId])) {
            $this->queryNumber++;

            $this->queries[$queryId] = [
                'query' => $queryWithParams,
                'number' => 0,
                'cached' => 0,
                'queryNumber' => $this->queryNumber,
            ];
        }

        if ($context['functionName'] === 'Doctrine\DBAL\Connection::executeCacheQuery') {
            $this->queries[$queryId]['cached']++;
            return;
        }

        $this->queries[$queryId]['number']++;
    }

    /**
     * Collects all queries from Doctrine\DBAL\Statement and SQL transaction calls
     *
     * @param array $context
     * @param array $storage
     */
    public function statement($context, &$storage)
    {
        // dont break z-ray
        if (!$context['this'] instanceof Statement
            && !$context['this'] instanceof Connection
        ) {
            return;
        }

        $query = '';
        $queryId = '';

        switch ($context['functionName']) {
            case 'Doctrine\DBAL\Connection::beginTransaction':
                $query = 'Begin Transaction';
                $this->queryNumber++;
                $queryId = $query . $this->queryNumber;
                break;
            case 'Doctrine\DBAL\Connection::rollback':
                $query = 'Rollback';
                $this->queryNumber++;
                $queryId = $query . $this->queryNumber;
                break;
            case 'Doctrine\DBAL\Connection::commit':
                $query = 'Commit';
                $this->queryNumber++;
                $queryId = $query . $this->queryNumber;
                break;
            case 'Doctrine\DBAL\Statement::execute':
                $reflection = new \ReflectionObject($context['this']);

                if (!$reflection->hasProperty('params')
                    || !$reflection->hasProperty('types')
                    || !$reflection->hasProperty('sql')
                ) {
                    break;
                }

                if (empty($context['functionArgs'][0])) {
                    $params = $reflection->getProperty('params');
                    $params->setAccessible(true);
                    $params = $params->getValue($context['this']);
                } else {
                    $params = $context['functionArgs'][0];
                }

                $types = $reflection->getProperty('types');
                $types->setAccessible(true);
                $types = $types->getValue($context['this']);

                $query = $reflection->getProperty('sql');
                $query->setAccessible(true);
                $query = $query->getValue($context['this']);

                # doctrine does it right
                list($queryWithParams, $params, $types) = \Doctrine\DBAL\SQLParserUtils::expandListParameters(
                    $query,
                    $params,
                    $types
                );

                $query = $this->formatQuery($queryWithParams, $params);
                $this->queryNumber++;
                $queryId = $query . $this->queryNumber;
                break;
            default:
                // nothing to do
                break;
        }

        if (empty($query)) {
            return;
        }

        $queryId = $this->getQueryId($queryId);

        if (!isset($this->queries[$queryId])) {
            $this->queries[$queryId] = [
                'query' => $query,
                'number' => 1,
                'cached' => 0,
                'queryNumber' => $this->queryNumber,
            ];
        }
    }

    /**
     * Collects entity usage from unit of work.
     *
     * @param array $context
     * @param array $storage
     */
    public function unitOfWork($context, &$storage)
    {
        // dont break z-ray
        if (empty($context['functionArgs'][0])
            || empty($context['returnValue'])
        ) {
            return;
        }

        $class = $context['functionArgs'][0];
        $hash = spl_object_hash($context['returnValue']);

        if (!isset($this->entities[$hash])) {
            $this->entities[$hash] = ['class' => $class, 'unique' => 1, 'ref' => 0];
        } else {
            $this->entities[$hash]['ref']++;
        }
    }

    /**
     * Collects entity usage from unit of work.
     *
     * @param array $context
     * @param array $storage
     */
    public function entityMapping($context, &$storage)
    {
        // dont break z-ray
        if (empty($context['functionArgs'][0])
            || !($context['functionArgs'][0] instanceof EntityManager)
        ) {
            return;
        }
        /* @var $em EntityManager */
        $em = $context['functionArgs'][0];

        $allMetadata = $em->getMetadataFactory()->getAllMetadata();

        if (empty($allMetadata)) {
            return;
        }
        /* @var $metadata ClassMetadata */
        foreach ($allMetadata as $metadata) {
            $this->entities[] = [
                'class' => $metadata->getName(),
                'unique' => 0,
                'ref' => 0,
            ];
        }
    }

    /**
     * Collects events from Doctrine EventManager
     *
     * @param array $context
     * @param array $storage
     */
    public function eventManagerDispatch($context, &$storage)
    {
        // dont break z-ray
        if (empty($context['functionArgs'][0])) {
            return;
        }

        $eventName = $context['functionArgs'][0];

        if (empty($this->events[$eventName])) {
            $this->events[$eventName] = [
                'name' => $eventName,
                'number' => 0,
                'listeners' => [],
            ];
        }

        $this->events[$eventName]['number']++;

        if (empty($context['locals']['listener'])) {
            return;
        }
        $listener = get_class($context['locals']['listener']);
        $this->events[$eventName]['listeners'][$listener] = true;
    }

    /**
     * Collects listener from Doctrine EventManager
     *
     * @param array $context
     * @param array $storage
     */
    public function eventManagerAddListener($context, &$storage)
    {
        // dont break z-ray
        if (empty($context['functionArgs'][0])
            || empty($context['functionArgs'][1])
        ) {
            return;
        }

        $events = (array) $context['functionArgs'][0];

        $listener = $context['functionArgs'][1];

        if (is_object($listener)) {
            $listener = get_class($listener);
        }

        foreach ($events as $event) {
            if (empty($this->events[$event])) {
                $this->events[$event] = [
                    'name' => $event,
                    'number' => 0,
                    'listeners' => [$listener => true],
                ];
            } else {
                $this->events[$event]['listeners'][$listener] = true;
            }
        }
    }

    /**
     * Collects all data from other functions to display information in Z-Ray
     *
     * @param array $context
     * @param array $storage
     */
    public function collectAllData($context, &$storage)
    {
        // collect entities
        foreach ($this->entities as $data) {
            if (!isset($storage['entities'][$data['class']])) {
                $storage['entities'][$data['class']] = [
                    'entity' => $data['class'],
                    'unique_entities' => $data['unique'],
                    'referenced_entities' => $data['ref'],
                ];
                continue;
            }
            $storage['entities'][$data['class']]['unique_entities'] += $data['unique'];
            $storage['entities'][$data['class']]['referenced_entities'] += $data['ref'];
        }

        if (!empty($storage['entities'])) {
            asort($storage['entities']);
        }

        // collect queries
        foreach ($this->queries as $query => $data) {
            $storage['queries'][$query] = $data;
        }

        // collect cache
        $storage['cache'] = $this->cache;

        $events = [
            'onClear',
            'onFlush',
            'postFlush',
            'postLoad',
            'postPersist',
            'postRemove',
            'postUpdate',
            'preFlush',
            'prePersist',
            'preRemove',
            'preUpdate',
            'loadClassMetadata',
            'onClassMetadataNotFound',
        ];

        foreach ($events as $event) {
            $storage['events'][$event] = [
                'name' => $event,
                'number' => 0,
                'listeners' => '',
            ];
        }

        foreach ($this->events as $event => $data) {
            $storage['events'][$event] = [
                'name' => $data['name'],
                'number' => $data['number'],
                'listeners' => implode("\n", array_keys($data['listeners'])),
            ];
        }
    }

    /**
     * Only for listen to the php shutdown event to collect all data
     *
     * @see \Sake\ZRayDoctrine2\DoctrineOrm::collectAllData
     */
    public function shutdown()
    {
    }

    /**
     * Format query with params
     *
     * @param string $query
     * @param array $params
     * @return string
     */
    private function formatQuery($query, array $params)
    {
        // convert params for query string
        foreach ($params as $key => $type) {
            $query = preg_replace('/\?/', $this->formatType($type), $query, 1);
        }
        return $query;
    }

    /**
     * Returns formatted type for query
     *
     * @param mixed $type Param type
     * @return string Converted value for query
     */
    private function formatType($type)
    {
        if (null === $type) {
            return 'null';
        }
        if (is_string($type)) {
            return "'" . $type . "'";
        }
        if (is_object($type)) {
            if (method_exists($type, '__toString')) {
                return "'" . ((string)$type) . "'";
            }
            if ($type instanceof \DateTime) {
                # driver independent
                return "'" . $type->format('Y-m-d H:i:s') . "'";
            }
            return get_class($type);
        }

        if (is_array($type)) {
            return implode(',', array_map([$this, 'formatType'], $type));
        }

        if (!is_scalar($type)) {
            return gettype($type);
        }
        return $type;
    }

    /**
     * Returns a unique query id
     *
     * @param string $query
     * @return string Unique id
     */
    private function getQueryId($query)
    {
        return md5($query);
    }
}
