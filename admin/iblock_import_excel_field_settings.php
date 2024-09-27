<?
if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
$moduleId = 'kda.importexcel';
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
CModule::IncludeModule($moduleId);
$bCurrency = CModule::IncludeModule("currency");
IncludeModuleLangFile(__FILE__);

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT <= "R") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$IBLOCK_ID = $_REQUEST['IBLOCK_ID'];
$fieldName = htmlspecialcharsex($_GET['field_name']);
$listNumber = $colNumber = false;
if(preg_match('/\[FIELDS_LIST\]\[(\d+)\]\[(\d+)/', $fieldName, $m))
{
	$listNumber = $m[1];
	$colNumber = $m[2];
}

function GetFieldEextraVal($arSettings, $name)
{
	$fnBase = str_replace('[FIELDS_LIST]', '', htmlspecialcharsbx($_GET['field_name']));
	$fName = 'EXTRA'.$fnBase.'['.$name.']';
	if(preg_match_all('/\[([^\]]*)\]/Us', $fnBase, $m))
	{
		foreach($m[1] as $key) $arSettings = (isset($arSettings[$key]) ? $arSettings[$key] : array());
	}
	$val = (isset($arSettings[$name]) ? $arSettings[$name] : '');
	return array($fName, $val);
}

$fl = new CKDAFieldList();
$arFieldGroups = $fl->GetFields($IBLOCK_ID);
$arFields = array();
if(is_array($arFieldGroups))
{
	foreach($arFieldGroups as $arGroup)
	{
		if(is_array($arGroup['items']))
		{
			$arFields = array_merge($arFields, $arGroup['items']);
		}
	}
}

$isOffer = false;
$field = $_REQUEST['field'];
$OFFER_IBLOCK_ID = 0;
if(strpos($field, 'OFFER_')===0)
{
	$OFFER_IBLOCK_ID = CKDAImportUtils::GetOfferIblock($IBLOCK_ID);
	$field = substr($field, 6);
	$isOffer = true;
}

$addField = '';
if(strpos($field, '|') !== false)
{
	list($field, $addField) = explode('|', $field);
}

/*$obJSPopup = new CJSPopup();
$obJSPopup->ShowTitlebar(GetMessage("KDA_IE_SETTING_UPLOAD_FIELD").($arFields[$field] ? ' "'.$arFields[$field].'"' : ''));*/

$profileId = htmlspecialcharsbx($_REQUEST['PROFILE_ID']);
$oProfile = new CKDAImportProfile();
$oProfile->ApplyExtra($PEXTRASETTINGS, $profileId);
if(isset($_POST['POSTEXTRA']) && strlen($_POST['POSTEXTRA']) > 0)
{
	$arFieldParams = $_POST['POSTEXTRA'];
	if(!defined('BX_UTF') || !BX_UTF)
	{
		$arFieldParams = $APPLICATION->ConvertCharset($arFieldParams, 'UTF-8', 'CP1251');
	}
	$arFieldParams = \KdaIE\Utils::JsObjectToPhp($arFieldParams);
	if(!$arFieldParams) $arFieldParams = array();
	$fName = 'EXTRA'.str_replace('[FIELDS_LIST]', '', $fieldName);
	//$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
	//eval('$arFieldsParamsInArray = &$P'.$fNameEval.';');
	$arFieldsParamsInArray = &$PEXTRASETTINGS;
	if(preg_match_all('/\[([^\]]*)\]/Us', $fName, $m))
	{
		foreach($m[1] as $key)
		{
			if(!is_array($arFieldsParamsInArray) || !array_key_exists($key, $arFieldsParamsInArray)) $arFieldsParamsInArray[$key] = array();
			$arFieldsParamsInArray = &$arFieldsParamsInArray[$key];
		}
	}
	$arFieldsParamsInArray = $arFieldParams;
}

if($_POST['action']) define('PUBLIC_AJAX_MODE', 'Y');

if($_POST['action']=='export_conv_csv')
{
	$arExtra = array();
	\CKDAImportExtrasettings::HandleParams($arExtra, array(array(array('CONVERSION'=>$_POST['CONVERSION'], 'EXTRA_CONVERSION'=>$_POST['EXTRA_CONVERSION']))), false);
	while(is_array($arExtra) && isset($arExtra[0])) $arExtra = $arExtra[0];
	$arConv = $arExtraConv = array();
	if(is_array($arExtra))
	{
		if(isset($arExtra['CONVERSION']) && is_array($arExtra['CONVERSION'])) $arConv = $arExtra['CONVERSION']; 
		if(isset($arExtra['EXTRA_CONVERSION']) && is_array($arExtra['EXTRA_CONVERSION'])) $arExtraConv = $arExtra['EXTRA_CONVERSION']; 
	}
	$arConv = array_map(array('CKDAImportUtils', 'SetConvType0'), $arConv);
	$arExtraConv = array_map(array('CKDAImportUtils', 'SetConvType1'), $arExtraConv);
	\CKDAImportUtils::ExportCsv(array_merge($arConv, $arExtraConv));
	die();
}
elseif($_POST['action']=='import_conv_csv')
{
	$arImportConv = array();
	if(isset($_FILES["import_file"]) && $_FILES["import_file"]["tmp_name"] && is_uploaded_file($_FILES["import_file"]["tmp_name"]))
	{
		$arFile = \CKDAImportUtils::MakeFileArray($_FILES["import_file"]);
		$arImportConv = \CKDAImportUtils::ImportCsv($arFile['tmp_name']);
	}
	$arConv = $arExtraConv = array();
	foreach($arImportConv as $conv)
	{
		$ctype = array_pop($conv);
		$conv = array_combine(array('CELL', 'WHEN', 'FROM', 'THEN', 'TO'), $conv);
		if($ctype==0) $arConv[] = $conv;
		elseif($ctype==1) $arExtraConv[] = $conv;
	}
	
	$key1 = $key2 = 0;
	if(preg_match('/\[FIELDS_LIST\]\[([^\]]*)\]\[([^\]]*)\]/', $fieldName, $m))
	{
		$key1 = $m[1];
		$key2 = $m[2];
	}
	$PEXTRASETTINGS[$key1][$key2] = array(
		'CONVERSION' => $arConv,
		'EXTRA_CONVERSION' => $arExtraConv
	);
	/*$APPLICATION->RestartBuffer();
	ob_end_clean();
	echo \KdaIE\Utils::PhpToJSObject(array('CONV'=>$arConv, 'EXTRA_CONV'=>$arExtraConv));
	die();*/
}
elseif($_POST['action']=='save_margin_template')
{
	$arPost = $_POST;
	if(!defined('BX_UTF') || !BX_UTF)
	{
		$arPost = $APPLICATION->ConvertCharsetArray($arPost, 'UTF-8', 'CP1251');
	}
	$arMarginTemplates = CKDAImportExtrasettings::SaveMarginTemplate($arPost);
}
elseif($_POST['action']=='delete_margin_template')
{
	$arMarginTemplates = CKDAImportExtrasettings::DeleteMarginTemplate($_POST['template_id']);
}
elseif($_POST['action']=='save' && is_array($_POST['EXTRASETTINGS']))
{
	$APPLICATION->RestartBuffer();
	ob_end_clean();

	CKDAImportExtrasettings::HandleParams($PEXTRASETTINGS, $_POST['EXTRASETTINGS']);
	preg_match_all('/\[([_\d]+[_P\d]*)\]/', $fieldName, $keys);
	$oid = 'field_settings_'.$keys[1][0].'_'.$keys[1][1];
	
	if($_GET['return_data'])
	{
		$returnJson = (empty($PEXTRASETTINGS[$keys[1][0]][$keys[1][1]]) ? '""' : \KdaIE\Utils::PhpToJSObject($PEXTRASETTINGS[$keys[1][0]][$keys[1][1]]));
		echo '<script>EList.SetExtraParams("'.$oid.'", '.$returnJson.')</script>';
	}
	else
	{
		$oProfile->UpdateExtra($profileId, $PEXTRASETTINGS);
		if(!empty($PEXTRASETTINGS[$keys[1][0]][$keys[1][1]])) echo '<script>$("#'.$oid.'").removeClass("inactive");</script>';
		else echo '<script>$("#'.$oid.'").addClass("inactive");</script>';
		echo '<script>BX.WindowManager.Get().Close();</script>';
	}
	die();
}

$oProfile = new CKDAImportProfile();
$arProfile = $oProfile->GetByID($profileId);
$SETTINGS_DEFAULT = $arProfile['SETTINGS_DEFAULT'];

$bPrice = false;
if((strncmp($field, "ICAT_PRICE", 10) == 0 && substr($field, -6)=='_PRICE') || $field=="ICAT_PURCHASING_PRICE")
{
	$bPrice = true;
	if($bCurrency)
	{
		$arCurrency = array();
		$lcur = CCurrency::GetList(($by="sort"), ($order1="asc"), LANGUAGE_ID);
		while($arr = $lcur->Fetch())
		{
			$arCurrency[] = array(
				'CURRENCY' => $arr['CURRENCY'],
				'FULL_NAME' => $arr['FULL_NAME']
			);
		}
	}
}

$bPicture = $bElemPicture = (bool)in_array($field, array('IE_PREVIEW_PICTURE', 'IE_DETAIL_PICTURE'));
$bIblockElement = false;
$iblockElementIblock = $IBLOCK_ID;
$iblockElementRelIblock = false;
$bIblockElementXmlId = false;
$propertyName = '';
$bIblockSection = false;
if($field=='ISECT_IBLOCK_SECTION_ID')
{
	$bIblockSection = true;
	$iblockSectionIblock = $IBLOCK_ID;
}
$bIblockElementSet = false;
$bIblockElementSetType = '';
$bCanUseForSKUGenerate = (bool)($isOffer && in_array($field, array('IE_NAME', 'IE_ID', 'IE_XML_ID', 'IE_CODE')));
$bTextHtml = false;
$bMultipleProp = $bMultipleField = false;
$bPropTypeList = false;
$bUser = false;
$bDirectory = false;
$arPropVals = array();
$maxPropVals = 1000;
if(strncmp($field, "IP_PROP", 7) == 0 && is_numeric(substr($field, 7)))
{
	$propId = intval(substr($field, 7));
	$dbRes = CIBlockProperty::GetList(array(), array('ID'=>$propId));
	if($arProp = $dbRes->Fetch())
	{
		$propertyName = $arProp['NAME'];
		if($arProp['PROPERTY_TYPE']=='F')
		{
			$bPicture = true;
		}
		elseif($arProp['PROPERTY_TYPE']=='L')
		{
			$bPropTypeList = true;
			$dbRes = \CIBlockPropertyEnum::GetList(array("SORT"=>"ASC", "VALUE"=>"ASC"), array('PROPERTY_ID'=>$propId));
			while(($arr = $dbRes->Fetch()) && count($arPropVals)<=$maxPropVals)
			{
				$arPropVals[] = $arr['VALUE'];
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='E' || $arProp['USER_TYPE']=='ElementXmlID')
		{
			$bIblockElement = true;
			if($arProp['LINK_IBLOCK_ID'] > 0)
			{
				$iblockElementIblock = $arProp['LINK_IBLOCK_ID'];
				$iblockElementRelIblock = $arProp['LINK_IBLOCK_ID'];
			}
			elseif($arProp['USER_TYPE']=='ElementXmlID')
			{
				$iblockElementRelIblock = $iblockElementIblock;
				$bIblockElementXmlId = true;
			}
			$dbRes = \CIblockElement::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), array('IBLOCK_ID'=>$iblockElementIblock), false, array('nTopCount'=>$maxPropVals), array('ID', 'NAME'));
			while($arr = $dbRes->Fetch())
			{
				$arPropVals[] = $arr['NAME'];
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='G')
		{
			$bIblockSection = true;
			$iblockSectionIblock = ($arProp['LINK_IBLOCK_ID'] ? $arProp['LINK_IBLOCK_ID'] : $IBLOCK_ID);
		}
		elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='HTML')
		{
			$bTextHtml = true;
		}
		elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='UserID')
		{
			$bUser = true;
		}
		elseif($arProp['USER_TYPE']=='directory' && $arProp['USER_TYPE_SETTINGS']['TABLE_NAME'] && CModule::IncludeModule('highloadblock'))
		{
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('TABLE_NAME'=>$arProp['USER_TYPE_SETTINGS']['TABLE_NAME'])))->fetch();
			$dbRes = CUserTypeEntity::GetList(array('SORT'=>'ASC', 'ID'=>'ASC'), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID'], 'LANG'=>LANGUAGE_ID));
			$arHLFields = array();
			while($arHLField = $dbRes->Fetch())
			{
				$arHLFields[$arHLField['FIELD_NAME']] = ($arHLField['EDIT_FORM_LABEL'] ? $arHLField['EDIT_FORM_LABEL'] : $arHLField['FIELD_NAME']);
			}
			$bDirectory = true;
			
			if(isset($arHLFields['UF_NAME']) && isset($arHLFields['UF_XML_ID']))
			{
				$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
				$entityDataClass = $entity->getDataClass();
				$dbRes = $entityDataClass::getList(array('order'=>array('UF_NAME'=>'ASC'), 'select'=>array('UF_NAME'), 'limit'=>$maxPropVals));
				while($arr = $dbRes->Fetch())
				{
					$arPropVals[] = $arr['UF_NAME'];
				}
			}
		}
		if($isOffer && in_array($arProp['PROPERTY_TYPE'], array('S', 'N', 'L', 'E', 'G')))
		{
			$bCanUseForSKUGenerate = true;
		}
		if($arProp['MULTIPLE']=='Y') $bMultipleProp = true;
	}
}

