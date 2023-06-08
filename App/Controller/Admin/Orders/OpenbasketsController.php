<?php

namespace App\Controller\Admin\Orders;

use App\Controller\Admin\OrdersController;
use App\Entity\Basket\Basket;
use App\Queries\OpenBasketsQuery;
use Src\Entity\Translation;
use Src\Form\SearchForm;
use Src\Views\BasicCard;
use Src\Views\ViewGroup;

class OpenbasketsController extends OrdersController
{
    public $searchForm;

    public function preprocessPage()
    {
        $this->setTitle(Translation::getTranslation("open_baskets"));
        $this->searchForm = SearchForm::createByObject(OpenBasketsQuery::getInstance());
        $this->searchForm->addClass("p-3");

        $twoMonthsAgo = date('Y-m-d', strtotime('-2 month'));
        $this->openBasketsCount = \CoreDB::database()->select(Basket::getTableName(), 'b')
        ->condition('b.item_count', 0, '>')
        ->condition('b.last_updated', $twoMonthsAgo . " 00:00:00", '>=')
        ->condition('b.is_ordered', 0)
        ->selectWithFunction(['COUNT(*) AS COUNT'])
        ->execute()->fetchColumn();
        $this->archivedBasketsCount = \CoreDB::database()->select(Basket::getTableName(), 'b')
        ->condition('b.item_count', 0, '>')
        ->condition('b.last_updated', $twoMonthsAgo . " 00:00:00", '<')
        ->condition('b.is_ordered', 0)
        ->selectWithFunction(['COUNT(*) AS COUNT'])
        ->execute()->fetchColumn();
    }

    public function echoContent()
    {
        $twoMonthsAgo = date('Y-m-d', strtotime('-2 month'));
        $today = date("Y-m-d");
        return ViewGroup::create("div", "")
        ->addField(
            ViewGroup::create("div", "row p-3")
            ->addField(
                BasicCard::create()
                ->setBorderClass("border-left-info")
                ->setHref(
                    self::getUrl() . "?basket.last_updated={$twoMonthsAgo}+%26+{$today}"
                )
                ->setTitle(Translation::getTranslation("open_baskets"))
                ->setDescription($this->openBasketsCount)
                ->setIconClass("fa fa-shopping-basket")
                ->addClass("col-lg-3 col-md-6 mb-4")
            )
            ->addField(
                BasicCard::create()
                ->setBorderClass("border-left-warning")
                ->setHref(
                    self::getUrl() . "?basket.last_updated=" . date("Y-m-d", 0) . "+%26+" .
                    date("Y-m-d", strtotime("-2 month -1 day"))
                )
                ->setTitle(Translation::getTranslation('archived_baskets'))
                ->setDescription($this->archivedBasketsCount)
                ->setIconClass("fas fa-cart-arrow-down")
                ->addClass("col-lg-3 col-md-6 mb-4")
            )
        )->addField(
            $this->searchForm
        );
    }
}
