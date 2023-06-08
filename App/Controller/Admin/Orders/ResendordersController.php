<?php

namespace App\Controller\Admin\Orders;

use App\Controller\Admin\OrdersController;
use App\Queries\ResendOrdersQuery;
use Src\Entity\Translation;
use Src\Form\SearchForm;

class ResendordersController extends OrdersController
{
    public $searchForm;

    public function preprocessPage()
    {
        $this->setTitle(Translation::getTranslation("resend_orders"));
        $this->searchForm = SearchForm::createByObject(ResendOrdersQuery::getInstance());
        $this->searchForm->addClass("p-3");
    }
}
