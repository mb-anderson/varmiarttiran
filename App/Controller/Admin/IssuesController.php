<?php

namespace App\Controller\Admin;

use App\AdminTheme\AdminTheme;
use Src\Entity\Translation;

class IssuesController extends AdminTheme
{
    public $searchForm;

    public function preprocessPage()
    {
        $this->setTitle(Translation::getTranslation("todo"));
    }

    public function getTemplateFile(): string
    {
        return "page-todo.twig";
    }

    public function echoContent()
    {
        return "";
    }
}
