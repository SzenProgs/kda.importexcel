<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\File\Image;
Loc::loadMessages(__FILE__);

class CKDAImportExcelData extends CKDAImportExcelBase {
	public function SaveRecordMass($arPacket)
	{
		$IBLOCK_ID = $this->params['IBLOCK_ID'][$this->worksheetNumForSave];
		$SECTION_ID = $this->params['SECTION_ID'][$this->worksheetNumForSave];
		
		$arElementIds = array();
		$arElems = $this->GetElementsData($arPacket, $arElementIds, $IBLOCK_ID);
		
		/*$arOffers = $arOfferIds = array();
		$arPacketOffers = $this->arPacketOffers;
		if(count($arPacketOffers) > 0 && ($arOfferIblock = $this->GetCachedOfferIblock($IBLOCK_ID)) && ($OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID']))
		{
			$arOffers = $this->GetElementsData($arPacketOffers, $arOfferIds, $OFFERS_IBLOCK_ID);
		}*/

		$oProfile = CKDAImportProfile::getInstance();
		$oProfile->SetMassMode(true, $arElementIds, $arOfferIds, $this->logger);
		
		foreach($arPacket as $k=>$arPacketItem)
		{
			if(isset($arPacketItem['ITEM']['worksheetCurrentRow'])) $this->worksheetCurrentRow = $arPacketItem['ITEM']['worksheetCurrentRow'];
			if(isset($arPacketItem['ITEM']['worksheetNumForSave'])) $this->worksheetNumForSave = $arPacketItem['ITEM']['worksheetNumForSave'];
			$this->stepparams['total_read_line']++;
			$this->stepparams['total_line']++;
			$this->stepparams['total_line_by_list'][$this->worksheetNumForSave]++;
			if(array_key_exists($arPacketItem['FILTER_HASH'], $arElems))
			{
				$duplicate = false;
				foreach($arElems[$arPacketItem['FILTER_HASH']] as $arElement)
				{
					$arRelProfiles = array();
					$res = $this->SaveRecordUpdate($IBLOCK_ID, $SECTION_ID, $arElement['ELEMENT'], $arPacketItem['FIELDS'], $arElement, $duplicate);
					if($res==='timesup')
					{
						$oProfile->SetMassMode(false);
						return false;
					}
					$duplicate = true;
				}
			}
			else
			{
				$this->SaveRecordAdd($IBLOCK_ID, $SECTION_ID, $arPacketItem['FIELDS'], $arPacketItem['ITEM'], $arPacketItem['FILTER']);
			}
			
			$this->stepparams['correct_line']++;
			$this->SaveStatusImport();
			$this->RemoveTmpImageDirs();
			if($this->CheckTimeEnding())
			{
				$oProfile->SetMassMode(false);
				return false;
			}
		}
		$oProfile->SetMassMode(false);
		return true;
	}
	
