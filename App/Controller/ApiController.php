<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomUser;
use App\Entity\EmailChangeRequest;
use App\Entity\Search\SearchApi;
use App\Entity\UserAddress;
use App\Entity\UserLinkedAccount;
use CoreDB\Kernel\Messenger;
use CoreDB\Kernel\ServiceController;
use Exception;
use Src\Entity\Translation;
use Src\Entity\User;
use Src\Entity\Variable;

class ApiController extends ServiceController
{
    /**
     * @inheritdoc
     */
    public function checkAccess(): bool
    {
        return boolval($this->method);
    }

    public function search()
    {
        $search = $_GET["search"];
        return $search ? SearchApi::getSearchResult($search) : [];
    }

    public function saveEmailChangeRequest()
    {
        $email = @$_POST["email"];
        $name = @$_POST["name"];
        $surname = @$_POST["surname"];
        $password = @$_POST["password"];
        $passwordAgain = @$_POST["password_again"];
        $accountnumber = @$_POST["accountnumber"];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception(
                Translation::getTranslation("enter_valid_mail")
            );
        } elseif (User::getUserByEmail($email)) {
            throw new \Exception(
                Translation::getTranslation("email_not_available")
            );
        } elseif ($password != $passwordAgain) {
            throw new \Exception(
                Translation::getTranslation("password_match_error")
            );
        } elseif (
            !User::validatePassword($password)
        ) {
            throw new \Exception(
                Translation::getTranslation("password_validation_error")
            );
        } elseif ($address = UserAddress::get(["account_number" => $accountnumber], false)) {
            \CoreDB::database()->beginTransaction();
            $user = CustomUser::get($address->ID->getValue());
            $changeRequest = new EmailChangeRequest(null, [
                "account" => $user->ID->getValue(),
                "new_mail" => $email,
                "ip_address" => User::getUserIp()
            ]);
            $changeRequest->save();
            $newUser = new CustomUser();
            $addressData = $user->address->getValue();
            $addressData[0]["account_number"] = null;
            $newUser->map([
                "name" => $name,
                "surname" => $surname,
                "password" => $password,
                "special_price_available" => $user->special_price_available->getValue(),
                "username" => CustomUser::generateUsername($email),
                "email" => $email,
                "active" => 1,
                "address" => $addressData,
                "pay_optional_at_checkout" => $user->pay_optional_at_checkout->getValue(),
                "email_verification_key" => hash(
                    "sha256",
                    $user->email->getValue() . microtime()
                )
            ]);
            $newUser->save();
            $linkedAccount = new UserLinkedAccount(null, [
                "master_account" => $user->ID->getValue(),
                "sub_account" => $newUser->ID->getValue()
            ]);
            $linkedAccount->save();

            \CoreDB::database()->commit();

            $verifyUrl = VerifyController::getUrl() . "{$newUser->ID}/" . $newUser->email_verification_key->getValue();
            \CoreDB::HTMLMail(
                $newUser->email->getValue(),
                Translation::getTranslation("email_verification"),
                Translation::getEmailTranslation("email_verification", [
                    $newUser->getFullName(), $verifyUrl, $verifyUrl
                ]),
                $newUser->getFullName()
            );

            $_SESSION[BASE_URL . "-UID"] = $newUser->ID;
            return Translation::getTranslation("new_email_added", [
                $address->account_number->getValue()
            ]);
        } else {
            throw new \Exception(
                Translation::getTranslation("invalid_operation")
            );
        }
    }

    public function activateAccountRequest()
    {
        $existing = UserAddress::get(["account_number" => @$_POST["accountnumber"]], false);
        if ($existing) {
            throw new Exception(Translation::getTranslation(
                "account_already_activated"
            ));
        }
        /** @var Customer */
        $customer = Customer::get(["account_number" => @$_POST["accountnumber"]]);
        $email = @$_POST["email"];
        $password = @$_POST["password"];
        $passwordAgain = @$_POST["password_again"];
        if (!$customer) {
            throw new \Exception(
                Translation::getTranslation("invalid_operation")
            );
        } elseif ($email != $customer->email->getValue()) {
            throw new \Exception(
                Translation::getTranslation("email_is_not_correct")
            );
        } elseif ($password != $passwordAgain) {
            throw new \Exception(
                Translation::getTranslation("password_match_error")
            );
        } elseif (!User::validatePassword($password)) {
            throw new \Exception(
                Translation::getTranslation("password_validation_error")
            );
        }

        $newUser = new CustomUser();
        $newUser->map($customer->toArray());
        $newUser->map([
            "username" => CustomUser::generateUsername($customer->email->getValue())
        ]);
        $newUser->map([
            "address" => [
                [
                    "company_name" => $customer->company_name->getValue(),
                    "address" => $customer->address->getValue(),
                    "account_number" => $customer->account_number->getValue(),
                    "town" => $customer->town->getValue(),
                    "county" => $customer->county->getValue(),
                    "postalcode" => $customer->postalcode->getValue(),
                    "county" => $customer->county->getValue() ?: " ",
                    "country" => 231, //GB,
                    "phone" => $customer->phone->getValue(),
                    "mobile" => $customer->mobile->getValue(),
                    "default" => 1
                ]
            ]
        ]);
        $newUser->save();

        $verifyUrl = VerifyController::getUrl() . "{$newUser->ID}/" . $newUser->email_verification_key->getValue();
        \CoreDB::HTMLMail(
            $newUser->email->getValue(),
            Translation::getTranslation("email_verification"),
            Translation::getEmailTranslation("email_verification", [
                $newUser->getFullName(), $verifyUrl, $verifyUrl
            ]),
            $newUser->getFullName()
        );

        $siteName = Variable::getByKey("site_name")->value->getValue();
        \CoreDB::HTMLMail(
            $newUser->email->getValue(),
            Translation::getTranslation("account_activated"),
            Translation::getEmailTranslation("account_activated", [
                $newUser->getFullName(),
                $siteName,
                $customer->account_number->getValue(),
                BASE_URL,
                $siteName
            ]),
            $newUser->getFullName()
        );

        $_SESSION[BASE_URL . "-UID"] = $newUser->ID;
        return Translation::getTranslation("account_activated", [
            $customer->account_number->getValue()
        ]);
    }
}
