<?php

namespace App\Command;

use App\Entity\Banner;
use App\Entity\BannerProduct;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketProduct;
use App\Entity\Basket\BillingAddress;
use App\Entity\Basket\OrderAddress;
use App\Entity\Blog;
use App\Entity\BlogAttachment;
use App\Entity\Postcode\Postcode;
use App\Entity\Product\FavoriteProducts;
use App\Entity\Product\Product;
use App\Entity\Product\ProductCategory;
use App\Entity\Product\ProductPrice;
use App\Entity\Product\Stock;
use App\Entity\UserAdditionalAddress;
use App\Entity\UserAddress;
use CoreDB;
use CoreDB\Kernel\Database\QueryPreparerAbstract;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use Exception;
use PDO;
use Src\Entity\DynamicModel;
use Src\Entity\File;
use Src\Entity\Translation;
use Src\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    protected static $defaultName = "app:import-data";

    /** Example usage:
     * php bin/console.php app:import-data core_multisite_user atozold localhost
     * core_multisite1234 http://localhost/atoz/public_html
     *
     * /opt/plesk/php/7.4/bin/php bin/console.php app:import-data core_multisite_user atoz_old localhost
     * core_multisite1234 https://dev.atoz-catering.co.uk
     */
    private PDO $oldDbConnection;
    protected function configure()
    {
        $this->setDescription("Import data from old database");
        $this->addArgument('dbusername', InputArgument::REQUIRED, "DB User");
        $this->addArgument('dbname', InputArgument::REQUIRED, "DB Name");
        $this->addArgument('dbhost', InputArgument::REQUIRED, "DB Host");
        $this->addArgument('dbpass', InputArgument::REQUIRED, "DB Pass");
        $this->addArgument('host', InputArgument::REQUIRED, "Host (Site Host)");
        $this->addArgument('operation', InputArgument::OPTIONAL, "Operation (import-account)");
        $this->addArgument('data', InputArgument::OPTIONAL, "Data for operation (Eg: account number for import user)");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dbusername = $input->getArgument("dbusername");
        $dbname = $input->getArgument("dbname");
        $dbhost = $input->getArgument("dbhost");
        $dbpass = $input->getArgument("dbpass");
        $host = $input->getArgument("host");
        define("BASE_URL", $host ?: "");
        $isConnected = CoreDB::database()->checkConnection($dbhost, $dbname, $dbusername, $dbpass);
        if (!$isConnected) {
            $output->writeln("Cant connect to old database.");
            return Command::FAILURE;
        }
        $operation = $input->getArgument("operation");
        try {
            $this->oldDbConnection = new PDO("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbusername, $dbpass);
            $this->oldDbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->oldDbConnection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $this->oldDbConnection->query("SET NAMES UTF8");
            if ($operation == "import-account") {
                $accountNumber = $input->getArgument("data");
                $this->importUsers($output, $accountNumber);
            } else {
                // $this->importPostcodes($output);
                // $this->importBlogs($output);
                // $this->importBanners($output);
                // $this->importCategories($output);
                $this->importProducts($output);
                $this->importStock($output);
                $this->importUsers($output);
                $this->importFavorites($output);
                $this->importOrders($output);
            }
            return Command::SUCCESS;
        } catch (Exception $ex) {
            $output->writeln($ex->getMessage());
            return Command::FAILURE;
        }
    }

    private function executeQuery(QueryPreparerAbstract $query)
    {
        $statement = $this->oldDbConnection->prepare($query->getQuery());
        $statement->execute($query->getParams());
        return $statement;
    }

    public function importPostcodes(OutputInterface $output)
    {
        $output->writeln("<info>Postcode import started.</info>");
        $query = \CoreDB::database()->select("postcodes", "p")
        ->select("p", ["Nextday", "MOV", "MinimumDC", "percentdc", "PostCode"])
        ->orderBy("p.PostCode");

        $result = $this->executeQuery($query);

        \CoreDB::database()->beginTransaction();
        $success = 0;
        while ($row = $result->fetchObject()) {
            $postcode = Postcode::get(["postcode" => $row->PostCode]) ?: new Postcode();
            $days = [];
            foreach (str_split($row->Nextday) as $day) {
                if ($day <= 7) {
                    $days[] = $day;
                }
            }
            $postcodeData = [
                "postcode" => $row->PostCode,
                "minimum_order_price" => $row->MOV - $row->percentdc,
                "delivery" => $row->MinimumDC,
                "day" => $days
            ];
            $postcode->map($postcodeData);
            $postcode->save();
            $success++;
        }
        \CoreDB::database()->commit();
        $output->writeln("<info>Postcode import finished. {$success} postcode imported.</info>");
    }
    public function importBlogs(OutputInterface $output)
    {
        BlogAttachment::clear();
        \CoreDB::cleanDirectory(__DIR__ . "/../../public_html/files/uploaded/blog_attachments");
        $query = \CoreDB::database()->select("blog", "b")
        ->orderBy("blog_id DESC");
        $oldBlogs = $this->executeQuery($query)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($oldBlogs as $oldBlog) {
            $title = trim(strip_tags($oldBlog["blog_title"]));
            $blog = Blog::get(["title" => $title]) ?: new Blog();
            $dom = new DOMDocument("1.0", "UTF-8");
            @$dom->loadHTML(
                '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' .
                $oldBlog["blog_body"]
            );
            /** @var DOMNodeList */
            $images = $dom->getElementsByTagName("img");
            $attachments = [];
            /** @var DOMElement $image */
            foreach ($images as $image) {
                $imageSrc = $image->getAttribute("src");
                $file = $this->saveFile($imageSrc, BlogAttachment::class, "attachment");
                $attachments[] = [
                    "attachment" => $file->ID->getValue()
                ];
                $image->setAttribute("src", $file->getUrl());
            }
            $videos = $dom->getElementsByTagName("source");
            foreach ($videos as $video) {
                $videoSrc = $video->getAttribute("src");
                $file = $this->saveFile($videoSrc, BlogAttachment::class, "attachment");
                $attachments[] = [
                    "attachment" => $file->ID->getValue()
                ];
                $video->setAttribute("src", $file->getUrl());
            }
            if ($oldBlog["blog_img1"]) {
                $coverImage = $this->saveFile($oldBlog["blog_img1"], Blog::class, "cover_image")->ID->getValue();
            } else {
                $coverImage = null;
            }
            $blog->map([
                "title" => $title,
                "content" => str_replace("Â", "", $dom->saveHTML()) ,
                "published" => 1,
                "cover_image" => $coverImage,
                "blog_attachment" => $attachments,
            ]);
            $blog->save();
            \CoreDB::database()->update(Blog::getTableName(), [
                "created_at" => $oldBlog["blog_date"],
                "last_updated" => $oldBlog["blog_date"]
            ])->condition("ID", $blog->ID->getValue())
            ->execute();
        }
        $output->writeln(
            Translation::getTranslation(
                "%d blogs imported",
                [count($oldBlogs)]
            )
        );
    }

    public function importBanners(OutputInterface $output)
    {
        CoreDB::database()->query("SET FOREIGN_KEY_CHECKS = 0;")->execute();
        Banner::clear();
        BannerProduct::clear();
        CoreDB::database()->query("SET FOREIGN_KEY_CHECKS = 1;")->execute();
        \CoreDB::cleanDirectory(__DIR__ . "/../../public_html/files/uploaded/banner");
        $query = \CoreDB::database()->select("banners")
        ->condition("banner_id", 3);
        $oldBanner = $this->executeQuery($query)->fetch(PDO::FETCH_ASSOC);
        $banners = [
            [
                "desktop_image" => $oldBanner["img_desktop"],
                "mobile_image" => $oldBanner["img_small"],
                "title" => $oldBanner["img_title"],
            ],
            [
                "desktop_image" => $oldBanner["img_desktop2"],
                "mobile_image" => $oldBanner["img_small2"],
                "title" => $oldBanner["img_title2"],
            ],
            [
                "desktop_image" => $oldBanner["img_desktop3"],
                "mobile_image" => $oldBanner["img_small3"],
                "title" => $oldBanner["img_title3"],
            ],
            [
                "desktop_image" => $oldBanner["img_desktop4"],
                "mobile_image" => $oldBanner["img_small4"],
                "title" => $oldBanner["img_title4"],
            ]
        ];
        foreach ($banners as $oldBanner) {
            if (!$oldBanner["title"]) {
                continue;
            }
            $banner = Banner::get(["title" => $oldBanner["title"]]) ?: new Banner();
            $desktopImage = $this->saveFile(
                $oldBanner["desktop_image"],
                Banner::class,
                "desktop_image"
            );
            $mobileImage = $this->saveFile(
                $oldBanner["mobile_image"],
                Banner::class,
                "mobile_image"
            );
            $banner->map([
                "title" => $oldBanner["title"] ?: " ",
                "desktop_image" => $desktopImage ? $desktopImage->ID->getValue() : null,
                "mobile_image" => $mobileImage ? $mobileImage->ID->getValue() : null,
                "url" => "#"
            ]);
            $banner->save();
        }
        $output->writeln(
            Translation::getTranslation(
                "%d banners imported",
                [count($banners)]
            )
        );
    }

    private function saveFile($url, $class, $fieldName): ?File
    {
        $url = trim(str_replace("../", "", $url));
        if (!strpos($url, "://")) {
            $url = "https://www.atoz-catering.co.uk/{$url}";
        }
        $content = @file_get_contents($url);
        if (!$content) {
            echo "URL : " . $url . "\n Content null\n";
            return null;
        }
        $explodedUrl = explode("/", $url);
        $filename = array_pop($explodedUrl);

        $file = new File();
        $file->status->setValue(File::STATUS_PERMANENT);

        $file->file_name->setValue($filename);
        $file->extension->setValue(pathinfo($filename, PATHINFO_EXTENSION));

        $file_url = getcwd()
        . "/public_html/files/uploaded/" .
        $class::getTableName() . "/$fieldName/";
        is_dir($file_url) ?: mkdir($file_url, 0776, true);
        $file_path = $class::getTableName() . "/$fieldName/" . md5($filename . microtime());
        $file->file_path->setValue($file_path);
        file_put_contents(getcwd() . "/public_html/files/uploaded/{$file_path}", $content);

        $file->mime_type->setValue(mime_content_type(getcwd() . "/public_html/files/uploaded/{$file_path}"));
        $file->file_size->setValue(filesize(getcwd() . "/public_html/files/uploaded/{$file_path}"));
        $file->save();
        return $file;
    }

    /**
     * @return DOMElement[]
     */
    private function findAll(&$dom, $tag, $attribute, $constant)
    {
        $resList = $dom->getElementsByTagName($tag);
        $retarr = array();
        foreach ($resList as $element) {
            if (strpos($element->getAttribute($attribute), $constant) !== false) {
                $retarr[] = $element;
            }
        }
        return $retarr;
    }

    /**
     * @return DOMElement
     */
    private function find($dom, $tag, $attribute, $constant)
    {
        $returnList = $dom->getElementsByTagName($tag);
        foreach ($returnList as $element) {
            if (strpos($element->getAttribute($attribute), $constant) !== false) {
                return $element;
            }
        }
    }

    private function importCategories(OutputInterface $output)
    {
        $dom = new DOMDocument("1.0", "UTF-8");
        @$dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' .
            file_get_contents("https://www.atoz-catering.co.uk/")
        );
        $mainNav = $this->find($dom, "nav", "class", "navbar");
        $weight = 0;
        foreach ($this->findAll($mainNav, "li", "class", "nav-item dropdown") as $topItem) {
            $subItems = $this->findAll($topItem, "a", "class", "dropdown-item");
            if (!$subItems) {
                continue;
            }
            $name = $this->find($topItem, "a", "class", "nav-link")->textContent;
            $name = trim($name);
            $topCategory = ProductCategory::get(["name" => $name]) ?: new ProductCategory();
            $topCategory->map([
                "name" => $name,
                "weight" => $weight++
            ]);
            $topCategory->save();
            foreach ($subItems as $subItem) {
                $subname = trim($subItem->textContent);
                $code = str_replace("products.php?catcode=", "", $subItem->getAttribute("href"));
                if ($subname == "Miscellaneous") {
                    $subname = "Other";
                    $topCategory->code->setValue("");
                    $topCategory->save();
                }
                $subCategory = ProductCategory::get(["name" => $subname, "code" => $code]) ?: new ProductCategory();
                $subCategory->map([
                    "name" => $subname,
                    "code" => $code,
                    "weight" => $weight++,
                    "parent" => $topCategory->ID->getValue()
                ]);
                $subCategory->save();
            }
        }
        $output->writeln(Translation::getTranslation("%s category imported", [$weight + 1]));
    }

    private function importUsers(OutputInterface $output, $accountNumber = null)
    {
        $query = \CoreDB::database()->select("users", "u");
        if ($accountNumber) {
            $query->condition("account_no", $accountNumber);
        }
        $oldUsers = $this->executeQuery($query)->fetchAll(PDO::FETCH_ASSOC);
        $dublicateAccountNumbers = 0;
        $dublicateMails = 0;
        $linkedAccountMap = [];
        foreach ($oldUsers as $oldUser) {
            if (!$oldUser["email"] || !$oldUser["account_no"]) {
                $output->writeln(
                    Translation::getTranslation(
                        "<error>%s id user has no email or account number. Failed.</error>",
                        [$oldUser["user_id"]]
                    )
                );
                continue;
            }
            $user = DynamicModel::get(["email" => $oldUser["email"]], User::getTableName()) ?:
                new DynamicModel(User::getTableName());
            $data = [
                "company_name" => $oldUser["company"] ?: " ",
                "name" => $oldUser["full_name"],
                "surname" => "",
                "email" => $oldUser["email"],
                "password" => $oldUser["pass"],
                "active" => 1,
                "email_verified" => 1,
                "username" => $this->generateUsername($oldUser["email"]),
                "pay_optional_at_checkout" => 1
            ];
            $addressByAccount = UserAddress::get(["account_number" => $oldUser["account_no"]]);
            if ($addressByAccount && $user->ID->getValue() != $addressByAccount->user->getValue()) {
                $dublicateAccountNumbers++;
                continue;
            }
            $userByEmail = User::getUserByEmail($data["email"]);
            if (
                ($userByEmail && $userByEmail->ID->getValue() != $user->ID->getValue())
            ) {
                $dublicateMails++;
                continue;
            } else {
                $data["username"] = $this->generateUsername($data["email"]);
            }
            if (!$data["name"]) {
                $data["name"] = " ";
            }
            $user->map($data);
            $user->save();

            if ($oldUser["date_added"]) {
                \CoreDB::database()->update(User::getTableName(), [
                    "created_at" => $oldUser["date_added"],
                    "last_updated" => $oldUser["date_added"]
                ])->condition("ID", $user->ID->getValue())
                ->execute();
            }

            $user = User::get($user->ID->getValue());
            $adressData = $this->executeQuery(
                \CoreDB::database()->select("customers")
                ->condition("accno", $oldUser["account_no"])
            )->fetch(PDO::FETCH_ASSOC);
            if ($adressData) {
                $user->map([
                    "address" => [
                        [
                            "account_number" => $oldUser["account_no"],
                            "address" => $adressData["adr1"] . " " . $adressData["adr2"],
                            "town" => $adressData["adr3"] ?: " ",
                            "county" => $adressData["adr4"] ?: " ",
                            "postalcode" => $adressData["postcode"] ?: " ",
                            "country" => 231, // GB
                            "phone" => $adressData["telephone"],
                            "mobile" => $adressData["mob"],
                            "default" => 1,
                            "intact_synched" => 1
                        ]
                    ]
                ]);
                $user->save();
            }

            $linkedAccounts = [
                $oldUser["linked_acc1"],
                $oldUser["linked_acc2"],
                $oldUser["linked_acc3"],
                $oldUser["linked_acc4"],
                $oldUser["linked_acc5"]
            ];
            $linkedAccounts = array_filter($linkedAccounts);
            $linkedAccountMap[$user->ID->getValue()] = $linkedAccounts;
        }
        if (!$accountNumber) {
            $query = \CoreDB::database()->select("adminusers", "u");
            $oldAdminUsers = $this->executeQuery($query)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($oldAdminUsers as $oldUser) {
                $user = DynamicModel::get(["email" => $oldUser["email"]], User::getTableName()) ?:
                    new DynamicModel(User::getTableName());
                $data = [
                    "company_name" => $user->company_name->getValue() ?: "AtoZ Catering",
                    "name" => $oldUser["first_name"],
                    "surname" => $oldUser["last_name"],
                    "email" => $oldUser["email"],
                    "username" => $this->generateUsername($oldUser["email"]),
                    "password" => $oldUser["pass"],
                    "created_at" => date("Y-m-d H:i:s", strtotime($oldUser["reg_date"])),
                    "active" => 1,
                    "email_verified" => 1,
                ];
                $userByEmail = User::getUserByEmail($data["email"]);
                if (
                    ($userByEmail && $userByEmail->ID->getValue() != $user->ID->getValue())
                ) {
                    $data["ID"] = $userByEmail->ID->getValue();
                }
                if (!$data["name"]) {
                    $data["name"] = " ";
                }
                if (!$user->ID->getValue()) {
                    $data["company_name"] = "AtoZ Catering";
                }
                $user->map($data);
                $user->save();

                /** @var User */
                $user = User::getUserByEmail($data["email"]);
                if (!$user->isUserInRole("Manager")) {
                    $user->roles->setValue([2]);
                    $user->save();
                }
            }
        }

        foreach ($linkedAccountMap as $userId => $linkedAccounts) {
            $user = User::get($userId);
            $linked_account = [];
            foreach ($linkedAccounts as $account) {
                $subAccountAddress = UserAdditionalAddress::get(["account_number" => $account]);
                $adressData = $this->executeQuery(
                    \CoreDB::database()->select("customers")
                    ->condition("accno", $account)
                )->fetch(PDO::FETCH_ASSOC);
                if ($adressData && !$subAccountAddress) {
                    $userAddress = $user->additional_delivery_address->getValue();
                    $userAddress[] = [
                        "user" => $user->ID->getValue(),
                        "account_number" => $account,
                        "address" => $adressData["adr1"] . " " . $adressData["adr2"],
                        "town" => $adressData["adr3"] ?: " ",
                        "county" => $adressData["adr4"] ?: " ",
                        "postalcode" => $adressData["postcode"] ?: " ",
                        "country" => 231, // GB
                        "phone" => $adressData["telephone"],
                        "mobile" => $adressData["mob"],
                        "intact_synched" => 1
                    ];
                    $user->additional_delivery_address->setValue($userAddress);
                    $output->writeln(
                        "<warning>Linked account saved " . $account . "</warning>"
                    );
                } elseif ($subAccountAddress) {
                    $linked_account[] = [
                        "sub_account" => $subAccountAddress->user->getValue()
                    ];
                } else {
                    $output->writeln(
                        "<warning>Linked account not found " . $account . "</warning>"
                    );
                }
            }
            $user->map([
                "linked_account" => $linked_account
            ]);
            $user->save();
        }

        $output->writeln(
            Translation::getTranslation("%s users imported.", [count($oldUsers)])
        );
        $output->writeln(
            "{$dublicateAccountNumbers} dublicate account numbers exist."
        );
        $output->writeln(
            "{$dublicateMails} dublicate mails exist."
        );
        $output->writeln(
            Translation::getTranslation("%s admin users imported.", [count($oldAdminUsers)])
        );
    }

    private function generateUsername(string $email)
    {
        $mailStart = explode("@", $email)[0];
        $userByMail = User::getUserByEmail($email);
        $tempUserName = $mailStart;
        $userByUsername = User::getUserByUsername($tempUserName);
        while (
            $userByUsername &&
            $userByUsername->ID->getValue() != (
                $userByMail ? $userByMail->ID->getValue() : null
            )
        ) {
            $tempUserName = $mailStart . random_int(0, 1000);
            $userByUsername = User::getUserByUsername($tempUserName);
        }
        return $tempUserName;
    }

    private function importFavorites(OutputInterface $output)
    {
        $query = \CoreDB::database()->select("favorites", "f")
            ->join("users", "u", "f.user_id = u.user_id")
            ->select("u", ["email"])
            ->select("f", ["stock_code"]);
        $favorites = $this->executeQuery($query)->fetchAll(PDO::FETCH_ASSOC);
        $success = 0;
        $fail = 0;
        foreach ($favorites as $favorite) {
            $product = Product::getByStockcode($favorite["stock_code"]);
            $user = User::getUserByEmail($favorite["email"]);
            if ($product && $user) {
                $data = [
                    "product" => $product->ID->getValue(),
                    "user" => $user->ID->getValue()
                ];
                $favoriteRecord = FavoriteProducts::get($data);
                if (!$favoriteRecord) {
                    $favoriteRecord = new FavoriteProducts();
                    $favoriteRecord->map($data);
                    $favoriteRecord->save();
                }
                $success++;
            } else {
                $fail++;
            }
        }

        $output->writeln(Translation::getTranslation(
            "%s favorite successs, %s favorite fail",
            [$success, $fail]
        ));
    }

    private function importOrders(OutputInterface $output)
    {
        $success = 0;
        $userFail = 0;
        $orderItemFail = 0;
        // CoreDB::database()->query("SET FOREIGN_KEY_CHECKS = 0;")->execute();
        // BasketProduct::clear();
        // OrderAddress::clear();
        // BillingAddress::clear();
        // Basket::clear();
        // CoreDB::database()->query("SET FOREIGN_KEY_CHECKS = 1;")->execute();
        $maxOrderId = \CoreDB::database()->select(Basket::getTableName())
        ->selectWithFunction(["MAX(order_id) AS max_order_id"])
        ->execute()->fetchObject()->max_order_id;
        $query = CoreDB::database()->select("orders", "o")
            ->leftjoin("payment_log", "pl", "pl.orderNo = o.order_id")
            ->leftjoin("users", "u", "o.user_id = u.user_id")
            ->select("o", ["*"])
            ->select("u", ["email"])
            ->select("pl", ["total AS paid_amount"])
            ->orderBy("order_id ASC")
            ->limit(1000);
        if ($maxOrderId) {
            $query->condition("o.order_id", $maxOrderId, ">");
        }
        $orders = $this->executeQuery($query);

        while ($order = $orders->fetch(\PDO::FETCH_ASSOC)) {
            $user = User::getUserByEmail(strval($order["email"]));
            if (!$user) {
                $userFail++;
                $user = new User();
            }
            $newOrder = new DynamicModel(Basket::getTableName());
            $branchMap = [
                1 => 1,
                3 => 2,
                4 => 3,
                5 => 4,
            ];
            $orderData = [
                "user" => $user->ID->getValue(),
                "total" => $order["total"],
                "order_time" => $order["order_date"],
                "order_notes" => $order["comment"],
                "intact_order_ref" => $order["reference"],
                "is_ordered" => 1,
                "is_checked_out" => 1,
                "paid_online" => $order["paid"],
                "type" => strpos($order["collectordeliver"], "Deliver") === 0 ?
                Basket::TYPE_DELIVERY : Basket::TYPE_COLLECTION,
                "due_to" => $order["duedate"],
                "branch" => @$branchMap[$order["storecode"]],
                "paid_amount" => $order["paid_amount"],
                "order_id" => $order["order_id"]
            ];
            $newOrder->map($orderData);
            $newOrder->save();
            $addressData = [
                    "order" => $newOrder->ID->getValue(),
                    "account_number" => $order["acc_name"],
                    "address" => $order["delivery_address"] ?: " ",
                    "town" => " ",
                    "county" => " ",
                    "country" => 231, //GB,
                    "postalcode" => " ",
            ];
            $orderAddress = new OrderAddress();
            $orderAddress->map($addressData);
            $orderAddress->save();
            $billingAddress = new BillingAddress();
            $billingAddress->map($addressData);
            $billingAddress->save();
            $basketItemsQuery = CoreDB::database()->select("order_contents", "oc")
                ->leftjoin("items", "i", "i.code = oc.item_id")
                ->condition("oc.order_id", $order["order_id"])
                ->select("oc", ["quantity", "price"])
                ->select("i", ["code"]);
            $basketItems = $this->executeQuery($basketItemsQuery)->fetchAll(PDO::FETCH_ASSOC);
            $subtotal = 0;
            foreach ($basketItems as $basketItem) {
                $product = Product::getByStockcode(strval($basketItem["code"]));
                if (!$product) {
                    $orderItemFail++;
                    $productId = null;
                } else {
                    $productId = $product->ID->getValue();
                }
                $orderItemData = [
                    "basket" => $newOrder->ID->getValue(),
                    "product" => $productId,
                    "item_vat" => 0,
                    "quantity" => $basketItem["quantity"],
                    "item_per_price" => $basketItem["price"],
                    "total_price" => $basketItem["quantity"] * $basketItem["price"],
                ];
                $subtotal += $basketItem["price"] * $basketItem["quantity"];
                $orderItem = new DynamicModel(BasketProduct::getTableName());
                $orderItem->map($orderItemData);
                $orderItem->save();
            }
            \CoreDB::database()->update(Basket::getTableName(), [
                "subtotal" => $subtotal
                ])->condition("ID", $newOrder->ID)->execute();
            $success++;
        }
        $output->writeln(Translation::getTranslation(
            "%s order successs, %s user not found, %s product not found",
            [$success, $userFail, $orderItemFail]
        ));
    }

    private function importProducts(OutputInterface $output)
    {
        $success = 0;
        $fail = 0;
        $oldProductsQuery = CoreDB::database()->select("items", "p")
            ->leftjoin("item_info", "ii", "ii.code = p.code")
            ->leftjoin("max_qty", "mq", "mq.item_code = p.code")
            ->leftjoin("exclude_stock", "es", "es.sku = p.code")
            ->leftjoin("sprice", "sp", "sp.itemcode = p.code")
            ->select("p", [
                "code",
                "item_desc",
                "altdesc",
                "exdetail1",
                "exdetail2",
                "catcode",
                "selling1",
                "selling3",
                "totalinstk",
                "defvatcode",
                "marmasstgy"
                ])
            ->select("ii", [
                "item_desc AS iidesc",
                "brand",
                "cooking_ins",
                "dietary",
                "ingredients",
                "packaging",
                "nutritional",
                "certificates",
                "generalinfo",
                "aditionalinfo"
            ])
            ->select("sp", [
                "selling1 AS sp1",
                "selling2 AS sp2",
                "selling3 AS sp3",
                "selling4 AS sp4",
                "selling5 AS sp5",
                "selling6 AS sp6",
                "selling7 AS sp7",
                "selling8 AS sp8",
                "selling9 AS sp9",
                "qtybrk1 AS qtybrk",
                "validfrom",
                "validto"
            ])
            ->select("mq", ["max_qty_no"])
            ->select("es", ["sku"])
            ->condition("noebiz", "False")
            ->condition("p.selling1", "0.00", "!=");
        $oldProducts = $this->executeQuery($oldProductsQuery)->fetchAll(PDO::FETCH_ASSOC);

        $categoryMap = \CoreDB::database()->select(ProductCategory::getTableName())
        ->select("", ["code", "ID"])
        ->execute()->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($oldProducts as $oldProduct) {
            try {
                $product = Product::getByStockcode($oldProduct["code"]) ?:
                    new Product();
                if (!$product->image->getValue()) {
                    $image = $this->saveFile("ppics/{$oldProduct["code"]}.jpg", Product::class, "image");
                    $image = $image ? $image->ID->getValue() : null;
                } else {
                    $image = $product->image->getValue();
                }
                $productInfo = [];

                if ($oldProduct["generalinfo"]) {
                    $dom = new DOMDocument();
                    @$dom->loadHTML($oldProduct["generalinfo"]);
                    $images = $dom->getElementsByTagName("img");
                    /** @var DOMElement $image */
                    foreach ($images as $imageEl) {
                        $url = $imageEl->getAttribute("src");
                        $url = trim(str_replace("../", "", $url));
                        if (!strpos($url, "://")) {
                            $url = "https://www.atoz-catering.co.uk/{$url}";
                        }
                        $imageData = file_get_contents($url);
                        $extension = pathinfo($url, PATHINFO_EXTENSION);
                        $imageEl->setAttribute("src", "data:image/{$extension};base64," . base64_encode($imageData));
                    }
                    $productInfo[] = [
                        "title" => "General Info",
                        "description" => $dom->saveHTML() . ""
                    ];
                }

                if ($oldProduct["brand"]) {
                    $productInfo[] = [
                        "title" => "Brand",
                        "description" => $oldProduct["brand"]
                    ];
                }
                if ($oldProduct["cooking_ins"]) {
                    $productInfo[] = [
                        "title" => "Instructions for Baking",
                        "description" => $oldProduct["cooking_ins"]
                    ];
                }
                if ($oldProduct["dietary"]) {
                    $productInfo[] = [
                        "title" => "Product suitability",
                        "description" => $oldProduct["dietary"]
                    ];
                }

                if ($oldProduct["ingredients"]) {
                    $productInfo[] = [
                        "title" => "Ingredients",
                        "description" => $oldProduct["ingredients"]
                    ];
                }

                if ($oldProduct["packaging"]) {
                    $productInfo[] = [
                        "title" => "Storage Instructions",
                        "description" => $oldProduct["packaging"]
                    ];
                }

                if ($oldProduct["nutritional"]) {
                    $productInfo[] = [
                        "title" => "Nutritional Values",
                        "description" => $oldProduct["nutritional"]
                    ];
                }

                if ($oldProduct["certificates"]) {
                    $productInfo[] = [
                        "title" => "Certificates & Documents",
                        "description" => $oldProduct["certificates"]
                    ];
                }

                if ($oldProduct["aditionalinfo"]) {
                    $productInfo[] = [
                        "title" => "Additional Info",
                        "description" => $oldProduct["aditionalinfo"]
                    ];
                }

                $prices = [
                    [
                        "item_count" => 0,
                        "price" => $oldProduct["selling1"],
                        "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                    ],
                    [
                        "item_count" => 0,
                        "price" => $oldProduct["selling3"],
                        "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                    ]
                ];

                for ($i = 1; $i <= 4; $i++) {
                    $oldProduct["sp" . $i] = floatval($oldProduct["sp" . $i]);
                }

                if ($oldProduct["sp1"] != "0.00") {
                    $prices[] = [
                        "item_count" => 0,
                        "price" => $oldProduct["sp1"],
                        "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                    ];
                }

                if ($oldProduct["sp3"] != "0.00") {
                    $prices[] = [
                        "item_count" => 0,
                        "price" => $oldProduct["sp3"],
                        "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                    ];
                }

                if ($oldProduct["sp2"] != "0.00") {
                    $prices[] = [
                        "item_count" => $oldProduct["qtybrk"] ? round($oldProduct["qtybrk"]) : 2,
                        "price" => $oldProduct["sp2"],
                        "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                    ];
                }

                if ($oldProduct["sp4"] != "0.00") {
                    $prices[] = [
                        "item_count" => 2,
                        "price" => $oldProduct["sp4"],
                        "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                    ];
                }

                if ($oldProduct["sp5"] != "0.00") {
                    $prices[] = [
                        "item_count" => 0,
                        "price" => $oldProduct["sp5"],
                        "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                    ];
                }

                if ($oldProduct["sp6"] != "0.00") {
                    $prices[] = [
                        "item_count" => 0,
                        "price" => $oldProduct["sp6"],
                        "price_type" => ProductPrice::PRICE_TYPE_DELIVERY
                    ];
                }

                if ($oldProduct["sp7"] != "0.00") {
                    $prices[] = [
                        "item_count" => 0,
                        "price" => $oldProduct["sp7"],
                        "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                    ];
                }

                if ($oldProduct["sp8"] != "0.00") {
                    $prices[] = [
                        "item_count" => 0,
                        "price" => $oldProduct["sp8"],
                        "price_type" => ProductPrice::PRICE_TYPE_COLLECTION
                    ];
                }

                $productData = [
                    "stockcode" => $oldProduct["code"],
                    "image" => $image,
                    "title" => $oldProduct["item_desc"],
                    "alt_desc" => $oldProduct["altdesc"] . (
                        $oldProduct["exdetail1"] ? "<br>" . $oldProduct["exdetail1"] : ""
                    ) . (
                        $oldProduct["exdetail2"] ? "<br>" . $oldProduct["exdetail2"] : ""
                    ),
                    "description" => $oldProduct["iidesc"],
                    "category" => @$categoryMap[$oldProduct["catcode"]],
                    "published" => 1,
                    "vat" => $oldProduct["defvatcode"] == "1" ? 20 : (
                        $oldProduct["defvatcode"] == "2" ? 5 : 0
                    ),
                    "maximum_order_count" => $oldProduct["max_qty_no"] ?: null,
                    "exclude_stock" => $oldProduct["sku"] ? 1 : 0,
                    "product_info" => $productInfo,
                    "price" => $prices,
                    "marmasstgy" => $oldProduct["marmasstgy"]
                ];
                if ($oldProduct["sp9"] != "0.00") {
                    $productData["weight"] = $oldProduct["sp9"];
                }
                if ($oldProduct["validfrom"]) {
                    $productData["sprice_valid_from"] = date("Y-m-d", strtotime($oldProduct["validfrom"]));
                } else {
                    $productData["sprice_valid_from"] = \CoreDB::currentDate();
                }
                if ($oldProduct["validto"]) {
                    $productData["sprice_valid_to"] = date("Y-m-d", strtotime($oldProduct["validto"]));
                }
                $product->map($productData);
                $product->save();
                $success++;
            } catch (Exception $ex) {
                $output->writeln($ex->getMessage());
                $fail++;
            }
        }

        $output->writeln(Translation::getTranslation(
            "%s product successs, %s product fail",
            [$success, $fail]
        ));
    }

    private function importStock(OutputInterface $output)
    {
        $success = 0;
        $fail = 0;
        $branchMap = [
            "hornsey01" => 1,
            "leyton03" => 2,
            "newcross04" => 3,
            "acton05" => 4,
        ];
        $stockQuery = CoreDB::database()->select("stock");
        $StockData = $this->executeQuery($stockQuery)
        ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($StockData as $row) {
            $product = Product::getByStockcode($row["sku"]);
            if (!$product) {
                $fail++;
                continue;
            }
            foreach ($row as $branchName => $stockQuantity) {
                if (!@$branchMap[$branchName]) {
                    continue;
                }
                $stock = Stock::get([
                    "product" => $product->ID->getValue(),
                    "branch" => $branchMap[$branchName]
                    ]) ?: new Stock();
                $stock->map([
                    "product" => $product->ID->getValue(),
                    "branch" => $branchMap[$branchName],
                    "quantity" => $stockQuantity
                ]);
                $stock->save();
                $success++;
            }
        }
        $output->writeln(Translation::getTranslation(
            "%s stock record successs, %s stock record fail",
            [$success, $fail]
        ));
    }
}
