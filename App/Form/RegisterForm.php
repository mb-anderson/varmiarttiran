<?php

namespace App\Form;

use App\Controller\WelcomeController;
use App\Entity\Customer;
use App\Entity\CustomUser;
use App\Entity\Postcode\Postcode;
use App\Entity\UserAddress;
use CoreDB;
use CoreDB\Kernel\Messenger;
use Exception;
use Src\Entity\DynamicModel;
use Src\Entity\Role;
use Src\Entity\Translation;
use Src\Entity\User;
use Src\Form\Form;
use Src\Form\Widget\FormWidget;
use Src\Form\Widget\InputWidget;
use Src\Form\Widget\SelectWidget;
use Src\Views\TextElement;

class RegisterForm extends Form
{
    public string $method = "POST";

    public int $stage = 1;
    public array $accounts = [];
    public function __construct()
    {
        parent::__construct();
        $this->addClass("user");
        $controller = CoreDB::controller();
        $controller->addJsFiles(
            "dist/register-form/register-form.js"
        );
        $controller->addFrontendTranslation("no_email_found_please_enter");
        $controller->addFrontendTranslation("please_enter_new_email");
        $controller->addFrontendTranslation("email");
        $controller->addFrontendTranslation("name");
        $controller->addFrontendTranslation("surname");
        $controller->addFrontendTranslation("password");
        $controller->addFrontendTranslation("password_again");
        $controller->addFrontendTranslation("account_not_activated");
        $controller->addFrontendTranslation("activate_account");
        $this->addField(
            InputWidget::create("company_name")
                ->addAttribute("autofocus", "true")
                ->setDescription(
                    TextElement::create(
                        Translation::getTranslation("if_not_company_type_your_name")
                    )->addClass("text-danger")
                )
        );
        $this->addField(
            InputWidget::create("doornumber")
        );
        $this->addField(
            InputWidget::create("name")
        );
        $this->addField(
            InputWidget::create("surname")
        );
        $this->addField(
            InputWidget::create("email")
                ->setType("email")
        );
        $this->addField(
            InputWidget::create("phone")
                ->setType("tel")
        );
        $this->addField(
            InputWidget::create("mobile")
                ->setType("tel")
        );
        $this->addField(
            InputWidget::create("address")
        );
        $this->addField(
            InputWidget::create("town")
        );
        $this->addField(
            InputWidget::create("county")
        );
        $this->addField(
            InputWidget::create("postalcode")
        );
        $this->addField(
            InputWidget::create("password")
                ->setType("password")
        );
        $this->addField(
            InputWidget::create("password_again")
                ->setType("password")
        );
        /** @var FormWidget $field */
        foreach ($this->fields as $field) {
            $label = Translation::getTranslation($field->name);
            $field->setLabel($label . " *")
                ->addClass("form-control-user")
                ->addAttribute("placeholder", $label)
                ->addAttribute("required", "true")
                ->addAttribute("autocomplete", "false");
        }
        $this->addField(
            InputWidget::create("opening_hours")
            ->setType("time")
            ->setLabel(Translation::getTranslation("earliest_delivey_time"))
            ->addClass("form-control-user")
            ->addAttribute("placeholder", Translation::getTranslation("earliest_delivey_time"))
            ->addAttribute("required", "true")
            ->addAttribute("autocomplete", "false")
        );
        $this->addField(
            SelectWidget::create("country")->setOptions(
                \CoreDB::database()->select("countries", "c")
                    ->select("c", ["ID", "name"])
                    ->execute()->fetchAll(\PDO::FETCH_KEY_PAIR)
            )->setValue(
                DynamicModel::get(["code" => "GB"], "countries")->ID->getValue()
            )
            ->setLabel(Translation::getTranslation("country"))
            ->addAttribute("required", "true")
            ->addAttribute("data-live-search", "true")
        );



        $this->addField(
            SelectWidget::create("shop_category")->setOptions(
                \CoreDB::database()->select("shop_categories", "s")
                    ->select("s", ["ID", "title"])
                    ->execute()->fetchAll(\PDO::FETCH_KEY_PAIR)
            )->setNullElement(
                Translation::getTranslation("please_choose")
            )
            ->removeClass("selectpicker")
                ->setLabel(Translation::getTranslation("shop_category"))
                ->addAttribute("required", "true")
                ->setDescription(
                    Translation::getTranslation("shop_category_description")
                )
        );
    }

    public function getFormId(): string
    {
        return "register_form";
    }

    public function getTemplateFile(): string
    {
        return "register-form.twig";
    }

