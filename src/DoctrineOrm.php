<?php
/**
 * Sake
 *
 * @link      http://github.com/sandrokeil/Z-Ray-Doctrine2 for the canonical source repository
 * @copyright Copyright (c) 2015 Sandro Keil
 * @license   http://github.com/sandrokeil/Z-Ray-Doctrine2/blob/master/LICENSE.txt New BSD License
 */

namespace Sake\ZRayDoctrine2;

/**
 * Class DoctrineOrm
 *
 * Collects information about Doctrine ORM and DBAL.
 */
class DoctrineOrm
{
    const INDEX_ENTITIES_UNIQUE = 'number of unique entities';
    const INDEX_ENTITIES_REFERENCE = 'number of referenced entities';

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
     * Collects all queries from Doctrine\DBAL\Connection
     *
     * @param array $context
     * @param array $storage
     */
    public function queries($context, &$storage)
    {
        $query = $context['functionArgs'][0];

        if (!isset($this->queries[$query])) {
            $this->queries[$query] = [
                'query' => $query,
                'number' => 0,
                'cached' => 0,
            ];
        }

        if ($context['functionName'] == 'Doctrine\DBAL\Connection::executeCacheQuery') {
            $this->queries[$query]['cached']++;
        } else {
            $this->queries[$query]['number']++;
        }
    }

    /**
     * Collects entity usage from unit of work.
     *
     * @param array $context
     * @param array $storage
     */
    public function entityMappings($context, &$storage)
    {
        $class = $context['functionArgs'][0];
        $hash = spl_object_hash($context['returnValue']);

        if (!isset($this->entities[$hash])) {
            $this->entities[$hash] = ['class' => $class, 'number' => 1, 'ref' => 0];
        } else {
            $this->entities[$hash]['ref']++;
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
                    self::INDEX_ENTITIES_UNIQUE => 1,
                    self::INDEX_ENTITIES_REFERENCE => $data['ref'],
                ];
            } else {
                $storage['entities'][$data['class']][self::INDEX_ENTITIES_UNIQUE]++;
                $storage['entities'][$data['class']][self::INDEX_ENTITIES_REFERENCE] += $data['ref'];
            }
        }
        asort($storage['entities']);

        // collect queries
        foreach ($this->queries as $query => $data) {
            $storage['queries'][$query] = $data;
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
}
