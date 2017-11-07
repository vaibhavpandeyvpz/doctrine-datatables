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
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;

/**
 * Class Builder
 * @package Doctrine\DataTables
 */
class Builder {

    /**
     * @var array
     */
    protected $columnAliases = array();

    /**
     * @var string
     */
    protected $columnField = 'data'; // or 'name'

    /**
     * @var string
     */
    protected $indexColumn = '*';

    /**
     * @var QueryBuilder|ORMQueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var array
     */
    protected $requestParams;
    
    /**
     * @var string
     */
    protected $searchDelimiter = false;
    

    /**
     * @return array
     */
    public function getData() {
        $query = $this->getFilteredQuery();
        $columns = &$this->requestParams['columns'];
        // Order
        if (array_key_exists('order', $this->requestParams)) {
            $order = &$this->requestParams['order'];
            foreach ($order as $sort) {
                $column = &$columns[intval($sort['column'])];
                if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                    $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                }
                $query->addOrderBy($column[$this->columnField], $sort['dir']);
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
        return $query instanceof ORMQueryBuilder ?
                $query->getQuery()->getScalarResult() : $query->execute()->fetchAll();
    }

    /**
     * @return QueryBuilder|ORMQueryBuilder
     */
    public function getFilteredQuery() {
        $query = clone $this->queryBuilder;
        $columns = &$this->requestParams['columns'];
        $c = count($columns);
        // Search
        if (array_key_exists('search', $this->requestParams)) {
            if ($value = trim($this->requestParams['search']['value'])) {
                
                if ($this->searchDelimiter !== false && strpos($value, $this->searchDelimiter) !== false) {
                    $index = 0;
                    $parts = explode($this->searchDelimiter, $value);
                    $partsAndX = $query->expr()->andX();
                    foreach ($parts as $part) {

                        $orX = $query->expr()->orX();
                        for ($i = 0; $i < $c; $i++) {
                            $column = &$columns[$i];
                            if ($column['searchable'] == 'true') {
                                if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                                    $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                                }
                                $orX->add($query->expr()->like($column[$this->columnField], ':search'.$index));
                            }
                        }
                        
                        $partsAndX->add($orX);
                        $query->setParameter('search'.$index, "%{$part}%");
                        $index++;
                    }
                    if ($partsAndX->count() >= 1) {
                        $query->andWhere($partsAndX);
                    }
                    
                } else {
                    $orX = $query->expr()->orX();
                    for ($i = 0; $i < $c; $i++) {
                        $column = &$columns[$i];
                        if ($column['searchable'] == 'true') {
                            if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                                $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                            }
                            $orX->add($query->expr()->like($column[$this->columnField], ':search'));
                        }
                    }
                    if ($orX->count() >= 1) {
                        $query->andWhere($orX)
                                ->setParameter('search', "%{$value}%");
                    }
                }
            }
        }
        // Filter
        
        for ($i = 0; $i < $c; $i++) {
            $column = &$columns[$i];
            $andX = $query->expr()->andX();
            if (($column['searchable'] == 'true') && ($value = trim($column['search']['value']))) {
                if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                    $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                }
                $operator = preg_match('~^\[(?<operator>[=!%<>]+)\].*$~', $value, $matches) ? $matches['operator'] : '=';
                switch ($operator) {
                    case '!=': // Not equals; usage: [!=]search_term
                        $andX->add($query->expr()->neq($column[$this->columnField], ":filter_{$i}"));
                        break;
                    case '%': // Like; usage: [%]search_term
                        $andX->add($query->expr()->like($column[$this->columnField], ":filter_{$i}"));
                        $value = "%{$value}%";
                        break;
                    case '<': // Less than; usage: [>]search_term
                        $andX->add($query->expr()->lt($column[$this->columnField], ":filter_{$i}"));
                        break;
                    case '>': // Greater than; usage: [<]search_term
                        $andX->add($query->expr()->gt($column[$this->columnField], ":filter_{$i}"));
                        break;
                    case '=': // Equals (default); usage: [=]search_term
                    default:
                        $andX->add($query->expr()->eq($column[$this->columnField], ":filter_{$i}"));
                        break;
                }
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
    public function getRecordsFiltered() {
        $query = $this->getFilteredQuery();
        if ($query instanceof ORMQueryBuilder) {
            return $query->resetDQLPart('select')
                            ->select("COUNT({$this->indexColumn})")
                            ->getQuery()
                            ->getSingleScalarResult();
        } else {
            return $query->resetQueryPart('select')
                            ->select("COUNT({$this->indexColumn})")
                            ->execute()
                            ->fetchColumn(0);
        }
    }

    /**
     * @return int
     */
    public function getRecordsTotal() {
        $query = clone $this->queryBuilder;
        if ($query instanceof ORMQueryBuilder) {
            return $query->resetDQLPart('select')
                            ->select("COUNT({$this->indexColumn})")
                            ->getQuery()
                            ->getSingleScalarResult();
        } else {
            return $query->resetQueryPart('select')
                            ->select("COUNT({$this->indexColumn})")
                            ->execute()
                            ->fetchColumn(0);
        }
    }

    /**
     * @return array
     */
    public function getResponse() {
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
    public function withIndexColumn($indexColumn) {
        $this->indexColumn = $indexColumn;
        return $this;
    }

    /**
     * @param array $columnAliases
     * @return static
     */
    public function withColumnAliases($columnAliases) {
        $this->columnAliases = $columnAliases;
        return $this;
    }

    /**
     * @param string $columnField
     * @return static
     */
    public function withColumnField($columnField) {
        $this->columnField = $columnField;
        return $this;
    }

    /**
     * @param QueryBuilder|ORMQueryBuilder $queryBuilder
     * @return static
     */
    public function withQueryBuilder($queryBuilder) {
        $this->queryBuilder = $queryBuilder;
        return $this;
    }

    /**
     * @param array $requestParams
     * @return static
     */
    public function withRequestParams($requestParams) {
        $this->requestParams = $requestParams;
        return $this;
    }
    
    /**
     * @param string $delimiter
     * @return static
     */
    public function withSearchDelimiter($delimiter) {
        $this->searchDelimiter = $delimiter;
        return $this;
    }

}
