<?php

namespace App\Queries;

use CoreDB\Kernel\Database\QueryCondition;
use CoreDB\Kernel\Database\SelectQueryPreparerAbstract;

class ResendOrdersQuery extends OrdersQuery
{
    public function getResultQuery(): SelectQueryPreparerAbstract
    {
        $query = parent::getResultQuery();
        $queryCondition = new QueryCondition($query);

        return $query;
    }
}
