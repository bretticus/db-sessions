db-sessions
===========

PHP Database Session Classes

Here are some PHP Session database classes. 
I decided to go with PDO. This is a work 
in progress.

Installation
----------------------------

First you need to create a table in your database:

    CREATE TABLE `session_handler` (
    `id` varchar(255) NOT NULL,
    `data` mediumtext NOT NULL,
    `timestamp` int(255) NOT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

Classes
-----------------------------

session_handler.php -- is used as a base class. It interfaces with 
session_handler_interface.php which is identical to 
PHP 5.4+ SessionHandlerInterface.

session_handler_encrypted.php -- encrypts the session data before 
it is written to the database.

session_handler_memcache.php -- Uses PHP memcache for subsequent reads. 
Writing must happen in the database each time for concurrency. 
Currently I'm using Repcache but the advantage here is that 
garbage collection will not clean up sessions in the database 
if they are being renewed (written to with a new timestamp.) 
I'm using repcached, but in a memcached-only cluster, this 
class will use the database to get a missing session and 
store it in memory. So TTL in memcached expiring will not 
expire the session if it's actively being used. Furthermore, 
memory replication isn't required.

session_handler_memcached.php -- Currently, PHP memcached 
(note the d at the end) was not available for my production 
stack so I did not complete or test this version.