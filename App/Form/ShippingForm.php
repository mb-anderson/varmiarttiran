<?php

namespace App\Form;

use App\Controller\ProductsController;
use App\Entity\Basket\Basket;
use App\Entity\Branch;
use App\Entity\CustomUser;
use App\Entity\UserAddress;
use App\Widget\DeliveryDateWidget;
use CoreDB;
use PDO;
use Src\Entity\Translation;
use Src\Form\Form;
use Src\Form\Widget\InputWidget;
use Src\Form\Widget\SelectWidget;

class ShippingForm extends Form
{
    public string $method = "POST";

    public $userAdresses = [];

    public function __construct()
    {
        parent::__construct();
        /** @var CustomUser */
        $user = \CoreDB::currentUser();

        $this->getUserAddresses();
        $this->addField(
            SelectWidget::create("shipping_option")
            ->setOptions(
                [
                    CustomUser::SHIPPING_OPTION_DELIVERY =>
                    Translation::getTranslation(
                        CustomUser::SHIPPING_OPTION_DELIVERY
                    ),
                    CustomUser::SHIPPING_OPTION_COLLECTION =>
                    Translation::getTranslation(
                        CustomUser::SHIPPING_OPTION_COLLECTION
                    )
                ]
            )->setValue(
                $user->shipping_option->getValue()
            )->addAttribute("required", "true")
            ->addClass("d-none")
        );


        $this->addField(
            SelectWidget::create("shipping_address")
            ->setOptions(
                $this->userAdresses
            )
            ->setNullElement(null)
            ->setLabel(
                Translation::getTranslation("address")
            )->setValue(
                $user->shipping_address->getValue()
            )
        );

        $this->addField(
            SelectWidget::create("branch")
            ->setOptions(
                \CoreDB::database()->select(Branch::getTableName(), "b")
                ->select("b", ["ID", "name"])
                ->execute()->fetchAll(PDO::FETCH_KEY_PAIR)
            )->setNullElement(Translation::getTranslation("please_choose"))
            ->setLabel(
                Translation::getTranslation("choose_store")
            )->setValue(
                $user->shipping_branch->getValue()
            )
        );

        /** @var DeliveryDateWidget */
        $deliveryDate = (new DeliveryDateWidget("delivery_date"))
        ->setLabel(Translation::getTranslation("collection_date"))
        ->setValue($user->delivery_date->getValue());
        $this->addField($deliveryDate);

        $this->addField(
            InputWidget::create("save")
            ->setValue(Translation::getTranslation("save"))
            ->setType("submit")
            ->addClass("btn btn-success mt-2")
            ->removeClass("form-control")
        );

        $controller = \CoreDB::controller();
        $controller->addCssFiles("dist/shipping-form/shipping-form.css");
        $controller->addJsFiles("dist/shipping-form/shipping-form.js");
    }

    public function getUserAddresses()
    {
        $user = CoreDB::currentUser();
        $userIds = [$user->ID->getValue()];
        foreach ($user->linked_account->getValue() as $linkedAccount) {
            if (!in_array($linkedAccount["sub_account"], $userIds)) {
                $userIds[] = $linkedAccount["sub_account"];
            }
        }
        foreach ($userIds as $userId) {
            $userAdresses = UserAddress::getAll([
                "user" => $userId
            ], false);
            /** @var UserAddress $address */
            foreach ($userAdresses as $address) {
                $this->userAdresses[$address->ID->getValue()] = $address;
            }
        }
    }

    public function getTemplateFile(): string
    {
        return "shipping-form.twig";
    }

    public function getFormId(): string
    {
        return "shipping_form";
    }

    public function validate(): bool
    {
        if (
            $this->request["shipping_option"] == Basket::TYPE_COLLECTION
        ) {
            if (
                !Basket::getUserBasket()->isDeliveryDayIsValid(
                    $this->request["delivery_date"],
                    $this->request["shipping_option"]
                )
            ) {
                    $this->setError(
                        "delivery_date",
                        Translation::getTranslation("please_select_valid_day")
                    );
            }
            if (!$this->request["branch"]) {
                $this->setError(
                    "branch",
                    Translation::getTranslation("please_select_a_branch")
                );
            }
        }
        return empty($this->errors);
    }

    public function submit()
    {
        /** @var CustomUser */
        $user = \CoreDB::currentUser();
        $user->shipping_option->setValue(
            $this->request["shipping_option"]
        );
        $user->shipping_branch->setValue(
            $this->request["branch"]
        );
        $user->shipping_address->setValue(
            $this->request["shipping_address"]
        );
        $user->delivery_date->setValue(
            $this->request["delivery_date"]
        );
        if ($this->request["shipping_option"] == Basket::TYPE_DELIVERY && $this->request["shipping_address"]) {
            $address = UserAddress::get(["ID" => $this->request["shipping_address"]], false);
        } else {
            $address = UserAddress::get(["user" => $user->ID->getValue()], false);
        }
        $user->save();
        $basket = Basket::getUserBasket();
        $basket->map([
            "type" => $this->request["shipping_option"],
            "branch" => $this->request["shipping_option"] == Basket::TYPE_COLLECTION ?
            $this->request["branch"] : null,
            "order_address" => $address ? [
                $address->toArray()
            ] : $user->address->getValue(),
            "billing_address" => $address ? [
                $address->toArray()
            ] : $user->address->getValue(),
            "delivery_date" => $this->request["shipping_option"] == Basket::TYPE_COLLECTION ?
            $this->request["delivery_date"] : null
        ]);
        $basket->save();
        CoreDB::goTo(
            @$_GET["destination"] ? BASE_URL . $_GET["destination"] : ProductsController::getUrl()
        );
    }
}
