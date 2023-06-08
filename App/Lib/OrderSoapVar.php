<?php

namespace App\Lib;

use App\Entity\Basket\Basket;
use App\Entity\Basket\VoucherCode;
use App\Entity\Branch;
use App\Entity\CustomUser;
use App\Entity\Product\Product;
use SoapVar;
use Src\Entity\DynamicModel;
use Src\Entity\Translation;

class OrderSoapVar
{
    private Basket $basket;
    public function __construct(Basket $basket)
    {
        $this->basket = $basket;
    }

    public function getData()
    {
        /** @var CustomUser */
        $user = CustomUser::get($this->basket->user->getValue());
        if ($this->basket->type->getValue() == Basket::TYPE_COLLECTION) {
            $deliveryCode = "I";
            /** @var Branch */
            $branch = Branch::get(
                $this->basket->branch->getValue()
            );
            $branchCode = "0" . ($branch->ID->getValue() + 1);
            $status = $branchCode;
            $alt = "Collection {$branch->name->getValue()}";
        } else {
            $deliveryCode = "D";
            $status = "A";
            $branchCode = "01";
            $alt = "Deliver to customer";
        }
        $deliveryAddress = current($this->basket->order_address->getValue());
        $country = DynamicModel::get($deliveryAddress["country"] ?: ["code" => "GB"], "countries");

        if ($this->basket->paid_amount->getValue()) {
            $paidInfo = "₺{$this->basket->paid_amount->getValue()} paid by credit card.";
        } else {
            $paidInfo = "Not Paid Yet";
        }
        return new SoapVar(
            [
                "AccountCode" => $deliveryAddress["account_number"],
                "CustName" => mb_strtoupper($user->getFullName()),
                "SalesRep" => "AWEB",
                "Ref" => 518235,
                'QuoteRef' => 0,
                "OrderDate" => date("Y-m-d\TH:i:s.000+01:00", strtotime(
                    $this->basket->order_time->getValue()
                )),
                "DeliveryAddress" => mb_strtoupper(@$deliveryAddress["address"]),
                "DeliveryAddress1" => mb_strtoupper(@$deliveryAddress["address"]),
                "DeliveryAddress2" => mb_strtoupper(@$deliveryAddress["town"]),
                "DeliveryAddress3" => mb_strtoupper(@$deliveryAddress["county"]),
                "DeliveryAddress4" => mb_strtoupper($country->name->getValue()),
                "DeliveryAddress5" => "",
                "DeliveryPostCode" => mb_strtoupper(@$deliveryAddress["postcode"]),
                "Number" => '',
                "Status" => $status,
                "UserCode" => "WEB",
                "Currency" => "GBP",
                "Summary" => $this->basket->order_notes->getValue(),
                "CalcDueDate" => "3",
                "LocationCode" => "01",
                "RouteCode" => $deliveryCode,
                "DeliveryCode" => $deliveryCode,
                "BranchCode" => $branchCode,
                "AdditionalInfo" => "This request send from: " . ENVIROMENT,
                "AdditionalInfo1" => $paidInfo,
                "AdditionalInfo2" =>
                Translation::getTranslation($this->basket->type->getValue(), null, "en") . " at " .
                    date("d-m-Y", strtotime($this->basket->delivery_date->getValue())),
                "AdditionalInfo3" => $this->basket->order_notes->getValue(),
                "AdditionalInfo4" => "",
                "CCRef" => "3",
                "AltNumber" => $alt,
                "EBUserCode" => "TESTUser",
                "DueDate" => date("Y-m-d\TH:i:s.000", strtotime(
                    $this->basket->delivery_date->getValue() ?: \CoreDB::currentDate()
                )),
                "NetAmount" => $this->basket->subtotal->getValue(),
                "VatAmount" => $this->basket->vat->getValue(),
                "GrossAmount" => $this->basket->total->getValue(),
                "HasSupplied" => false,
                "FullySupplied" => false,
                "DepositCostCenter" => 0,
                "TypeCode" => "A",
                "CCCode" => ""
            ],
            SOAP_ENC_OBJECT,
            'TIntactSalesOrder'
        );
    }

    public function getOrderLines()
    {
        if ($this->basket->is_canceled->getValue()) {
            return [];
        }
        $orderItems = $this->basket->order_item->getValue();
        $orderLines = [];
        foreach ($orderItems as $item) {
            /** @var Product */
            $product = Product::get($item["product"]);

            $orderLines[] = new SoapVar(
                [
                    "ProductCode" => $product->stockcode->getValue(),
                    "QuantityOrdered" => $item["quantity"],
                    "UnitPrice" => $item["item_per_price"]
                ],
                SOAP_ENC_OBJECT
            );
        }
        $orderLines[] = new SoapVar(
            [
                "ProductCode" => 'ZZDEL001',
                "QuantityOrdered" => '1',
                "UnitPrice" => $this->basket->delivery->getValue() ?: 0
            ],
            SOAP_ENC_OBJECT
        );
        if ($this->basket->applied_voucher_code->getValue()) {
            /** @var VoucherCode */
            $code = VoucherCode::get($this->basket->applied_voucher_code->getValue());
            $orderLines[] = new SoapVar(
                [
                    "ProductCode" => $code->stockcode,
                    "QuantityOrdered" => '1',
                    "UnitPrice" => $this->basket->voucher_code_discount->getValue()
                ],
                SOAP_ENC_OBJECT
            );
        }
        return $orderLines;
    }
}
