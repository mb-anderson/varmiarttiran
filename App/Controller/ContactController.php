<?php

namespace App\Controller;

use App\Entity\Branch;
use App\Form\ContactForm;
use App\Theme\CustomTheme;
use Src\Entity\Translation;
use Src\Views\CollapsableCard;
use Src\Views\Link;
use Src\Views\TextElement;
use Src\Views\ViewGroup;

class ContactController extends CustomTheme
{
    public array $branches;
    public ContactForm $contactForm;

    public function checkAccess(): bool
    {
        return true;
    }

    public function preprocessPage()
    {
        $this->branches = Branch::getRootElements();
        $this->setTitle(Translation::getTranslation("contact_us"));
        $this->contactForm = new ContactForm();
        $this->contactForm->processForm();
    }

    public function echoContent()
    {
        $content = ViewGroup::create("div", "row p-3");
        $content->addField(
            ViewGroup::create("div", "col-12 mb-5 p-3")
            ->addField(
                TextElement::create(Translation::getTranslation("general_enquiries_email") . ": ")
                ->addClass("text-info font-weight-bold")
            )->addField(
                Link::create("mailto:mburakyucel38@gmail.com", "mburakyucel38@gmail.com")
            )->addField(
                TextElement::create("<br/>" . Translation::getTranslation("general_enquiries_phone") . ": ")
                ->setIsRaw(true)
                ->addClass("text-info font-weight-bold")
            )->addField(
                Link::create("tel:+905076000349", "+90 507 600 0349")
            )
        );
        /** @var Branch $branch */
        foreach ($this->branches as $branch) {
            $content->addField(
                ViewGroup::create("div", "col-12")
                ->addField(
                    CollapsableCard::create($branch->name)
                    ->setContent(
                        ViewGroup::create("div", "")
                        ->addField(
                            TextElement::create(
                                "<p class='text-info font-weight-bold'>" .
                                    Translation::getTranslation("address")
                                . "</p>" .
                                "<p>$branch</p>" .
                                "<p class='text-info font-weight-bold'>" .
                                    Translation::getTranslation("opening_hours") .
                                "</p>" .
                                $branch->opening_hours
                            )->setIsRaw(true)
                        )->addField(
                            TextElement::create(Translation::getTranslation("email") . ": ")
                            ->addClass("text-info font-weight-bold")
                        )->addField(
                            Link::create("mailto:{$branch->email}", $branch->email)
                        )->addField(
                            TextElement::create("<br/>" . Translation::getTranslation("phone") . ": ")
                            ->setIsRaw(true)
                            ->addClass("text-info font-weight-bold")
                        )->addField(
                            Link::create("tel:{$branch->phone}", $branch->phone)
                        )
                    )
                    ->setId("branch_" . $branch->ID)
                )
            );
        }

        $content->addField(
            $this->contactForm
            ->addClass("col-12 p-3")
        );

        return $content;
    }
}