	public function GetElementsData(&$arPacket, &$arElementIds, $IBLOCK_ID)
	{
		$arElemKeys = array('ID', 'NAME', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE');
		$arPropKeys = array();
		$arProductKeys = array();
		$arPricesKeys = array();
		$arPricesIds = array();
		$arStoresKeys = array();
		$arStoresIds = array();
		$arFilterKeys = array();
		$arDataFilter = array('LOGIC'=>'OR');
		$arPacketFilter = array();
		foreach($arPacket as $k=>$arPacketItem)
		{
			unset($arPacketItem['FILTER']['IBLOCK_ID'], $arPacketItem['FILTER']['CHECK_PERMISSIONS']);
			$arItemFilter = array();
			foreach($arPacketItem['FILTER'] as $fk=>$fv)
			{
				if(substr($fk, 0, 1)=='=') $fk = substr($fk, 1);
				while(is_array($fv)) $fv = reset($fv);
				$fv = $this->Trim($fv);
				$arItemFilter[$fk] = $fv;
				if(!in_array($fk, $arFilterKeys)) $arFilterKeys[] = $fk;
				$fk2 = (preg_match('/PROPERTY_\d+_VALUE$/', $fk) ? mb_substr($fk, 0, -6) : $fk);
				if(!in_array($fk2, $arElemKeys)) $arElemKeys[] = $fk2;
			}
			ksort($arItemFilter);
			//$arPacket[$k]['FILTER_KEYS'] = $arItemFilter;
			array_walk_recursive($arItemFilter, array($this, 'TrimToLower'));
			$arPacket[$k]['FILTER_HASH'] = md5(serialize($arItemFilter));
			if(count($arPacketItem['FILTER'])==1 && !is_array(current($arPacketItem['FILTER'])))
			{
				foreach($arPacketItem['FILTER'] as $k=>$v) $arPacketFilter[$k][] = $v;
			}
			else $arDataFilter[] = $arPacketItem['FILTER'];
			
			foreach($arPacketItem['FIELDS']['ELEMENT'] as $fk=>$fv)
			{
				if(!in_array($fk, $arElemKeys)) $arElemKeys[] = $fk;
			}
			foreach($arPacketItem['FIELDS']['PROPS'] as $fk=>$fv)
			{
				if(!in_array($fk, $arPropKeys)) $arPropKeys[] = $fk;
			}
			foreach($arPacketItem['FIELDS']['PRODUCT'] as $fk=>$fv)
			{
				if(!in_array($fk, $arProductKeys)) $arProductKeys[] = $fk;
			}
			foreach($arPacketItem['FIELDS']['PRICES'] as $fk=>$fv)
			{
				if(!in_array($fk, $arPricesIds)) $arPricesIds[] = $fk;
				foreach($fv as $fk2=>$fv2)
				{
					if(!in_array($fk2, $arPricesKeys)) $arPricesKeys[] = $fk2;
				}
			}
			foreach($arPacketItem['FIELDS']['STORES'] as $fk=>$fv)
			{
				if(!in_array($fk, $arStoresIds)) $arStoresIds[] = $fk;
				foreach($fv as $fk2=>$fv2)
				{
					if(!in_array($fk2, $arStoresKeys)) $arStoresKeys[] = $fk2;
				}
			}
		}
		sort($arFilterKeys);
		
		foreach($this->conv->GetLoadElemFields() as $field)
		{
			if(strpos($field, 'IE_')===0)
			{
				$key = substr($field, 3);
				$arElementNameFields[] = $key;
				if($key=='PREVIEW_PICTURE_DESCRIPTION' || $key=='DETAIL_PICTURE_DESCRIPTION')
				{
					$key = substr($key, 0, -12);
				}
				if(!in_array($key, $arElemKeys)) $arElemKeys[] = $key;
			}
			elseif(strpos($field, 'ICAT_PRICE')===0)
			{
				$arPrice = explode('_', substr($field, 10), 2);
				if(!in_array($arPrice[0], $arPricesIds)) $arPricesIds[] = $arPrice[0];
				if(!in_array($arPrice[1], $arPricesKeys)) $arPricesKeys[] = $arPrice[1];
			}
			elseif(strpos($field, 'ICAT_STORE')===0)
			{
				$arStore = explode('_', substr($field, 10), 2);
				if(!in_array($arStore[0], $arStoresIds)) $arStoresIds[] = $arStore[0];
				if(!in_array($arStore[1], $arStoresKeys)) $arStoresKeys[] = $arStore[1];
			}
			elseif(strpos($field, 'ICAT_')===0)
			{
				$fieldKey = substr($field, 5);
				if(!in_array($fieldKey, $arProductKeys)) $arProductKeys[] = $fieldKey;
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				$fieldKey = substr($field, 7);
				if(strpos($fieldKey, '_')!==false)
				{
					$fieldKey = current(explode('_', $fieldKey));
				}
				if(!in_array($fieldKey, $arPropKeys)) $arPropKeys[] = $fieldKey;
			}
			/*elseif(strpos($field, 'ISECT_')===0)
			{
				$arFieldsSection[] = substr($field, 6);
			}*/
		}

		if(count($arDataFilter) < 2 && empty($arPacketFilter)) return false;
		if(!empty($arPacketFilter))
		{
			if(count($arDataFilter) < 2) $arDataFilter = $arPacketFilter;
			else $arDataFilter[] = $arPacketFilter;
		}
		$arFilter = array('IBLOCK_ID'=>$IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		if(isset($arDataFilter['LOGIC'])) $arFilter[] = $arDataFilter;
		else $arFilter = array_merge($arFilter, $arDataFilter);

		$arElems = array();
		$arElementIds = array();
		$arElementsHash = array();
		$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFilter, $arElemKeys);
		while($arElement = $dbRes->Fetch())
		{
			$arItemKeys = array();
			foreach($arFilterKeys as $k)
			{
				if(array_key_exists($k, $arElement)) $arItemKeys[$k] = (is_array($arElement[$k]) ? $arElement[$k] : (string)$arElement[$k]);
				elseif(array_key_exists($k.'_VALUE', $arElement)) $arItemKeys[$k] = (is_array($arElement[$k.'_VALUE']) ? $arElement[$k.'_VALUE'] : (string)$arElement[$k.'_VALUE']);
			}

			if(count($arItemKeys) > 0)
			{
				array_walk_recursive($arItemKeys, array($this, 'TrimToLower'));
				$hash = md5(serialize($arItemKeys));
				$arElementIds[] = $arElement['ID'];
				$arElementsHash[$arElement['ID']] = $hash;
				if(!isset($arElems[$hash])) $arElems[$hash] = array();
				$arElems[$hash][$arElement['ID']] = array('ELEMENT' => $arElement);
			}
		}
		
		if(!empty($arElementIds))
		{
			if(!empty($arPropKeys))
			{
				$propsDef = $this->GetIblockProperties($IBLOCK_ID);
				$arPropIds = array();
				foreach($arPropKeys as $propKey)
				{
					$propKey = (int)current(explode('_', $propKey));
					$arPropIds[$propKey] = $propKey;
				}
				
				$dbRes = \CIBlockElement::GetPropertyValues($IBLOCK_ID, array('ID'=>$arElementIds), true, array('ID'=>$arPropIds));
				while($arr = $dbRes->Fetch())
				{
					$arCurElem = array();
					foreach($arPropIds as $propId)
					{
						if(!is_array($arr[$propId]) && strlen($arr[$propId])==0 && !is_array($arr['DESCRIPTION'][$propId]) && strlen($arr['DESCRIPTION'][$propId])==0) continue;
						$arCurProp = array(
							'ID' => $propId,
							'MULTIPLE' => $propsDef[$propId]['MULTIPLE'],
							'PROPERTY_TYPE' => $propsDef[$propId]['PROPERTY_TYPE'],
							'USER_TYPE' => $propsDef[$propId]['USER_TYPE'],
							'LINK_IBLOCK_ID' => $propsDef[$propId]['LINK_IBLOCK_ID'],
							'USER_TYPE_SETTINGS' => $propsDef[$propId]['USER_TYPE_SETTINGS']
						);
						if($propsDef[$propId]['MULTIPLE'] && is_array($arr[$propId]))
						{
							if(count($arr[$propId])==0)
							{
								$arr[$propId] = array('');
								$arr['DESCRIPTION'][$propId] = array('');
							}
							$arCurElem[$propId] = array();
							foreach($arr[$propId] as $k=>$v)
							{
								$arCurElem[$propId][] = array('VALUE'=>$v, 'DESCRIPTION'=>$arr['DESCRIPTION'][$propId][$k], 'PROPERTY_VALUE_ID'=>$arr['PROPERTY_VALUE_ID'][$propId][$k]);
							}
							$arCurElem[$propId] = array_merge($arCurProp, array('VALUES'=>$arCurElem[$propId]));
						}
						else
						{
							$arCurElem[$propId] = array_merge($arCurProp, array('VALUE'=>$arr[$propId], 'DESCRIPTION'=>$arr['DESCRIPTION'][$propId], 'PROPERTY_VALUE_ID'=>$arr['PROPERTY_VALUE_ID'][$propId]));
						}
					}
					if($arElems[$arElementsHash[$arr['IBLOCK_ELEMENT_ID']]][$arr['IBLOCK_ELEMENT_ID']])
					{
						$arElems[$arElementsHash[$arr['IBLOCK_ELEMENT_ID']]][$arr['IBLOCK_ELEMENT_ID']]['PROPS'] = $arCurElem;
					}
				}
			}
			
			if(!empty($arProductKeys))
			{
				$dbRes = $this->productor->GetList(array(), array('ID'=>$arElementIds), false, false, array_merge(array('ID', 'TYPE', 'QUANTITY', 'SUBSCRIBE', 'SUBSCRIBE_ORIG', 'QUANTITY_TRACE', 'QUANTITY_TRACE_ORIG', 'CAN_BUY_ZERO', 'CAN_BUY_ZERO_ORIG', 'NEGATIVE_AMOUNT_TRACE_ORIG'), $arProductKeys));
				while($arr = $dbRes->Fetch())
				{
					if($arElems[$arElementsHash[$arr['ID']]][$arr['ID']])
					{
						$arElems[$arElementsHash[$arr['ID']]][$arr['ID']]['PRODUCT'][] = $arr;
					}
				}
			}
			
			if(!empty($arPricesIds) && !empty($arPricesKeys))
			{
				$dbRes = $this->pricer->GetList(array('QUANTITY_FROM'=>'ASC', 'ID'=>'ASC'), array('PRODUCT_ID'=>$arElementIds, 'CATALOG_GROUP_ID'=>$arPricesIds), false, false, array_merge(array('ID', 'PRODUCT_ID', 'CATALOG_GROUP_ID', 'QUANTITY_FROM', 'QUANTITY_TO', 'CURRENCY', 'PRICE', 'EXTRA_ID'), $arPricesKeys));
				while($arr = $dbRes->Fetch())
				{
					if($arElems[$arElementsHash[$arr['PRODUCT_ID']]][$arr['PRODUCT_ID']])
					{
						$arElems[$arElementsHash[$arr['PRODUCT_ID']]][$arr['PRODUCT_ID']]['PRICES'][$arr['CATALOG_GROUP_ID']][] = $arr;
					}
				}
			}
			
			if(!empty($arStoresIds) && !empty($arStoresKeys))
			{
				$dbRes = \Bitrix\Catalog\StoreProductTable::getList(array('filter'=>array('PRODUCT_ID'=>$arElementIds, 'STORE_ID'=>$arStoresIds), 'select'=>array_merge(array('ID', 'PRODUCT_ID', 'STORE_ID'), $arStoresKeys)));
				while($arr = $dbRes->Fetch())
				{
					if($arElems[$arElementsHash[$arr['PRODUCT_ID']]][$arr['PRODUCT_ID']])
					{
						$arElems[$arElementsHash[$arr['PRODUCT_ID']]][$arr['PRODUCT_ID']]['STORES'][$arr['STORE_ID']][] = $arr;
					}
				}
			}
		}
		
		return $arElems;
	}
	
	public function SaveRecordUpdate($IBLOCK_ID, $SECTION_ID, $arElement, $arFields, $arData=array(), $duplicate=false)
	{
		$elemName = $arElement['NAME'];
		if($this->params['ONLY_DELETE_MODE']=='Y')
		{
			$ID = $arElement['ID'];
			$this->DeleteElement($ID, $IBLOCK_ID);
			$this->stepparams['element_removed_line']++;
			unset($ID);
			return true;
		}
		
		$updated = false;
		$ID = $arElement['ID'];
		$arFieldsProps2 = $arFields['PROPS'];
		$arFieldsElement2 = $arFields['ELEMENT'];
		$arFieldsSections2 = $arFields['SECTIONS'];
		$arFieldsProduct2 = $arFields['PRODUCT'];
		$arFieldsPrices2 = $arFields['PRICES'];
		$arFieldsProductStores2 = $arFields['STORES'];
		$arFieldsProductDiscount2 = $arFields['DISCOUNT'];
		$arFieldsReview2 = $arFields['REVIEWS'];
		if($this->conv->SetElementId($ID, $duplicate)
			&& $this->conv->UpdateProperties($arFieldsProps2, $ID)!==false
			&& $this->conv->UpdateElementFields($arFieldsElement2, $ID)!==false
			&& $this->conv->UpdateSectionFields($arFieldsSections2, $ID)!==false
			&& $this->conv->UpdateProduct($arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $ID)!==false
			&& $this->conv->UpdateDiscountFields($arFieldsProductDiscount2, $ID)!==false
			&& $this->conv->SetElementId(0))
		{
			$this->BeforeElementSave($ID, 'update');
			if($this->params['ONLY_CREATE_MODE_PRODUCT']!='Y')
			{
				$this->UnsetUidFields($arFieldsElement2, $arFieldsProps2, $this->params['CURRENT_ELEMENT_UID']);
				if(!empty($this->fieldOnlyNew))
				{
					$this->UnsetExcessSectionFields($this->fieldOnlyNew, $arFieldsSections2, $arFieldsElement2);
				}
				$arElementSections = false;
				if($this->params['ELEMENT_ADD_NEW_SECTIONS']=='Y' && !isset($arFieldsElement2['IBLOCK_SECTION']))
				{
					$arElementSections = $this->GetElementSections($ID, $arElement['IBLOCK_SECTION_ID']);
					$arFieldsElement2['IBLOCK_SECTION'] = $arElementSections;
				}
				$this->GetSections($arFieldsElement2, $IBLOCK_ID, $SECTION_ID, $arFieldsSections2);
				if($this->params['NOT_LOAD_ELEMENTS_WO_SECTION']=='Y' 
					&& (!isset($arFieldsElement2['IBLOCK_SECTION']) || empty($arFieldsElement2['IBLOCK_SECTION']))) return true;
				
				foreach($arElement as $k=>$v)
				{
					$action = $this->fieldSettings['IE_'.$k]['LOADING_MODE'];
					if($action)
					{
						if($action=='ADD_BEFORE') $arFieldsElement2[$k] = $arFieldsElement2[$k].$v;
						elseif($action=='ADD_AFTER') $arFieldsElement2[$k] = $v.$arFieldsElement2[$k];
					}
				}
				
				if(!empty($this->fieldOnlyNew))
				{
					$this->UnsetExcessFields($this->fieldOnlyNew, $arFieldsElement2, $arFieldsProps2, $arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $arFieldsProductDiscount2);
				}
				
				$this->RemoveProperties($ID, $IBLOCK_ID);
				$this->SaveProperties($ID, $IBLOCK_ID, $arFieldsProps2, $arData['PROPS']);
				$this->SaveProduct($ID, $IBLOCK_ID, $arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, false, $arData);
				$this->AfterSaveProduct($arFieldsElement2, $ID, $IBLOCK_ID, true);
				
				if($this->CheckRequiredProps($arFieldsProps2, $IBLOCK_ID, $ID) && $this->UpdateElement($ID, $IBLOCK_ID, $arFieldsElement2, $arElement, $arElementSections))
				{
					//$this->SetTimeBegin($ID);
				}
				else
				{
					$this->Err(sprintf(Loc::getMessage("KDA_IE_UPDATE_ELEMENT_ERROR"), $this->GetLastError(), $this->worksheetNumForSave+1, $this->worksheetCurrentRow, $ID));
				}
				
				$this->SaveDiscount($ID, $IBLOCK_ID, $arFieldsProductDiscount2, $elemName);
				$this->SaveBlogComment($ID, $IBLOCK_ID, $arFieldsReview2);
				$updated = true;
			}
		}
		
		$isChanges = $this->IsChangedElement();
		if($this->SaveElementId($ID) && $updated)
		{
			$this->stepparams['element_updated_line']++;
			if($isChanges) $this->stepparams['element_changed_line']++;
		}
		if($elemName && !$arFieldsElement2['NAME']) $arFieldsElement2['NAME'] = $elemName;
		return $this->SaveRecordAfter($ID, $IBLOCK_ID, $arFields['ITEM'], $arFieldsElement2, $isChanges, !$this->isPacket);
	}
	
	public function SaveRecordAdd($IBLOCK_ID, $SECTION_ID, $arFields, $arItem, $arFilter)
	{
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		$arFieldsProps = $arFields['PROPS'];
		$arFieldsElement = $arFields['ELEMENT'];
		$arFieldsSections = $arFields['SECTIONS'];
		$arFieldsProduct = $arFields['PRODUCT'];
		$arFieldsPrices = $arFields['PRICES'];
		$arFieldsProductStores = $arFields['STORES'];
		$arFieldsProductDiscount = $arFields['DISCOUNT'];
		$arFieldsReview = $arFields['REVIEWS'];
		
		if($this->params['ONLY_UPDATE_MODE_PRODUCT']!='Y')
		{
			$this->UnsetUidFields($arFieldsElement, $arFieldsProps, $this->params['CURRENT_ELEMENT_UID'], true);
			if(!$this->CheckIdForNewElement($arFieldsElement)) return false;
			
			if($this->params['ELEMENT_NEW_DEACTIVATE']=='Y')
			{
				$arFieldsElement['ACTIVE'] = 'N';
			}
			elseif(!$arFieldsElement['ACTIVE'])
			{
				$arFieldsElement['ACTIVE'] = 'Y';
			}
			$arFieldsElement['IBLOCK_ID'] = $IBLOCK_ID;
			$this->GetSections($arFieldsElement, $IBLOCK_ID, $SECTION_ID, $arFieldsSections);
			if($this->params['NOT_LOAD_ELEMENTS_WO_SECTION']=='Y' 
				&& (!isset($arFieldsElement['IBLOCK_SECTION']) || empty($arFieldsElement['IBLOCK_SECTION']))) return false;
			$this->GetDefaultElementFields($arFieldsElement, $iblockFields);

			if($this->CheckRequiredProps($arFieldsProps, $IBLOCK_ID) && ($ID = $this->AddElement($arFieldsElement)))
			{
				$this->AddTagIblock($IBLOCK_ID);
				$this->BeforeElementSave($ID, 'add');
				$this->logger->AddElementChanges('IE_', $arFieldsElement);
				//$this->SetTimeBegin($ID);
				$this->SaveProperties($ID, $IBLOCK_ID, $arFieldsProps, array(), true, $arFieldsElement);
				$this->PrepareProductAdd($arFieldsProduct, $ID, $IBLOCK_ID);
				$this->SaveProduct($ID, $IBLOCK_ID, $arFieldsProduct, $arFieldsPrices, $arFieldsProductStores);
				$this->AfterSaveProduct($arFieldsElement, $ID, $IBLOCK_ID);
				$this->SaveDiscount($ID, $IBLOCK_ID, $arFieldsProductDiscount, $arFieldsElement['NAME']);
				$this->SaveBlogComment($ID, $IBLOCK_ID, $arFieldsReview);
				$this->AfterElementAdd($IBLOCK_ID, $ID);
				if($this->SaveElementId($ID)) $this->stepparams['element_added_line']++;
				return $this->SaveRecordAfter($ID, $IBLOCK_ID, $arFields['ITEM'], $arFieldsElement, true, !$this->isPacket);
			}
			else
			{
				$this->Err(sprintf(Loc::getMessage("KDA_IE_ADD_ELEMENT_ERROR"), $this->GetLastError(), $this->worksheetNumForSave+1, $this->worksheetCurrentRow));
				return false;
			}
		}
		else
		{
			$this->logger->AddElementMassChanges($arFieldsElement, $arFieldsProps, $arFieldsProduct, $arFieldsProductStores, $arFieldsPrices);
			$this->logger->SaveElementNotFound($arFilter, $this->worksheetCurrentRow);
		}
		return true;
	}
	
	public function UpdateElement($ID, $IBLOCK_ID, $arFieldsElement, $arElement=array(), $arElementSections=array(), $isOffer=false)
	{
		if(!empty($arFieldsElement))
		{
			$this->PrepareElementPictures($arFieldsElement, $IBLOCK_ID, $arElement, $isOffer);

			if($this->params['ELEMENT_NOT_CHANGE_SECTIONS']=='Y')
			{
				unset($arFieldsElement['IBLOCK_SECTION'], $arFieldsElement['IBLOCK_SECTION_ID']);
			}
			elseif(!isset($arFieldsElement['IBLOCK_SECTION_ID']) && isset($arFieldsElement['IBLOCK_SECTION']) && is_array($arFieldsElement['IBLOCK_SECTION']) && count($arFieldsElement['IBLOCK_SECTION']) > 0)
			{
				reset($arFieldsElement['IBLOCK_SECTION']);
				$arFieldsElement['IBLOCK_SECTION_ID'] = current($arFieldsElement['IBLOCK_SECTION']);
			}
			if(array_key_exists('IBLOCK_SECTION', $arFieldsElement))
			{
				if(!is_array($arElementSections)) $arElementSections = $this->GetElementSections($ID, $arElement['IBLOCK_SECTION_ID'], false);
				$arElement['IBLOCK_SECTION'] = $arElementSections;
			}
			if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']!='Y')
			{
				foreach($arFieldsElement as $k=>$v)
				{
					if($k=='IBLOCK_SECTION' && is_array($v))
					{
						if(count($v)==count($arElementSections) && count(array_diff($v, $arElementSections))==0
							&& (!isset($arFieldsElement['IBLOCK_SECTION_ID']) || $arFieldsElement['IBLOCK_SECTION_ID']==$arElement['IBLOCK_SECTION_ID']))
						{
							unset($arFieldsElement[$k]);
							unset($arFieldsElement['IBLOCK_SECTION_ID']);
						}
					}
					elseif($k=='PREVIEW_PICTURE' || $k=='DETAIL_PICTURE')
					{
						if(!$this->IsChangedImage($arElement[$k], $arFieldsElement[$k]))
						{
							unset($arFieldsElement[$k]);
						}
						elseif(empty($arFieldsElement[$k]))
						{
							unset($arFieldsElement[$k]);
						}
					}
					elseif($v==$arElement[$k])
					{
						unset($arFieldsElement[$k]);
					}
				}
			}
			
			if(isset($arFieldsElement['IBLOCK_SECTION']) && is_array($arFieldsElement['IBLOCK_SECTION']) && count($arFieldsElement['IBLOCK_SECTION']) > 0 && !isset($arFieldsElement['IBLOCK_SECTION_ID']))
			{
				reset($arFieldsElement['IBLOCK_SECTION']);
				$arFieldsElement['IBLOCK_SECTION_ID'] = current($arFieldsElement['IBLOCK_SECTION']);
			}
			
			if(isset($arFieldsElement['DETAIL_PICTURE']) && is_array($arFieldsElement['DETAIL_PICTURE']) && empty($arFieldsElement['DETAIL_PICTURE'])) unset($arFieldsElement['DETAIL_PICTURE']);
			if(isset($arFieldsElement['DETAIL_PICTURE']))
			{
				if(is_array($arFieldsElement['DETAIL_PICTURE']) && (!isset($arFieldsElement['PREVIEW_PICTURE']) || !is_array($arFieldsElement['PREVIEW_PICTURE']))) $arFieldsElement['PREVIEW_PICTURE'] = array();
			}
			elseif(isset($arFieldsElement['PREVIEW_PICTURE']) && is_array($arFieldsElement['PREVIEW_PICTURE']) && empty($arFieldsElement['PREVIEW_PICTURE'])) unset($arFieldsElement['PREVIEW_PICTURE']);
			
			if($arFieldsElement['IPROPERTY_TEMPLATES'])
			{
				$ipropValues = new \Bitrix\Iblock\InheritedProperty\ElementValues($IBLOCK_ID, $ID);
				$arValues = $ipropValues->queryValues();
				$arElement['IPROPERTY_TEMPLATES'] = array();
				foreach($arValues as $k=>$v)
				{
					$arElement['IPROPERTY_TEMPLATES'][$k] = ($v['ENTITY_TYPE']=='E' ? $v['VALUE'] : '');
				}
				if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']!='Y')
				{
					foreach($arFieldsElement['IPROPERTY_TEMPLATES'] as $k=>$v)
					{
						if($v==$arElement['IPROPERTY_TEMPLATES'][$k])
						{
							unset($arFieldsElement['IPROPERTY_TEMPLATES'][$k]);
						}
					}
					if(count($arFieldsElement['IPROPERTY_TEMPLATES'])==0) unset($arFieldsElement['IPROPERTY_TEMPLATES']);
				}
			}
		}

		if(empty($arFieldsElement) && $this->params['ELEMENT_NOT_UPDATE_WO_CHANGES']=='Y')
		{
			if($this->IsChangedElement())
			{
				$this->el->Update($ID, array('TIMESTAMP_X'=>new \Bitrix\Main\Type\DateTime(), 'MODIFIED_BY' => intval($GLOBALS['USER']->GetID())));
				\Bitrix\KdaImportexcel\DataManager\InterhitedpropertyValues::ClearElementValues($IBLOCK_ID, $ID);
			}
			if($this->IsFacetChanges()) \Bitrix\KdaImportexcel\DataManager\IblockElementTable::updateElementIndex($IBLOCK_ID, $ID);
			return true;
		}
		
		//$el = new CIblockElement();
		if(!isset($arFieldsElement['MODIFIED_BY']) && $this->GetCurUserID() > 0 && $arElement['MODIFIED_BY']!=$this->GetCurUserID()) $arFieldsElement['MODIFIED_BY'] = $this->GetCurUserID();
		if($this->el->UpdateComp($ID, $arFieldsElement, false, true, false))
		{
			$this->AddTagIblock($IBLOCK_ID);
			$this->logger->AddElementChanges('IE_', $arFieldsElement, $arElement);
			\Bitrix\KdaImportexcel\DataManager\InterhitedpropertyValues::ClearElementValues($IBLOCK_ID, $ID);
			return true;
		}
		else
		{
			$this->SetLastError($this->el->LAST_ERROR);
			return false;
		}
	}
	
	public function AddElement($arFieldsElement, $isOffer=false)
	{
		$this->PrepareElementPictures($arFieldsElement, $arFieldsElement['IBLOCK_ID'], array(), $isOffer);
		$arProps = $this->GetIblockDefaultProperties($arFieldsElement['IBLOCK_ID']);
		$arProps = (array_key_exists('PROPERTY_VALUES', $arFieldsElement) ? $arFieldsElement['PROPERTY_VALUES'] : array()) + $arProps;
		if(!empty($arProps)) $arFieldsElement['PROPERTY_VALUES'] = $arProps;
		//$el = new CIblockElement();
		//$ID = $el->Add($arFieldsElement, false, true, false);
		$ID = $this->el->AddComp($arFieldsElement, false, true, false);
		if($ID)
		{
			if(isset($arFieldsElement['ID']) && isset($arFieldsElement['TMP_ID']))
			{
				$el = new CIblockElement();
				$isProps = (bool)(isset($arFieldsElement['PROPERTY_VALUES']) && !empty($arFieldsElement['PROPERTY_VALUES']));
				$isSections = (bool)(isset($arFieldsElement['IBLOCK_SECTION']) && !empty($arFieldsElement['IBLOCK_SECTION']));
				if($isProps)
				{
					$emptyProps = array();
					foreach($arFieldsElement['PROPERTY_VALUES'] as $pk=>$pv)
					{
						$emptyProps[$pk] = false;
					}
					\CIBlockElement::SetPropertyValuesEx($ID, $arFieldsElement['IBLOCK_ID'], $emptyProps);
				}
				if($isSections) $el->Update($ID, array('IBLOCK_SECTION'=>false), false, true, true);
				$arElemFields = array('ID'=>$arFieldsElement['ID']);
				if(!isset($arFieldsElement['XML_ID'])) $arElemFields['XML_ID'] = $arFieldsElement['ID'];
				if(\Bitrix\KdaImportexcel\DataManager\IblockElementIdTable::update($arFieldsElement['TMP_ID'], $arElemFields))
				{
					\Bitrix\KdaImportexcel\DataManager\IblockElementIdTable::RemoveV2Props($ID, $arFieldsElement['IBLOCK_ID']);
					\CIBlockElement::UpdateSearch($ID, true);
					$ID = $arFieldsElement['ID'];
				}
				if($isProps) \CIBlockElement::SetPropertyValuesEx($ID, $arFieldsElement['IBLOCK_ID'], $arFieldsElement['PROPERTY_VALUES']);
				$arUFields = array();
				if($isSections) $arUFields['IBLOCK_SECTION'] = $arFieldsElement['IBLOCK_SECTION'];
				if($arFieldsElement['IPROPERTY_TEMPLATES']) $arUFields['IPROPERTY_TEMPLATES'] = $arFieldsElement['IPROPERTY_TEMPLATES'];
				if(!empty($arUFields)) $el->Update($ID, $arUFields, false, true, true);
			}
		}
		else
		{
			$this->SetLastError($this->el->LAST_ERROR);
			return false;
		}
		return $ID;
	}
}
?>