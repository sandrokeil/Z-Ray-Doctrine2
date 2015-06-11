The Doctrine 2 plugin for Zend Server Z-Ray provides various information about the usage of Doctrine 2 in your application. Get a deeper look how entities and queries are used. Additional features are in the planning.

## Tab Entities
The `Entities` tab displays information about used entities of the current page. There are the following columns:

 * **Entity**: FCQN of the entity class
 * **Number Of Unique Entities**: How many unique objects of this entity exist.
 * **Number Of Referenced Entities**: How many references exist for this entity.

## Tab Queries
The `Queries` tab displays information about executed queries. There are the following columns:

 * **Query**: The executed query.
 * **Number**: The number of executions e.g. with different parameters.
 * **Cached**: The number of cached queries.

Note: real queries are calculated as `Number - Cached`.