<?php

namespace App\Controller;

use App\Controller\Admin\Products\Enquirement\InsertController;
use App\Controller\Checkout\SentController;
use App\Entity\Basket\Basket;
use App\Entity\CustomUser;
use App\Entity\Offer\Offer;
use App\Entity\PaymentMethod;
use App\Entity\Product\Enquirement;
use App\Entity\Product\FavoriteProducts;
use App\Entity\Product\Product;
use App\Entity\Analytics\ProductTracker;
use App\Exception\BasketException;
use CoreDB;
use CoreDB\Kernel\Messenger;
use Exception;
use Src\Controller\AjaxController as ControllerAjaxController;
use Src\Entity\Translation;
use Src\Entity\User;

class AjaxController extends ControllerAjaxController
{
    public function addItemToBasket()
    {
        $itemId = @$_POST["itemId"];
        $quantity = @$_POST["quantity"];
        $variation = @$_POST["variation"];
        $place = @$_POST["place"];
        /** @var Product */
        $product = Product::get($itemId);
        if (
            $product &&
            !$product->is_special_product->getValue() &&
            $product->isPrivateAndOwnerMatches() &&
            ( $product->is_variable->getValue() ? $variation : true )
        ) {
            $basket = Basket::getUserBasket();
            try {
                if ($basket->uncheckout()) {
                    $product = Product::get($itemId);
                }
                $basketProduct = $basket->addItem($product, $quantity, $variation ?: null);
                $response = $basketProduct->toArray();
                if ($place) {
                    $tracker = ProductTracker::get([
                        "basket_product" => $basketProduct->ID->getValue()
                    ]) ?: new ProductTracker();
                    $tracker->map([
                        "basket_product" => $basketProduct->ID->getValue(),
                        "place" => $place,
                        "url" => @$_SERVER["HTTP_REFERER"]
                    ]);
                    $tracker->save();
                }
            } catch (BasketException $ex) {
                http_response_code(400);
                $this->createMessage($ex->getMessage());
                return [
                    "quantity" => $ex->getQuantity()
                ];
            }
            $response += $basket->toArray();
            $response["for_free_delivery"] = $basket->getMinimumOrderPrice() -
            $basket->subtotal->getValue() -
            $basket->calculateVat();
            return $response;
        } elseif ($itemId == "update") {
            $basket = Basket::getUserBasket();
            return $basket->toArray();
        } else {
            throw new Exception(
                Translation::getTranslation("invalid_operation")
            );
        }
    }

    public function makeOfferToProduct()
    {
        $itemId = @$_POST["itemId"];
        $offer = @$_POST["offer"];
        /** @var Product */
        $product = Product::get($itemId);
        $currentUser = \CoreDB::currentUser();
        $maxOffer = Offer::getMaxOffer($product);
        $response = [];
        if ($product && $currentUser->isLoggedIn() && $offer > $maxOffer) {
            $offerEntity = new Offer();
            $offerEntity->map([
                "user" => $currentUser->ID,
                "product" => $product->ID,
                "offer" => floatval($offer),
            ]);
            $offerEntity->save();
        } elseif ($offer <= $maxOffer) {
            throw new Exception(Translation::getTranslation("offer_must_be_bigger_than_max"));
        }

        $response = [
            "product" => $itemId,
            "offer" => strval($offerEntity->offer->getValue())
        ];
        return $response;
    }

    public function cleanBasket()
    {
        $basket = Basket::getUserBasket();
        $basket->order_item->setValue([]);
        $basket->save();
        $this->createMessage(
            Translation::getTranslation("basket_cleaned"),
            Messenger::SUCCESS
        );
    }

    public function toggleFavorite()
    {
        $itemId = @$_POST["itemId"];
        $product = Product::get($itemId);
        if ($product) {
            $toggled = FavoriteProducts::toggleFavorite($product);
            $message = $toggled ? "product_added_favorites" : "product_removed_favorites";
            return [
                "toggled" => $toggled,
                "message" => Translation::getTranslation(
                    $message,
                    [$product->title->getValue()]
                ),
            ];
        }
    }

