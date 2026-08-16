<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetTitle("Отзывы");

?><?$APPLICATION->IncludeComponent(
	"intec.universe:main.reviews", 
	"template.4", 
	array(
		"CACHE_TIME" => "3600000",
		"CACHE_TYPE" => "A",
		"DESCRIPTION_SHOW" => "N",
		"DETAIL_URL" => "",
		"ELEMENTS_COUNT" => "200",
		"FOOTER_SHOW" => "N",
		"HEADER_SHOW" => "N",
		"IBLOCK_ID" => "25",
		"IBLOCK_TYPE" => "content",
		"LAZYLOAD_USE" => "N",
		"LINK_USE" => "N",
		"LIST_PAGE_URL" => "",
		"ORDER_BY" => "ASC",
		"PROPERTY_POSITION" => "",
		"SECTIONS" => array(
			0 => "",
			1 => "",
		),
		"SECTIONS_MODE" => "id",
		"SECTION_URL" => "",
		"SETTINGS_USE" => "Y",
		"SORT_BY" => "SORT",
		"COMPONENT_TEMPLATE" => "template.4"
	),
	false
);?> <br>
 <br><?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php") ?>