The Doctrine 2 plugin for Zend Server Z-Ray provides various information about the usage of Doctrine 2 in your
application. Get a deeper look how entities and entity mappings, queries with parameter, events and caches are used.

## Tab Entities
The `Entities` tab displays information about used entities of the current page and entity mappings.
There are the following columns:

 * **Entity**: FCQN of the entity class
 * **Number Of Unique Entities**: How many unique objects of this entity exist
 * **Number Of Referenced Entities**: How many references exist for this entity


## Tab Queries
The `Queries` tab displays information about executed queries.
There are the following columns:

 * **#**: Query number
 * **Query**: The executed query with parameter
 * **Number**: The number of executions
 * **Cached**: The number of cached queries

Note: real queries are calculated as `Number - Cached`.

### Tab Events
The `Events` tab displays information about Doctrine 2 events with `Symfony\Bridge\Doctrine` support.
There are the following columns:

 * **Event**: The event name
 * **Number**: The Number of occurrences
 * **Listeners**: Listener classes

### Tab Cache
The `Cache` tab displays information about Doctrine 2 caches and which cache driver is used.
There are the following columns:

 * **Type**: The cache type
 * **Status**: Which cache driver is used
