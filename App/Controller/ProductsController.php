<?php

namespace App\Controller;

use App\Controller\Products\InsertController;
use App\Form\ProductSearchForm;
use App\Queries\ProductsQuery;
use App\Theme\CustomTheme;
use CoreDB;
use CoreDB\Kernel\Router;
use Src\Entity\Translation;

class ProductsController extends CustomTheme
{
    protected ProductSearchForm $productListSearch;

    public function checkAccess(): bool
    {
        $currentUser = \CoreDB::currentUser();
        if (!$currentUser->isLoggedIn()) {
            return true;
        } else {
            return parent::checkAccess();
        }
    }

    public function preprocessPage()
    {
        if (isset($this->arguments[0]) && $this->arguments[0]) {
            if ($this->arguments[0] == "add" && \CoreDB::currentUser()->isLoggedIn()) {
                Router::getInstance()->route(InsertController::getUrl());
            } else {
                CoreDB::goTo(LoginController::getUrl() . "?destination=" . CoreDB::requestUrl());
            }
        }
        $this->setTitle(Translation::getTranslation("products"));
        $this->productListSearch = ProductSearchForm::createByObject(
            ProductsQuery::getInstance()
        );
        $this->productListSearch->addClass("p-3");
    }

    public function echoContent()
    {
        return $this->productListSearch;
    }
}
