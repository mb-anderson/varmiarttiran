<?php

namespace App\Views;

use App\Entity\CustomUser;

class ReceivedOffersProductCard extends ProductTeaserCard
{
    public $listOptionField = "favorite_card_list_option";

    public function getTemplateFile(): string
    {
        if ($this->listOption == CustomUser::PRODUCT_CARD_LIST_OPTION_LIST) {
            return "received-offer-teaser-card-list.twig";
        } else {
            return "received-offer-teaser-card.twig";
        }
    }
}