    public function resendVerifyMail()
    {
        $user = CoreDB::currentUser();
        $userByMail = User::getUserByEmail(@$_POST["mail"]);
        if (!filter_var(@$_POST["mail"], FILTER_VALIDATE_EMAIL)) {
            throw new Exception(
                Translation::getTranslation("enter_valid_mail")
            );
        } elseif (
            $userByMail && $userByMail->ID->getValue() != $user->ID->getValue()
        ) {
            throw new Exception(
                Translation::getTranslation("email_not_available")
            );
        } else {
            $user->map([
                "email" => @$_POST["mail"],
                "email_verification_key" => hash(
                    "sha256",
                    $user->getFullName() . $user->email->getValue() . microtime()
                )
            ]);
            $user->save();
            $verifyUrl = VerifyController::getUrl() . "{$user->ID}/" . $user->email_verification_key->getValue();
            \CoreDB::HTMLMail(
                $user->email->getValue(),
                Translation::getTranslation("email_verification"),
                Translation::getEmailTranslation("email_verification", [
                    $user->getFullName(), $verifyUrl, $verifyUrl
                ]),
                $user->getFullName()
            );
            $this->createMessage(
                Translation::getTranslation("verification_mail_resend"),
                Messenger::SUCCESS
            );
        }
    }

    public function basketInvoice()
    {
        $this->response_type = self::RESPONSE_TYPE_RAW;
        /** @var Basket $basket */
        $basket = Basket::get(@$_GET["basket-id"]);
        if ($basket) {
            try {
                echo $basket->generatePdf("I");
            } catch (Exception $ex) {
                echo $ex->getMessage();
            }
        }
    }

    public function togglePrivateProducts()
    {
        $basket = Basket::getUserBasket();
        if ($basket->private_products_excluded->getValue()) {
            $basket->private_products_excluded->setValue(0);
            $text = Translation::getTranslation("exclude_private_products");
        } else {
            $basket->private_products_excluded->setValue(1);
            $text = Translation::getTranslation("include_private_products");
        }
        $basket->save();
        return [
            "text" => $text,
            "excluded" => boolval($basket->private_products_excluded->getValue())
        ];
    }

    public function checkEnquirement()
    {
        $itemId = @$_POST["itemId"];
        $enquirement = Enquirement::getUserActiveEnquirement($itemId) ?: new Enquirement();
        $minumumEnquirementCount = Enquirement::getMinimumEnquirementCount();
        return [
            "quantity" => $enquirement->quantity->getValue() ?: $minumumEnquirementCount,
            "description" => $enquirement->description->getValue(),
            "is_exists" => boolval($enquirement->ID->getValue()),
            "minimum_count" => $minumumEnquirementCount,
            "exist_warning" => Translation::getTranslation("enquiry_exist_warning"),
        ];
    }

    public function enquireProduct()
    {
        $quantity = @$_POST["quantity"];
        $description = @$_POST["description"];
        $itemId = @$_POST["itemId"];
        /** @var Product */
        $product = Product::get($itemId);
        if (!$product) {
            throw new Exception(Translation::getTranslation("invalid_operation"));
        }
        if (!$quantity) {
            throw new Exception(
                Translation::getTranslation("cannot_empty", [
                    Translation::getTranslation("quantity")
                ])
            );
        }
        $minumumEnquirementCount = Enquirement::getMinimumEnquirementCount();
        if ($quantity < $minumumEnquirementCount) {
            throw new Exception(
                Translation::getTranslation("minimum_item_error", [
                    $minumumEnquirementCount
                ])
            );
        }
        if (!$description) {
            throw new Exception(
                Translation::getTranslation("cannot_empty", [
                    Translation::getTranslation("description")
                ])
            );
        }

        $enquirement = Enquirement::getUserActiveEnquirement($itemId) ?: new Enquirement();

        /** @var CustomUser */
        $user = \CoreDB::currentUser();
        $enquirement->user->setValue($user->ID->getValue());
        $enquirement->product->setValue($itemId);
        $enquirement->quantity->setValue($quantity);
        $enquirement->description->setValue($description);
        $enquirement->status->setValue(
            Enquirement::STATUS_OPEN
        );
        $enquirement->save();

        $message = Translation::getEmailTranslation("enquiry_sent", [
            $user->getFullName(),
            $product->title->getValue(),
            $product->stockcode->getValue(),
            $quantity,
            InsertController::getUrl() . $enquirement->ID->getValue(),
            $user->address->getValue()[0]["company_name"],
            $user->email->getValue()
        ]);
        // \CoreDB::HTMLMail(
        //     "CSLWebOrder@cleansupply.co.uk",
        //     "New Enquiry #" . $enquirement->ID->getValue(),
        //     $message,
        //     Variable::getByKey("site_name")->value->getValue()
        // );

        $this->createMessage(
            Translation::getTranslation("enquiry_saved") .
            Translation::getTranslation("thank_you_for_order"),
            Messenger::SUCCESS
        );
    }

