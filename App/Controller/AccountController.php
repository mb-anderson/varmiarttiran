<?php

namespace App\Controller;

use App\Theme\CustomTheme;
use Src\Entity\Translation;
use Src\Views\BasicCard;
use Src\Views\ViewGroup;

class AccountController extends CustomTheme
{
    public ViewGroup $content;

    public function preprocessPage()
    {
        $this->setTitle(Translation::getTranslation("account"));
        $this->content = ViewGroup::create("div", "row");
        $this->content->addField(
            BasicCard::create()
            ->setTitle(Translation::getTranslation("recent_orders"))
            ->setDescription(Translation::getTranslation("recent_order_description"))
            ->setIconClass("fa fa-history")
            ->setHref(MyordersController::getUrl())
            ->setBorderClass("border-left-info")
            ->addClass("col-md-6 my-1")
        );

        $this->content->addField(
            BasicCard::create()
            ->setTitle(Translation::getTranslation("account_settings"))
            ->setDescription(Translation::getTranslation("account_settings_description"))
            ->setIconClass("fa fa-user")
            ->setHref(ProfileController::getUrl())
            ->setBorderClass("border-left-info")
            ->addClass("col-md-6 my-1")
        );
    }

    public function echoContent()
    {
        return $this->content;
    }
}
