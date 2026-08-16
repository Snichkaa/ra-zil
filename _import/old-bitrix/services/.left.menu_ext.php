<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die() ?>
<?php

/**
 * @var array $aMenuLinks
 */

global $APPLICATION;


$aMenuLinksExt = $APPLICATION->IncludeComponent(
	"intec.universe:menu.sections", 
	"", 
	array(
		"IS_SEF" => "Y",
		"SEF_BASE_URL" => "/services/",
		"SECTION_PAGE_URL" => "#SECTION_CODE#/",
		"DETAIL_PAGE_URL" => "#SECTION_CODE#/#ELEMENT_ID#/",
		"IBLOCK_TYPE" => "catalogs",
		"IBLOCK_ID" => "16",
		"DEPTH_LEVEL" => "4",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "36000000",
		"ID" => $_REQUEST["ID"],
		"SECTION_URL" => "/services/?SECTION_ID=#ID#",
		"USUAL" => "N",
        "ELEMENTS_ROOT" => "N",
        "ELEMENTS_SECTIONS" => "Y"
	),
	false
);

$arSelect = Array("*");
$arFilter = Array("IBLOCK_ID"=> "16", "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y");
$dbResult = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>50), $arSelect);
while($obResult = $dbResult->GetNextElement())
{
    $arFields = $obResult->GetFields();
    $aMenuLinksExt[] = [
        $arFields["NAME"],
        "/services/".$arFields["CODE"]."/",
    ];
}

$aMenuLinks = array_merge($aMenuLinks, $aMenuLinksExt);