    public function validate(): bool
    {
        $this->stage = 2;
        if (@$this->request["submit"]) {
            $this->restoreValues();
            $this->accounts = $this->validateAddress();
            if (!empty($this->accounts)) {
                $this->stage = 1;
                $this->setMessage(
                    Translation::getTranslation(
                        "found_accounts",
                        [count($this->accounts)]
                    ),
                    Messenger::INFO
                );
            }
        } elseif (@$this->request["register"]) {
            $this->validateRegistration();
        }
        return empty($this->errors);
    }

    public function submit()
    {
        if (@$this->request["submit"]) {
        } elseif (@$this->request["register"]) {
            $this->registerUser();
        }
    }

    private function validateAddress()
    {
        $accounts = \CoreDB::database()
            ->select(UserAddress::getTableName(), "ua")
            ->join(User::getTableName(), "u", "u.ID = ua.user")
            ->condition("postalcode", @$this->request["postalcode"])
            ->condition("address", @$this->request["doornumber"] . "%", "LIKE")
            ->select("ua", ["account_number", "company_name"])
            ->select("u", ["email"])
            ->execute()->fetchAll(\PDO::FETCH_OBJ);
        $customerQuery = \CoreDB::database()->select(Customer::getTableName(), "c")
        ->condition("postalcode", @$this->request["postalcode"])
        ->condition("address", @$this->request["doornumber"] . "%", "LIKE")
        ->select("c", [
            "account_number",
            "company_name",
            "email"
        ])
        ->selectWithFunction(["1 AS is_customer"]);
        if ($accounts) {
            $customerQuery->condition(
                "c.account_number",
                array_map(function ($el) {
                    return $el->account_number;
                }, $accounts),
                "NOT IN"
            );
        }
        $accounts = array_merge(
            $accounts,
            $customerQuery->execute()->fetchAll(\PDO::FETCH_OBJ)
        );
        foreach ($accounts as $account) {
            $account->email = $this->obfuscateEmail($account->email);
        }
        return $accounts;
    }

    private function obfuscateEmail($email)
    {
        $exploded   = explode("@", $email);
        $name = implode('@', array_slice($exploded, 0, count($exploded) - 1));
        return substr($name, 0, 2) . str_repeat('*', 6) . "@" . end($exploded);
    }

    private function validateRegistration()
    {
        foreach ($this->fields as $fieldName => $field) {
            if (in_array($fieldName, ["doornumber"])) {
                continue;
            }
            if (!$this->request[$fieldName]) {
                $this->setError($fieldName, Translation::getTranslation("cannot_empty", [
                    $this->fields[$fieldName]->label
                ]));
            }
        }
        if ($this->request["password"] != $this->request["password_again"]) {
            $this->setError(
                "password",
                Translation::getTranslation("password_match_error")
            );
        } elseif (
            !User::validatePassword($this->request["password"])
        ) {
            $this->setError(
                "password",
                Translation::getTranslation("password_validation_error")
            );
        }
        if (!DynamicModel::get(["ID" => $this->request["country"]], "countries")) {
            $this->setError("country", Translation::getTranslation("cannot_empty", [
                $this->fields["country"]->label
            ]));
        }
    }

    private function registerUser()
    {
        try {
            $user = new CustomUser();
            $mapData = $this->request;
            $mapData["active"] = 1;
            $mapData["username"] = CustomUser::generateUsername($this->request["email"]);
            $mapData["pay_optional_at_checkout"] = 1;
            $mapData["address"] = [
                [
                    "company_name" => $this->request["company_name"],
                    "address" => $this->request["address"],
                    "town" => $this->request["town"],
                    "county" => $this->request["county"],
                    "postalcode" => $this->request["postalcode"],
                    "country" => $this->request["country"],
                    "phone" => $this->request["phone"],
                    "mobile" => $this->request["mobile"],
                    "default" => 1,
                ]
            ];
            unset($mapData["password"]);
            $user->map($mapData);
            $user->save();
            $user->roles->setValue([Role::get(["role" => "Customer"])->ID->getValue()]);
            $user->password->setValue(
                password_hash($this->request["password"], PASSWORD_BCRYPT)
            );
            $userAddress = UserAddress::get(["user" => $user->ID->getValue()], false);
            $user->shipping_address->setValue($userAddress->ID->getValue());
            $user->save();
            $_SESSION[BASE_URL . "-UID"] = $user->ID;
            CoreDB::goTo(WelcomeController::getUrl());
        } catch (Exception $ex) {
            $this->setError("", $ex->getMessage());
        }
    }
}
