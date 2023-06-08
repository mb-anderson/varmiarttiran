<?php

namespace App\Queries;

use App\Entity\Offer\Offer;
use App\Entity\Product\FavoriteProducts;
use App\Entity\Product\Product;
use App\Views\FavoriteProductTeaserCard;
use App\Views\ReceivedOffersProductCard;
use CoreDB;
use CoreDB\Kernel\Database\SelectQueryPreparerAbstract;
use Src\Theme\ResultsViewer;

class ReceivedOffersQuery extends ProductsQuery
{
    public function getResultQuery(): SelectQueryPreparerAbstract
    {
        $currentUser = CoreDB::currentUser();
        $query = parent::getResultQuery();
        $query->join(Offer::getTableName(), "fp", "fp.product = products.ID")
        ->condition("fp.product", Product::get(["user" => $currentUser->ID])->ID->getValue());
        return $query;
    }

    public function getPaginationLimit(): int
    {
        return 0;
    }

    public function getResultsViewer(): ResultsViewer
    {
        return new ReceivedOffersProductCard();
    }
}
