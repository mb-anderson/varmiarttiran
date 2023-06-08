<?php

namespace App\Controller\Products;

use App\Controller\ProductsController;
use App\Entity\Product\Product;
use CoreDB\Kernel\Router;
use Src\Controller\NotFoundController;
use Src\Entity\Translation;
use Src\Form\InsertForm;

class InsertController extends ProductsController
{
    public InsertForm $productInsertForm;
    public ?Product $product;

    public function preprocessPage()
    {

            $this->product = Product::get($this->arguments[0]);
        if (!$this->product) {
            $this->product = new Product();
            $this->setTitle(Translation::getTranslation("add") . " | " .
            Translation::getTranslation("product"));
        } else {
            $this->setTitle(Translation::getTranslation("edit") . " | " .
            Translation::getTranslation("product") . " {$this->product->title}");
        }


        $this->productInsertForm = new InsertForm($this->product);
        $this->productInsertForm->processForm();
        $this->productInsertForm->addClass("p-3");
    }

    public function echoContent()
    {
        return $this->productInsertForm;
    }
}
