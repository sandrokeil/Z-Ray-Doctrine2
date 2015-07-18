<?php
/**
 * Sake
 *
 * @link      http://github.com/sandrokeil/Z-Ray-Doctrine2 for the canonical source repository
 * @copyright Copyright (c) 2015 Sandro Keil
 * @license   http://github.com/sandrokeil/Z-Ray-Doctrine2/blob/master/LICENSE.txt New BSD License
 */

namespace Sake\ZRayDoctrine2;

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
     * Collects all queries from Doctrine\DBAL\Connection
     *
     * @param array $context
     * @param array $storage
     */
    public function queries($context, &$storage)
    {
        // dont break z-ray
        if (empty($context['functionArgs'][0])) {
            return;
        }
        $query = $context['functionArgs'][0];
        $params = '';

        if (!empty($context['functionArgs'][1])) {
            $params = print_r($context['functionArgs'][1], true);
        }

        if (!isset($this->queries[$query])) {
            $this->queries[$query] = [
                'query' => $query,
                'number' => 0,
                'cached' => 0,
                'params' => $params,
            ];
        }

        if ($context['functionName'] == 'Doctrine\DBAL\Connection::executeCacheQuery') {
            $this->queries[$query]['cached']++;
            return;
        }
        if (!empty($context['functionArgs'][1])
            && $this->queries[$query]['cached'] === 0
        ) {
            if (!empty($this->queries[$query]['params'])) {
                $this->queries[$query]['params'] .= '<hr/>';
            }
            $this->queries[$query]['params'] .= $params;
        }

        $this->queries[$query]['number']++;
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
