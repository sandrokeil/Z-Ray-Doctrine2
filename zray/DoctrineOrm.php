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
    const INDEX_ENTITIES_UNIQUE = 'unique_entities';
    const INDEX_ENTITIES_REFERENCE = 'referenced_entities';

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
     * Last query to set parameters from persister
     *
     * @var string
     */
    protected $lastQuery = '';

    /**
     * Cache settings
     */
    protected $cache = [
        'metadata' => [
            'name' => 'Metadata Cache',
            'status' => 'none used (check your cache settings)'
        ],
        'query' => [
            'name' => 'Query Cache',
            'status' => 'none used (check your cache settings)'
        ],
        'result' => [
            'name' => 'Result Cache',
            'status' => 'none used (check your cache settings)'
        ],
        'hydration' => [
            'name' => 'Hydration Cache',
            'status' => 'none used (check your cache settings)'
        ],
        'secondLevel' => [
            'name' => 'Second Level Cache',
            'status' => 'none used (check your cache settings)'
        ],
    ];

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
            || !$context['functionArgs'][0] instanceof Cache
        ) {
            return;
        }

        switch (get_class($context['functionArgs'][0])) {
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
                $status = 'unknown';
                break;
        }

        switch ($context['functionName']) {
            case 'Doctrine\ORM\Configuration::setMetadataCacheImpl':
                $this->cache['metadata']['status'] = $status;
                break;
            case 'Doctrine\ORM\Configuration::setSecondLevelCacheEnabled':
                $this->cache['query']['status'] = $status;
                break;
            case 'Doctrine\DBAL\Configuration::setResultCacheImpl':
                $this->cache['result']['status'] = $status;
                break;
            case 'Doctrine\ORM\Configuration::setQueryCacheImpl':
                $this->cache['secondLevel']['status'] = $status;
                break;
            case 'Doctrine\ORM\Configuration::setHydrationCacheImpl':
                $this->cache['hydration']['status'] = $status;
                break;
            default:
                break;
        }
    }

    /**
     * Collects queries from \Doctrine\ORM\Persisters\Entity\BasicEntityPersister
     *
     * @param array $context
     * @param array $storage
     */
    public function persister($context, &$storage)
    {
        // dont break z-ray
        if (empty($context['functionArgs'][0])
            || !$this->lastQuery
            || empty($context['returnValue'])
        ) {
            $this->lastQuery = '';
            return;
        }

        $params = array();

        foreach ($context['returnValue'] as &$values) {
            $params += $values;
        }

        $this->queries[$this->lastQuery]['query'] = $this->formatQueryWithSprintf(
            $this->queries[$this->lastQuery]['query'],
            $params
        );
        $this->lastQuery = '';
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


        if (!empty($context['functionArgs'][1])) {
            $queryWithParams = $this->formatQueryWithSprintf($queryWithParams, $context['functionArgs'][1]);
        }

        if (!isset($this->queries[$query])) {
            $this->queryNumber++;

            $this->queries[$query] = [
                'query' => $queryWithParams,
                'number' => 0,
                'cached' => 0,
                'queryNumber' => $this->queryNumber,
            ];
        }

        if ($context['functionName'] === 'Doctrine\DBAL\Connection::executeCacheQuery') {
            $this->queries[$query]['cached']++;
            return;
        }

        $this->queries[$query]['number']++;
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

        switch ($context['functionName']) {
            case 'Doctrine\DBAL\Connection::beginTransaction':
                $query = 'Begin Transaction';
                $this->queryNumber++;
                break;
            case 'Doctrine\DBAL\Connection::rollback':
                $query = 'Rollback';
                $this->queryNumber++;
                break;
            case 'Doctrine\DBAL\Connection::commit':
                $query = 'Commit';
                $this->queryNumber++;
                break;
            case 'Doctrine\DBAL\Statement::__construct':
                $query = $context['locals']['sql'];
                $this->lastQuery = $query;
                $this->queryNumber++;
                break;
            default:
                $query = '';
                break;
        }

        $query = trim($query);

        if ($query) {
            if (!isset($this->queries[$query])) {
                $this->queries[$query] = [
                    'query' => $query,
                    'number' => 1,
                    'cached' => 0,
                    'queryNumber' => $this->queryNumber,
                ];
            }
            return;
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
    private function formatQueryWithSprintf($query, array $params)
    {
        if (isset($params[0][0])) {
            foreach ($params as &$param) {
                $param = implode(',', $param);
            }
        }
        // convert params for query string
        foreach ($params as &$type) {
            if (null === $type) {
                $type = 'null';
                continue;
            }
            if (is_string($type)) {
                $type = "'" . $type . "'";
                continue;
            }
            if (!is_scalar($type)) {
                $type = gettype($type);
            }
        }
        array_unshift($params, str_replace('?', '%s', $query));
        return call_user_func_array('sprintf', $params);
    }

}
