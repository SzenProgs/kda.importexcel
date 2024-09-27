<?php
namespace Bitrix\KdaImportexcel\DataManager;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class ProductD7 extends Product
{
	protected $productFields = null;
	protected static $productGroupClass = null;
	protected static $arProductGroups = array();
	
	public function __construct($ie=false)
	{
		$this->productFields = array_keys(\Bitrix\Catalog\ProductTable::getMap());
		parent::__construct($ie);
	}
	
	public static function SetProductGroup($ID, $productGroup)
	{
		if(strlen($productGroup) > 0 && !is_numeric($productGroup))
		{
			if(!array_key_exists($productGroup, self::$arProductGroups))
			{
				self::$arProductGroups[$productGroup] = '';
				if(!isset(self::$productGroupClass))
				{
					if(Loader::includeModule('highloadblock') && ($hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('NAME'=>'ProductMarkingCodeGroup')))->fetch()))
					{
						$dbRes = \CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
						$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
						self::$productGroupClass = $entity->getDataClass();
					}
					else self::$productGroupClass = false;
				}
				if(self::$productGroupClass)
				{
					$entityDataClass = self::$productGroupClass;
					if($arr = $entityDataClass::getList(array('filter'=>array('LOGIC'=>'OR', array('=UF_NAME'=>$productGroup), array('=UF_NAME'=>ToLower($productGroup))), 'select'=>array('ID')))->fetch())
					{
						self::$arProductGroups[$productGroup] = $arr['ID'];
					}
				}
			}
			$productGroup = self::$arProductGroups[$productGroup];
		}

		$GLOBALS["USER_FIELD_MANAGER"]->Update("PRODUCT", $ID, array('UF_PRODUCT_GROUP'=>$productGroup));
	}
	
	public function GetList($arOrder = array(), $arFilter = array(), $arGroupBy = false, $arNavStartParams = false, $arSelectFields = array())
	{
		$arParams = array();
		if(!empty($arOrder)) $arParams['order'] = $arOrder;
		if(!empty($arFilter)) $arParams['filter'] = $arFilter;
		if(is_array($arGroupBy) && !empty($arGroupBy)) $arParams['group'] = $arGroupBy;
		if(is_array($arNavStartParams) && !empty($arNavStartParams))
		{
			if($arNavStartParams['nTopCount']) $arParams['limit'] = $arNavStartParams['nTopCount'];
		}
		if(!empty($arSelectFields)) $arParams['select'] = array_intersect($arSelectFields, $this->productFields);
		return \Bitrix\Catalog\Model\Product::getList($arParams);
	}
	
	public function Add($arFields, $IBLOCK_ID=false, $boolCheck = true)
	{
		$arFieldsOrig = $arFields;
		foreach($arFields as $k=>$v)
		{
			if(!in_array($k, $this->productFields)) unset($arFields[$k]);
		}
		$arFields = array('fields' => $arFields);
		if($IBLOCK_ID) $arFields['external_fields']['IBLOCK_ID'] = $IBLOCK_ID;
		$result = \Bitrix\Catalog\Model\Product::add($arFields);
		if($result->isSuccess())
		{
			$ID = (int)$result->getId();
			if(isset($arFieldsOrig['UF_PRODUCT_GROUP'])) self::SetProductGroup($ID, $arFieldsOrig['UF_PRODUCT_GROUP']);
			return $ID;
		}
		else return false;
	}
	
	public function Update($ID, $IBLOCK_ID=false, $arFields=array())
	{
		$arFieldsOrig = $arFields;
		foreach($arFields as $k=>$v)
		{
			if(!in_array($k, $this->productFields)) unset($arFields[$k]);
		}
		$arFields = array('fields' => $arFields);
		if($IBLOCK_ID) $arFields['external_fields']['IBLOCK_ID'] = $IBLOCK_ID;
		if($result = \Bitrix\Catalog\Model\Product::update($ID, $arFields))
		{
			if($result->isSuccess())
			{
				if(isset($arFieldsOrig['UF_PRODUCT_GROUP'])) self::SetProductGroup($ID, $arFieldsOrig['UF_PRODUCT_GROUP']);
				return true;
			}
			else return false;
		}
		else return false;
	}
	
	public function Delete($ID)
	{
		if($result = \Bitrix\Catalog\Model\Product::delete($ID))
		{
			return $result->isSuccess();
		}
		else return false;
	}
}