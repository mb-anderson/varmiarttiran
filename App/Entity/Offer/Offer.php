<?php

namespace App\Entity\Offer;

use App\Entity\Product\Product;
use CoreDB\Kernel\Model;
use CoreDB\Kernel\Database\DataType\TableReference;
use CoreDB\Kernel\Database\DataType\FloatNumber;
use CoreDB\Kernel\Database\DataType\Integer;

/**
 * Object relation with table offers
 * @author makarov
 */

class Offer extends Model
{
    /**
    * @var TableReference $product
    * Product reference
    */
    public TableReference $product;
    /**
    * @var TableReference $user
    * User reference
    */
    public TableReference $user;
    /**
    * @var FloatNumber $offer
    * Offer price
    */
    public FloatNumber $offer;

    /**
     * @inheritdoc
     */
    public static function getTableName(): string
    {
        return "offers";
    }

    public static function getMaxOffer(Product $product)
    {
        /**
         * @var int $maxOfferId
         * Maximum offer's id of the product
         */
        $maxOfferId = \CoreDB::database()->select(Offer::getTableName())
        ->condition("product", $product->ID->getValue())
        ->selectWithFunction(["MAX(offer) AS max_offer_id"])
        ->execute()->fetchObject()->max_offer_id;
        return $maxOfferId ?: 0;
    }
}
