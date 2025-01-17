<?
if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
$moduleId = 'kda.importexcel';
CModule::IncludeModule('iblock');
CModule::IncludeModule($moduleId);
$bCurrency = CModule::IncludeModule("currency");
IncludeModuleLangFile(__FILE__);

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT <= "R") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$HIGHLOADBLOCK_ID = $_REQUEST['HIGHLOADBLOCK_ID'];
$fieldName = htmlspecialcharsex($_GET['field_name']);

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
$arFields = $fl->GetHigloadBlockFields($HIGHLOADBLOCK_ID);

$addField = '';
if(strpos($field, '|') !== false)
{
	list($field, $addField) = explode('|', $field);
}

/*$obJSPopup = new CJSPopup();
$obJSPopup->ShowTitlebar(GetMessage("KDA_IE_SETTING_UPLOAD_FIELD").($arFields[$field]['NAME_LANG'] ? ' "'.$arFields[$field]['NAME_LANG'].'"' : ''));*/

$oProfile = new CKDAImportProfile('highload');
$oProfile->ApplyExtra($PEXTRASETTINGS, $_REQUEST['PROFILE_ID']);
if(isset($_POST['POSTEXTRA']) && strlen($_POST['POSTEXTRA']) > 0)
{
	$arFieldParams = $_POST['POSTEXTRA'];
	if(!defined('BX_UTF') || !BX_UTF)
	{
		$arFieldParams = $APPLICATION->ConvertCharset($arFieldParams, 'UTF-8', 'CP1251');
	}
	$arFieldParams = CUtil::JsObjectToPhp($arFieldParams);
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

if($_POST['action']=='save' && is_array($_POST['EXTRASETTINGS']))
{
	$APPLICATION->RestartBuffer();
	ob_end_clean();
	
	CKDAImportExtrasettings::HandleParams($PEXTRASETTINGS, $_POST['EXTRASETTINGS']);
	preg_match_all('/\[([_\d]+[_P\d]*)\]/', $fieldName, $keys);
	$oid = 'field_settings_'.$keys[1][0].'_'.$keys[1][1];
	
	if($_GET['return_data'])
	{
		$returnJson = (empty($PEXTRASETTINGS[$keys[1][0]][$keys[1][1]]) ? '""' : CUtil::PhpToJSObject($PEXTRASETTINGS[$keys[1][0]][$keys[1][1]]));
		echo '<script>EList.SetExtraParams("'.$oid.'", '.$returnJson.')</script>';
	}
	else
	{
		$oProfile->UpdateExtra($_REQUEST['PROFILE_ID'], $PEXTRASETTINGS);
		if(!empty($PEXTRASETTINGS[$keys[1][0]][$keys[1][1]])) echo '<script>$("#'.$oid.'").removeClass("inactive");</script>';
		else echo '<script>$("#'.$oid.'").addClass("inactive");</script>';
		echo '<script>BX.WindowManager.Get().Close();</script>';
	}
	
	die();
}

$oProfile = new CKDAImportProfile('highload');
$arProfile = $oProfile->GetByID($_REQUEST['PROFILE_ID']);
$SETTINGS_DEFAULT = $arProfile['SETTINGS_DEFAULT'];


$bPicture = false;
$bIblockElement = false;
$bIblockSection = false;
$bMultipleProp = false;
$bHlblock = true;
$bCrmCompany = false;
$arPropVals = array();
$maxPropVals = 1000;

$ftype = $arFields[$field]['USER_TYPE_ID'];
if($ftype=='file')
{
	$bPicture = true;
}
elseif($ftype=='iblock_element')
{
	$bIblockElement = true;
	$iblockElementIblock = ($arFields[$field]['SETTINGS']['IBLOCK_ID'] ? $arFields[$field]['SETTINGS']['IBLOCK_ID'] : 0);
	if($iblockElementIblock > 0)
	{
		$dbRes = \CIblockElement::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), array('IBLOCK_ID'=>$iblockElementIblock), false, array('nTopCount'=>$maxPropVals), array('ID', 'NAME'));
		while($arr = $dbRes->Fetch())
		{
			$arPropVals[] = $arr['NAME'];
		}
	}
}
elseif($ftype=='iblock_section')
{
	$bIblockSection = true;
	$iblockSectionIblock = ($arProp['LINK_IBLOCK_ID'] ? $arProp['LINK_IBLOCK_ID'] : $IBLOCK_ID);
}
elseif($ftype=='hlblock' && $arFields[$field]['SETTINGS']['HLBLOCK_ID'] && CModule::IncludeModule('highloadblock'))
{
	$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('ID'=>$arFields[$field]['SETTINGS']['HLBLOCK_ID'])))->fetch();
	$dbRes = CUserTypeEntity::GetList(array('SORT'=>'ASC', 'ID'=>'ASC'), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID'], 'LANG'=>LANGUAGE_ID));
	$arHLFields = array('ID'=>'ID');
	$arHLFieldByIds = array();
	while($arHLField = $dbRes->Fetch())
	{
		$arHLFieldByIds[$arHLField['ID']] = $arHLField['FIELD_NAME'];
		$arHLFields[$arHLField['FIELD_NAME']] = ($arHLField['EDIT_FORM_LABEL'] ? $arHLField['EDIT_FORM_LABEL'] : $arHLField['FIELD_NAME']);
	}
	$bHlblock = true;

	if($arFields[$field]['SETTINGS']['HLFIELD_ID'] && isset($arHLFieldByIds[$arFields[$field]['SETTINGS']['HLFIELD_ID']]))
	{
		$showField = $arHLFieldByIds[$arFields[$field]['SETTINGS']['HLFIELD_ID']];
		$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$entityDataClass = $entity->getDataClass();
		$dbRes = $entityDataClass::getList(array('order'=>array($showField=>'ASC'), 'select'=>array($showField), 'group'=>array($showField), 'limit'=>$maxPropVals));
		while($arr = $dbRes->Fetch())
		{
			$arPropVals[] = $arr[$showField];
		}
	}
}
elseif($ftype=='enumeration')
{
	$fenum = new CUserFieldEnum();
	$dbRes = $fenum->GetList(array("SORT"=>"ASC", "VALUE"=>"ASC"), array('USER_FIELD_ID'=>$arFields[$field]['ID']));
	while(($arr = $dbRes->Fetch()) && count($arPropVals)<=$maxPropVals)
	{
		$arPropVals[] = $arr['VALUE'];
	}
}
elseif($ftype=='crm' && CModule::IncludeModule('crm'))
{
	$bCrmCompany = true;
}

