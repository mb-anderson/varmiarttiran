<?php

namespace App\Entity;

use App\Entity\Log\IntactLog;
use App\Lib\UserSoapVar;
use CoreDB\Kernel\Model;
use CoreDB\Kernel\Database\DataType\TableReference;
use CoreDB\Kernel\Database\DataType\Text;
use CoreDB\Kernel\Database\DataType\ShortText;
use CoreDB\Kernel\Database\DataType\Checkbox;
use Src\Entity\DynamicModel;
use Src\Entity\Translation;
use Src\Entity\Variable;
use Src\Entity\Watchdog;
use Src\Form\Widget\SelectWidget;
use Src\Theme\View;

/**
 * Object relation with table custom_user_address
 * @author makarov
 */

class UserAddress extends Model
{
    /**
    * @var ShortText $account_number
    * Unique account number
    */
    public ShortText $account_number;
    /**
    * @var ShortText $company_name
    *
    */
    public ShortText $company_name;
    /**
    * @var TableReference $user
    *
    */
    public TableReference $user;
    /**
    * @var Text $address
    *
    */
    public Text $address;
    /**
    * @var ShortText $town
    *
    */
    public ShortText $town;
    /**
    * @var ShortText $county
    *
    */
    public ShortText $county;
    /**
    * @var ShortText $postalcode
    *
    */
    public ShortText $postalcode;
    /**
    * @var TableReference $country
    *
    */
    public TableReference $country;
    /**
    * @var ShortText $phone
    *
    */
    public ShortText $phone;
    /**
    * @var ShortText $mobile
    *
    */
    public ShortText $mobile;
    /**
    * @var Checkbox $default
    *
    */
    public Checkbox $default;
    /**
    * @var Checkbox $intact_synched
    * This account has been synched with intact.
    */
    public Checkbox $intact_synched;

    /**
     * @inheritdoc
     */
    public static function getTableName(): string
    {
        return "user_address";
    }

    public static function get($filter, $isDefault = true)
    {
        if ($isDefault && static::class == UserAddress::class) {
            @$filter["default"] = 1;
        }
        return parent::get($filter);
    }

    public static function getAll(array $filter, $isDefault = true): array
    {
        if ($isDefault && static::class == UserAddress::class) {
            @$filter["default"] = 1;
        }
        return parent::getAll($filter);
    }

    public function synchToIntact()
    {
        $apiUrl =  Variable::getByKey(
            ENVIROMENT == "production" ? "api_url" : "api_test_url"
        )->value->getValue();
        $url = "{$apiUrl}/wsdl/IIntact";
        $option =  [
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'exceptions' => 1
        ];
        $soapClient = new \SoapClient($url, $option);
        $soapVar = new UserSoapVar($this);
        try {
            $newCustomer = $soapClient->AddNewCustomer(
                $soapVar->getData()
            );
            if ($newCustomer == -2) {
                $newCustomer = $soapClient->UpdateCustomer(
                    $soapVar->getData()
                );
            }
            $intactLog = new IntactLog();
            $intactLog->map([
                "account" => $this->ID->getValue(),
                "request_type" => IntactLog::REQUEST_TYPE_ACCOUNT,
                "request" => $soapClient->__getLastRequest(),
                "response" => $soapClient->__getLastResponse()
            ]);
            $intactLog->save();
            $this->intact_synched->setValue(1);
        } catch (\SoapFault $ex) {
            $intactLog = new IntactLog();
            $intactLog->map([
                "account" => $this->ID->getValue(),
                "request_type" => IntactLog::REQUEST_TYPE_ACCOUNT,
                "request" => $soapClient->__getLastRequest(),
                "response" => $soapClient->__getLastResponse()
            ]);
            $intactLog->save();
            $this->intact_synched->setValue(0);
            Watchdog::log("address_intact_error", $ex->getMessage());
        }
        $this->update();
    }

    public function save()
    {
        if (get_class($this) == UserAddress::class) {
            $this->default->setValue(1);
        }
        if (!IS_CLI && !$this->account_number->getValue()) {
            $this->account_number->setValue(
                $this->getNewAccountNumber()
            );
        }
        $result = parent::save();
        return $result;
    }

    public function delete(): bool
    {
        foreach (IntactLog::getAll(["account" => $this->ID->getValue()]) as $log) {
            $log->delete();
        }
        return parent::delete();
    }

    private function getNewAccountNumber()
    {
        $maxAccountNo = \CoreDB::database()->select(UserAddress::getTableName())
        ->selectWithFunction(["MAX(account_number)"])
        ->condition("account_number", "W0%", "LIKE")
        ->execute()->fetchColumn();
        $maxNumber = filter_var($maxAccountNo, FILTER_SANITIZE_NUMBER_INT);
        if (!$maxNumber) {
            $maxNumber = 0;
        }
        return "W" .  str_pad($maxNumber + 1, 5, '0', STR_PAD_LEFT);
    }

    protected function getFieldWidget(string $field_name, bool $translateLabel): ?View
    {
        if ($field_name == "country") {
            $widget = new SelectWidget("");
            $widget->setOptions(
                \CoreDB::database()->select("countries", "c")
                ->select("c", ["ID", "name"])
                ->execute()->fetchAll(\PDO::FETCH_KEY_PAIR)
            )->setValue(
                $this->country->getValue() ?:
                DynamicModel::get(["code" => "GB"], "countries")->ID->getValue()
            )
            ->addAttribute("data-live-search", "true")
            ->setLabel(
                Translation::getTranslation($field_name)
            );
            return $widget;
        } elseif ($field_name == "account_number") {
            return parent::getFieldWidget($field_name, $translateLabel)
            ->addAttribute("disabled", "true");
        } elseif ($field_name == "default" || $field_name == "intact_synched") {
            return null;
        } else {
            return parent::getFieldWidget($field_name, $translateLabel);
        }
    }

    public function __toString()
    {
        $data = $this->toArray();
        unset(
            $data["default"],
            $data["user"],
            $data["phone"],
            $data["mobile"],
            $data["intact_synched"]
        );
        $data = array_filter($data);
        $data["country"] = DynamicModel::get($this->country->getValue() ?: ["code" => "GB"], "countries")->name;
        return implode(", ", $data);
    }
}
