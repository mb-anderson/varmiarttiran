<?php

namespace App\Command;

use App\Entity\Customer;
use App\Entity\Product\Product;
use App\Entity\Product\ProductCategory;
use App\Entity\Product\ProductPrice;
use App\Entity\UserAddress;
use CoreDB;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportItemsCommand extends Command
{
    protected static $defaultName = "import:items";

    private InputInterface $input;
    private OutputInterface $output;
    private $itemsFilePath;
    private $spriceFilePath;
    private $customersFilePath;

    protected function configure()
    {
        $this->setDescription(
            "Import products from Items.csv, Product prices from Sprice.csv"
        );
        $this->addArgument('items_file_path', InputArgument::REQUIRED, "Items.csv file path.");
        $this->addArgument('sprice_file_path', InputArgument::REQUIRED, "SPrice.csv file path.");
        $this->addArgument('customers_file_path', InputArgument::OPTIONAL, "Customers.csv file path.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->itemsFilePath = $input->getArgument("items_file_path");
        $this->spriceFilePath = $input->getArgument("sprice_file_path");
        $this->customersFilePath = $input->getArgument("customers_file_path");
        if (!is_file($this->itemsFilePath)) {
            throw new Exception("Items.csv file cannot found on path: " . $this->itemsFilePath);
        }
        if (!is_file($this->spriceFilePath)) {
            throw new Exception("SPrice.csv file cannot found on path: " . $this->spriceFilePath);
        }
        if ($this->customersFilePath && !is_file($this->customersFilePath)) {
            throw new Exception("Customers.csv file cannot found on path: " . $this->customersFilePath);
        }
        \CoreDB::database()->beginTransaction();
        $this->importItems();
        $this->importSprice();
        CoreDB::database()->commit();
        if ($this->customersFilePath) {
            $this->importCustomers();
            CoreDB::database()->commit();
        }
        return Command::SUCCESS;
    }

    private function importItems()
    {
        $this->output->writeln("<info>Items import started.</info>");
        $file = fopen($this->itemsFilePath, "r");
        $categoryMap = \CoreDB::database()->select(ProductCategory::getTableName())
        ->select("", ["code", "ID"])
        ->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);
        \CoreDB::database()->update(
            Product::getTableName(),
            [
                "weight" => 0
            ]
        )->execute();
        while (!feof($file)) {
            $data = fgetcsv($file, 5000, ",");
            if (!$data) {
                continue;
            }
            $product = Product::getByStockcode($data[0]);
            if (!$product) {
                $this->output->writeln(
                    "<comment>Stockcode not found: " . $data[0] .
                    ". New product will create.</comment>"
                );
                $product = new Product();
            }
            $title = $data[1] . (
                $data[3] ? " " . $data[3] : ""
            ) . (
                $data[4] ? " " . $data[4] : ""
            );
            $product->map([
                "stockcode" => $data[0], //code
                "title" => ($title ?: $product->title->getValue()) ?: " ", //item_desc
                "alt_desc" =>  $data[2],
                "category" => @$categoryMap[$data[8]],
                "vat" => $data[11] == "1" ? 20 : (
                    $data[11] == "2" ? 5 : 0
                ),
                "price" => [
                    [
                        "item_count" => 0,
                        "price" => $data[12], // selling1
                        "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                    ],
                    [
                        "item_count" => 0,
                        "price" => $data[14], //selling3
                        "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                    ]
                ],
                "published" => $data[16] == "False" ? 1 : 0,
                "marmasstgy" => $data[21]
            ]);
            $product->save();
            $this->output->writeln("<info>Item import complete: " . $data[0] . "<info>");
        }
        fclose($file);
        $this->output->writeln("<info>Items import finished.</info>");
    }

    private function importSprice()
    {
        $this->output->writeln("<info>SPrice import started.</info>");
        $file = fopen($this->spriceFilePath, "r");
        while (!feof($file)) {
            $data = fgetcsv($file, 5000, ",");
            if (!$data || $data[0] !== "WEB-STD") {
                continue;
            }
            $product = Product::getByStockcode($data[2]);
            if (!$product) {
                $this->output->writeln("<comment>Stockcode not found: " . $data[2] . "</comment>");
                continue;
            }
            $oldPrices = $product->price->getValue();
            $prices = [
                $oldPrices[0], $oldPrices[1]
            ];

            if ($data[3] != 0) {
                $prices[] = [
                    "item_count" => 0,
                    "price" => $data[3],
                    "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                ];
            }

            if ($data[5] != 0) {
                $prices[] = [
                    "item_count" => 0,
                    "price" => $data[5],
                    "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                ];
            }

            if ($data[4] != 0) {
                $prices[] = [
                    "item_count" => $data[13] > 0 ? round($data[13]) : 2,
                    "price" => $data[4],
                    "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                ];
            }

            if ($data[6] != 0) {
                $prices[] = [
                    "item_count" => 2,
                    "price" => $data[6],
                    "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                ];
            }
            if ($data[7] != 0) {
                $prices[] = [
                    "item_count" => 0,
                    "price" => $data[7],
                    "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                ];
            }
            if ($data[8] != 0) {
                $prices[] = [
                    "item_count" => 2,
                    "price" => $data[8],
                    "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                ];
            }
            if ($data[9] != 0) {
                $prices[] = [
                    "item_count" => 0,
                    "price" => $data[9],
                    "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                ];
            }
            if ($data[10] != 0) {
                $prices[] = [
                    "item_count" => 2,
                    "price" => $data[10],
                    "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                ];
            }
            $product->price->setValue($prices);
            $product->weight->setValue(
                $data[11]
            );
            if ($data[20]) {
                $product->sprice_valid_from->setValue(
                    date("Y-m-d", strtotime($data[20]))
                );
            }
            if ($data[21]) {
                $product->sprice_valid_to->setValue(
                    date("Y-m-d", strtotime($data[21]))
                );
            }
            $product->save();
            $this->output->writeln("<info>Sprice import complete: " . $data[2] . "<info>");
        }
        fclose($file);
        $this->output->writeln("<info>SPrice import finished.</info>");
    }

    private function importCustomers()
    {
        $this->output->writeln("<info>Customers import started.</info>");
        $file = fopen($this->customersFilePath, "r");
        while (!feof($file)) {
            $data = fgetcsv($file, 5000, ",");
            if (
                !$data || UserAddress::get([
                "account_number" => $data[0]
                ], false)
            ) {
                continue;
            }
            $customer = Customer::get(["account_number" => $data[0]]) ?: new Customer();
            $customer->map([
                "company_name" => $data[1],
                "name" => $data[8],
                "email" => $data[7],
                "account_number" => $data[0],
                "address" => $data[2] . " " . $data[3],
                "town" => $data[4],
                "county" => $data[5],
                "postalcode" => $data[9],
                "phone" => $data[6],
                "mobile" => $data[10]
            ]);
            $customer->save();
            $this->output->writeln("<info>Customer import complete: " . $data[0] . "<info>");
        }
        fclose($file);
        $this->output->writeln("<info>Customers import finished.</info>");
    }
}