$bSectionUid = $bSectionUidWoLevel = false;
if(preg_match('/^ISECT\d*_'.$SETTINGS_DEFAULT['SECTION_UID'].'$/', $field))
{
	$bSectionUid = true;
	if($field=='ISECT_'.$SETTINGS_DEFAULT['SECTION_UID'])
	{
		$bSectionUidWoLevel = true;
	}
}

if(preg_match('/^ISECT\d*_(UF_.*)$/', $field, $m))
{
	$fieldCode = $m[1];
	$dbRes = CUserTypeEntity::GetList(array(), array('ENTITY_ID' => 'IBLOCK_'.$IBLOCK_ID.'_SECTION', 'FIELD_NAME'=>$fieldCode));
	if($arUserField = $dbRes->Fetch())
	{
		if($arUserField['MULTIPLE']=='Y') $bMultipleField = true;
		if($arUserField['USER_TYPE_ID']=='iblock_element')
		{
			$bIblockElement = true;
		}
	}
}

if(preg_match('/^ICAT_(SET2?)_/', $field, $m))
{
	$bMultipleField = true;
	if($field=='ICAT_SET_ITEM_ID' || $field=='ICAT_SET2_ITEM_ID')
	{
		$bIblockElement = true;
		$bIblockElementSet = true;
		$bIblockElementSetType = $m[1];
		$iblockElementIblock = $IBLOCK_ID;
	}
}

$bUid = false;
if(!$isOffer && is_array($SETTINGS_DEFAULT['ELEMENT_UID']) && in_array($field, $SETTINGS_DEFAULT['ELEMENT_UID']))
{
	$bUid = true;
}

$bOfferUid = false;
if($isOffer && is_array($SETTINGS_DEFAULT['ELEMENT_UID_SKU']) && in_array('OFFER_'.$field, $SETTINGS_DEFAULT['ELEMENT_UID_SKU']))
{
	$bOfferUid = true;
}

$bChangeable = false;
$bExtLink = false;
if(in_array($field, array('IE_PREVIEW_TEXT', 'IE_DETAIL_TEXT')))
{
	$bChangeable = true;
	$bExtLink = true;
}

$bVideo = (bool)($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='video');
$bPropList = (bool)($field=='IP_LIST_PROPS');
$bStoreList = (bool)($field=='ICAT_LIST_STORES');
$bSectPropList = (bool)($field=='ISECT_SECTION_PROPERTIES');

$bProductGift = false;
if($field=='ICAT_DISCOUNT_BRGIFT')
{
	$bProductGift = true;
	$iblockElementIblock = $IBLOCK_ID;
}

if($bIblockElementSet || $bIblockElementXmlId)
{
	$arIblocks = $fl->GetIblocks();
}

