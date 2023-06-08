<?php

namespace App\Controller;

use App\Entity\Offer\Offer;
use App\Entity\Product\Product;
use App\Form\ProductSearchForm;
use App\Queries\FavoriteProductsQuery;
use App\Queries\GivenOffersQuery;
use App\Queries\PrivateProductsQuery;
use App\Queries\ReceivedOffersQuery;
use App\Theme\CustomTheme;
use Src\Entity\Translation;

class OffersController extends CustomTheme
{
    public ProductSearchForm $givenOfferListSearch;
    public ProductSearchForm $receivedOfferListSearch;

    public function getTemplateFile(): string
    {
        return "page-offers.twig";
    }

    public function preprocessPage()
    {
        $this->setTitle(Translation::getTranslation("offers"));
        $this->receivedOfferListSearch = ProductSearchForm::createByObject(
            ReceivedOffersQuery::getInstance()
        );
        $this->receivedOfferListSearch->addClass("p-3");
        unset($this->receivedOfferListSearch->pagination);

        $this->givenOfferListSearch = ProductSearchForm::createByObject(
            GivenOffersQuery::getInstance()
        );
        unset($this->givenOfferListSearch->pagination);
        $this->givenOfferListSearch->addClass("p-3");
    }

    public function echoContent()
    {
        return $this->givenOfferListSearch;
    }
}
