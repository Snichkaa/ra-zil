<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Реквизиты");
global $multicity;
$arProperties = $multicity->getPropertys();
?>
    <style>
        .requisites__item td {
            padding: 5px 10px;
        }
        .requisites__name {
            text-align: right;
        }
        .requisites__value {
            font-weight: bold;
        }
    </style>
    <div class="requisites">
        <div class="intec-content">
            <div class="intec-content-wrapper">
                <div class="requisites__body">
                    <table class="requisites__list">
                        <? foreach ($multicity->getPropertys() as $code => $property): ?>
                            <? if (stripos($code, "REQUISITES") === false || empty($property["VALUE"])) continue; ?>
                            <tr class="requisites__item">
                                <td class="requisites__name"><?= $property["NAME"] ?>:</td>
                                <td class="requisites__value"><?= $property["VALUE"] ?></td>
                            </tr>
                        <? endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>