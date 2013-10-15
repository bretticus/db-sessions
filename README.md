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
