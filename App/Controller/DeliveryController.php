<?php

namespace App\Controller;

use App\Queries\PostcodeQuery;
use App\Theme\CustomTheme;
use CoreDB\Kernel\Messenger;
use Src\Entity\Translation;
use Src\Form\SearchForm;

class DeliveryController extends CustomTheme
{
    public SearchForm $postcodeSearch;

    public function checkAccess(): bool
    {
        return true;
    }

    public function preprocessPage()
    {
        $postcodeQuery = PostcodeQuery::getInstance();
        $this->postcodeSearch = SearchForm::createByObject($postcodeQuery);
        $this->setTitle(
            Translation::getTranslation("delivery_days")
        );
        $this->createMessage(
            Translation::getTranslation("postcode_table_info"),
            Messenger::INFO
        );
    }

    public function echoContent()
    {
        return $this->postcodeSearch;
    }
}
