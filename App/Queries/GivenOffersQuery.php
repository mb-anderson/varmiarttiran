<?php

namespace App\Queries;

use App\Entity\Offer\Offer;
use App\Views\GivenOffersProductCard;
use CoreDB;
use CoreDB\Kernel\Database\SelectQueryPreparerAbstract;
use Src\Theme\ResultsViewer;

class GivenOffersQuery extends ProductsQuery
{
    public function getResultQuery(): SelectQueryPreparerAbstract
    {
        $currentUser = CoreDB::currentUser();
        $query = parent::getResultQuery();
        $query->join(Offer::getTableName(), "fp", "fp.product = products.ID")
        ->condition("fp.user", $currentUser->ID->getValue());
        return $query;
    }

    public function getPaginationLimit(): int
    {
        return 0;
    }

    public function getResultsViewer(): ResultsViewer
    {
        return new GivenOffersProductCard();
    }
}