$useSaleDiscount = (bool)(CModule::IncludeModule('sale') && (string)COption::GetOptionString('sale', 'use_sale_discount_only') == 'Y');
$bDiscountValue = (bool)(strpos($field, 'ICAT_DISCOUNT_VALUE')===0 && !$useSaleDiscount);
$bSaleDiscountValue = (bool)(strpos($field, 'ICAT_DISCOUNT_VALUE')===0 && $useSaleDiscount);
$countCols = intval($_REQUEST['count_cols']);	

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");
?>
<form action="" method="post" enctype="multipart/form-data" name="field_settings" class="kda_ie_settings_form">
	<input type="hidden" name="action" value="save">
	<table width="100%">
		<col width="50%">
		<col width="50%">
		<?if($bPropList){?>
			<tr>
				<td colspan="2">
					<?
					echo BeginNote();
					echo '<b>'.GetMessage("KDA_IE_SETTINGS_EXAMPLE").'</b>: <i>'.GetMessage("KDA_IE_SETTINGS_EXAMPLE_PROPLIST").'</i><br>'.
						'<b>'.GetMessage("KDA_IE_SETTINGS_EXAMPLE2").'</b>: <i>'.GetMessage("KDA_IE_SETTINGS_EXAMPLE_PROPLIST2").'</i>';
					echo EndNote();
					?>
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_PROPS_SEP");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_PROPS_SEP');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="3">
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_PROPVALS_SEP");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_PROPVALS_SEP');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="3">
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_VALDESC_SEP");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_VALDESC_SEP');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="3">
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_SAVE_OLD_VALUES");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_NEWPROP_SAVE_OLD_VALUES');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y"<?if($val=='Y'){echo ' checked';}?>>
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_CREATE_NEW");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_CREATE_NEW');
					$createNewProps = (bool)($val=='Y');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?> onchange="ESettings.ToggleSubparams(this)">
				</td>
			</tr>
			
			<tr class="subparams" <?if(!$createNewProps){echo 'style="display: none;"';}?>>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_PREFIX");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_NEWPROP_PREFIX');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>">
				</td>
			</tr>
			
			<tr class="subparams" <?if(!$createNewProps){echo 'style="display: none;"';}?>>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_SORT");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_NEWPROP_SORT');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>">
				</td>
			</tr>
			
			<tr class="subparams" <?if(!$createNewProps){echo 'style="display: none;"';}?>>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_TYPE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_NEWPROP_TYPE');
					?>
					<select name="<?=$fName?>">
						<option value="S"<?if($val=='S'){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_TYPE_STRING");?></option>
						<option value="N"<?if($val=='N'){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_TYPE_NUMBER");?></option>
						<option value="L"<?if($val=='L'){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_TYPE_LIST");?></option>
						<option value="S:HTML"<?if($val=='S:HTML'){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_TYPE_HTML");?></option>
					</select>
				</td>
			</tr>
			
			<tr class="subparams" <?if(!$createNewProps){echo 'style="display: none;"';}?>>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_MULTIPLE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_NEWPROP_MULTIPLE');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			
			<tr class="subparams" <?if(!$createNewProps){echo 'style="display: none;"';}?>>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_SMART_FILTER");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_NEWPROP_SMART_FILTER');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<tr class="subparams" <?if(!$createNewProps){echo 'style="display: none;"';}?>>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NEWPROP_DISPLAY_EXPANDED");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_NEWPROP_DISPLAY_EXPANDED');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			
			<?
			$arFeatures = array();
			if(is_callable(array('\Bitrix\Iblock\Model\PropertyFeature', 'isEnabledFeatures')) && \Bitrix\Iblock\Model\PropertyFeature::isEnabledFeatures())
			{
				$arFeatures = \Bitrix\Iblock\Model\PropertyFeature::getPropertyFeatureList(array());
			}
			foreach($arFeatures as $arFeature)
			{
			?>
				<tr class="subparams" <?if(!$createNewProps){echo 'style="display: none;"';}?>>
					<td class="adm-detail-content-cell-l"><?echo $arFeature['FEATURE_NAME'];?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_NEWPROP_FEATURE_'.htmlspecialcharsbx($arFeature['MODULE_ID'].':'.$arFeature['FEATURE_ID']));
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
			<?
			}
			?>
		<?}?>
		
		<?if($bStoreList){?>
			<tr>
				<td colspan="2">
					<?
					echo BeginNote();
					echo '<b>'.GetMessage("KDA_IE_SETTINGS_EXAMPLE").'</b>: <i>'.GetMessage("KDA_IE_SETTINGS_EXAMPLE_STORELIST").'</i>';
					echo EndNote();
					?>
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_STORELIST_STORES_SEP");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'STORELIST_STORES_SEP');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="3" placeholder=";">
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_STORELIST_STOREVALS_SEP");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'STORELIST_STOREVALS_SEP');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="3" placeholder=":">
				</td>
			</tr>
		<?}?>
		
		<?if($bSectPropList){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SECTPROPS_SMART_FILTER");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SECTPROPS_SMART_FILTER');
					?>
					<select name="<?=$fName?>">
						<option value=""><?echo GetMessage("KDA_IE_SETTINGS_NOT_CHANGE");?></option>
						<option value="Y"<?if($val=='Y'){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_YES");?></option>
						<option value="N"<?if($val=='N'){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_NO");?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SECTPROPS_DISPLAY_EXPANDED");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SECTPROPS_DISPLAY_EXPANDED');
					?>
					<select name="<?=$fName?>">
						<option value=""><?echo GetMessage("KDA_IE_SETTINGS_NOT_CHANGE");?></option>
						<option value="Y"<?if($val=='Y'){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_YES");?></option>
						<option value="N"<?if($val=='N'){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_NO");?></option>
					</select>
				</td>
			</tr>
		<?}?>
	
		<?if($bIblockElement && $iblockElementRelIblock===false && strlen($propertyName) > 0){?>
			<tr>
				<td colspan="2">
					<?
					echo BeginNote();
					echo sprintf(GetMessage("KDA_IE_SETTINGS_IBLOCKELEMENT_WO_IBLOCK"), $propertyName);
					echo EndNote();
					?>
				</td>
			</tr>
		<?}?>
		<?if($bIblockElement || $bProductGift){?>
			<tr>
				<td class="adm-detail-content-cell-l">
				<?
				if(!$bMultipleProp && strlen($propertyName) > 0)
				{
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'REL_ELEMENT_EXTRA_FIELD');
					echo '<select name="'.$fName.'" class="kda-ie-select2text"><option value="PRIMARY"'.($val=='PRIMARY' ? ' selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_REL_ELEMENT_FIELD").'</option><option value="EXTRA"'.($val=='EXTRA' ? ' selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_REL_ELEMENT_EXTRA_FIELD").'</option></select>';
				}
				else
				{
					echo GetMessage("KDA_IE_SETTINGS_REL_ELEMENT_FIELD");
				}
				?>:
				</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'REL_ELEMENT_FIELD');
					list($fName2, $val2) = GetFieldEextraVal($PEXTRASETTINGS, 'CHANGE_LINKED_IBLOCK');
					list($fName3, $val3) = GetFieldEextraVal($PEXTRASETTINGS, 'LINKED_IBLOCK');
					$fIblockId = ($val2=='Y' && !empty($val3) ? current($val3) : $iblockElementIblock);
					if(!$bMultipleProp && strlen($propertyName) > 0) $strOptions = $fl->GetSelectGeneralFields($fIblockId, $val, '');
					else $strOptions = $fl->GetSelectUidFields($fIblockId, $val, '');
					if(preg_match('/<option[^>]+value="IE_ID".*<\/option>/Uis', $strOptions, $m))
					{
						$strOptions = $m[0].str_replace($m[0], '', $strOptions);
					}
					?>
					<select name="<?echo $fName;?>" class="chosen" style="max-width: 450px;"><?echo $strOptions;?></select>
				</td>
			</tr>
		<?}?>
		
		
		<?if($bIblockSection){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_REL_SECTION_FIELD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'REL_SECTION_FIELD');					
					$fl->ShowSelectSectionUidFields($iblockSectionIblock, $fName, ($val ? $val : 'ID'));
					?>
				</td>
			</tr>
		<?}?>
		
		<?if($bPropTypeList){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_NOT_CREATE_VALS");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_NOT_CREATE_VALS');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_FIELD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PROPLIST_FIELD');
					?>
					<select name="<?echo $fName;?>">
						<option value="VALUE" <?if($val=='VALUE'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_FIELD_VALUE");?></option>
						<option value="XML_ID" <?if($val=='XML_ID'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_FIELD_XML_ID");?></option>
						<option value="SORT" <?if($val=='SORT'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_PROPLIST_FIELD_SORT");?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?if($bDirectory && !empty($arHLFields)){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_HLBL_FIELD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'HLBL_FIELD');
					?>
					<select name="<?echo $fName;?>" class="chosen">
						<?
						foreach($arHLFields as $k=>$name)
						{
							echo '<option value="'.$k.'"'.(($val==$k || (!$val && $k=='UF_NAME')) ? ' selected' : '').'>'.$name.'</option>';
						}
						?>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?if($bVideo){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_VIDEO_WIDTH");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'VIDEO_WIDTH');
					?>
					<input type="text" name="<?echo $fName;?>" value="<?echo htmlspecialcharsbx($val)?>" placeholder="400">
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_VIDEO_HEIGHT");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'VIDEO_HEIGHT');
					?>
					<input type="text" name="<?echo $fName;?>" value="<?echo htmlspecialcharsbx($val)?>" placeholder="300">
				</td>
			</tr>
		<?}?>
		
		<?if($bUid || $bOfferUid){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_ELEMENT_SEARCH_MODE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'UID_SEARCH_SUBSTRING');
					?>
					<select name="<?echo $fName;?>">
						<option value=""><?echo GetMessage("KDA_IE_SETTINGS_ELEMENT_SEARCH_FULL");?></option>
						<option value="Y" <?if($val=='Y'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_ELEMENT_SEARCH_SUBSTRING");?></option>
						<option value="B" <?if($val=='B'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_ELEMENT_SEARCH_BEGIN");?></option>
						<option value="E" <?if($val=='E'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_ELEMENT_SEARCH_END");?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?if($bSectionUid || $field=='SECTION_SEP_NAME'){?>
			<?if($bSectionUid){?>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SECTION_NAME_SEPARATED");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SECTION_UID_SEPARATED');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
			<?}?>
			<?if(!$bSectionUidWoLevel){?>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SECTION_SEARCH_IN_SUBSECTIONS");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SECTION_SEARCH_IN_SUBSECTIONS');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SECTION_SEARCH_WITHOUT_PARENT");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SECTION_SEARCH_WITHOUT_PARENT');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
			<?}?>
		<?}?>
		
		<?if(in_array($field, array("IE_SECTION_PATH", "ISECT_PATH_NAMES", "SECTION_SEP_NAME_PATH"))){?>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("KDA_IE_SETTINGS_SECTION_PATH_SEPARATOR");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SECTION_PATH_SEPARATOR');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" placeholder="<?echo GetMessage("KDA_IE_SETTINGS_SECTION_PATH_SEPARATOR_PLACEHOLDER");?>">
				</td>
			</tr>
		<?}?>
		
		<?if($field=="IE_SECTION_PATH"){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SECTION_PATH_SEPARATED");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SECTION_PATH_SEPARATED');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SECTION_PATH_NAME_SEPARATED");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SECTION_UID_SEPARATED');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if($bCanUseForSKUGenerate){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_USE_FOR_SKU_GENERATE");?>: <span id="hint_USE_FOR_SKU_GENERATE"></span><script>BX.hint_replace(BX('hint_USE_FOR_SKU_GENERATE'), '<?echo GetMessage("KDA_IE_SETTINGS_USE_FOR_SKU_GENERATE_HINT"); ?>');</script></td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'USE_FOR_SKU_GENERATE');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if($bMultipleProp || $bMultipleField){?>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("KDA_IE_SETTINGS_CHANGE_MULTIPLE_SEPARATOR");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'CHANGE_MULTIPLE_SEPARATOR');
					list($fName2, $val2) = GetFieldEextraVal($PEXTRASETTINGS, 'MULTIPLE_SEPARATOR');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?> onchange="$('#multiple_separator').css('display', (this.checked ? '' : 'none'));"><br>
					<input type="text" id="multiple_separator" name="<?=$fName2?>" value="<?=htmlspecialcharsbx($val2)?>" placeholder="<?echo GetMessage("KDA_IE_SETTINGS_MULTIPLE_SEPARATOR_PLACEHOLDER");?>" <?=($val!='Y' ? 'style="display: none"' : '')?>>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("KDA_IE_SETTINGS_MULTIPLE_SAVE_OLD_VALUES");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'MULTIPLE_SAVE_OLD_VALUES');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		<?if($bMultipleProp){?>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("KDA_IE_SETTINGS_MULTIPLE_FROM_VALUE");?>:<br><small><?echo GetMessage("KDA_IE_SETTINGS_MULTIPLE_FROM_VALUE_COMMENT");?></small></td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName1, $val1) = GetFieldEextraVal($PEXTRASETTINGS, 'MULTIPLE_FROM_VALUE');
					list($fName2, $val2) = GetFieldEextraVal($PEXTRASETTINGS, 'MULTIPLE_TO_VALUE');
					?>
					<input type="text" size="5" name="<?=$fName1?>" value="<?echo htmlspecialcharsbx($val1);?>" placeholder="1">
					<?echo GetMessage("KDA_IE_SETTINGS_MULTIPLE_TO_VALUE");?>
					<input type="text" size="5" name="<?=$fName2?>" value="<?echo htmlspecialcharsbx($val2);?>">
				</td>
			</tr>
		<?}?>
		
		<?if($bTextHtml){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_HTML_TITLE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'TEXT_HTML');
					?>
					<select name="<?echo $fName;?>">
						<option value=""><?echo GetMessage("KDA_IE_SETTINGS_HTML_NOT_VALUE");?></option>
						<option value="text" <?if($val=='text'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_HTML_TEXT");?></option>
						<option value="html" <?if($val=='html'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_HTML_HTML");?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?
		if($bUser && is_callable(array('\Bitrix\Main\UserTable', 'getMap'))){
			$arUserFields = array();
			$arUserMap = \Bitrix\Main\UserTable::getMap();
			foreach($arUserMap as $k=>$v)
			{
				if(!($v instanceOf \Bitrix\Main\Entity\IntegerField || $v instanceOf \Bitrix\Main\Entity\StringField || $v instanceOf \Bitrix\Main\Entity\TextField || (is_array($v) && in_array($v['data_type'], array('integer', 'string', 'text')) && !isset($v['expression'])))) continue;
				$columnName = '';
				if(is_callable(array($v, 'getColumnName'))) $columnName = $v->getColumnName();
				elseif(is_array($v) && !is_numeric($k)) $columnName = $k;
				if(!$columnName) continue;
				if(GetMessage("KDA_IE_SETTINGS_USER_REL_FIELD_".$columnName)) $fieldTitle = GetMessage("KDA_IE_SETTINGS_USER_REL_FIELD_".$columnName);
				elseif(is_callable(array($v, 'getTitle'))) $fieldTitle = $v->getTitle();
				else $fieldTitle = $columnName;
				$arUserFields[$columnName] = $fieldTitle;
				
			}
			if(!empty($arUserFields))
			{
		?>
		
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_USER_REL_FIELD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'USER_REL_FIELD');
					?>
					<select name="<?echo $fName;?>">
						<?
						foreach($arUserFields as $k=>$v)
						{
							echo '<option value="'.($k=='ID' ? '' : htmlspecialcharsbx($k)).'"'.($val==$k ? ' selected' : '').'>'.htmlspecialcharsbx($v).'</option>';
						}
						?>
					</select>
				</td>
			</tr>
		<?	
			}
		}
		?>
		
		<?if($bDiscountValue || $bSaleDiscountValue){?>
			<?
			$dbPriceType = CCatalogGroup::GetList(array("SORT" => "ASC"));
			$arPriceTypes = array();
			while($arPriceType = $dbPriceType->Fetch())
			{
				$arPriceTypes[] = $arPriceType;
			}
			if(count($arPriceTypes) > 1){
			?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PRICE_TYPE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'CATALOG_GROUP_IDS');
					if(!is_array($val)) $val = array();
					?>
					<select name="<?echo $fName;?>[]" multiple>
						<?foreach($arPriceTypes as $arPriceType){?>
							<option value="<?echo $arPriceType["ID"]?>" <?if(in_array($arPriceType["ID"], $val)){echo 'selected';}?>><?echo ($arPriceType["NAME_LANG"] ? $arPriceType["NAME_LANG"] : $arPriceType["NAME"]);?></option>
						<?}?>
					</select>
				</td>
			</tr>
			<?}?>
		<?}
		if($bDiscountValue || $bSaleDiscountValue){?>
			<?
			$dbSite = \CIBlock::GetSite($IBLOCK_ID);
			$arSites = array();
			while($arSite = $dbSite->Fetch())
			{
				$arSites[] = $arSite;
			}
			if(count($arSites) > 1){
			?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SITE_ID");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SITE_IDS');
					if(!is_array($val)) $val = array();
					?>
					<select name="<?echo $fName;?>[]" multiple size="3">
						<?foreach($arSites as $arSite){?>
							<option value="<?echo $arSite["SITE_ID"]?>" <?if(in_array($arSite["SITE_ID"], $val)){echo 'selected';}?>><?echo '['.$arSite["SITE_ID"].'] '.$arSite["NAME"];?></option>
						<?}?>
					</select>
				</td>
			</tr>
			<?}?>
		<?}?>
		
		<?if($bIblockElementSet || $bIblockElementXmlId){?>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("KDA_IE_SETTINGS_CHANGE_LINKED_IBLOCK");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'CHANGE_LINKED_IBLOCK');
					list($fName2, $val2) = GetFieldEextraVal($PEXTRASETTINGS, 'LINKED_IBLOCK');
					if(!is_array($val2)) $val2 = array();
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?> onchange="$('#linked_iblock').css('display', (this.checked ? '' : 'none'));"><br>
					<select type="text" id="linked_iblock" name="<?=$fName2?>[]" multiple <?=($val!='Y' ? 'style="display: none"' : '')?>>
						<?
						foreach($arIblocks as $type)
						{
							?><optgroup label="<?echo $type['NAME']?>"><?
							foreach($type['IBLOCKS'] as $iblock)
							{
								?><option value="<?echo $iblock["ID"];?>" <?if(in_array($iblock["ID"], $val2)){echo 'selected';}?>><?echo htmlspecialcharsbx($iblock["NAME"].' ['.$iblock["ID"].']'); ?></option><?
							}
							?></optgroup><?
						}
						?>
					</select>
				</td>
			</tr>
		  <?if($bIblockElementSet){?>
				<tr>
					<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("KDA_IE_SETTINGS_SET_ADD_PRODUCTS_TO_EXISTS_".$bIblockElementSetType);?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SET_ADD_PRODUCTS_TO_EXISTS');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
			  <?if($bIblockElementSetType=='SET2'){?>
				<tr>
					<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("KDA_IE_SETTINGS_CHECK_PRODUCTS_EXISTS");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'CHECK_PRODUCTS_EXISTS');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
			  <?}?>
		  <?}?>
		<?}?>
		
		<tr class="heading">
			<td colspan="2">
					<div class="kda-ie-settings-header-links">
						<div class="kda-ie-settings-header-links-inner">
							<a href="javascript:void(0)" onclick="ESettings.ExportConvCSV(this)"><?echo GetMessage("KDA_IE_SETTINGS_EXPORT_CSV"); ?></a> /
							<a href="javascript:void(0)" onclick="ESettings.ImportConvCSV(this)"><?echo GetMessage("KDA_IE_SETTINGS_IMPORT_CSV"); ?></a>
						</div>
					</div>
				<?echo GetMessage("KDA_IE_SETTINGS_CONVERSION_TITLE");?>
			</td>
		</tr>
		<tr>
			<td class="kda-ie-settings-margin-container kda-ie-conv-share-wrap" colspan="2" id="kda-ie-conv-wrap0">
				<?
				list($fName, $arVals) = GetFieldEextraVal($PEXTRASETTINGS, 'CONVERSION');
				$showCondition = true;
				if(!is_array($arVals) || count($arVals)==0)
				{
					$showCondition = false;
					$arVals = array(
						array(
							'CELL' => '',
							'WHEN' => '',
							'FROM' => '',
							'THEN' => '',
							'TO' => ''
						)
					);
				}
				
				$arColLetters = range('A', 'Z');
				foreach(range('A', 'Z') as $v1)
				{
					foreach(range('A', 'Z') as $v2)
					{
						$arColLetters[] = $v1.$v2;
					}
				}
				
				$arCellGroupOptions = array(
					'' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_CURRENT"),
				);
				for($i=1; $i<=$countCols; $i++)
				{
					$arCellGroupOptions[$i] = sprintf(GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_NUMBER"), $i, $arColLetters[$i-1]);
				}
				$arCellGroupOptions['ELSE'] = GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_ELSE");
				
				$arCellGroupOptions = array(
					array(
						'NAME' => '',
						'ITEMS' => $arCellGroupOptions
					)
				);
				
				$arGroupOptions = array(
					'CELL' => $arCellGroupOptions,
					'WHEN' => array(
						array(
							'NAME' => '',
							'ITEMS' => array(
								'EQ' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_EQ"),
								'NEQ' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NEQ"),
								'GT' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_GT"),
								'LT' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_LT"),
								'GEQ' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_GEQ"),
								'LEQ' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_LEQ"),
								'BETWEEN' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_BETWEEN"),
								'CONTAIN' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_CONTAIN"),
								'NOT_CONTAIN' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_CONTAIN"),
								'BEGIN_WITH' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_BEGIN_WITH"),
								'ENDS_IN' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_ENDS_IN"),
								'EMPTY' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_EMPTY"),
								'NOT_EMPTY' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_EMPTY"),
								'REGEXP' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_REGEXP"),
								'NOT_REGEXP' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_REGEXP"),
								'ANY' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_ANY")
							)
						)
					),
					'THEN' => array(
						array(
							'NAME' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_STRING"),
							'ITEMS' => array(
								'REPLACE_TO' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_REPLACE_TO"),
								'REMOVE_SUBSTRING' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_REMOVE_SUBSTRING"),
								'REPLACE_SUBSTRING_TO' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_REPLACE_SUBSTRING_TO"),
								'ADD_TO_BEGIN' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_ADD_TO_BEGIN"),
								'ADD_TO_END' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_ADD_TO_END"),
								'LCASE' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_LCASE"),
								'UCASE' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_UCASE"),
								'UFIRST' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_UFIRST"),
								'UWORD' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_UWORD"),
								'TRANSLIT' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_TRANSLIT")
							)
						),
						array(
							'NAME' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_HTML"),
							'ITEMS' => array(
								'STRIP_TAGS' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_STRIP_TAGS"),
								'CLEAR_TAGS' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_CLEAR_TAGS"),
								'DOWNLOAD_BY_LINK' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_DOWNLOAD_BY_LINK"),
								'DOWNLOAD_IMAGES' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_DOWNLOAD_IMAGES")
							)
						),
						array(
							'NAME' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_MATH"),
							'ITEMS' => array(
								'MATH_ROUND' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ROUND"),
								'MATH_MULTIPLY' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_MULTIPLY"),
								'MATH_DIVIDE' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_DIVIDE"),
								'MATH_ADD' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ADD"),
								'MATH_SUBTRACT' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT"),
								'MATH_ADD_PERCENT' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ADD_PERCENT"),
								'MATH_SUBTRACT_PERCENT' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT_PERCENT"),
								'MATH_FORMULA' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_FORMULA"),
							)
						),
						array(
							'NAME' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_OTHER"),
							'ITEMS' => array(
								'NOT_LOAD' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_NOT_LOAD"),
								'EXPRESSION' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_EXPRESSION")
							)
						)
					)
				);
				
				$arSelects = array();
				$arOptions = array();
				echo '<span style="display: none;">';
				foreach($arGroupOptions as $k=>$v)
				{
					$arOptions[$k] = array();
					$arSelects[$k] = '<select name="SHARE_'.htmlspecialcharsbx($k).'">';
					foreach($v as $k2=>$v2)
					{
						if(strlen($v2['NAME']) > 0 ) $arSelects[$k] .= '<optgroup label="'.htmlspecialcharsbx($v2['NAME']).'">';
						foreach($v2['ITEMS'] as $k3=>$v3)
						{
							$arOptions[$k][$k3] = preg_replace('/\(.*\)/', '', $v3);
							$arSelects[$k] .= '<option value="'.htmlspecialcharsbx($k3!==0 ? $k3 : '').'">'.htmlspecialcharsbx($v3).'</option>';
						}
						if(strlen($v2['NAME']) > 0 ) $arSelects[$k] .= '</optgroup>';
					}
					$arSelects[$k] .= '</select>';
					echo $arSelects[$k];
				}
				echo '</span>';
				
				foreach($arVals as $k=>$v)
				{
					/*
					$cellsOptions = '<option value="">'.sprintf(GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_CURRENT"), $i).'</option>';
					for($i=1; $i<=$countCols; $i++)
					{
						$cellsOptions .= '<option value="'.$i.'"'.($v['CELL']==$i ? ' selected' : '').'>'.sprintf(GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_NUMBER"), $i, $arColLetters[$i-1]).'</option>';
					}
					$cellsOptions .= '<option value="ELSE"'.($v['CELL']=='ELSE' ? ' selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_ELSE").'</option>';
					*/
					echo '<div class="kda-ie-settings-conversion" '.(!$showCondition ? 'style="display: none;"' : '').'>'.
							GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_TITLE").
							' <span class="field_cell kda-ie-conv-select" data-select-name="SHARE_CELL"><input type="hidden" name="'.$fName.'[CELL][]" value="'.htmlspecialcharsbx($v['CELL']).'"><span class="kda-ie-conv-select-value" data-default-val="'.htmlspecialcharsbx(current($arOptions['CELL'])).'">'.(array_key_exists($v['CELL'], $arOptions['CELL']) ? $arOptions['CELL'][$v['CELL']] : current($arOptions['CELL'])).'</span></span>'.
							/*' <select name="'.$fName.'[CELL][]" class="field_cell">'.
								$cellsOptions.
							'</select> '.*/
							' <span class="field_when kda-ie-conv-select" data-select-name="SHARE_WHEN"><input type="hidden" name="'.$fName.'[WHEN][]" value="'.htmlspecialcharsbx($v['WHEN']).'"><span class="kda-ie-conv-select-value" data-default-val="'.htmlspecialcharsbx(current($arOptions['WHEN'])).'">'.(array_key_exists($v['WHEN'], $arOptions['WHEN']) ? $arOptions['WHEN'][$v['WHEN']] : current($arOptions['WHEN'])).'</span></span>'.
							/*' <select name="'.$fName.'[WHEN][]" class="field_when">'.
								'<option value="EQ" '.($v['WHEN']=='EQ' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_EQ").'</option>'.
								'<option value="NEQ" '.($v['WHEN']=='NEQ' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NEQ").'</option>'.
								'<option value="GT" '.($v['WHEN']=='GT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_GT").'</option>'.
								'<option value="LT" '.($v['WHEN']=='LT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_LT").'</option>'.
								'<option value="GEQ" '.($v['WHEN']=='GEQ' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_GEQ").'</option>'.
								'<option value="LEQ" '.($v['WHEN']=='LEQ' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_LEQ").'</option>'.
								'<option value="BETWEEN" '.($v['WHEN']=='BETWEEN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_BETWEEN").'</option>'.
								'<option value="CONTAIN" '.($v['WHEN']=='CONTAIN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_CONTAIN").'</option>'.
								'<option value="NOT_CONTAIN" '.($v['WHEN']=='NOT_CONTAIN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_CONTAIN").'</option>'.
								'<option value="BEGIN_WITH" '.($v['WHEN']=='BEGIN_WITH' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_BEGIN_WITH").'</option>'.
								'<option value="ENDS_IN" '.($v['WHEN']=='ENDS_IN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_ENDS_IN").'</option>'.
								'<option value="EMPTY" '.($v['WHEN']=='EMPTY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_EMPTY").'</option>'.
								'<option value="NOT_EMPTY" '.($v['WHEN']=='NOT_EMPTY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_EMPTY").'</option>'.
								'<option value="REGEXP" '.($v['WHEN']=='REGEXP' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_REGEXP").'</option>'.
								'<option value="NOT_REGEXP" '.($v['WHEN']=='NOT_REGEXP' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_REGEXP").'</option>'.
								'<option value="ANY" '.($v['WHEN']=='ANY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_ANY").'</option>'.
							'</select> '.*/
							/*'<input type="text" name="'.$fName.'[FROM][]" class="field_from" value="'.htmlspecialcharsbx($v['FROM']).'"> '.*/
							' <span class="kda-ie-conv-field field_from">'.
								'<textarea name="'.$fName.'[FROM][]" rows="1">'.htmlspecialcharsbx($v['FROM']).'</textarea>'.
								'<input class="choose_val" value="..." type="button" onclick="ESettings.ShowChooseVal(this, '.$countCols.')">'.
							'</span>'.
							GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_THEN").
							' <span class="field_then kda-ie-conv-select" data-select-name="SHARE_THEN"><input type="hidden" name="'.$fName.'[THEN][]" value="'.htmlspecialcharsbx($v['THEN']).'"><span class="kda-ie-conv-select-value" data-default-val="'.htmlspecialcharsbx(current($arOptions['THEN'])).'">'.(array_key_exists($v['THEN'], $arOptions['THEN']) ? $arOptions['THEN'][$v['THEN']] : current($arOptions['THEN'])).'</span></span>'.
							/*' <select name="'.$fName.'[THEN][]">'.
								'<optgroup label="'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_STRING").'">'.
									'<option value="REPLACE_TO" '.($v['THEN']=='REPLACE_TO' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_REPLACE_TO").'</option>'.
									'<option value="REMOVE_SUBSTRING" '.($v['THEN']=='REMOVE_SUBSTRING' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_REMOVE_SUBSTRING").'</option>'.
									'<option value="REPLACE_SUBSTRING_TO" '.($v['THEN']=='REPLACE_SUBSTRING_TO' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_REPLACE_SUBSTRING_TO").'</option>'.
									'<option value="ADD_TO_BEGIN" '.($v['THEN']=='ADD_TO_BEGIN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_ADD_TO_BEGIN").'</option>'.
									'<option value="ADD_TO_END" '.($v['THEN']=='ADD_TO_END' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_ADD_TO_END").'</option>'.
									'<option value="LCASE" '.($v['THEN']=='LCASE' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_LCASE").'</option>'.
									'<option value="UCASE" '.($v['THEN']=='UCASE' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_UCASE").'</option>'.
									'<option value="UFIRST" '.($v['THEN']=='UFIRST' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_UFIRST").'</option>'.
									'<option value="UWORD" '.($v['THEN']=='UWORD' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_UWORD").'</option>'.
									'<option value="TRANSLIT" '.($v['THEN']=='TRANSLIT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_TRANSLIT").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_HTML").'">'.
									'<option value="STRIP_TAGS" '.($v['THEN']=='STRIP_TAGS' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_STRIP_TAGS").'</option>'.
									'<option value="CLEAR_TAGS" '.($v['THEN']=='CLEAR_TAGS' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_CLEAR_TAGS").'</option>'.
									'<option value="DOWNLOAD_BY_LINK" '.($v['THEN']=='DOWNLOAD_BY_LINK' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_DOWNLOAD_BY_LINK").'</option>'.
									'<option value="DOWNLOAD_IMAGES" '.($v['THEN']=='DOWNLOAD_IMAGES' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_DOWNLOAD_IMAGES").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_MATH").'">'.
									'<option value="MATH_ROUND" '.($v['THEN']=='MATH_ROUND' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ROUND").'</option>'.
									'<option value="MATH_MULTIPLY" '.($v['THEN']=='MATH_MULTIPLY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_MULTIPLY").'</option>'.
									'<option value="MATH_DIVIDE" '.($v['THEN']=='MATH_DIVIDE' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_DIVIDE").'</option>'.
									'<option value="MATH_ADD" '.($v['THEN']=='MATH_ADD' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ADD").'</option>'.
									'<option value="MATH_SUBTRACT" '.($v['THEN']=='MATH_SUBTRACT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT").'</option>'.
									'<option value="MATH_ADD_PERCENT" '.($v['THEN']=='MATH_ADD_PERCENT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ADD_PERCENT").'</option>'.
									'<option value="MATH_SUBTRACT_PERCENT" '.($v['THEN']=='MATH_SUBTRACT_PERCENT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT_PERCENT").'</option>'.
									'<option value="MATH_FORMULA" '.($v['THEN']=='MATH_FORMULA' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_FORMULA").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_OTHER").'">'.
									'<option value="NOT_LOAD" '.($v['THEN']=='NOT_LOAD' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_NOT_LOAD").'</option>'.
									'<option value="EXPRESSION" '.($v['THEN']=='EXPRESSION' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_EXPRESSION").'</option>'.
								'</optgroup>'.
							'</select> '.*/
							/*'<input type="text" name="'.$fName.'[TO][]" value="'.htmlspecialcharsbx($v['TO']).'">'.*/
							' <span class="kda-ie-conv-field field_to">'.
								'<textarea name="'.$fName.'[TO][]" rows="1">'.htmlspecialcharsbx($v['TO']).'</textarea>'.
								'<input class="choose_val" value="..." type="button" onclick="ESettings.ShowChooseVal(this, '.$countCols.')">'.
							'</span>'.
							'<a href="javascript:void(0)" onclick="ESettings.ConversionUp(this)" title="'.GetMessage("KDA_IE_SETTINGS_UP").'" class="up"></a>'.
							'<a href="javascript:void(0)" onclick="ESettings.ConversionDown(this)" title="'.GetMessage("KDA_IE_SETTINGS_DOWN").'" class="down"></a>'.
							'<a href="javascript:void(0)" onclick="ESettings.RemoveConversion(this)" title="'.GetMessage("KDA_IE_SETTINGS_DELETE").'" class="delete"></a>'.
						 '</div>';
				}
				?>
				<a href="javascript:void(0)" onclick="return ESettings.AddConversion(this, event);" title="<?echo GetMessage("KDA_IE_SETTINGS_CONVERSION_ADD_HINT");?>"><?echo GetMessage("KDA_IE_SETTINGS_CONVERSION_ADD_VALUE");?></a>
			</td>
		</tr>
		<?if($listNumber!==false && $colNumber!==false){?>
			<tr>
				<td colspan="2" class="kda_ie_col_value_list">
					<a href="javascript:void(0)" onclick="return ESettings.ShowValuesFromFile(this, '<?=$profileId?>', '<?echo $listNumber;?>', '<?echo $colNumber;?>', 0)" title="<?echo GetMessage("KDA_IE_SHOW_VALUES_FROM_FILE");?>"><?echo GetMessage("KDA_IE_SHOW_VALUES_FROM_FILE");?></a>
					<div style="display: none;">
					<a href="javascript:void(0)" onclick="return ESettings.ShowValuesFromFile(this, '<?=$profileId?>', '<?echo $listNumber;?>', '<?echo $colNumber;?>', 0)" title="<?echo GetMessage("KDA_IE_SHOW_VALUES_FROM_FILE_SOURCE");?>"><?echo GetMessage("KDA_IE_SHOW_VALUES_FROM_FILE_SOURCE");?></a>
					/
					<a href="javascript:void(0)" onclick="return ESettings.ShowValuesFromFile(this, '<?=$profileId?>', '<?echo $listNumber;?>', '<?echo $colNumber;?>', 1)" title="<?echo GetMessage("KDA_IE_SHOW_VALUES_FROM_FILE_CONV");?>"><?echo GetMessage("KDA_IE_SHOW_VALUES_FROM_FILE_CONV");?></a>
					</div>
				</td>
			</tr>
		<?}?>
		
		
		<?if($bPrice){
			list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'MARGINS');
			$pfile = '';
			$arMarginTemplates = CKDAImportExtrasettings::GetMarginTemplates($pfile);
			$showMargin = true;
			if($_POST['action']=='load_margin_template' && is_array($arMarginTemplates[$_POST['template_id']]))
			{
				$val = $arMarginTemplates[$_POST['template_id']]['MARGINS'];
			}
			if(!is_array($val) || count($val)==0)
			{
				$showMargin = false;
				$val = array(array(
					'TYPE' => 1,
					'PERCENT' => '',
					'PRICE_FROM' => '',
					'PRICE_TO' => ''
				));
			}
			?>
			<tr class="heading">
				<td colspan="2">
					<div class="kda-ie-settings-header-links">
						<div class="kda-ie-settings-header-links-inner">
							<a href="javascript:void(0)" onclick="ESettings.ShowMarginTemplateBlockLoad(this)"><?echo GetMessage("KDA_IE_SETTINGS_LOAD_TEMPLATE"); ?></a> /
							<a href="javascript:void(0)" onclick="ESettings.ShowMarginTemplateBlock(this)"><?echo GetMessage("KDA_IE_SETTINGS_SAVE_TEMPLATE"); ?></a>
						</div>
						<div class="kda-ie-settings-margin-templates" id="margin_templates">
							<div class="kda-ie-settings-margin-templates-inner">
								<?echo GetMessage("KDA_IE_SETTINGS_MARGIN_CHOOSE_EXISTS_TEMPLATE"); ?><br>
								<select name="MARGIN_TEMPLATE_ID">
									<option value=""><?echo GetMessage("KDA_IE_SETTINGS_MARGIN_NOT_CHOOSE"); ?></option>
									<?
									foreach($arMarginTemplates as $key=>$template)
									{
										?><option value="<?=$key?>"><?=$template['TITLE']?></option><?
									}
									?>
								</select><br>
								<?echo GetMessage("KDA_IE_SETTINGS_MARGIN_NEW_TEMPLATE"); ?><br>
								<input type="text" name="MARGIN_TEMPLATE_NAME" value="" placeholder="<?echo GetMessage("KDA_IE_SETTINGS_MARGIN_TEMPLATE_NAME"); ?>"><br>
								<input type="submit" onclick="return ESettings.SaveMarginTemplate(this, '<?echo GetMessage("KDA_IE_SETTINGS_TEMPLATE_SAVED"); ?>');" name="save" value="<?echo GetMessage("KDA_IE_SETTINGS_SAVE_BTN"); ?>">
							</div>
						</div>
						<div class="kda-ie-settings-margin-templates" id="margin_templates_load">
							<div class="kda-ie-settings-margin-templates-inner">
								<?echo GetMessage("KDA_IE_SETTINGS_MARGIN_CHOOSE_TEMPLATE"); ?><br>
								<select name="MARGIN_TEMPLATE_ID">
									<option value=""><?echo GetMessage("KDA_IE_SETTINGS_MARGIN_NOT_CHOOSE"); ?></option>
									<?
									foreach($arMarginTemplates as $key=>$template)
									{
										?><option value="<?=$key?>"><?=$template['TITLE']?></option><?
									}
									?>
								</select><br>
								<a href="javascript:void(0)" onclick="ESettings.RemoveMarginTemplate(this, '<?echo GetMessage("KDA_IE_SETTINGS_TEMPLATE_DELETED"); ?>')" title="<?echo GetMessage("KDA_IE_SETTINGS_DELETE"); ?>" class="delete"></a>
								<input type="submit" onclick="return ESettings.LoadMarginTemplate(this);" name="save" value="<?echo GetMessage("KDA_IE_SETTINGS_LOAD_BTN"); ?>">
							</div>
						</div>
					</div>
					<?echo GetMessage("KDA_IE_SETTINGS_MARGIN_TITLE"); ?>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="kda-ie-settings-margin-container">
					<div id="settings_margins">
						<?
						foreach($val as $k=>$v)
						{
						?>
							<div class="kda-ie-settings-margin" style="display: <?=($showMargin ? 'block' : 'none')?>;">
								<?echo GetMessage("KDA_IE_SETTINGS_APPLY"); ?> <select name="<?=$fName?>[TYPE][]"><option value="1" <?=($v['TYPE']==1 ? 'selected' : '')?>><?echo GetMessage("KDA_IE_SETTINGS_APPLY_MARGIN"); ?></option><option value="-1" <?=($v['TYPE']==-1 ? 'selected' : '')?>><?echo GetMessage("KDA_IE_SETTINGS_APPLY_DISCOUNT"); ?></option></select>
								<input type="text" name="<?=$fName?>[PERCENT][]" value="<?=htmlspecialcharsbx($v['PERCENT'])?>">
								<select name="<?=$fName?>[PERCENT_TYPE][]"><option value="P" <?=($v['PERCENT_TYPE']=='P' ? 'selected' : '')?>><?echo GetMessage("KDA_IE_SETTINGS_TYPE_PERCENT"); ?></option><option value="F" <?=($v['PERCENT_TYPE']=='F' ? 'selected' : '')?>><?echo GetMessage("KDA_IE_SETTINGS_TYPE_FIX"); ?></option></select>
								<?echo GetMessage("KDA_IE_SETTINGS_AT_PRICE"); ?> <?echo GetMessage("KDA_IE_SETTINGS_FROM"); ?> <input type="text" name="<?=$fName?>[PRICE_FROM][]" value="<?=htmlspecialcharsbx($v['PRICE_FROM'])?>">
								<?echo GetMessage("KDA_IE_SETTINGS_TO"); ?> <input type="text" name="<?=$fName?>[PRICE_TO][]" value="<?=htmlspecialcharsbx($v['PRICE_TO'])?>">
								<a href="javascript:void(0)" onclick="ESettings.RemoveMargin(this)" title="<?echo GetMessage("KDA_IE_SETTINGS_DELETE"); ?>" class="delete"></a>
							</div>
						<?
						}
						?>
						<input type="button" value="<?echo GetMessage("KDA_IE_SETTINGS_ADD_MARGIN_DISCOUNT"); ?>" onclick="ESettings.AddMargin(this)">
					</div>
				</td>
			</tr>
			
			<tr class="heading">
				<td colspan="2">
					<?echo GetMessage("KDA_IE_SETTINGS_PRICE_PROCESSING"); ?>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PRICE_ROUND");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PRICE_ROUND_RULE');
					?>
					<select name="<?=$fName?>">
						<option value=""><?echo GetMessage("KDA_IE_SETTINGS_PRICE_ROUND_NOT");?></option>
						<option value="ROUND" <?if($val=='ROUND') echo 'selected';?>><?echo GetMessage("KDA_IE_SETTINGS_PRICE_ROUND_ROUND");?></option>
						<option value="CEIL" <?if($val=='CEIL') echo 'selected';?>><?echo GetMessage("KDA_IE_SETTINGS_PRICE_ROUND_CEIL");?></option>
						<option value="FLOOR" <?if($val=='FLOOR') echo 'selected';?>><?echo GetMessage("KDA_IE_SETTINGS_PRICE_ROUND_FLOOR");?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PRICE_ROUND_COEFFICIENT");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PRICE_ROUND_COEFFICIENT');
					?>
					<input type="text" name="<?=$fName?>" value="<?echo htmlspecialcharsbx($val)?>">
					<span id="hint_PRICE_ROUND_COEFFICIENT"></span><script>BX.hint_replace(BX('hint_PRICE_ROUND_COEFFICIENT'), '<?echo GetMessage("KDA_IE_SETTINGS_PRICE_ROUND_COEFFICIENT_HINT"); ?>');</script>
				</td>
			</tr>
			
			<?if($field!="ICAT_PURCHASING_PRICE"){?>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_PRICE_USE_EXT");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PRICE_USE_EXT');
						$priceExt = $val;
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?echo ($val=='Y' ? 'checked' : '')?> onchange="$('#price_ext').css('display', (this.checked ? '' : 'none'));">
					</td>
				</tr>
				<tr id="price_ext" <?if($priceExt!='Y'){echo 'style="display: none;"';}?>>
					<td class="adm-detail-content-cell-l"></td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PRICE_QUANTITY_FROM');
						?>
						<?echo GetMessage("KDA_IE_SETTINGS_PRICE_QUANTITY_FROM");?>
						<input type="text" name="<?=$fName?>" value="<?echo htmlspecialcharsbx($val)?>" size="10">
						<input class="choose_val" value="..." type="button" onclick="ESettings.ShowChooseVal(this, <?echo $countCols?>, true)">
						&nbsp; &nbsp;
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PRICE_QUANTITY_TO');
						?>
						<?echo GetMessage("KDA_IE_SETTINGS_PRICE_QUANTITY_TO");?>
						<input type="text" name="<?=$fName?>" value="<?echo htmlspecialcharsbx($val)?>" size="10">
						<input class="choose_val" value="..." type="button" onclick="ESettings.ShowChooseVal(this, <?echo $countCols?>, true)">
					</td>
				</tr>
			<?}?>
		<?}
		
		
		
		
		if($bPicture)
		{
			$arFieldNames = array(
				'SCALE',
				'WIDTH',
				'HEIGHT',
				'IGNORE_ERRORS_DIV',
				'IGNORE_ERRORS',
				'METHOD_DIV',
				'METHOD',
				'COMPRESSION',
				'USE_WATERMARK_FILE',
				'WATERMARK_FILE',
				'WATERMARK_FILE_ALPHA',
				'WATERMARK_FILE_POSITION',
				'USE_WATERMARK_TEXT',
				'WATERMARK_TEXT',
				'WATERMARK_TEXT_FONT',
				'WATERMARK_TEXT_COLOR',
				'WATERMARK_TEXT_SIZE',
				'WATERMARK_TEXT_POSITION',
				'CHANGE_EXTENSION',
				'NEW_EXTENSION',
				'MIRROR'
			);
			$arFields = array();
			foreach($arFieldNames as $k=>$field)
			{
				list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'PICTURE_PROCESSING');
				$arFields[$field] = array(
					'NAME' => $fName.'['.$field.']',
					'VALUE' => (is_array($val) && array_key_exists($field, $val) ? $val[$field] : '')
				);
			}
			?>
			<tr class="heading">
				<td colspan="2"><?echo GetMessage("KDA_IE_SETTINGS_PICTURE_PROCESSING"); ?></td>
			</tr>
			
			<?/*?>
			<tr>
				<td class="adm-detail-content-cell-r" colspan="2" style="padding-left: 50px;">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'EXCLUDE_DETAIL_PICTURE');
					?>
					<div class="adm-list-item">
						<div class="adm-list-control">
							<input type="hidden" name="<?=$fName?>" value="N">
							<input type="checkbox" id="<?echo md5($fName);?>" name="<?=$fName?>" value="Y" <?echo ($val=='Y' ? 'checked' : '')?>>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo md5($fName);?>"
							><?echo GetMessage("KDA_IE_SETTINGS_EXCLUDE_DETAIL_PICTURE");?></label>
						</div>
					</div>
				</td>
			</tr>
			<?*/?>
			
			<tr>
				<td class="adm-detail-content-cell-r" colspan="2" style="padding-left: 50px;">
				<?if($bElemPicture){?>
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'INCLUDE_PICTURE_PROCESSING');
						$bElemPictureIncProcessing = (bool)($val=='Y');

						echo BeginNote();
						echo GetMessage("KDA_IE_SETTINGS_INCLUDE_PICTURE_PROCESSING_NOTE");
						echo EndNote();
						?>
						<div class="adm-list-item">
							<div class="adm-list-control">
								<input type="checkbox" id="<?echo md5($fName);?>" name="<?=$fName?>" value="Y" <?echo ($val=='Y' ? 'checked' : '')?> onclick="if(this.checked){$('#kda_picprocessing_wrap').show();}else{$('#kda_picprocessing_wrap').hide();}">
							</div>
							<div class="adm-list-label">
								<label
									for="<?echo md5($fName);?>"
								><?echo GetMessage("KDA_IE_SETTINGS_INCLUDE_PICTURE_PROCESSING");?></label>
							</div>
						</div>
				<?}?>
				<div id="kda_picprocessing_wrap" <?if($bElemPicture && !$bElemPictureIncProcessing){echo 'style="display: none;"';}?>>
					<div></div>
					<div class="adm-list-item">
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['SCALE']['NAME']?>"
								name="<?echo $arFields['SCALE']['NAME']?>"
								<?
								if($arFields['SCALE']['VALUE']==="Y")
									echo "checked";
								?>
								onclick="
									BX('DIV_<?echo $arFields['WIDTH']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['HEIGHT']['NAME']?>').style.display =
									/*BX('DIV_<?echo $arFields['IGNORE_ERRORS_DIV']['NAME']?>').style.display =*/
									BX('DIV_<?echo $arFields['METHOD_DIV']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['COMPRESSION']['NAME']?>').style.display =
									this.checked? 'block': 'none';
								"
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['SCALE']['NAME']?>"
							><?echo GetMessage("KDA_IE_PICTURE_SCALE")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WIDTH']['NAME']?>"
						style="padding-left:16px;display:<?
							echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_WIDTH")?>:&nbsp;<input name="<?echo $arFields['WIDTH']['NAME']?>" type="text" value="<?echo htmlspecialcharsbx($arFields['WIDTH']['VALUE'])?>" size="7">
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['HEIGHT']['NAME']?>"
						style="padding-left:16px;display:<?
							echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_HEIGHT")?>:&nbsp;<input name="<?echo $arFields['HEIGHT']['NAME']?>" type="text" value="<?echo htmlspecialcharsbx($arFields['HEIGHT']['VALUE'])?>" size="7">
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['IGNORE_ERRORS_DIV']['NAME']?>"
						style="padding-left:16px;display:<?
							//echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
							echo 'none';
						?>"
					>
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['IGNORE_ERRORS']['NAME']?>"
								name="<?echo $arFields['IGNORE_ERRORS']['NAME']?>"
								<?
								if($arFields['IGNORE_ERRORS']['VALUE']==="Y")
									echo "checked";
								?>
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['IGNORE_ERRORS']['NAME']?>"
							><?echo GetMessage("KDA_IE_PICTURE_IGNORE_ERRORS")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['METHOD_DIV']['NAME']?>"
						style="padding-left:16px;display:<?
							echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
						?>"
					>
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['METHOD']['NAME']?>"
								name="<?echo $arFields['METHOD']['NAME']?>"
								<?
									if($arFields['METHOD']['VALUE']==="Y")
										echo "checked";
								?>
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['METHOD']['NAME']?>"
							><?echo GetMessage("KDA_IE_PICTURE_METHOD")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['COMPRESSION']['NAME']?>"
						style="padding-left:16px;display:<?
							echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_COMPRESSION")?>:&nbsp;<input
							name="<?echo $arFields['COMPRESSION']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['COMPRESSION']['VALUE'])?>"
							style="width: 30px"
						>
					</div>
					<div class="adm-list-item">
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>"
								name="<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>"
								<?
								if($arFields['USE_WATERMARK_FILE']['VALUE']==="Y")
									echo "checked";
								?>
								onclick="
									BX('DIV_<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_FILE_ALPHA']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_FILE_POSITION']['NAME']?>').style.display =
									this.checked? 'block': 'none';
								"
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>"
							><?echo GetMessage("KDA_IE_PICTURE_USE_WATERMARK_FILE")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_FILE']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?CAdminFileDialog::ShowScript(array(
							"event" => "BtnClick".strtr($fieldName, array('['=>'_', ']'=>'_')),
							"arResultDest" => array("ELEMENT_ID" => strtr($arFields['WATERMARK_FILE']['NAME'], array('['=>'_', ']'=>'_'))),
							"arPath" => array("PATH" => GetDirPath($arFields['WATERMARK_FILE']['VALUE'])),
							"select" => 'F',// F - file only, D - folder only
							"operation" => 'O',// O - open, S - save
							"showUploadTab" => true,
							"showAddToMenuTab" => false,
							"fileFilter" => 'jpg,jpeg,png,gif',
							"allowAllFiles" => false,
							"SaveConfig" => true,
						));?>
						<?echo GetMessage("KDA_IE_PICTURE_WATERMARK_FILE")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_FILE']['NAME']?>"
							id="<?echo strtr($arFields['WATERMARK_FILE']['NAME'], array('['=>'_', ']'=>'_'))?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_FILE']['VALUE'])?>"
							size="35"
						>&nbsp;<input type="button" value="..." onClick="BtnClick<?echo strtr($fieldName, array('['=>'_', ']'=>'_'))?>()">
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_FILE_ALPHA']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_FILE']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_WATERMARK_FILE_ALPHA")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_FILE_ALPHA']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_FILE_ALPHA']['VALUE'])?>"
							size="3"
						>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_FILE_POSITION']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_FILE']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_WATERMARK_POSITION")?>:&nbsp;<?echo SelectBox(
							$arFields['WATERMARK_FILE_POSITION']['NAME'],
							IBlockGetWatermarkPositions(),
							"",
							$arFields['WATERMARK_FILE_POSITION']['VALUE']
						);?>
					</div>
					<div class="adm-list-item">
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>"
								name="<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>"
								<?
								if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y")
									echo "checked";
								?>
								onclick="
									BX('DIV_<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_TEXT_FONT']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_TEXT_SIZE']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_TEXT_POSITION']['NAME']?>').style.display =
									this.checked? 'block': 'none';
								"
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>"
							><?echo GetMessage("KDA_IE_PICTURE_USE_WATERMARK_TEXT")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_WATERMARK_TEXT")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_TEXT']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_TEXT']['VALUE'])?>"
							size="35"
						>
						<?CAdminFileDialog::ShowScript(array(
							"event" => "BtnClickFont".strtr($fieldName, array('['=>'_', ']'=>'_')),
							"arResultDest" => array("ELEMENT_ID" => strtr($arFields['WATERMARK_TEXT_FONT']['NAME'], array('['=>'_', ']'=>'_'))),
							"arPath" => array("PATH" => GetDirPath($arFields['WATERMARK_TEXT_FONT']['VALUE'])),
							"select" => 'F',// F - file only, D - folder only
							"operation" => 'O',// O - open, S - save
							"showUploadTab" => true,
							"showAddToMenuTab" => false,
							"fileFilter" => 'ttf',
							"allowAllFiles" => false,
							"SaveConfig" => true,
						));?>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_TEXT_FONT']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_WATERMARK_TEXT_FONT")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_TEXT_FONT']['NAME']?>"
							id="<?echo strtr($arFields['WATERMARK_TEXT_FONT']['NAME'], array('['=>'_', ']'=>'_'))?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_TEXT_FONT']['VALUE'])?>"
							size="35">&nbsp;<input
							type="button"
							value="..."
							onClick="BtnClickFont<?echo strtr($fieldName, array('['=>'_', ']'=>'_'))?>()"
						>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_WATERMARK_TEXT_COLOR")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>"
							id="<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_TEXT_COLOR']['VALUE'])?>"
							size="7"
						><script>
							function EXTRA_WATERMARK_TEXT_COLOR(color)
							{
								BX('<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>').value = color.substring(1);
							}
						</script>&nbsp;<input
							type="button"
							value="..."
							onclick="BX.findChildren(this.parentNode, {'tag': 'IMG'}, true)[0].onclick();"
						><span style="float:left;width:1px;height:1px;visibility:hidden;position:absolute;"><?
							$APPLICATION->IncludeComponent(
								"bitrix:main.colorpicker",
								"",
								array(
									"SHOW_BUTTON" =>"Y",
									"ONSELECT" => "EXTRA_WATERMARK_TEXT_COLOR",
								)
							);
						?></span>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_TEXT_SIZE']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_WATERMARK_SIZE")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_TEXT_SIZE']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_TEXT_SIZE']['VALUE'])?>"
							size="3"
						>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_TEXT_POSITION']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['WATERMARK_TEXT_POSITION']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_WATERMARK_POSITION")?>:&nbsp;<?echo SelectBox(
							$arFields['WATERMARK_TEXT_POSITION']['NAME'],
							IBlockGetWatermarkPositions(),
							"",
							$arFields['WATERMARK_TEXT_POSITION']['VALUE']
						);?>
					</div>
					<div class="adm-list-item">
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['CHANGE_EXTENSION']['NAME']?>"
								name="<?echo $arFields['CHANGE_EXTENSION']['NAME']?>"
								<?
								if($arFields['CHANGE_EXTENSION']['VALUE']==="Y")
									echo "checked";
								?>
								onclick="
									BX('DIV_<?echo $arFields['NEW_EXTENSION']['NAME']?>').style.display =
									this.checked? 'block': 'none';
								"
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['CHANGE_EXTENSION']['NAME']?>"
							><?echo GetMessage("KDA_IE_PICTURE_CHANGE_EXTENSION")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['NEW_EXTENSION']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['CHANGE_EXTENSION']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("KDA_IE_PICTURE_NEW_EXTENSION")?>:&nbsp;<select
							name="<?echo $arFields['NEW_EXTENSION']['NAME']?>"
						>
							<option value="webp"<?if($arFields['NEW_EXTENSION']['VALUE']=='webp'){echo ' selected';}?>>webp</option>
							<option value="jpg"<?if($arFields['NEW_EXTENSION']['VALUE']=='jpg'){echo ' selected';}?>>jpg</option>
							<option value="png"<?if($arFields['NEW_EXTENSION']['VALUE']=='png'){echo ' selected';}?>>png</option>
						</select>
					</div>
					<?if(class_exists('\Bitrix\Main\File\Image')){?>
					<div class="adm-list-item">
						<div class="adm-list-control">
							<input type="checkbox" value="Y" id="<?echo $arFields['MIRROR']['NAME']?>" name="<?echo $arFields['MIRROR']['NAME']?>" <?echo ($arFields['MIRROR']['VALUE']==="Y" ? 'checked' : '')?>>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['MIRROR']['NAME']?>"
							><?echo GetMessage("KDA_IE_PICTURE_MIRROR")?></label>
						</div>
					</div>
					<?}?>
				</div>
				</td>
			</tr>
		<?}?>
		
		
		
		
		
		<?/*if($bPrice && !empty($arCurrency)){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_FIELD_CURRENCY");?>:</td>
				<td class="adm-detail-content-cell-r">			
					<select name="CURRENT_CURRENCY">
					<?
					$lcur = CCurrency::GetList(($by="sort"), ($order1="asc"), LANGUAGE_ID);
					foreach($arCurrency as $item)
					{
						?><option value="<?echo $item['CURRENCY']?>">[<?echo $item['CURRENCY']?>] <?echo $item['FULL_NAME']?></option><?
					}
					?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_CONVERT_CURRENCY");?>:</td>
				<td class="adm-detail-content-cell-r">			
					<select name="CONVERT_CURRENCY">
						<option value=""><?echo GetMessage("KDA_IE_CONVERT_NO_CHOOSE");?></option>
					<?
					$lcur = CCurrency::GetList(($by="sort"), ($order1="asc"), LANGUAGE_ID);
					foreach($arCurrency as $item)
					{
						?><option value="<?echo $item['CURRENCY']?>">[<?echo $item['CURRENCY']?>] <?echo $item['FULL_NAME']?></option><?
					}
					?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_PRICE_MARGIN");?>:</td>
				<td class="adm-detail-content-cell-r">			
					<input type="text" name="PRICE_MARGIN" value="0" size="5"> %
				</td>
			</tr>
		<?}*/?>
		
		<?if(1 /*$field!='SECTION_SEP_NAME'*/){?>
			<tr class="heading">
				<td colspan="2"><?echo GetMessage("KDA_IE_SETTINGS_FILTER"); ?></td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_FILTER_UPLOAD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $arVals) = GetFieldEextraVal($PEXTRASETTINGS, 'UPLOAD_VALUES');
					$fName .= '[]';
					if(!is_array($arVals) || count($arVals) == 0)
					{
						$arVals = array('');
					}
					foreach($arVals as $k=>$v)
					{
						$hide = (bool)in_array($v, array('{empty}', '{not_empty}'));
						$select = '<select name="filter_vals" onchange="ESettings.OnValChange(this)">'.
								'<option value="">'.GetMessage("KDA_IE_SETTINGS_FILTER_VAL").'</option>'.
								'<option value="{empty}" '.($v=='{empty}' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_FILTER_EMPTY").'</option>'.
								'<option value="{not_empty}" '.($v=='{not_empty}' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_FILTER_NOT_EMPTY").'</option>'.
							'</select>';
						echo '<div>'.$select.' <input type="text" name="'.$fName.'" value="'.htmlspecialcharsbx($v).'" '.($hide ? 'style="display: none;"' : '').'></div>';
					}
					?>
					<a href="javascript:void(0)" onclick="ESettings.AddValue(this)"><?echo GetMessage("KDA_IE_ADD_VALUE");?></a>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_FILTER_NOT_UPLOAD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $arVals) = GetFieldEextraVal($PEXTRASETTINGS, 'NOT_UPLOAD_VALUES');
					$fName .= '[]';
					if(!is_array($arVals) || count($arVals) == 0)
					{
						$arVals = array('');
					}
					foreach($arVals as $k=>$v)
					{
						$hide = (bool)in_array($v, array('{empty}', '{not_empty}'));
						$select = '<select name="filter_vals" onchange="ESettings.OnValChange(this)">'.
								'<option value="">'.GetMessage("KDA_IE_SETTINGS_FILTER_VAL").'</option>'.
								'<option value="{empty}" '.($v=='{empty}' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_FILTER_EMPTY").'</option>'.
								'<option value="{not_empty}" '.($v=='{not_empty}' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_FILTER_NOT_EMPTY").'</option>'.
							'</select>';
						echo '<div>'.$select.' <input type="text" name="'.$fName.'" value="'.htmlspecialcharsbx($v).'" '.($hide ? 'style="display: none;"' : '').'></div>';
					}
					?>
					<a href="javascript:void(0)" onclick="ESettings.AddValue(this)"><?echo GetMessage("KDA_IE_ADD_VALUE");?></a>
				</td>
			</tr>
			<?if($field!='SECTION_SEP_NAME_PATH' && $field!='SECTION_SEP_NAME'){?>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_USE_FILTER_FOR_DEACTIVATE");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'USE_FILTER_FOR_DEACTIVATE');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
				<tr>
					<td class="kda-ie-settings-margin-container" colspan="2">
						<a href="javascript:void(0)" onclick="ESettings.ShowPHPExpression(this)"><?echo GetMessage("KDA_IE_SETTINGS_FILTER_EXPRESSION");?></a>
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'FILTER_EXPRESSION');
						?>
						<div class="kda-ie-settings-phpexpression" style="display: none;">
							<?echo GetMessage("KDA_IE_SETTINGS_FILTER_EXPRESSION_HINT");?>
							<textarea name="<?echo $fName?>"><?echo $val?></textarea>
						</div>
					</td>
				</tr>
			<?}?>
		<?}?>	
		
		<tr class="heading">
			<td colspan="2"><?echo GetMessage("KDA_IE_SETTINGS_ADDITIONAL"); ?></td>
		</tr>
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_ONLY_FOR_NEW");?>:</td>
			<td class="adm-detail-content-cell-r" style="min-width: 30%;">
				<?
				list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SET_NEW_ONLY');
				?>
				<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
			</td>
		</tr>
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_NOT_TRIM");?>:</td>
			<td class="adm-detail-content-cell-r" style="min-width: 30%;">
				<?
				list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'NOT_TRIM');
				?>
				<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
			</td>
		</tr>
		
		<?if($isOffer /*$bCanUseForSKUGenerate*/){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_BIND_TO_GENERATED_SKU");?>: <span id="hint_BIND_TO_GENERATED_SKU"></span><script>BX.hint_replace(BX('hint_BIND_TO_GENERATED_SKU'), '<?echo GetMessage("KDA_IE_SETTINGS_BIND_TO_GENERATED_SKU_HINT"); ?>');</script></td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'BIND_TO_GENERATED_SKU');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		<?if($isOffer){?>
			<?/*if($bOfferUid){?>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SEARCH_SINGLE_OFFERS");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SEARCH_SINGLE_OFFERS');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
			<?}*/?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_COPY_CELL_ON_OFFERS");?>: <span id="hint_COPY_CELL_ON_OFFERS"></span><script>BX.hint_replace(BX('hint_COPY_CELL_ON_OFFERS'), '<?echo GetMessage("KDA_IE_SETTINGS_COPY_CELL_ON_OFFERS_HINT"); ?>');</script></td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'COPY_CELL_ON_OFFERS');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if($bPicture){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_SAVE_ORIGINAL_PATH");?>: <span id="hint_SAVE_ORIGINAL_PATH"></span><script>BX.hint_replace(BX('hint_SAVE_ORIGINAL_PATH'), '<?echo GetMessage("KDA_IE_SETTINGS_SAVE_ORIGINAL_PATH_HINT"); ?>');</script></td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SAVE_ORIGINAL_PATH');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_FILE_TIMEOUT");?>:</td>
				<td class="adm-detail-content-cell-r" style="min-width: 30%;">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'FILE_TIMEOUT');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="8" placeholder="0">
				</td>
			</tr>		
		
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_FIELD_FILE_HEADERS");?>:</td>
				<td class="adm-detail-content-cell-r" style="min-width: 30%;">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'FILE_HEADERS');
					?>
					<textarea name="<?echo $fName?>" rows="2" cols="60" placeholder="<?echo GetMessage("KDA_IE_SETTINGS_EXAMPLE").":\r\n";
					?>User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0)<?echo "\r\n";
					?>Accept: text/html;q=0.9,image/webp,*/*;q=0.8"><?echo $val?></textarea>
				</td>
			</tr>
		<?}?>
		
		
		<?if($bExtLink){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_EXCEL_STYLES_TO_HTML");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'EXCEL_STYLES_TO_HTML');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_LOAD_BY_EXTLINK");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'LOAD_BY_EXTLINK');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if($bChangeable){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_LOADING_MODE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'LOADING_MODE');
					?>
					<select name="<?=$fName?>">
						<option value=""><?echo GetMessage("KDA_IE_SETTINGS_LOADING_MODE_CHANGE");?></option>
						<option value="ADD_BEFORE"<?if($val=='ADD_BEFORE'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_LOADING_MODE_BEFORE");?></option>
						<option value="ADD_AFTER"<?if($val=='ADD_AFTER'){echo 'selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_LOADING_MODE_AFTER");?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?if($bIblockElement && !$bIblockElementSet){?>
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_REL_ELEMENT_ALLOW_ORIG");?>:</td>
			<td class="adm-detail-content-cell-r">
				<?
				list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'REL_ELEMENT_ALLOW_ORIG');
				?>
				<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
			</td>
		</tr>
		<?}?>
		
		<?if($bMultipleProp && $bIblockElement && strlen($propertyName) > 0 && (!$iblockElementIblock || $iblockElementIblock==$IBLOCK_ID)){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_EXCLUDE_CURRENT_ELEMENT");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'EXCLUDE_CURRENT_ELEMENT');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if(!in_array($field, array('SECTION_SEP_NAME', 'SECTION_SEP_NAME_PATH'))){?>
		<?
		if(!$isOffer) $arSFields = $fl->GetSettingsFields($IBLOCK_ID);
		else $arSFields = $fl->GetSettingsFields($OFFER_IBLOCK_ID, $IBLOCK_ID);
		?>
		<tr class="heading">
			<td colspan="2"><?echo GetMessage("KDA_IE_SETTINGS_EXTRA_CONVERSION_TITLE");?></td>
		</tr>
		<tr>
			<td class="kda-ie-settings-margin-container kda-ie-conv-share-wrap" colspan="2" id="kda-ie-conv-wrap1">
				<?
				list($fName, $arVals) = GetFieldEextraVal($PEXTRASETTINGS, 'EXTRA_CONVERSION');
				$showCondition = true;
				if(!is_array($arVals) || count($arVals)==0)
				{
					$showCondition = false;
					$arVals = array(
						array(
							'CELL' => '',
							'WHEN' => '',
							'FROM' => '',
							'THEN' => '',
							'TO' => ''
						)
					);
				}
				
				$arCellGroupOptions = array(
					array(
						'NAME' => '',
						'ITEMS' => array(
							'' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_CURRENT"),
						)
					)
				);
				foreach($arSFields as $k=>$arGroup)
				{
					if(is_array($arGroup['FIELDS']))
					{
						$arCellGroupOptions[] = array(
							'NAME' => $arGroup['TITLE'],
							'ITEMS' => $arGroup['FIELDS']
						);
					}
				}


				$arCellOptions = array();
				for($i=1; $i<=$countCols; $i++)
				{
					$arCellOptions[$i] = sprintf(GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_NUMBER"), $i, $arColLetters[$i-1]);
				}
				$arCellGroupOptions[] = array(
					'NAME' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_GROUP_FILEVALS"),
					'ITEMS' => $arCellOptions
				);
				
				$arCellGroupOptions[] = array(
					'NAME' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_GROUP_OTHER"),
					'ITEMS' => array(
						'LOADED' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_LOADED"),
						'DUPLICATE' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_DUPLICATE"),
						'ELSE' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_ELSE"),
					)
				);
				
				$arGroupOptions['CELL'] = $arCellGroupOptions;
				$arGroupOptions['THEN'][count($arGroupOptions['THEN']) - 1]['ITEMS'] = array(
					'NOT_LOAD' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_NOT_LOAD"),
					'NOT_LOAD_ELEMENT' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_NOT_LOAD_ELEMENT"),
					'EXPRESSION' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_EXPRESSION")
				);
				
				$arSelects = array();
				$arOptions = array();
				echo '<span style="display: none;">';
				foreach($arGroupOptions as $k=>$v)
				{
					$arOptions[$k] = array();
					$arSelects[$k] = '<select name="SHARE_'.htmlspecialcharsbx($k).'">';
					foreach($v as $k2=>$v2)
					{
						if(strlen($v2['NAME']) > 0 ) $arSelects[$k] .= '<optgroup label="'.htmlspecialcharsbx($v2['NAME']).'">';
						foreach($v2['ITEMS'] as $k3=>$v3)
						{
							$arOptions[$k][$k3] = preg_replace('/\(.*\)/', '', $v3);
							$arSelects[$k] .= '<option value="'.htmlspecialcharsbx($k3!==0 ? $k3 : '').'">'.htmlspecialcharsbx($v3).'</option>';
						}
						if(strlen($v2['NAME']) > 0 ) $arSelects[$k] .= '</optgroup>';
					}
					$arSelects[$k] .= '</select>';
					echo $arSelects[$k];
				}
				echo '</span>';
			
							
				foreach($arVals as $k=>$v)
				{
					echo '<div class="kda-ie-settings-conversion" '.(!$showCondition ? 'style="display: none;"' : '').'>'.
							GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_TITLE").
							' <span class="field_cell kda-ie-conv-select" data-select-name="SHARE_CELL"><input type="hidden" name="'.$fName.'[CELL][]" value="'.htmlspecialcharsbx($v['CELL']).'"><span class="kda-ie-conv-select-value" data-default-val="'.htmlspecialcharsbx(current($arOptions['CELL'])).'">'.(array_key_exists($v['CELL'], $arOptions['CELL']) ? $arOptions['CELL'][$v['CELL']] : current($arOptions['CELL'])).'</span></span>'.
							/*' <select name="'.$fName.'[CELL][]" class="field_cell">'.
								$cellsOptions.
							'</select> '.*/
							' <span class="field_when kda-ie-conv-select" data-select-name="SHARE_WHEN"><input type="hidden" name="'.$fName.'[WHEN][]" value="'.htmlspecialcharsbx($v['WHEN']).'"><span class="kda-ie-conv-select-value" data-default-val="'.htmlspecialcharsbx(current($arOptions['WHEN'])).'">'.(array_key_exists($v['WHEN'], $arOptions['WHEN']) ? $arOptions['WHEN'][$v['WHEN']] : current($arOptions['WHEN'])).'</span></span>'.
							/*' <select name="'.$fName.'[WHEN][]" class="field_when">'.
								'<option value="EQ" '.($v['WHEN']=='EQ' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_EQ").'</option>'.
								'<option value="NEQ" '.($v['WHEN']=='NEQ' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NEQ").'</option>'.
								'<option value="GT" '.($v['WHEN']=='GT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_GT").'</option>'.
								'<option value="LT" '.($v['WHEN']=='LT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_LT").'</option>'.
								'<option value="GEQ" '.($v['WHEN']=='GEQ' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_GEQ").'</option>'.
								'<option value="LEQ" '.($v['WHEN']=='LEQ' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_LEQ").'</option>'.
								'<option value="BETWEEN" '.($v['WHEN']=='BETWEEN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_BETWEEN").'</option>'.
								'<option value="CONTAIN" '.($v['WHEN']=='CONTAIN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_CONTAIN").'</option>'.
								'<option value="NOT_CONTAIN" '.($v['WHEN']=='NOT_CONTAIN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_CONTAIN").'</option>'.
								'<option value="BEGIN_WITH" '.($v['WHEN']=='BEGIN_WITH' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_BEGIN_WITH").'</option>'.
								'<option value="ENDS_IN" '.($v['WHEN']=='ENDS_IN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_ENDS_IN").'</option>'.
								'<option value="EMPTY" '.($v['WHEN']=='EMPTY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_EMPTY").'</option>'.
								'<option value="NOT_EMPTY" '.($v['WHEN']=='NOT_EMPTY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_EMPTY").'</option>'.
								'<option value="REGEXP" '.($v['WHEN']=='REGEXP' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_REGEXP").'</option>'.
								'<option value="NOT_REGEXP" '.($v['WHEN']=='NOT_REGEXP' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_REGEXP").
								'<option value="ANY" '.($v['WHEN']=='ANY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_ANY").'</option>'.
							'</select> '.*/
							/*'<input type="text" name="'.$fName.'[FROM][]" class="field_from" value="'.htmlspecialcharsbx($v['FROM']).'"> '.*/
							' <span class="kda-ie-conv-field field_from">'.
								'<textarea name="'.$fName.'[FROM][]" rows="1">'.htmlspecialcharsbx($v['FROM']).'</textarea>'.
								'<input class="choose_val" value="..." type="button" onclick="ESettings.ShowExtraChooseVal(this, '.$countCols.')">'.
							'</span>'.
							GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_THEN").
							' <span class="field_then kda-ie-conv-select" data-select-name="SHARE_THEN"><input type="hidden" name="'.$fName.'[THEN][]" value="'.htmlspecialcharsbx($v['THEN']).'"><span class="kda-ie-conv-select-value" data-default-val="'.htmlspecialcharsbx(current($arOptions['THEN'])).'">'.(array_key_exists($v['THEN'], $arOptions['THEN']) ? $arOptions['THEN'][$v['THEN']] : current($arOptions['THEN'])).'</span></span>'.
							/*' <select name="'.$fName.'[THEN][]">'.
								'<optgroup label="'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_STRING").'">'.
									'<option value="REPLACE_TO" '.($v['THEN']=='REPLACE_TO' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_REPLACE_TO").'</option>'.
									'<option value="REMOVE_SUBSTRING" '.($v['THEN']=='REMOVE_SUBSTRING' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_REMOVE_SUBSTRING").'</option>'.
									'<option value="REPLACE_SUBSTRING_TO" '.($v['THEN']=='REPLACE_SUBSTRING_TO' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_REPLACE_SUBSTRING_TO").'</option>'.
									'<option value="ADD_TO_BEGIN" '.($v['THEN']=='ADD_TO_BEGIN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_ADD_TO_BEGIN").'</option>'.
									'<option value="ADD_TO_END" '.($v['THEN']=='ADD_TO_END' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_ADD_TO_END").'</option>'.
									'<option value="LCASE" '.($v['THEN']=='LCASE' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_LCASE").'</option>'.
									'<option value="UCASE" '.($v['THEN']=='UCASE' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_UCASE").'</option>'.
									'<option value="UFIRST" '.($v['THEN']=='UFIRST' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_UFIRST").'</option>'.
									'<option value="UWORD" '.($v['THEN']=='UWORD' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_UWORD").'</option>'.
									'<option value="TRANSLIT" '.($v['THEN']=='TRANSLIT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_TRANSLIT").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_HTML").'">'.
									'<option value="STRIP_TAGS" '.($v['THEN']=='STRIP_TAGS' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_STRIP_TAGS").'</option>'.
									'<option value="CLEAR_TAGS" '.($v['THEN']=='CLEAR_TAGS' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_CLEAR_TAGS").'</option>'.
									'<option value="DOWNLOAD_BY_LINK" '.($v['THEN']=='DOWNLOAD_BY_LINK' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_DOWNLOAD_BY_LINK").'</option>'.
									'<option value="DOWNLOAD_IMAGES" '.($v['THEN']=='DOWNLOAD_IMAGES' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_DOWNLOAD_IMAGES").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_MATH").'">'.
									'<option value="MATH_ROUND" '.($v['THEN']=='MATH_ROUND' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ROUND").'</option>'.
									'<option value="MATH_MULTIPLY" '.($v['THEN']=='MATH_MULTIPLY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_MULTIPLY").'</option>'.
									'<option value="MATH_DIVIDE" '.($v['THEN']=='MATH_DIVIDE' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_DIVIDE").'</option>'.
									'<option value="MATH_ADD" '.($v['THEN']=='MATH_ADD' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ADD").'</option>'.
									'<option value="MATH_SUBTRACT" '.($v['THEN']=='MATH_SUBTRACT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT").'</option>'.
									'<option value="MATH_ADD_PERCENT" '.($v['THEN']=='MATH_ADD_PERCENT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ADD_PERCENT").'</option>'.
									'<option value="MATH_SUBTRACT_PERCENT" '.($v['THEN']=='MATH_SUBTRACT_PERCENT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT_PERCENT").'</option>'.
									'<option value="MATH_FORMULA" '.($v['THEN']=='MATH_FORMULA' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_FORMULA").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_OTHER").'">'.
									'<option value="NOT_LOAD" '.($v['THEN']=='NOT_LOAD' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_NOT_LOAD_FIELD").'</option>'.
									'<option value="NOT_LOAD_ELEMENT" '.($v['THEN']=='NOT_LOAD_ELEMENT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_NOT_LOAD_ELEMENT").'</option>'.
									'<option value="EXPRESSION" '.($v['THEN']=='EXPRESSION' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_EXPRESSION").'</option>'.
								'</optgroup>'.
							'</select> '.*/
							/*'<input type="text" name="'.$fName.'[TO][]" value="'.htmlspecialcharsbx($v['TO']).'">'.*/
							' <span class="kda-ie-conv-field field_to">'.
								'<textarea name="'.$fName.'[TO][]" rows="1">'.htmlspecialcharsbx($v['TO']).'</textarea>'.
								'<input class="choose_val" value="..." type="button" onclick="ESettings.ShowExtraChooseVal(this, '.$countCols.')">'.
							'</span>'.
							'<a href="javascript:void(0)" onclick="ESettings.ConversionUp(this)" title="'.GetMessage("KDA_IE_SETTINGS_UP").'" class="up"></a>'.
							'<a href="javascript:void(0)" onclick="ESettings.ConversionDown(this)" title="'.GetMessage("KDA_IE_SETTINGS_DOWN").'" class="down"></a>'.
							'<a href="javascript:void(0)" onclick="ESettings.RemoveConversion(this)" title="'.GetMessage("KDA_IE_SETTINGS_DELETE").'" class="delete"></a>'.
						 '</div>';
				}
				?>
				<a href="javascript:void(0)" onclick="return ESettings.AddConversion(this, event);"><?echo GetMessage("KDA_IE_SETTINGS_CONVERSION_ADD_VALUE");?></a>
			</td>
		</tr>
		<?}?>
		
		<tr>
			<td colspan="2">
				<br><br><br><br><br><br><br>
			</td>
		</tr>
	</table>