    public function changeListOption()
    {
        $listOption = @$_POST["listOption"];
        $listOptionField = @$_POST["listOptionField"];
        if (
            in_array($listOption, [
            CustomUser::PRODUCT_CARD_LIST_OPTION_CARD,
            CustomUser::PRODUCT_CARD_LIST_OPTION_LIST
            ]) &&
            in_array($listOptionField, [
                "product_card_list_option",
                "favorite_card_list_option",
                "bespoke_card_list_option",
            ])
        ) {
            $user = \CoreDB::currentUser();
            $user->$listOptionField->setValue($listOption);
            $user->save();
        } else {
            throw new Exception(
                Translation::getTranslation("invalid_operation")
            );
        }
    }

    public function orderAgain()
    {
        /** @var Basket */
        $orderAgainBasket = Basket::get(@$_GET["basket-id"]);
        if (
            !$orderAgainBasket ||
            $orderAgainBasket->user->getValue() != \CoreDB::currentUser()->ID->getValue()
        ) {
            \CoreDB::messenger()->createMessage(
                Translation::getTranslation("access_denied")
            );
        } else {
            $oldBasketItems = $orderAgainBasket->order_item->getValue();
            $oldItems = [];
            foreach ($oldBasketItems as $item) {
                $oldItems[$item["product"]] = $item;
            }
            $userBasket = Basket::getUserBasket();
            $newBasketItems = $userBasket->order_item->getValue();
            $newItems = [];
            foreach ($newBasketItems as $item) {
                $newItems[$item["product"]] = $item;
            }
            foreach ($oldItems as $productId => $item) {
                $product = Product::get($productId);
                try {
                    if (!@$newItems[$productId] && $product) {
                        $userBasket->addItem(
                            $product,
                            intval($item["quantity"]),
                            intval($item["variant"]) ?: null
                        );
                    }
                } catch (BasketException $ex) {
                    \CoreDB::messenger()->createMessage(
                        $ex->getMessage()
                    );
                    $userBasket->addItem(
                        $product,
                        $ex->getQuantity(),
                        intval($item["variant"]) ?: null
                    );
                }
            }
        }
        \CoreDB::goTo(CheckoutController::getUrl());
    }

    public function getActiveOrders()
    {
        /** @var Basket */
        $userBasket = Basket::getUserBasket();
        $activeOrders = $userBasket->getActiveOrders();
        $orders = [];
        foreach ($activeOrders as $order) {
            $orders[] = [
                "text" => $order->__toString() . "<br><br>",
                "value" => $order->ID->getValue()
            ];
        }
        return [
            "title" => Translation::getTranslation("active_order_choose"),
            "orders" => $orders,
            "value" => current($orders)["value"],
            "merge" => Translation::getTranslation("merge")
        ];
    }

