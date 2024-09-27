<?php
namespace Bitrix\KdaImportexcel;

use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class Conversion
{
	protected $loadElemFields = array();
	protected $updateElemFields = array();
	protected $disableConv = false;
	protected $fieldSettings = array();
	protected $fieldPrefixes = array('IE_', 'IP_PROP', 'ICAT_', 'IPROP_TEMP_', 'ISECT_');
	protected $iblockId = 0;
	protected $offersIblockId = 0;
	protected $parentId = 0;
	protected $elementId = 0;
	protected $elementIsDuplicate = false;
	protected $elementFields = null;
	protected $isSku = false;
	protected $allowLoad = true;
	
	public function __construct($ie=false, $iblockId=0, $fieldSettings = array())
	{
		$this->ie = $ie;
		$this->iblockId = $iblockId;
		$this->fieldSettings = $fieldSettings;
		
		$prefixPattern = '/^(OFFER_|PARENT_)?('.implode('|', $this->fieldPrefixes).')[A-Z0-9_]+$/';
		$prefixPattern2 = '/#((OFFER_|PARENT_)?('.implode('|', $this->fieldPrefixes).')[A-Z0-9_]+)#/';
		foreach($fieldSettings as $fieldName=>$fieldSet)
		{
			if(isset($fieldSet['EXTRA_CONVERSION']) && !empty($fieldSet['EXTRA_CONVERSION']))
			{
				if(!in_array($fieldName, $this->updateElemFields)) $this->updateElemFields[] = $fieldName;
				foreach($fieldSet['EXTRA_CONVERSION'] as $k=>$v)
				{
					$loadElemFields = array();
					if(preg_match_all($prefixPattern2, $v['TO'], $m)) $loadElemFields = array_merge($loadElemFields, $m[1]);
					if(preg_match_all($prefixPattern2, $v['FROM'], $m)) $loadElemFields = array_merge($loadElemFields, $m[1]);
					if(preg_match($prefixPattern, $v['CELL'])) $loadElemFields[] = $v['CELL'];
					foreach($loadElemFields as $loadElemField)
					{
						if(!in_array($loadElemField, $this->loadElemFields)) $this->loadElemFields[] = $loadElemField;
					}
				}
			}
		}
	}
	
	public function AddLoadElemFields($arFields)
	{
		if(is_array($arFields))
		{
			foreach($arFields as $field)
			{
				if(!in_array($field, $this->loadElemFields)) $this->loadElemFields[] = $field;
			}
		}
	}
	
	public function Enable()
	{
		$this->disableConv = false;
	}
	
	public function Disable()
	{
		$this->disableConv = true;
	}
	
	public function SetSkuMode($isSku, $offersIblockId = 0, $parentId=0)
	{
		if($offersIblockId > 0) $this->offersIblockId = $offersIblockId;
		if($parentId > 0) $this->parentId = $parentId;
		$this->isSku = (bool)$isSku;
	}
	
	public function GetSkuMode()
	{
		return (bool)$this->isSku;
	}
	
	public function SetAllowLoad($allow = true)
	{
		$allow = (bool)$allow;
		$this->allowLoad = $allow;
	}
	
	public function GetAllowLoad()
	{
		return $this->allowLoad;
	}
	
	public function BeginUpdateFields()
	{
		$this->SetAllowLoad(true);
	}
	
	public function EndUpdateFields()
	{
		return $this->GetAllowLoad();
	}
	
	public function GetLoadElemFields()
	{
		return $this->loadElemFields;
	}
	
	public function GetChangedElementFields(&$arFieldsElement, $ID)
	{
		if($this->disableConv || empty($this->updateElemFields))
		{
			$arFieldsElement = array();
			return;
		}
		$this->BeginUpdateFields();
		foreach($arFieldsElement as $fieldKey=>$fieldVal)
		{
			if($fieldKey=='IPROPERTY_TEMPLATES')
			{
				foreach($fieldVal as $fieldKey2=>$fieldVal2)
				{
					$fKey2 = 'IPROP_TEMP_'.$fieldKey2;
					$newVal = $this->UpdateElementField($fKey2, $fieldVal2, $ID);
					if($newVal!==$fieldVal2 && $newVal!==false)
					{
						$arFieldsElement[$fieldKey][$fieldKey2] = $newVal;
					}
					else
					{
						unset($arFieldsElement[$fieldKey][$fieldKey2]);
						if(empty($arFieldsElement[$fieldKey])) unset($arFieldsElement[$fieldKey]);
					}
				}
				continue;
			}
			$fKey = 'IE_'.$fieldKey;
			$newVal = $this->UpdateElementField($fKey, $fieldVal, $ID);
			if($newVal!==$fieldVal && $newVal!==false) $arFieldsElement[$fieldKey] = $newVal;
			else unset($arFieldsElement[$fieldKey]);
		}
		return $this->EndUpdateFields();
	}

	public function UpdateElementFields(&$arFieldsElement, $ID)
	{
		if($this->disableConv || empty($this->updateElemFields)) return;
		$this->BeginUpdateFields();
		foreach($arFieldsElement as $fieldKey=>$fieldVal)
		{
			if($fieldKey=='IPROPERTY_TEMPLATES')
			{
				foreach($fieldVal as $fieldKey2=>$fieldVal2)
				{
					$fKey2 = 'IPROP_TEMP_'.$fieldKey2;
					$arFieldsElement[$fieldKey][$fieldKey2] = $this->UpdateElementField($fKey2, $fieldVal2, $ID);
					if($arFieldsElement[$fieldKey][$fieldKey2]===false)
					{
						unset($arFieldsElement[$fieldKey][$fieldKey2]);
						if(empty($arFieldsElement[$fieldKey])) unset($arFieldsElement[$fieldKey]);
					}
				}
				continue;
			}
			$fKey = 'IE_'.$fieldKey;
			$arFieldsElement[$fieldKey] = $this->UpdateElementField($fKey, $fieldVal, $ID);
			if($arFieldsElement[$fieldKey]===false) unset($arFieldsElement[$fieldKey]);
		}
		return $this->EndUpdateFields();
	}
	
	public function UpdateSectionFields(&$arSections, $ID)
	{
		if($this->disableConv || empty($this->updateElemFields)) return;
		$this->BeginUpdateFields();
		foreach($arSections as $k=>$arSection)
		{
			foreach($arSection as $fieldKey=>$fieldVal)
			{
				$fKey = 'ISECT'.$k.'_'.$fieldKey;
				if(count($arSections)==1 && $k==0 && !isset($this->fieldSettings[$fKey]['EXTRA_CONVERSION']))
				{
					$fKey2 = 'ISECT_'.$fieldKey;
					if(isset($this->fieldSettings[$fKey2]['EXTRA_CONVERSION'])) $fKey = $fKey2;
				}
				$arSections[$k][$fieldKey] = $this->UpdateElementField($fKey, $fieldVal, $ID);
				if($arSections[$k][$fieldKey]===false) unset($arSections[$k][$fieldKey]);
			}
		}
		return $this->EndUpdateFields();
	}
	
	public function UpdateProperties(&$arProps, $ID)
	{
		if($this->disableConv || empty($this->updateElemFields)) return;
		$this->BeginUpdateFields();
		foreach($arProps as $propKey=>$propVal)
		{
			$fKey = 'IP_PROP'.$propKey;
			$findSubkeys = false;
			if(is_array($propVal))
			{
				foreach($propVal as $k=>$v)
				{
					$fKey2 = $fKey.'/'.$k;
					if(array_key_exists(($this->isSku ? 'OFFER_' : '').$fKey2, $this->fieldSettings))
					{
						$findSubkeys = true;
						$arProps[$propKey][$k] = $this->UpdateElementField($fKey2, $v, $ID);
						if($arProps[$propKey][$k]===false) unset($arProps[$propKey][$k]);
					}
				}
			}
			if(!$findSubkeys)
			{
				$arProps[$propKey] = $this->UpdateElementField($fKey, $propVal, $ID);
				if($arProps[$propKey]===false) unset($arProps[$propKey]);
			}
			if(is_array($arProps[$propKey]))
			{
				foreach($arProps[$propKey] as $k=>$v)
				{
					if($v===false && $v!==$propVal[$k]) unset($arProps[$propKey][$k]);
					if(empty($arProps[$propKey]) && !empty($propVal)) unset($arProps[$propKey]);
				}
			}
		}
		return $this->EndUpdateFields();
	}
	
	public function UpdateProduct(&$arProduct, &$arPrices, &$arStores, $ID)
	{
		if($this->disableConv || empty($this->updateElemFields)) return;
		$this->BeginUpdateFields();
		foreach($arProduct as $productKey=>$productVal)
		{
			$fKey = 'ICAT_'.$productKey;
			$arProduct[$productKey] = $this->UpdateElementField($fKey, $productVal, $ID);
			if($arProduct[$productKey]===false) unset($arProduct[$productKey]);
		}
		
		foreach($arPrices as $gid=>$arFieldsPrice)
		{
			foreach($arFieldsPrice as $priceKey=>$priceVal)
			{
				$fKey = 'ICAT_PRICE'.$gid.'_'.$priceKey;
				$arPrices[$gid][$priceKey] = $this->UpdateElementField($fKey, $priceVal, $ID);
				if($arPrices[$gid][$priceKey]===false) unset($arPrices[$gid][$priceKey]);
			}
		}
		
		foreach($arStores as $sid=>$arFieldsStore)
		{
			foreach($arFieldsStore as $storeKey=>$storeVal)
			{
				$fKey = 'ICAT_STORE'.$sid.'_'.$storeKey;
				$arStores[$sid][$storeKey] = $this->UpdateElementField($fKey, $storeVal, $ID);
				if($arStores[$sid][$storeKey]===false) unset($arStores[$sid][$storeKey]);
			}
		}
		return $this->EndUpdateFields();
	}
	
	public function UpdateDiscountFields(&$arFieldsProductDiscount, $ID)
	{
		if($this->disableConv || empty($this->updateElemFields)) return;
		$this->BeginUpdateFields();
		foreach($arFieldsProductDiscount as $fieldKey=>$fieldVal)
		{
			$fKey = 'ICAT_DISCOUNT_'.$fieldKey;
			$arFieldsProductDiscount[$fieldKey] = $this->UpdateElementField($fKey, $fieldVal, $ID);
			if($arFieldsProductDiscount[$fieldKey]===false) unset($arFieldsProductDiscount[$fieldKey]);
		}
		return $this->EndUpdateFields();
	}
	
	public function UpdateElementField($fKey, $val, $ID)
	{
		if($this->isSku) $fKey = 'OFFER_'.$fKey;
		$fs = $this->fieldSettings;
		if(!isset($fs[$fKey]) || !isset($fs[$fKey]['EXTRA_CONVERSION']) || !is_array($fs[$fKey]['EXTRA_CONVERSION'])) return $val;
		$fs = $fs[$fKey];
		
		if($fKey=='IE_SECTION_PATH') $this->JoinSectionPaths($val, $fKey);
		if(is_array($val))
		{
			if(isset($fs['REL_ELEMENT_EXTRA_FIELD']) && isset($fs['REL_ELEMENT_FIELD']) && isset($val[$fs['REL_ELEMENT_EXTRA_FIELD']]) && isset($val[$fs['REL_ELEMENT_EXTRA_FIELD']][$fs['REL_ELEMENT_FIELD']]))
			{
				$val[$fs['REL_ELEMENT_EXTRA_FIELD']][$fs['REL_ELEMENT_FIELD']] = $newVal = $this->UpdateElementField($fKey, $val[$fs['REL_ELEMENT_EXTRA_FIELD']][$fs['REL_ELEMENT_FIELD']], $ID);
				if($fs['REL_ELEMENT_EXTRA_FIELD']=='PRIMARY' && $newVal===false) return false;
			}
			else
			{
				foreach($val as $k=>$v)
				{
					$val[$k] = $this->ApplyExtraConversions($fKey, $v, $ID);
					if($val[$k]===false)
					{
						$val = false;
						break;
					}
				}
			}
		}
		else
		{
			$val = $this->ApplyExtraConversions($fKey, $val, $ID);
		}
		if($fKey=='IE_SECTION_PATH') $this->SplitSectionPaths($val, $fKey);
		return $val;
	}
	
	public function JoinSectionPaths(&$val, $fKey)
	{
		$fs = $this->fieldSettings[$fKey];
		$sep = ($fs['SECTION_PATH_SEPARATOR'] ? $fs['SECTION_PATH_SEPARATOR'] : '/');
		if(is_array($val))
		{
			foreach($val as $k=>$v)
			{
				if(is_array($v))
				{
					$val[$k] = implode($sep, $v);
				}
			}
			$val = implode($this->ie->params['ELEMENT_MULTIPLE_SEPARATOR'], $val);
		}
	}
	
	public function SplitSectionPaths(&$val, $fKey)
	{
		$fs = $this->fieldSettings[$fKey];
		$sep = ($fs['SECTION_PATH_SEPARATOR'] ? $fs['SECTION_PATH_SEPARATOR'] : '/');
		if($fs['SECTION_PATH_SEPARATED']=='Y')
			$arVals = explode($this->ie->params['ELEMENT_MULTIPLE_SEPARATOR'], $val);
		else $arVals = array($val);
		foreach($arVals as $k=>$subvalue)
		{
			$arVals[$k] = array_map('trim', explode($sep, $subvalue));
		}
		$val = $arVals;
	}
	
	public function ApplyExtraConversions($fKey, $val, $ID)
	{
		if(is_array($val)) return $val;
		$fs = $this->fieldSettings;
		
		//if(isset($fs[$fKey]) && isset($fs[$fKey]['EXTRA_CONVERSION']) && is_array($fs[$fKey]['EXTRA_CONVERSION']))
		if(is_array($fs[$fKey]['EXTRA_CONVERSION']))
		{
			$arFields = $this->GetElementFields($ID);
			$arFileFields = $this->ie->GetCurrentItemValues();
			$prefixPattern = '/^(PARENT_)?('.implode('|', $this->fieldPrefixes).')[A-Z0-9_]+$/';
			$prefixPattern2Part = '#(PARENT_)?(('.implode('|', $this->fieldPrefixes).'|VAL|CELL|DATETIME|FILEDATE|'.str_replace('#', '', implode('|', $this->ie->rcurrencies)).')[A-Z0-9_]*)#';
			$prefixPattern2 = '/((\$\{[\'"])'.$prefixPattern2Part.'([\'"]\})|'.$prefixPattern2Part.')/';
		
			foreach($fs[$fKey]['EXTRA_CONVERSION'] as $k=>$v)
			{
				$this->currentItemFieldVal = $val;
				$condVal = (string)$val;
				if(strlen($v['CELL']) > 0 && preg_match($prefixPattern, $v['CELL']))
				{
					if(isset($arFields[$v['CELL']])) $condVal = (string)$arFields[$v['CELL']];
					else $condVal = '';
				}
				elseif(strlen($v['CELL']) > 0 && preg_match('/^CELL(\d+)$/', $v['CELL'], $m2))
				{
					$cellKey = (int)$m2[1] - 1;
					if(isset($arFileFields[$cellKey])) $condVal = (string)$arFileFields[$cellKey];
					else $condVal = '';
				}

				if(strlen($v['FROM']) > 0) $v['FROM'] = preg_replace_callback($prefixPattern2, array($this, 'ConversionExtraReplaceValues'), $v['FROM']);

				if($v['CELL']=='ELSE' || $v['CELL']=='LOADED') $v['WHEN'] = '';
				if(($v['CELL']=='ELSE' && !$execConv)
					|| ($v['CELL']=='LOADED' && $this->IsAlreadyLoaded($ID))
					|| ($v['CELL']=='DUPLICATE' && $this->elementIsDuplicate)
					|| ($v['WHEN']=='EQ' && $condVal==$v['FROM'])
					|| ($v['WHEN']=='NEQ' && $condVal!=$v['FROM'])
					|| (in_array($v['WHEN'], array('GT', 'LT', 'GEQ', 'LEQ', 'BETWEEN')) && $this->CompareNums($condVal, $v['FROM'], $v['WHEN']))
					|| ($v['WHEN']=='CONTAIN' && strpos($condVal, $v['FROM'])!==false)
					|| ($v['WHEN']=='NOT_CONTAIN' && strpos($condVal, $v['FROM'])===false)
					|| ($v['WHEN']=='BEGIN_WITH' && strpos($condVal, $v['FROM'])===0)
					|| ($v['WHEN']=='ENDS_IN' && mb_substr($condVal, -mb_strlen($v['FROM']))===$v['FROM'])
					|| ($v['WHEN']=='REGEXP' && preg_match('/'.ToLower($v['FROM']).'/'.\CKDAImportUtils::getUtfModifier(), ToLower($condVal)))
					|| ($v['WHEN']=='NOT_REGEXP' && !preg_match('/'.ToLower($v['FROM']).'/'.\CKDAImportUtils::getUtfModifier(), ToLower($condVal)))
					|| ($v['WHEN']=='EMPTY' && strlen(trim($condVal))==0)
					|| ($v['WHEN']=='NOT_EMPTY' && strlen(trim($condVal)) > 0)
					|| ($v['WHEN']=='ANY'))
				{
					if(strlen($v['TO']) > 0) $v['TO'] = preg_replace_callback($prefixPattern2, array($this, 'ConversionExtraReplaceValues'), $v['TO']);
					if($v['THEN']=='REPLACE_TO') $val = $v['TO'];
					elseif($v['THEN']=='REMOVE_SUBSTRING' && strlen($v['TO']) > 0) $val = str_replace($v['TO'], '', $val);
					elseif($v['THEN']=='REPLACE_SUBSTRING_TO' && strlen($v['FROM']) > 0)
					{
						if($v['WHEN']=='REGEXP')
						{
							if(preg_match('/'.$v['FROM'].'/i'.\CKDAImportUtils::getUtfModifier(), $val)) $val = preg_replace('/'.$v['FROM'].'/i'.\CKDAImportUtils::getUtfModifier(), $v['TO'], $val);
							else $val = preg_replace('/'.ToLower($v['FROM']).'/i'.\CKDAImportUtils::getUtfModifier(), $v['TO'], $val);
						}
						else $val = str_replace($v['FROM'], $v['TO'], $val);
					}
					elseif($v['THEN']=='ADD_TO_BEGIN') $val = $v['TO'].$val;
					elseif($v['THEN']=='ADD_TO_END') $val = $val.$v['TO'];
					elseif($v['THEN']=='LCASE') $val = ToLower($val);
					elseif($v['THEN']=='UCASE') $val = ToUpper($val);
					elseif($v['THEN']=='UFIRST') $val = preg_replace_callback('/^(\s*)(.*)$/', array('\Bitrix\KdaImportexcel\Conversion', 'UFirstCallback'), $val);
					elseif($v['THEN']=='UWORD') $val = implode(' ', array_map(array('\Bitrix\KdaImportexcel\Conversion', 'UWordCallback'), explode(' ', $val)));
					elseif($v['THEN']=='MATH_ROUND') $val = round($this->GetFloatVal($val), $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_MULTIPLY') \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($val = $this->GetFloatVal($val) * $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_DIVIDE') $val = ($this->GetFloatVal($v['TO'])==0 ? 0 :\Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) / $this->GetFloatVal($v['TO'])));
					elseif($v['THEN']=='MATH_ADD') $val = \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) + $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_SUBTRACT') $val = \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) - $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_ADD_PERCENT') $val = (strlen($val) > 0 ? \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) * (1 + $this->GetFloatVal($v['TO'])/100)) : '');
					elseif($v['THEN']=='MATH_SUBTRACT_PERCENT') $val = (strlen($val) > 0 ? \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) * (1 - $this->GetFloatVal($v['TO'])/100)) : '');
					elseif($v['THEN']=='MATH_FORMULA') $val = $this->CalcFloatValue($v['TO']);
					elseif($v['THEN']=='EXPRESSION') $val = $this->ExecuteFilterExpression($ID, $val, $v['TO'], false, array('NAME'=>$fKey));
					elseif($v['THEN']=='STRIP_TAGS') $val = strip_tags($val);
					elseif($v['THEN']=='CLEAR_TAGS') $val = preg_replace('/<([a-z][a-z0-9:]*)[^>]*(\/?)>/i','<$1$2>', $val);
					elseif($v['THEN']=='TRANSLIT')
					{
						if(strlen($v['TO']) > 0) $val = $v['TO'];
						$val = \CUtil::translit($val, LANGUAGE_ID);
					}
					elseif($v['THEN']=='DOWNLOAD_BY_LINK')
					{
						$val = \Bitrix\KdaImportexcel\IUtils::DownloadTextTextByLink($val, $v['TO']);
					}
					elseif($v['THEN']=='DOWNLOAD_IMAGES')
					{
						$val = \Bitrix\KdaImportexcel\IUtils::DownloadImagesFromText($val, $v['TO']);
					}
					elseif($v['THEN']=='NOT_LOAD') $val = false;
					elseif($v['THEN']=='NOT_LOAD_ELEMENT')
					{
						$val = false;
						$this->SetAllowLoad(false);
					}
					$execConv = true;
				}
			}
		}
		return $val;
	}
	
	public function ConversionExtraReplaceValues($m)
	{
		$value = '';
		$paramName = $m[0];
		$quot = "'";
		$isVar = false;
		if(preg_match('/^\$\{([\'"])(.*)[\'"]\}?$/', $paramName, $m2))
		{
			$quot = $m2[1];
			$paramName = $m2[2];
			$isVar = true;
		}
		$key = substr($paramName, 1, -1);
		if(preg_match('/^CELL(\d+)$/', $key, $m2) && is_callable(array($this->ie, 'GetCurrentItemValues')))
		{
			$arFields = $this->ie->GetCurrentItemValues();
			$cellKey = (int)$m2[1] - 1;
			if(isset($arFields[$cellKey])) $value = $arFields[$cellKey];
		}
		elseif(preg_match('/^CELL_([A-Z]+\d+)$/', $key, $m2))
		{
			if($this->ie->worksheet && ($val = $this->ie->worksheet->getCell($m2[1])))
			{
				$value = $this->ie->GetCalculatedValue($val);
			}
		}
		elseif($key=='VAL')
		{
			$value = $this->currentItemFieldVal;
		}
		elseif($key=='DATETIME')
		{
			$value = ConvertTimeStamp(false, 'FULL');
		}
		elseif($key=='FILEDATE')
		{
			$value = $this->ie->GetImportFileDate();
		}
		elseif(in_array($paramName, $this->ie->rcurrencies))
		{
			$arRates = $this->ie->GetCurrencyRates();
			$value = (isset($arRates[$key]) ? floatval($arRates[$key]) : 1);
		}
		else
		{
			$arFields = $this->GetElementFields();
			if(isset($arFields[$key])) $value = $arFields[$key];
		}
		if($isVar)
		{
			$this->ie->extraConvParams[$paramName] = $value;
			return '$this->extraConvParams['.$quot.$paramName.$quot.']';
		}
		else return $value;
	}
	
	public function IsAlreadyLoaded($ID)
	{
		$oProfile = \CKDAImportProfile::getInstance();
		return $oProfile->IsAlreadyLoaded($ID, ($this->isSku ? 'O' : 'E'));
	}
	
	public function SetElementId($ID = 0, $duplicate = false)
	{
		if($ID!=$this->elementId)
		{
			$this->elementId = $ID;
			$this->elementFields = null;
			$this->elementIsDuplicate = $duplicate;
		}
		return true;
	}
	
	public function GetElementFields($ID = 0)
	{
		/*if($this->isSection)
		{
			return $this->GetSectionFields($ID);
		}*/
		
		if(($ID == 0 || $ID==$this->elementId) && is_array($this->elementFields))
		{
			return $this->elementFields;
		}

		if($this->isSku)
		{
			$loadElemParentFields2 = preg_grep('/^PARENT_/', $this->loadElemFields);
			$loadElemParentFields = array_map(array(__CLASS__, 'ReplaceSymbols7'), $loadElemParentFields2);
			$loadElemFields = array_diff($this->loadElemFields, $loadElemParentFields2);
			$arFilter = array('ID' => $ID, 'IBLOCK_ID' => $this->offersIblockId);
			$arElement2 = $this->GetElementFieldsEx($arFilter, $loadElemFields);
			$arFilterParent = array('ID' => $this->parentId, 'IBLOCK_ID' => $this->iblockId);
			$arElement2Parent = $this->GetElementFieldsEx($arFilterParent, $loadElemParentFields);
			if(!empty($arElement2Parent))
			{
				foreach($arElement2Parent as $k=>$v)
				{
					$arElement2['PARENT_'.$k] = $v;
				}
			}
		}
		else
		{
			$arFilter = array('ID' => $ID, 'IBLOCK_ID' => $this->iblockId);
			$arElement2 = $this->GetElementFieldsEx($arFilter, $this->loadElemFields);
		}

		$this->elementId = $ID;
		$this->elementFields = $arElement2;
		return $this->elementFields;
	}
	
	public function GetElementFieldsEx($arFilter, $loadElemFields)
	{
		if(empty($loadElemFields)) return array();
		
		$arElementFields = array('ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID');
		$arElementNameFields = array();
		$arPropsFields = array();
		$arFieldsProduct = array();
		$arFieldsPrices = array();
		$arFieldsProductStores = array();
		$arFieldsSection = array();
		foreach($loadElemFields as $field)
		{
			if(strpos($field, 'IE_')===0)
			{
				$key = substr($field, 3);
				$arElementNameFields[] = $key;
				if($key=='PREVIEW_PICTURE_DESCRIPTION' || $key=='DETAIL_PICTURE_DESCRIPTION')
				{
					$key = substr($key, 0, -12);
				}
				if(!in_array($key, $arElementFields)) $arElementFields[] = $key;
			}
			elseif(strpos($field, 'ICAT_PRICE')===0)
			{
				$arPrice = explode('_', substr($field, 10), 2);
				$arFieldsPrices[$arPrice[0]][] = $arPrice[1];
			}
			elseif(strpos($field, 'ICAT_STORE')===0)
			{
				$arStore = explode('_', substr($field, 10), 2);
				$arFieldsProductStores[$arStore[0]][] = $arStore[1];
			}
			elseif(strpos($field, 'ICAT_')===0)
			{
				$arFieldsProduct[] = substr($field, 5);
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				$fieldKey = substr($field, 7);
				$arPropsFields[] = $fieldKey;
				if(strpos($fieldKey, '_')!==false)
				{
					$arPropsFields[] = current(explode('_', $fieldKey));
				}
			}
			elseif(strpos($field, 'ISECT_')===0)
			{
				$arFieldsSection[] = substr($field, 6);
			}
		}
		
		$arSelectElementFields = $arElementFields;

		/*if(!empty($arFieldsPrices))
		{
			$arPriceIds = array();
			foreach($arFieldsPrices as $k=>$v)
			{
				$arPriceIds[] = $k;
			}
			$arPriceCodes = array();
			$dbRes = \CCatalogGroup::GetList(array(), array('ID'=>$arPriceIds), false, false, array('ID', 'NAME'));
			while($arCatalogGroup = $dbRes->Fetch())
			{
				$arPriceCodes[$arCatalogGroup['ID']] = $arCatalogGroup['NAME'];
			}
			$arGroupPrices = \CIBlockPriceTools::GetCatalogPrices($arFilter['IBLOCK_ID'], $arPriceCodes);
			if(!is_array($arGroupPrices)) $arGroupPrices = array();
			foreach($arGroupPrices as $k=>$v)
			{
				$arGroupPrices[$k]['CAN_VIEW'] = 1;
				$arSelectElementFields[] = $v['SELECT'];
			}
		}*/
		
		$arElement2 = array();
		//$dbResElements = \CIblockElement::GetList(array(), $arFilter, false, array('nTopCount'=>1), $arSelectElementFields);
		$dbResElements = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFilter, $arSelectElementFields, array(), 1);
		if($arElement = $dbResElements->Fetch())
		{			
			foreach($arElement as $k=>$v)
			{
				if(in_array($k, $arElementFields))
				{
					if($k=='PREVIEW_PICTURE' || $k=='DETAIL_PICTURE')
					{
						$v = $this->GetFileValue($v);
					}
					$arElement2['IE_'.$k] = $v;
				}
			}

			if(in_array('PREVIEW_PICTURE_DESCRIPTION', $arElementNameFields))
			{
				$arElement2['IE_PREVIEW_PICTURE_DESCRIPTION'] = $this->GetFileDescription($arElement['PREVIEW_PICTURE']);
			}
			if(in_array('DETAIL_PICTURE_DESCRIPTION', $arElementNameFields))
			{
				$arElement2['IE_DETAIL_PICTURE_DESCRIPTION'] = $this->GetFileDescription($arElement['DETAIL_PICTURE']);
			}
			if(in_array('IBLOCK_SECTION_IDS', $arElementNameFields))
			{
				$arSectionIds = array();
				$dbRes2 = \CIBlockElement::GetElementGroups($arElement['ID'], true, array('ID'));
				while($arSection = $dbRes2->Fetch())
				{
					$arSectionIds[] = $arSection['ID'];
				}
				$arElement2['IE_IBLOCK_SECTION_IDS'] = implode(',', $arSectionIds);
			}
			if(in_array('IBLOCK_SECTION_PARENT_IDS', $arElementNameFields))
			{
				if(!isset($this->sectionParentIds)) $this->sectionParentIds = array();
				if(!array_key_exists($arElement['IBLOCK_SECTION_ID'], $this->sectionParentIds))
				{
					$arSectionIds = array();
					$dbRes = \Bitrix\Iblock\SectionTable::GetList(array(
						'filter'=>array('ID'=>$arElement['IBLOCK_SECTION_ID']),
						'runtime' => array(new \Bitrix\Main\Entity\ReferenceField(
							'SECTION2',
							'\Bitrix\Iblock\SectionTable',
							array(
								'>=this.LEFT_MARGIN' => 'ref.LEFT_MARGIN',
								'<=this.RIGHT_MARGIN' => 'ref.RIGHT_MARGIN',
								'this.IBLOCK_ID' => 'ref.IBLOCK_ID'
							)
						)), 
						'select'=>array('SID'=>'SECTION2.ID'), 
						'order'=>array('SECTION2.DEPTH_LEVEL'=>'ASC')
					));
					while($arSection = $dbRes->Fetch())
					{
						$arSectionIds[] = $arSection['SID'];
					}
					$this->sectionParentIds[$arElement['IBLOCK_SECTION_ID']] = implode(',', $arSectionIds);
				}
				$arElement2['IE_IBLOCK_SECTION_PARENT_IDS'] = $this->sectionParentIds[$arElement['IBLOCK_SECTION_ID']];
			}
			
			if(!empty($arPropsFields))
			{
				$dbRes2 = \CIBlockElement::GetProperty($arElement['IBLOCK_ID'], $arElement['ID'], array(), array('ID'=>preg_grep('/^\d+$/', $arPropsFields)));
				while($arProp = $dbRes2->Fetch())
				{
					if(in_array($arProp['ID'], $arPropsFields))
					{
						$fieldKey = 'IP_PROP'.$arProp['ID'];
						$arRelFields = array();
						$val = $arProp['VALUE'];
						if($arProp['PROPERTY_TYPE']=='L')
						{
							$arRelFieldKeys = array_map(array(__CLASS__, 'GetLastFieldFromStr'), preg_grep('/^'.$arProp['ID'].'_/', $arPropsFields));
							foreach($arRelFieldKeys as $k=>$v)
							{
								$arRelFields[$v] = $this->GetPropertyListValue($arProp, $val, $v);
							}
							$val = $this->GetPropertyListValue($arProp, $val);
						}
						elseif($arProp['PROPERTY_TYPE']=='E')
						{
							$arRelFieldKeys = array_map(array(__CLASS__, 'GetLastFieldFromStr'), preg_grep('/^'.$arProp['ID'].'_/', $arPropsFields));
							foreach($arRelFieldKeys as $k=>$v)
							{
								$arRelFields[$v] = $this->GetPropertyElementValue($arProp, $val, $v);
							}
							$val = $this->GetPropertyElementValue($arProp, $val);	
						}
						elseif($arProp['PROPERTY_TYPE']=='G')
						{
							$val = $this->GetPropertySectionValue($arProp, $val, $relField);
						}
						elseif($arProp['PROPERTY_TYPE']=='F')
						{
							$val = $this->GetFileValue($val);
						}
						elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='directory')
						{
							$arRelFieldKeys = array_map(array(__CLASS__, 'GetLastFieldFromStr'), preg_grep('/^'.$arProp['ID'].'_/', $arPropsFields));
							foreach($arRelFieldKeys as $k=>$v)
							{
								$arRelFields[$v] = $this->GetHighloadBlockValue($arProp, $val, $v);
							}
							$val = $this->GetHighloadBlockValue($arProp, $val);
						}
						elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='HTML')
						{
							$val = $this->GetHtmlValue($val);
						}
						
						if(is_array($val)) $val = serialize($val);
						if($arProp['MULTIPLE']=='Y' && isset($arElement2[$fieldKey]) && strlen($arElement2[$fieldKey]) > 0)
						{
							$arElement2[$fieldKey] .= $this->ie->params['ELEMENT_MULTIPLE_SEPARATOR'].$val;
							foreach($arRelFields as $k=>$v)
							{
								$arElement2[$fieldKey.'_'.$k] .= $this->ie->params['ELEMENT_MULTIPLE_SEPARATOR'].$v;
							}
						}
						else
						{
							$arElement2[$fieldKey] = $val;
							foreach($arRelFields as $k=>$v)
							{
								$arElement2[$fieldKey.'_'.$k] = $v;
							}
						}
					}
					
					if(in_array($arProp['ID'].'_DESCRIPTION', $arPropsFields))
					{
						$val = $arProp['DESCRIPTION'];
						$key = 'IP_PROP'.$arProp['ID'].'_DESCRIPTION';
						
						/*if($arProp['MULTIPLE']=='Y')
						{
							if(!isset($arElement2[$key])) $arElement2[$key] = array();
							$arElement2[$key][] = $val;
						}
						else
						{
							$arElement2[$key] = $val;
						}*/
						if($arProp['MULTIPLE']=='Y' && isset($arElement2[$key]) && strlen($arElement2[$key]) > 0)
						{
							$arElement2[$key] .= $this->ie->params['ELEMENT_MULTIPLE_SEPARATOR'].$val;
						}
						else
						{
							$arElement2[$key] = $val;
						}
					}
				}
			}
			
			if(!empty($arFieldsProduct))
			{
				$dbRes2 = \CCatalogProduct::GetList(array(), array('ID'=>$arElement['ID']), false, array('nTopCount'=>1), array());
				if($arProduct = $dbRes2->Fetch())
				{
					foreach($arProduct as $k=>$v)
					{
						if($k=='VAT_ID')
						{
							if($v)
							{
								if(!isset($this->catalogVats)) $this->catalogVats = array();
								if(!isset($this->catalogVats[$v]))
								{
									$vatPercent = '';
									$dbRes = \CCatalogVat::GetList(array(), array('ID'=>$v), array('RATE'));
									if($arVat = $dbRes->Fetch())
									{
										$vatPercent = $arVat['RATE'];
									}
									$this->catalogVats[$v] = $vatPercent;
								}
								$v = $this->catalogVats[$v];
							}
							else
							{
								$v = '';
							}
						}
						elseif($k=='MEASURE')
						{
							if(!isset($this->catalogMeasure) || !is_array($this->catalogMeasure))
							{
								$this->catalogMeasure = array();
								$dbRes = \CCatalogMeasure::getList(array(), array());
								while($arr = $dbRes->Fetch())
								{
									$this->catalogMeasure[$arr['ID']] = ($arr['SYMBOL_RUS'] ? $arr['SYMBOL_RUS'] : $arr['SYMBOL_INTL']);
								}
							}
							$v = $this->catalogMeasure[$v];
						}
							
						$elemKey = 'ICAT_'.$k;
						$arElement2[$elemKey] = $v;
					}
					
					if(in_array('MEASURE_RATIO', $arFieldsProduct))
					{
						$dbRes = \CCatalogMeasureRatio::getList(array(), array('PRODUCT_ID' => $arElement['ID']), false, false, array('RATIO'));
						if($arRatio = $dbRes->Fetch())
						{
							$arElement2['ICAT_MEASURE_RATIO'] = $arRatio['RATIO'];
						}
						else
						{
							$arElement2['ICAT_MEASURE_RATIO'] = '';
						}
					}
				}
			}
			
			if(!empty($arFieldsPrices))
			{
				foreach($arFieldsPrices as $key=>$arPriceSelectField)
				{
					if(empty($arPriceSelectField)) continue;
					
					if(in_array('PRICE', $arPriceSelectField) && !in_array('CURRENCY', $arPriceSelectField)) $arPriceSelectField[] = 'CURRENCY';
					if(in_array('EXTRA', $arPriceSelectField)) $arPriceSelectField[] = 'EXTRA_ID';
					$dbRes2 = \CPrice::GetList(array(), array('PRODUCT_ID'=>$arElement['ID'], 'CATALOG_GROUP_ID'=>$key), false, array('nTopCount'=>1), $arPriceSelectField);
					if($arPrice = $dbRes2->Fetch())
					{
						foreach($arPrice as $k=>$v)
						{
							$elemKey = 'ICAT_PRICE'.$key.'_'.$k;
							$arElement2[$elemKey] = $v;
						}
						
						if($arPrice['EXTRA_ID'])
						{
							if(!isset($this->catalogPriceExtra)) $this->catalogPriceExtra = array();
							if(!isset($this->catalogPriceExtra[$arPrice['EXTRA_ID']]))
							{
								$extraPercent = '';
								$dbRes = \CExtra::GetList(array(), array('ID'=>$arPrice['EXTRA_ID']), false, array('nTopCount'=>1), array('PERCENTAGE'));
								if($arExtra = $dbRes->Fetch())
								{
									$extraPercent = $arExtra['PERCENTAGE'];
								}
								$this->catalogPriceExtra[$arPrice['EXTRA_ID']] = $extraPercent;
							}
							$elemKey = 'ICAT_PRICE'.$key.'_EXTRA';
							$arElement2[$elemKey] = $this->catalogPriceExtra[$arPrice['EXTRA_ID']];
						}
						
						if(in_array('PRICE_DISCOUNT', $arPriceSelectField))
						{
							$arElement2['ICAT_PRICE'.$key.'_PRICE_DISCOUNT'] = $this->GetCatalogPriceDiscount($arElement['ID'], $arElement['IBLOCK_ID'], $key);
						}
					}
				}
			}

			if(!empty($arFieldsProductStores))
			{
				foreach($arFieldsProductStores as $key=>$arStoreSelectField)
				{
					$dbRes2 = \CCatalogStoreProduct::GetList(array(), array('PRODUCT_ID'=>$arElement['ID'], 'STORE_ID'=>$key), false, array('nTopCount'=>1), $arStoreSelectField);
					if($arStore = $dbRes2->Fetch())
					{
						foreach($arStore as $k=>$v)
						{
							$elemKey = 'ICAT_STORE'.$key.'_'.$k;
							$arElement2[$elemKey] = $v;
						}
					}
				}
			}
			
			if(!empty($arFieldsSection) && $arElement['IBLOCK_SECTION_ID'] > 0)
			{
				$dbRes2 = \CIblockSection::GetList(array(), array('ID'=>$arElement['IBLOCK_SECTION_ID'], 'IBLOCK_ID'=>$arElement['IBLOCK_ID']), false, $arFieldsSection);
				if($arSection = $dbRes2->Fetch())
				{
					foreach($arSection as $k=>$v)
					{
						$elemKey = 'ISECT_'.$k;
						$arElement2[$elemKey] = $v;
					}
				}
			}
		}

		return $arElement2;
	}
	
	public function GetCatalogPriceDiscount($ID, $IBLOCK_ID, $GROUP_ID)
	{
		if(!isset($this->catalogPriceCodes))
		{
			$this->catalogPriceCodes = array();
			$this->catalogPriceGroups = array();
			$this->arSelectElementFieldsForPrice = array();
		}
		if(!array_key_exists($GROUP_ID, $this->catalogPriceCodes))
		{
			if($arCatalogGroup = \CCatalogGroup::GetList(array(), array('ID'=>$GROUP_ID), false, false, array('NAME'))->Fetch())
			{
				$this->catalogPriceCodes[$GROUP_ID] = $arCatalogGroup['NAME'];
			}
			else $this->catalogPriceCodes[$GROUP_ID] = false;
			
			$arGroupPrices = \CIBlockPriceTools::GetCatalogPrices($IBLOCK_ID, array($this->catalogPriceCodes[$GROUP_ID]));
			if(!is_array($arGroupPrices)) $arGroupPrices = array();
			foreach($arGroupPrices as $k=>$v)
			{
				$v['CAN_VIEW'] = 1;
				$this->catalogPriceGroups[$k] = $v;
				$this->arSelectElementFieldsForPrice[$GROUP_ID] = $v['SELECT'];
			}
		}
		
		if($this->catalogPriceCodes[$GROUP_ID] && $this->arSelectElementFieldsForPrice[$GROUP_ID])
		{
			$dbResElementForPrice = \CIblockElement::GetList(array(), array('ID'=>$ID), false, array('nTopCount'=>1), array_merge(array('ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID'), array($this->arSelectElementFieldsForPrice[$GROUP_ID])));
			$arElementForPrice = $dbResElementForPrice->Fetch();
			$priceCode = $this->catalogPriceCodes[$GROUP_ID];
			$arPrices = \CIBlockPriceTools::GetItemPrices($IBLOCK_ID, array($priceCode=>$this->catalogPriceGroups[$priceCode]), $arElementForPrice, true, array(), 0, $this->GetIblockSite($IBLOCK_ID, true));
			if(isset($arPrices[$priceCode]) && isset($arPrices[$priceCode]['DISCOUNT_VALUE'])) return $arPrices[$priceCode]['DISCOUNT_VALUE'];
		}
		return '';
	}
	
	public function GetIblockSite($IBLOCK_ID, $one=false)
	{
		if(!isset($this->arIblockSites)) $this->arIblockSites = array();
		if(!$this->arIblockSites[$IBLOCK_ID])
		{
			$arSiteList = array();
			$rsIBlockSites = \CIBlock::GetSite($IBLOCK_ID);
			while ($arIBlockSite = $rsIBlockSites->Fetch())
			{
				$arSiteList[] = $arIBlockSite['SITE_ID'];
			}
			if(count($arSiteList)==0) $arSiteList[] = '';
			$this->arIblockSites[$IBLOCK_ID] = $arSiteList;
		}
		if($one) return $this->arIblockSites[$IBLOCK_ID][0];
		else return $this->arIblockSites[$IBLOCK_ID];
	}
	
	public function GetFileValue($val)
	{
		if($val)
		{
			$arFile = \CKDAImportUtils::GetFileArray($val);
			if($arFile)
			{
				$val = $arFile['SRC'];
			}
			else
			{
				$val = '';
			}
		}
		return $val;
	}
	
	public function GetFileDescription($val)
	{
		if($val)
		{
			$arFile = \CKDAImportUtils::GetFileArray($val);
			if($arFile)
			{
				$val = $arFile['DESCRIPTION'];
			}
			else
			{
				$val = '';
			}
		}
		return $val;
	}
	
	public function GetPropertyListValue($arProp, $val, $relField = '')
	{
		if($val)
		{
			$selectField = 'VALUE';
			if($relField)
			{
				$selectField = $relField;
			}
			
			if(!isset($this->propVals[$arProp['ID']][$selectField][$val]))
			{
				$dbRes = \CIBlockPropertyEnum::GetList(array(), array("PROPERTY_ID"=>$arProp['ID'], "ID"=>$val));
				if($arPropEnum = $dbRes->Fetch())
				{
					$this->propVals[$arProp['ID']][$selectField][$val] = $arPropEnum[$selectField];
				}
				else
				{
					$this->propVals[$arProp['ID']][$selectField][$val] = '';
				}
			}
			$val = $this->propVals[$arProp['ID']][$selectField][$val];
		}
		return $val;
	}
	
	public function GetPropertyElementValue($arProp, $val, $relField = '')
	{
		if($val)
		{
			$selectField = 'NAME';
			if($relField)
			{
				$selectField = $relField;
				if(strpos($relField, 'IE_')===0)
				{
					$selectField = substr($relField, 3);
				}
				elseif(strpos($relField, 'IP_PROP')===0)
				{
					$selectField = 'PROPERTY_'.substr($relField, 7);
				}
			}
			
			if(!isset($this->propVals[$arProp['ID']][$selectField][$val]))
			{
				//$dbRes = \CIBlockElement::GetList(array(), array("ID"=>$val), false, false, array($selectField));
				$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp(array("ID"=>$val), array($selectField));
				if($arElem = $dbRes->Fetch())
				{
					$selectedField = $selectField;
					if(strpos($selectedField, 'PROPERTY_')===0) $selectedField .= '_VALUE';
					$this->propVals[$arProp['ID']][$selectField][$val] = $arElem[$selectedField];
				}
				else
				{
					$this->propVals[$arProp['ID']][$selectField][$val] = '';
				}
			}
			$val = $this->propVals[$arProp['ID']][$selectField][$val];
		}
		return $val;
	}
	
	public function GetPropertySectionValue($arProp, $val, $relField = '')
	{
		if($val)
		{
			$selectField = 'NAME';
			if($relField)
			{
				$selectField = $relField;
			}
			if(!isset($this->propVals[$arProp['ID']][$selectField][$val]))
			{
				$arFilter = array("ID"=>$val);
				if($arProp['LINK_IBLOCK_ID']) $arFilter['IBLOCK_ID'] = $arProp['LINK_IBLOCK_ID'];
				$dbRes = \CIBlockSection::GetList(array(), $arFilter, false, array($selectField));
				if($arSect = $dbRes->GetNext())
				{
					$this->propVals[$arProp['ID']][$selectField][$val] = $arSect[$selectField];
				}
				else
				{
					$this->propVals[$arProp['ID']][$selectField][$val] = '';
				}
			}
			$val = $this->propVals[$arProp['ID']][$selectField][$val];
		}
		return $val;
	}
	
	public function GetHighloadBlockValue($arProp, $val, $relField = '')
	{
		if($val && \CModule::IncludeModule('highloadblock') && $arProp['USER_TYPE_SETTINGS']['TABLE_NAME'])
		{
			$selectField = 'UF_NAME';
			if($relField)
			{
				$selectField = $relField;
			}
			
			if(!isset($this->propVals[$arProp['ID']][$selectField][$val]))
			{
				if(!$this->hlbl[$arProp['ID']] || !$this->hlblFields[$arProp['ID']])
				{
					$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('TABLE_NAME'=>$arProp['USER_TYPE_SETTINGS']['TABLE_NAME'])))->fetch();
					if(!$hlblock) return '';
					if(!$this->hlbl[$arProp['ID']])
					{
						$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
						$this->hlbl[$arProp['ID']] = $entity->getDataClass();
					}
					if(!$this->hlblFields[$arProp['ID']])
					{
						$dbRes = \CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
						$arHLFields = array();
						while($arHLField = $dbRes->Fetch())
						{
							$arHLFields[$arHLField['FIELD_NAME']] = $arHLField;
						}
						$this->hlblFields[$arProp['ID']] = $arHLFields;
					}
				}
				$entityDataClass = $this->hlbl[$arProp['ID']];
				$arHLFields = $this->hlblFields[$arProp['ID']];
				if(!array_key_exists($selectField, $arHLFields)) return '';
				$dbRes2 = $entityDataClass::GetList(array('filter'=>array("UF_XML_ID"=>$val), 'select'=>array('ID', $selectField), 'limit'=>1));
				if($arr2 = $dbRes2->Fetch())
				{
					$v = $arr2[$selectField];
					if(is_array($arHLFields[$selectField]) && $arHLFields[$selectField]['USER_TYPE_ID']=='file')
					{
						$v = $this->GetFileValue($v);
					}
					$this->propVals[$arProp['ID']][$selectField][$val] = $v;
				}
				else
				{
					$this->propVals[$arProp['ID']][$selectField][$val] = '';
				}
			}
			return $this->propVals[$arProp['ID']][$selectField][$val];
		}
		return $val;
	}
	
	public function GetHtmlValue($val)
	{
		if(is_array($val)) $val = $val['TEXT'];
		return $val;
	}
	
	public function GetFloatVal($val)
	{
		return $this->ie->GetFloatVal($val);
	}
	
	public function CalcFloatValue($val)
	{
		return $this->ie->CalcFloatValue($val);
	}
	
	public function GetFloatValWithCalc($val)
	{
		return $this->ie->GetFloatValWithCalc($val);
	}
	
	public function CompareNums($v1, $v2, $c)
	{
		return $this->ie->CompareNums($v1, $v2, $c);
	}
	
	public function ExecuteFilterExpression($ID, $val, $expression, $altReturn = true, $field = false)
	{
		return $this->ie->ExecuteFilterExpression($val, $expression, $altReturn, array('ID'=>$ID), $field);
	}
	
	public static function UFirstCallback($m)
	{
		return $m[1].ToUpper(mb_substr($m[2], 0, 1)).ToLower(mb_substr($m[2], 1));
	}
	
	public static function UWordCallback($m)
	{
		return ToUpper(mb_substr($m, 0, 1)).ToLower(mb_substr($m, 1));
	}
	
	public static function ReplaceSymbols7($n)
	{
		return substr($n, 7);
	}
	
	public static function GetLastFieldFromStr($n)
	{
		return end(explode("_", $n, 2));
	}
}