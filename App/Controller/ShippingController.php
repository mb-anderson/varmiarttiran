<?php

namespace App\Controller;

use App\Form\ShippingForm;
use App\Theme\CustomTheme;
use Src\Entity\Translation;

class ShippingController extends CustomTheme
{
    public ShippingForm $shippingForm;

    public function checkAccess(): bool
    {
        $currentUser = \CoreDB::currentUser();
        return $currentUser->isLoggedIn();
    }

    public function preprocessPage()
    {
        $this->setTitle(
            Translation::getTranslation("select_collection_or_delivery")
        );
        $this->shippingForm = new ShippingForm();
        $this->shippingForm->processForm();
    }

    public function echoContent()
    {
        return $this->shippingForm;
    }
}
