<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die() ?>
<?php
/**
 * @var array $aMenuLinks
 */

global $APPLICATION;

$IBLOCK_ID = "13";

$aMenuLinksExt = $APPLICATION->IncludeComponent(
	"intec.universe:menu.sections", 
	"", 
	array(
		"IS_SEF" => "Y",
		"SEF_BASE_URL" => "/catalog/",
		"SECTION_PAGE_URL" => "#SECTION_CODE_PATH#/",
		"DETAIL_PAGE_URL" => "#SECTION_CODE_PATH#/#ELEMENT_CODE#/",
		"IBLOCK_TYPE" => "catalogs",
		"IBLOCK_ID" => $IBLOCK_ID,
		"DEPTH_LEVEL" => "4",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "36000000",
		"ID" => $_REQUEST["ID"],
		"SECTION_URL" => "/catalog/?SECTION_ID=#ID#"
	),
	false
);

// TODO: Скрываем разделы в зависимости от выбранного города
global $multicity;
foreach ($aMenuLinksExt as $key => $menuItem) {
    $dbResultSection = CIBlockSection::GetList([], ['IBLOCK_ID'=>$IBLOCK_ID, "ID" => $menuItem[3]["SECTION"]["ID"]], false, ["UF_CITY"]);
    while($obResultSection = $dbResultSection->GetNext()) {
        $dbResultProperty = CUserFieldEnum::GetList([], ["CODE" => "UF_CITY", "XML_ID" => $multicity->getCity()]);
        while ($obResultProperty = $dbResultProperty->GetNext())
            if (!in_array($obResultProperty["ID"], $obResultSection["UF_CITY"]))
                unset($aMenuLinksExt[$key]);
    }
}

$aMenuLinks = array_merge($aMenuLinks, $aMenuLinksExt);