</form>
<?
if(!is_array($arSFields)) $arSFields = array();

$arRates = array(
	'USD' => GetMessage("KDA_IE_SETTINGS_LANG_RATE_USD"),
	'EUR' => GetMessage("KDA_IE_SETTINGS_LANG_RATE_EUR"),
);
if($bCurrency && is_callable(array('\Bitrix\Currency\CurrencyTable', 'getList')))
{
	$dbRes = \Bitrix\Currency\CurrencyTable::getList(array('filter'=>array('!CURRENCY'=>array('USD', 'EUR', 'RUB', 'RUR')), 'select'=>array('CURRENCY', 'NAME'=>'CURRENT_LANG_FORMAT.FULL_NAME')));
	while($arr = $dbRes->Fetch())
	{
		$arRates[$arr['CURRENCY']] = GetMessage("KDA_IE_SETTINGS_LANG_RATE_ITEM").' '.$arr['CURRENCY'].' ('.$arr['NAME'].')';
	}
}
?>
<script>
var admKDASettingMessages = {
	'VAL': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_CURRENT_VALUE"));?>',
	'CELL_VALUE': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_CELL_VALUE"));?>',
	'CLINK': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_CELL_LINK"));?>',
	'CNOTE': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_CELL_COMMENT"));?>',
	'HASH': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_HASH_FILEDS"));?>',
	'FILENAME': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_IFILENAME"));?>',
	'FILEDATE': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_IFILEDATE"));?>',
	'SHEETNAME': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_ISHEETNAME"));?>',
	'ROWNUMBER': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_IROWNUMBER"));?>',
	'DATETIME': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_DATETIME"));?>',
	'SEP_SECTION': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_SEP_SECTION"));?>',
	'RATE_USD': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_RATE_USD"));?>',
	'RATE_EUR': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_RATE_EUR"));?>',
	'RATES': <?echo (is_array($arRates) && count($arRates) > 0 ? \KdaIE\Utils::PhpToJSObject($arRates) : "''");?>,
	'EXTRAFIELDS': <?echo \KdaIE\Utils::PhpToJSObject($arSFields)?>,
	'VALUES': <?echo (is_array($arPropVals) && count($arPropVals) > 0 ? \KdaIE\Utils::PhpToJSObject($arPropVals) : "''");?>
};
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");?>