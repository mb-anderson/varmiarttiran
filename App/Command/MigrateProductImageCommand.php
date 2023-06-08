<?php

namespace App\Command;

use App\Entity\Product\Product;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateProductImageCommand extends Command
{
    protected static $defaultName = "migrate:product-images";
    protected function configure()
    {
        $this->setDescription(
            "Migrate product images to provide adding more than one image."
        );
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Product[] */
        $products = Product::getAll([]);
        foreach ($products as $product) {
            if (!$product->image->getValue()) {
                continue;
            }
            $product->map([
                "product_picture" => [
                    [
                        "image" => $product->image->getValue()
                    ]
                ]
            ]);
            $product->save();
        }
        \CoreDB::database()->drop(Product::getTableName(), "image")
        ->execute();
        return Command::SUCCESS;
    }
}
