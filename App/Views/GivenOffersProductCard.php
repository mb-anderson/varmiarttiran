<?php

namespace App\Views;

use App\Entity\CustomUser;

class GivenOffersProductCard extends ProductTeaserCard
{
    public $listOptionField = "favorite_card_list_option";

    public function getTemplateFile(): string
    {
        if ($this->listOption == CustomUser::PRODUCT_CARD_LIST_OPTION_LIST) {
            return "given-offer-teaser-card-list.twig";
        } else {
            return "given-offer-teaser-card.twig";
        }
    }
}
