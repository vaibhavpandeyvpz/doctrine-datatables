<?php

/*
 * This file is part of vaibhavpandeyvpz/doctrine-datatables package.
 *
 * (c) Vaibhav Pandey <contact@vaibhavpandey.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.md.
 */

namespace Doctrine\DataTables;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class Builder
 * @package Doctrine\DataTables
 */
class Builder
{
    /**
     * @var array
     */
    protected $columnAliases = array();

    /**
     * @var string
     */
    protected $indexColumn = '*';

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var array
     */
    protected $requestParams;

    /**
     * @return array
     */
    public function getData()
    {
        $query = $this->getFilteredQuery();
        $columns = &$this->requestParams['columns'];
        // Order
        if (array_key_exists('order', $this->requestParams)) {
            $order = &$this->requestParams['order'];
            foreach ($order as $sort) {
                $column = &$columns[intval($sort['column'])];
                if (array_key_exists($column['data'], $this->columnAliases)) {
                    $column['data'] = $this->columnAliases[$column['data']];
                }
                $query->addOrderBy($column['data'], $sort['dir']);
            }
        }
        // Offset
        if (array_key_exists('start', $this->requestParams)) {
            $query->setFirstResult(intval($this->requestParams['start']));
        }
        // Limit
        if (array_key_exists('length', $this->requestParams)) {
            $length = intval($this->requestParams['length']);
            if ($length > 0) {
                $query->setMaxResults($length);
            }
        }
        // Fetch
        return $query->execute()->fetchAll();
    }

    /**
     * @return QueryBuilder
     */
    public function getFilteredQuery()
    {
        $query = clone $this->queryBuilder;
        $columns = &$this->requestParams['columns'];
        // Search
        if (array_key_exists('search', $this->requestParams)) {
            if ($value = trim($this->requestParams['search']['value'])) {
                $orX = $query->expr()->orX();
                for ($i = 0; $i < count($columns); $i++) {
                    $column = &$columns[$i];
                    if ($column['searchable'] == 'true') {
                        if (array_key_exists($column['data'], $this->columnAliases)) {
                            $column['data'] = $this->columnAliases[$column['data']];
                        }
                        $orX->add($query->expr()->like($column['data'], ':search'));
                    }
                }
                if ($orX->count() >= 1) {
                    $query->andWhere($orX)
                        ->setParameter('search', "%{$value}%");
                }
            }
        }
        // Filter
        for ($i = 0; $i < count($columns); $i++) {
            $column = &$columns[$i];
            $andX = $query->expr()->andX();
            if (($column['searchable'] == 'true') && ($value = trim($column['search']['value']))) {
                if (array_key_exists($column['data'], $this->columnAliases)) {
                    $column['data'] = $this->columnAliases[$column['data']];
                }
                $andX->add($query->expr()->eq($column['data'], ":filter_{$i}"));
                $query->setParameter("filter_{$i}", $value);
            }
            if ($andX->count() >= 1) {
                $query->andWhere($andX);
            }
        }
        // Done
        return $query;
    }

    /**
     * @return int
     */
    public function getRecordsFiltered()
    {
        return $this->getFilteredQuery()
            ->resetQueryPart('select')
            ->select("count({$this->indexColumn})")
            ->execute()
            ->fetchColumn(0);
    }

    /**
     * @return int
     */
    public function getRecordsTotal()
    {
        $tmp = clone $this->queryBuilder;
        return $tmp->resetQueryPart('select')
            ->select("count({$this->indexColumn})")
            ->execute()
            ->fetchColumn(0);
    }

    /**
     * @return array
     */
    public function getResponse()
    {
        return array(
            'data' => $this->getData(),
            'draw' => $this->requestParams['draw'],
            'recordsFiltered' => $this->getRecordsFiltered(),
            'recordsTotal' => $this->getRecordsTotal(),
        );
    }

    /**
     * @param string $indexColumn
     * @return static
     */
    public function withIndexColumn($indexColumn)
    {
        $this->indexColumn = $indexColumn;
        return $this;
    }

    /**
     * @param array $columnAliases
     * @return static
     */
    public function withColumnAliases($columnAliases)
    {
        $this->columnAliases = $columnAliases;
        return $this;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return static
     */
    public function withQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
        return $this;
    }

    /**
     * @param array $requestParams
     * @return static
     */
    public function withRequestParams($requestParams)
    {
        $this->requestParams = $requestParams;
        return $this;
    }
}
