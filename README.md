# vaibhavpandeyvpz/doctrine-datatables
Helper library to implement [Doctrine](http://www.doctrine-project.org/) powered server-side processing for [jquery-datatables](https://github.com/DataTables/DataTables) with joins, search, filtering and ordering.

[![Latest Version](https://img.shields.io/github/release/vaibhavpandeyvpz/doctrine-datatables.svg?style=flat-square)](https://github.com/vaibhavpandeyvpz/doctrine-datatables/releases) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/vaibhavpandeyvpz/doctrine-datatables/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/vaibhavpandeyvpz/doctrine-datatables/?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/4ac1aa6a-a495-49e0-b5d6-d7b82be2a5f6/mini.png)](https://insight.sensiolabs.com/projects/4ac1aa6a-a495-49e0-b5d6-d7b82be2a5f6) [![Total Downloads](https://img.shields.io/packagist/dt/vaibhavpandeyvpz/doctrine-datatables.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/doctrine-datatables) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md) 

Install
-------
```bash
composer require vaibhavpandeyvpz/doctrine-datatables
```

Basic Usage
-----
```php
<?php

use Doctrine\DataTables;

$connection = /** instanceof Doctrine\DBAL\Connection */;

$datatables = (new DataTables\Builder())
    ->withIndexColumn('id')
    ->withQueryBuilder(
        $connection->createQueryBuilder()
            ->select('*')
            ->from('users')
    )
    ->withRequestParams($_GET);

echo json_encode($datatables->getData());
```

Advanced Usage (Joins)
-----
```php
<?php

use Doctrine\DataTables;

$connection = /** instanceof Doctrine\DBAL\Connection */;

$datatables = (new DataTables\Builder())
    ->withColumnAliases([
        'id' => 'u.id',
        'created_at' => 'u.created_at',
        'updated_at' => 'u.updated_at',
    ])
    ->withIndexColumn('id')
    ->withQueryBuilder(
        $connection->createQueryBuilder()
            ->select('u.*', 'p.first_name', 'p.last_name')
            ->from('users', 'u')
            ->leftJoin('u', 'profiles', 'p', 'p.user_id = u.id')
    )
    ->withRequestParams($_GET);

echo json_encode($datatables->getData());
```

License
------
See [LICENSE.md](https://github.com/vaibhavpandeyvpz/doctrine-datatables/blob/master/LICENSE.md) file.
