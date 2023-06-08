<?php

namespace App\Controller;

use App\Entity\Basket\Basket;
use App\Entity\Log\PaymentLog;
use App\Entity\UserAddress;
use CoreDB\Kernel\ServiceController;
use Src\Entity\Variable;
use Src\Entity\Watchdog;

class CronController extends ServiceController
{
    public function checkAccess(): bool
    {
        $cron_key = Variable::getByKey("cron_key");
        if (!$cron_key) {
            $cron_key = Variable::create("cron_key");
            $cron_key->map([
                "type" => "text",
                "value" => bin2hex(random_bytes(10)) // 20 characters, only 0-9a-f
            ]);
            $cron_key->save();
        }
        return isset($_GET["cron_key"]) ? $cron_key->value == $_GET["cron_key"] : false;
    }

    public function checkRun()
    {
        try {
            $this->checkUnSynchedAccounts();
            $this->checkUnsynchedOrders();
            $this->checkUnSynchedPayments();
        } catch (\Exception $ex) {
            Watchdog::log("Check run failed", $ex->getMessage());
            return $ex->getMessage();
        }
    }

    private function checkUnSynchedAccounts()
    {
        $unsynchedAccounts = UserAddress::getAll(["intact_synched" => 0], false);
            /** @var UserAddress $address */
        foreach ($unsynchedAccounts as $address) {
            $address->synchToIntact();
        }
    }

    private function checkUnsynchedOrders()
    {
        $unsynchedOrders = array_merge(
            Basket::getAll([
                "is_ordered" => 1,
                "intact_order_ref" => ""
            ]),
            Basket::getAll([
                "is_ordered" => 1,
                "intact_order_ref" => null
            ])
        );
        /** @var Basket $order */
        foreach ($unsynchedOrders as $order) {
            $order->sendIntact();
            $order->save();
        }
        $unsynchedOrders = Basket::getAll([
            "need_update_intact" => 1
        ]);
        /** @var Basket $order */
        foreach ($unsynchedOrders as $order) {
            $order->sendIntact();
            $order->save();
        }
    }

    private function checkUnSynchedPayments()
    {
        $payments = PaymentLog::getAll([
            "is_success" => 1,
            "intact_synched" => 0
        ]);
            /** @var PaymentLog $payment */
        foreach ($payments as $payment) {
            $payment->synchIntact();
            $payment->save();
        }
    }

    public function checkExpiredBaskets()
    {
        $expiredCheckedOutBaskets = \CoreDB::database()
        ->select(Basket::getTableName(), "b")
        ->condition("is_ordered", 0)
        ->condition("is_checked_out", 1)
        ->condition(
            "checkout_time",
            date("Y-m-d H:i:s", strtotime("-20 minutes")),
            "<"
        )
        ->select("b", ["ID"])
        ->execute()->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($expiredCheckedOutBaskets as $basketId) {
            /** @var Basket */
            $basket = Basket::get($basketId);
            $basket->uncheckout();
        }
    }
}
