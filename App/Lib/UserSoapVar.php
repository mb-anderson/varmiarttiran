<?php

namespace App\Lib;

use App\Entity\CustomUser;
use App\Entity\UserAddress;
use SoapVar;
use Src\Entity\DynamicModel;

class UserSoapVar
{
    public UserAddress $address;

    public function __construct(UserAddress $address)
    {
        $this->address = $address;
    }

    public function getData()
    {
        $address = $this->address->toArray();
        /** @var CustomUser */
        $user = CustomUser::get($this->address->user->getValue());
        $category = DynamicModel::get($user->shop_category->getValue(), "shop_categories");
        $country = DynamicModel::get($address["country"] ?: ["code" => "GB"], "countries");
        $data = [
            "Code" => $address["account_number"],
            "Name" => $address["company_name"],
            "Contact1" => $user->getFullName(),
            "Address1" => $address["address"],
            "Address2" => $address["town"],
            "Address3" => $address["county"],
            "Address4" => $country->name->getValue(),
            "PostCode" => $address["postalcode"],
            "Phone1" => $address["phone"],
            "Phone2" => $address["mobile"],
            "EMail" => $user->email->getValue(),
            "DeliveryAddress1" => $address["address"],
            "DeliveryAddress2" => $address["town"],
            "DeliveryAddress3" => $address["county"],
            "DeliveryAddress4" => $country->name->getValue(),
            "DeliveryPostCode" => $address["postalcode"],
            "CurrencyCode" => 'GBP',
            "RepCode" => 'AWEB',
            "CategoryCode" => $category ? $category->code->getValue() : null,
            "DefaultVatCode" => '1',
            "DefaultNominalCode" => '10110',
            "PriceCode" => '1',
        ];
        foreach ($data as &$el) {
            $el = mb_strtoupper($el);
        }
        return new SoapVar(
            $data,
            SOAP_ENC_OBJECT,
            'TIntactCustomer'
        );
    }
}
