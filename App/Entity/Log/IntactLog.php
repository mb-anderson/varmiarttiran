<?php

namespace App\Entity\Log;

use CoreDB\Kernel\Database\DataType\EnumaratedList;
use CoreDB\Kernel\Model;
use CoreDB\Kernel\Database\DataType\TableReference;
use CoreDB\Kernel\Database\DataType\LongText;

/**
 * Object relation with table intact_log
 * @author makarov
 */

class IntactLog extends Model
{
     /**
    * REQUEST_TYPE_ORDER description.
    */
    public const REQUEST_TYPE_ORDER = "order";
    /**
    * REQUEST_TYPE_PAYMENT description.
    */
    public const REQUEST_TYPE_PAYMENT = "payment";
    /**
    * REQUEST_TYPE_ACCOUNT description.
    */
    public const REQUEST_TYPE_ACCOUNT = "account";

    /**
    * @var TableReference $order
    * Order reference.
    */
    public TableReference $order;
    /**
    * @var TableReference $account
    * Account reference.
    */
    public TableReference $account;
    /**
    * @var EnumaratedList $request_type
    * Type of request sent.
    */
    public EnumaratedList $request_type;
    /**
    * @var LongText $request
    * Sent request.
    */
    public LongText $request;
    /**
    * @var LongText $response
    * Response returned.
    */
    public LongText $response;

    /**
     * @inheritdoc
     */
    public static function getTableName(): string
    {
        return "intact_log";
    }
}
