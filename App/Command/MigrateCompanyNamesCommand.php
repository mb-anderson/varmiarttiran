<?php

namespace App\Command;

use App\Entity\Basket\BillingAddress;
use App\Entity\Basket\OrderAddress;
use App\Entity\CustomUser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCompanyNamesCommand extends Command
{
    protected static $defaultName = "migrate:company_name";

    protected function configure()
    {
        $this->setDescription(
            "Migrate company_name field from user to address"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userIds = \CoreDB::database()->select(CustomUser::getTableName(), "cu")
        ->select("cu", ["ID"])
        ->execute()->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($userIds as $userId) {
            /** @var CustomUser */
            $user = CustomUser::get($userId);
            $address = $user->address->getValue();
            $accountNumbers = [];
            if (!empty($address)) {
                $address[0]["company_name"] = $user->company_name->getValue();
                $accountNumbers = [$address[0]["account_number"]];
            }
            $user->address->setValue($address);
            $additionalAddresses = $user->additional_delivery_address->getValue();
            foreach ($additionalAddresses as &$additionalAddress) {
                $additionalAddress["company_name"] = $user->company_name->getValue();
                $accountNumbers[] = $additionalAddress["account_number"];
            }
            $user->additional_delivery_address->setValue($additionalAddresses);
            if (!empty($accountNumbers)) {
                \CoreDB::database()->update(OrderAddress::getTableName(), [
                    "company_name" => $user->company_name->getValue()
                ])->condition("account_number", $accountNumbers, "IN")
                ->execute();
                \CoreDB::database()->update(BillingAddress::getTableName(), [
                    "company_name" => $user->company_name->getValue()
                ])->condition("account_number", $accountNumbers, "IN")
                ->execute();
            }
            try {
                $user->save();
            } catch (\Exception $ex) {
                $output->writeln($ex->getMessage());
                return Command::FAILURE;
            }
        }
        try {
            \CoreDB::database()->drop(CustomUser::getTableName(), "company_name")
            ->execute();
        } catch (\Exception $ex) {
            $output->writeln($ex->getMessage());
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
