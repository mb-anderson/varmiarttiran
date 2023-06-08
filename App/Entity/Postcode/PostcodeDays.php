<?php

namespace App\Entity\Postcode;

use CoreDB\Kernel\Model;
use CoreDB\Kernel\Database\DataType\TableReference;

/**
 * Object relation with table postcode_days
 * @author makarov
 */

class PostcodeDays extends Model
{
    /**
    * @var TableReference $postcode
    * Postcode reference.
    */
    public TableReference $postcode;
    /**
    * @var TableReference $day
    * Day reference.
    */
    public TableReference $day;

    /**
     * @inheritdoc
     */
    public static function getTableName(): string
    {
        return "postcode_days";
    }
}
