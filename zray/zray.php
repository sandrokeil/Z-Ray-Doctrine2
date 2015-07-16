<?php
/**
 * Sake
 *
 * @link      http://github.com/sandrokeil/Z-Ray-Doctrine2 for the canonical source repository
 * @copyright Copyright (c) 2015 Sandro Keil
 * @license   http://github.com/sandrokeil/Z-Ray-Doctrine2/blob/master/LICENSE.txt New BSD License
 */

namespace Sake\ZRayDoctrine2;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'DoctrineOrm.php';

// Allocate ZRayExtension for namespace "doctrine2"
$zre = new \ZRayExtension('doctrine2');

$zre->setMetadata(array(
    'logo' => __DIR__ . DIRECTORY_SEPARATOR . 'logo.png',
));

// needed to get first query, connect() is to late
$zre->setEnabledAfter('Doctrine\DBAL\Connection::__construct');

$doctrine = new DoctrineOrm();

// Doctrine\DBAL
$zre->traceFunction('Doctrine\DBAL\Connection::executeUpdate', function(){}, array($doctrine, 'queries'));
$zre->traceFunction('Doctrine\DBAL\Connection::executeQuery', function(){}, array($doctrine, 'queries'));
$zre->traceFunction('Doctrine\DBAL\Connection::executeCacheQuery', function(){}, array($doctrine, 'queries'));

// Doctrine\ORM
$zre->traceFunction('Doctrine\ORM\UnitOfWork::createEntity', function(){}, array($doctrine, 'unitOfWork'));

// Z-Ray Doctrine extension
$zre->traceFunction('Sake\ZRayDoctrine2\DoctrineOrm::shutdown', function(){}, array($doctrine, 'collectAllData'));

// own shutdown function to collect all data
register_shutdown_function(array($doctrine, 'shutdown'));
