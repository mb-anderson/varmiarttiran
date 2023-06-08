<?php

namespace Src\Entity;

use CoreDB\Kernel\Database\DataType\LongText;
use CoreDB\Kernel\Database\DataType\ShortText;
use CoreDB\Kernel\Model;

/**
 * Object relation with table emails
 * @author makarov
 */

class Email extends Model
{
    public ShortText $key;
    public LongText $en;
    public LongText $tr;

    /**
     * @inheritdoc
     */
    public static function getTableName(): string
    {
        return "emails";
    }
}