if($arFields[$field]['MULTIPLE']=='Y') $bMultipleProp = true;

$bUid = false;
if($arFields['UID']=='Y')
{
	$bUid = true;
}

$countCols = intval($_REQUEST['count_cols']);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");
?>
<form action="" method="post" enctype="multipart/form-data" name="field_settings">
	<input type="hidden" name="action" value="save">
	<table width="100%">
		<col width="50%">
		<col width="50%">
		<?if($bIblockElement){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_REL_ELEMENT_FIELD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'REL_ELEMENT_FIELD');
					$strOptions = $fl->GetSelectUidFields($iblockElementIblock, $val, '');
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
					?>
					<select name="<?echo $fName;?>" class="chosen">
						<option value="ID"<?if($val=='ID') echo ' selected';?>><?echo GetMessage("KDA_IE_SETTINGS_SECTION_ID"); ?></option>
						<option value="NAME"<?if($val=='NAME') echo ' selected';?>><?echo GetMessage("KDA_IE_SETTINGS_SECTION_NAME"); ?></option>
						<option value="CODE"<?if($val=='CODE') echo ' selected';?>><?echo GetMessage("KDA_IE_SETTINGS_SECTION_CODE"); ?></option>
						<option value="XML_ID"<?if($val=='XML_ID') echo ' selected';?>><?echo GetMessage("KDA_IE_SETTINGS_SECTION_XML_ID"); ?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?if($bHlblock && !empty($arHLFields)){?>
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
							echo '<option value="'.$k.'"'.(($val==$k || (!$val && $k=='ID')) ? ' selected' : '').'>'.$name.'</option>';
						}
						?>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?if($bUid){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_ELEMENT_SEARCH_SUBSTRING");?>: <span id="hint_UID_SEARCH_SUBSTRING"></span><script>BX.hint_replace(BX('hint_UID_SEARCH_SUBSTRING'), '<?echo GetMessage("KDA_IE_SETTINGS_ELEMENT_SEARCH_SUBSTRING_HINT"); ?>');</script></td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'UID_SEARCH_SUBSTRING');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if($bMultipleProp){?>
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
		
		<?if($bCrmCompany){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_REL_CRM_COMPANY_FIELD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'REL_CRM_COMPANY_FIELD');
					?>
					<select name="<?echo $fName;?>">
						<option value=""<?if($val==''){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_REL_CRM_COMPANY_ID");?></option>
						<option value="TITLE"<?if($val=='TITLE'){echo ' selected';}?>><?echo GetMessage("KDA_IE_SETTINGS_REL_CRM_COMPANY_TITLE");?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<tr class="heading">
			<td colspan="2"><?echo GetMessage("KDA_IE_SETTINGS_CONVERSION_TITLE");?></td>
		</tr>
		<tr>
			<td class="kda-ie-settings-margin-container kda-ie-conv-share-wrap" colspan="2">
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
								//'BETWEEN' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_BETWEEN"),
								'CONTAIN' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_CONTAIN"),
								'NOT_CONTAIN' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_CONTAIN"),
								//'BEGIN_WITH' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_BEGIN_WITH"),
								//'ENDS_IN' => GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_ENDS_IN"),
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
								//'DOWNLOAD_BY_LINK' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_DOWNLOAD_BY_LINK"),
								//'DOWNLOAD_IMAGES' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_DOWNLOAD_IMAGES")
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
								//'MATH_ADD_PERCENT' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ADD_PERCENT"),
								//'MATH_SUBTRACT_PERCENT' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT_PERCENT"),
								//'MATH_FORMULA' => GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_FORMULA"),
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
					/*$cellsOptions = '<option value="">'.sprintf(GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_CURRENT"), $i).'</option>';
					for($i=1; $i<=$countCols; $i++)
					{
						$cellsOptions .= '<option value="'.$i.'"'.($v['CELL']==$i ? ' selected' : '').'>'.sprintf(GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_NUMBER"), $i, $arColLetters[$i-1]).'</option>';
					}
					$cellsOptions .= '<option value="ELSE"'.($v['CELL']=='ELSE' ? ' selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CELL_ELSE").'</option>';*/
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
								'<option value="CONTAIN" '.($v['WHEN']=='CONTAIN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_CONTAIN").'</option>'.
								'<option value="NOT_CONTAIN" '.($v['WHEN']=='NOT_CONTAIN' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_CONTAIN").'</option>'.
								'<option value="EMPTY" '.($v['WHEN']=='EMPTY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_EMPTY").'</option>'.
								'<option value="NOT_EMPTY" '.($v['WHEN']=='NOT_EMPTY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_EMPTY").'</option>'.
								'<option value="REGEXP" '.($v['WHEN']=='REGEXP' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_REGEXP").'</option>'.
								'<option value="NOT_REGEXP" '.($v['WHEN']=='NOT_REGEXP' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_NOT_REGEXP").'</option>'.
								'<option value="ANY" '.($v['WHEN']=='ANY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_CONDITION_ANY").'</option>'.
							'</select> '.*/
							/*<input type="text" name="'.$fName.'[FROM][]" class="field_from" value="'.htmlspecialcharsbx($v['FROM']).'"> '.*/
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
									'<option value="STRIP_TAGS" '.($v['THEN']=='STRIP_TAGS' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_STRIP_TAGS").'</option>'.
									'<option value="CLEAR_TAGS" '.($v['THEN']=='CLEAR_TAGS' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_CLEAR_TAGS").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_GROUP_MATH").'">'.
									'<option value="MATH_ROUND" '.($v['THEN']=='MATH_ROUND' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ROUND").'</option>'.
									'<option value="MATH_MULTIPLY" '.($v['THEN']=='MATH_MULTIPLY' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_MULTIPLY").'</option>'.
									'<option value="MATH_DIVIDE" '.($v['THEN']=='MATH_DIVIDE' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_DIVIDE").'</option>'.
									'<option value="MATH_ADD" '.($v['THEN']=='MATH_ADD' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_ADD").'</option>'.
									'<option value="MATH_SUBTRACT" '.($v['THEN']=='MATH_SUBTRACT' ? 'selected' : '').'>'.GetMessage("KDA_IE_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT").'</option>'.
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
		
		
		<?
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
			<tr>
				<td class="adm-detail-content-cell-l"></td>
				<td class="adm-detail-content-cell-r">
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
				</td>
			</tr>
		<?}?>
		
		
		<tr class="heading">
			<td colspan="2"><?echo GetMessage("KDA_IE_SETTINGS_FILTER"); ?></td>
		</tr>
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_FILTER_UPLOAD");?>:</td>
			<td class="adm-detail-content-cell-r">
				<?
				list($fName, $arVals) = GetFieldEextraVal($PEXTRASETTINGS, 'UPLOAD_VALUES');
				$fName .= '[]';
				if(is_array($arVals) && count($arVals) > 0)
				{
					foreach($arVals as $k=>$v)
					{
						echo '<div><input type="text" name="'.$fName.'" value="'.htmlspecialchars($v).'"></div>';
					}
				}
				else
				{
					echo '<div><input type="text" name="'.$fName.'" value=""></div>';
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
				if(is_array($arVals) && count($arVals) > 0)
				{
					foreach($arVals as $k=>$v)
					{
						echo '<div><input type="text" name="'.$fName.'" value="'.htmlspecialchars($v).'"></div>';
					}
				}
				else
				{
					echo '<div><input type="text" name="'.$fName.'" value=""></div>';
				}
				?>
				<a href="javascript:void(0)" onclick="ESettings.AddValue(this)"><?echo GetMessage("KDA_IE_ADD_VALUE");?></a>
			</td>
		</tr>
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
		
		
		<tr class="heading">
			<td colspan="2"><?echo GetMessage("KDA_IE_SETTINGS_ADDITIONAL"); ?></td>
		</tr>
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_SETTINGS_ONLY_FOR_NEW");?>:</td>
			<td class="adm-detail-content-cell-r">
				<?
				list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'SET_NEW_ONLY');
				?>
				<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
			</td>
		</tr>
	</table>
</form>
<script>
var admKDASettingMessages = {
	'CELL_VALUE': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_CELL_VALUE"));?>',
	'CELL_LINK': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_CELL_LINK"));?>',
	'IFILENAME': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_IFILENAME"));?>',
	'ISHEETNAME': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_ISHEETNAME"));?>',
	'RATE_USD': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_RATE_USD"));?>',
	'RATE_EUR': '<?echo htmlspecialcharsex(GetMessage("KDA_IE_SETTINGS_LANG_RATE_EUR"));?>',
	'VALUES': <?echo (is_array($arPropVals) && count($arPropVals) > 0 ? CUtil::PhpToJSObject($arPropVals) : "''");?>
};
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");?>