    public function getMergeInfo()
    {
        [$mergeOrder, $userBasket] = $this->getBasketForMerge();
        $startTotal = $mergeOrder->total->getValue();
        \CoreDB::database()->beginTransaction();
        $mergeOrder->mergeWith($userBasket);
        \CoreDB::database()->rollback();
        return [
            "message" => Translation::getTranslation("merge_warning", [
                $mergeOrder->__toString(),
                $mergeOrder->delivery->getValue(),
                $mergeOrder->vat->getValue(),
                $mergeOrder->subtotal->getValue(),
                $mergeOrder->total->getValue(),
                $mergeOrder->total->getValue() - $startTotal
            ]),
            "continue" => Translation::getTranslation("continue_to_merge"),
            "optional_pay" => boolval(
                $mergeOrder->type->getValue() == Basket::TYPE_COLLECTION ||
                \CoreDB::currentUser()->pay_optional_at_checkout->getValue()
            )
        ];
    }

    public function mergeBasket()
    {
        [$mergeOrder, $userBasket] = $this->getBasketForMerge();
        if (
            $mergeOrder->type->getValue() != Basket::TYPE_COLLECTION &&
            !\CoreDB::currentUser()->pay_optional_at_checkout->getValue()
        ) {
            throw new Exception(
                Translation::getTranslation("optional_pay_not_available")
            );
        }
        $userBasket->uncheckout();
        $mergeOrder->need_update_intact->setValue(1);
        $mergeOrder->mergeWith($userBasket);
        /** @var Basket */
        $mergeOrder = Basket::get($mergeOrder->ID->getValue());
        //$mergeOrder->sendIntact();
        $mergeOrder->save();
        $userBasket->order_item->setValue([]);
        $userBasket->save();
        \CoreDB::messenger()->createMessage(
            Translation::getTranslation("order_merge_success"),
            Messenger::INFO
        );
        return [
            "location" => SentController::getUrl() . "?basket={$mergeOrder->ID}"
        ];
    }

    private function getBasketForMerge()
    {
        $orderId = @$_POST["order"];
        $userBasket = Basket::getUserBasket();
        $activeOrders = $userBasket->getActiveOrders();
        /** @var Basket */
        $mergeOrder = null;
        foreach ($activeOrders as $order) {
            if ($order->ID->getValue() == $orderId) {
                $mergeOrder = $order;
                break;
            }
        }
        if (!$mergeOrder) {
            throw new Exception(
                Translation::getTranslation("access_denied")
            );
        }
        return [$mergeOrder, $userBasket];
    }

    public function cancelOrder()
    {
        $orderId = @$_POST["order"];
        /** @var Basket */
        $order = Basket::get($orderId);
        if (!$order) {
            throw new Exception(
                Translation::getTranslation("access_denied")
            );
        }
        $order->map([
            "is_canceled" => 1,
            "cancel_time" => \CoreDB::currentDate()
        ]);
        $order->save();
        \CoreDB::HTMLMail(
            \CoreDB::currentUser()->email->getValue(),
            Translation::getTranslation("order_canceled"),
            Translation::getEmailTranslation("order_canceled", [
                \CoreDB::currentUser()->getFullName(),
                $order->order_id->getValue(),
                date("d F Y H:i:s", strtotime($order->cancel_time->getValue()))
            ]),
            \CoreDB::currentUser()->getFullName()
        );
        \CoreDB::HTMLMail(
            "mburakyucel38@gmail.com",
            Translation::getTranslation("order_canceled", null, "en"),
            Translation::getEmailTranslation("order_canceled", [
                \CoreDB::currentUser()->getFullName(),
                $order->order_id->getValue(),
                date("d F Y H:i:s", strtotime($order->cancel_time->getValue()))
            ], "en"),
            \CoreDB::currentUser()->getFullName()
        );
        $this->createMessage(
            Translation::getTranslation("order_canceled"),
            Messenger::SUCCESS
        );
        return Translation::getTranslation("order_canceled");
    }

    public function removeCard()
    {
        $cardId = @$_POST["cardId"];
        $paymentMethod = PaymentMethod::get([
            "user" => \CoreDB::currentUser()->ID->getValue(),
            "ID" => $cardId
        ]);
        if (!$paymentMethod) {
            throw new \Exception(
                Translation::getTranslation("invalid_operation")
            );
        }
        $paymentMethod->delete();
        $this->createMessage(
            Translation::getTranslation("card_remove_success"),
            Messenger::SUCCESS
        );
    }
}
