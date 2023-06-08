<?php

namespace App\Lib;

use App\Entity\Basket\Basket;
use App\Entity\Log\PaymentLog;
use SoapVar;

class PaymentSoapVar
{
    public PaymentLog $log;
    public function __construct(PaymentLog $log)
    {
        $this->log = $log;
    }

    public function getData()
    {
        $basket = Basket::get($this->log->order->getValue());
         $basketData = [
             "DebitAccount" => "61550",
             "AccountCode" => mb_strtoupper(
                 $basket->order_address->getValue()[0]["account_number"]
             ),
             "CustomerRef" => $this->log->transaction_ref->getValue(),
             "TrDate" => \CoreDB::currentDate(),
             "RepCode" => "ATOZ",
             "Ref" => $basket->order_id->getValue(),
             "Amount" => $this->log->amount->getValue(),
         ];
         return new SoapVar($basketData, SOAP_ENC_OBJECT, 'TIntactSalesReceipt');
    }
}
