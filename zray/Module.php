<?php
/**
 * Sake
 *
 * @link      http://github.com/sandrokeil/Z-Ray-Doctrine2 for the canonical source repository
 * @copyright Copyright (c) 2015 Sandro Keil
 * @license   http://github.com/sandrokeil/Z-Ray-Doctrine2/blob/master/LICENSE.txt New BSD License
 */
namespace Doctrine2;

class Module extends \ZRay\ZRayModule
{
    /**
     * Initialize extension
     *
     * @return array
     */
    public function config()
    {
        return array(
            'extension' => array(
                'name' => 'doctrine2'
            ),
            'defaultPanels' => array(
                'queries' => true,
                'entities' => true,
            ),
            'panels' => array(
                'entities' => array(
                    'display' => true,
                    'logo' => 'logo.png',
                    'menuTitle' => 'Entities',
                    'panelTitle' => 'Entities',
                    'searchId' => 'doctrine-entities-search',
                    'pagerId' => 'doctrine-entities-pager',
                ),
                'queries' => array(
                    'display' => true,
                    'logo' => 'logo.png',
                    'menuTitle' => 'Queries',
                    'panelTitle' => 'Queries',
                    'searchId' => 'doctrine-queries-search',
                    'pagerId' => 'doctrine-queries-pager',
                ),
            ),
        );
    }
}
