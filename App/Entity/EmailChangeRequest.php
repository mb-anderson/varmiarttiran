<?php

namespace App\Entity;

use CoreDB\Kernel\Model;
use CoreDB\Kernel\Database\DataType\TableReference;
use CoreDB\Kernel\Database\DataType\ShortText;

/**
 * Object relation with table email_change_requests
 * @author makarov
 */

class EmailChangeRequest extends Model
{
    /**
    * @var TableReference $account
    * User reference.
    */
    public TableReference $account;
    /**
    * @var ShortText $new_mail
    * Requested change email.
    */
    public ShortText $new_mail;
    /**
    * @var ShortText $ip_adress
    * Ip address of user requesting change.
    */
    public ShortText $ip_address;
    /**
     * @inheritdoc
     */
    public static function getTableName(): string
    {
        return "email_change_requests";
    }
}
