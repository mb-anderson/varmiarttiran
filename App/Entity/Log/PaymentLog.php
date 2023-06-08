<?php

namespace App\Entity\Log;

use App\Lib\PaymentSoapVar;
use CoreDB\Kernel\Database\DataType\Checkbox;
use CoreDB\Kernel\Database\DataType\FloatNumber;
use CoreDB\Kernel\Model;
use CoreDB\Kernel\Database\DataType\TableReference;
use CoreDB\Kernel\Database\DataType\LongText;
use CoreDB\Kernel\Database\DataType\ShortText;
use Src\Entity\Variable;

/**
 * Object relation with table payment_log
 * @author makarov
 */

class PaymentLog extends Model
{
    /**
    * @var TableReference $order
    * Order reference.
    */
    public TableReference $order;
    /**
    * @var FloatNumber $amount
    * Paid amount
    */
    public FloatNumber $amount;
    /**
    * @var ShortText $transaction_ref
    * Transaction reference.
    */
    public ShortText $transaction_ref;
    /**
    * @var Checkbox $is_success
    * Payment is successful.
    */
    public Checkbox $is_success;
    /**
    * @var Checkbox $intact_synched
    * Is this payment has been synched with intact.
    */
    public Checkbox $intact_synched;
    /**
    * @var LongText $response
    * Response in JSON format.
    */
    public LongText $response;

    /**
     * @inheritdoc
     */
    public static function getTableName(): string
    {
        return "payment_log";
    }

    /** @throws \Exception */
    public function synchIntact()
    {
        $apiUrl =  Variable::getByKey(
            ENVIROMENT == "production" ? "api_url" : "api_test_url"
        )->value->getValue();
        $url = "{$apiUrl}/wsdl/IIntact";
        $option = [
            'trace' => 1 ,
            'use' => SOAP_LITERAL,
            'exceptions' => 1
        ];
        $soapClient = new \SoapClient($url, $option);
        $paymentVar = new PaymentSoapVar($this);
        $soapResponse = $soapClient->CreateNewSalesReceipt($paymentVar->getData());
        $this->intact_synched->setValue(1);
    }
}
