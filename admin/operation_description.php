<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

return array(
    "ESOL_IMPORT_EXCEL_DELETE_PRODUCTS" => array(
        "title" => Loc::getMessage("KDA_IE_OPERATIONS_DELETE_PRODUCTS"),
    )
);