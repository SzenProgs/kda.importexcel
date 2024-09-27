<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class CKDAImportExcel extends CKDAImportExcelData {	
	function __construct($filename, $params, $fparams, $stepparams, $pid = false)
	{
		parent::__construct($filename, $params);
		$this->params = $params;
		$this->fparams = $fparams;
		$this->maxReadRows = 500;
		$this->skipRows = 0;
		$this->sections = array();
		$this->propVals = array();
		$this->hlbl = array();
		$this->breakWorksheet = false;
		$this->fl = new CKDAFieldList();
		$this->stepparams = $stepparams;
		if(!isset($this->stepparams['api_page'])) $this->stepparams['api_page'] = 1;
		$this->stepparams['total_read_line'] = intval($this->stepparams['total_read_line']);
		$this->stepparams['total_line'] = intval($this->stepparams['total_line']);
		$this->stepparams['correct_line'] = intval($this->stepparams['correct_line']);
		$this->stepparams['error_line'] = intval($this->stepparams['error_line']);
		$this->stepparams['killed_line'] = intval($this->stepparams['killed_line']);
		$this->stepparams['offer_killed_line'] = intval($this->stepparams['offer_killed_line']);
		$this->stepparams['element_added_line'] = intval($this->stepparams['element_added_line']);
		$this->stepparams['element_updated_line'] = intval($this->stepparams['element_updated_line']);
		$this->stepparams['element_changed_line'] = intval($this->stepparams['element_changed_line']);
		$this->stepparams['element_removed_line'] = intval($this->stepparams['element_removed_line']);
		$this->stepparams['sku_added_line'] = intval($this->stepparams['sku_added_line']);
		$this->stepparams['sku_updated_line'] = intval($this->stepparams['sku_updated_line']);
		$this->stepparams['sku_changed_line'] = intval($this->stepparams['sku_changed_line']);
		$this->stepparams['section_added_line'] = intval($this->stepparams['section_added_line']);
		$this->stepparams['section_updated_line'] = intval($this->stepparams['section_updated_line']);
		$this->stepparams['section_deactivate_line'] = intval($this->stepparams['section_deactivate_line']);
		$this->stepparams['section_remove_line'] = intval($this->stepparams['section_remove_line']);
		$this->stepparams['zero_stock_line'] = intval($this->stepparams['zero_stock_line']);
		$this->stepparams['offer_zero_stock_line'] = intval($this->stepparams['offer_zero_stock_line']);
		$this->stepparams['old_removed_line'] = intval($this->stepparams['old_removed_line']);
		$this->stepparams['offer_old_removed_line'] = intval($this->stepparams['offer_old_removed_line']);
		$this->stepparams['worksheetCurrentRow'] = intval($this->stepparams['worksheetCurrentRow']);
		if(!isset($this->stepparams['total_line_by_list'])) $this->stepparams['total_line_by_list'] = array();
		if(!isset($this->stepparams['total_file_lists_line'])) $this->stepparams['total_file_lists_line'] = array();
		if(!isset($this->stepparams['total_file_line']))
		{
			$this->stepparams['total_file_line'] = 0;
			if(is_array($this->params['LIST_LINES']))
			{
				foreach($this->params['LIST_ACTIVE'] as $k=>$v)
				{
					if($v=='Y')
					{
						$this->stepparams['total_file_line'] += $this->params['LIST_LINES'][$k];
					}
				}
			}
		}
		if(!$this->params['SECTION_UID']) $this->params['SECTION_UID'] = 'NAME';
		$this->params['ELEMENT_MULTIPLE_SEPARATOR'] = $this->GetSeparator($this->params['ELEMENT_MULTIPLE_SEPARATOR']);
		if(!isset($this->params['ELEMENT_NOT_LOAD_FORMATTING'])) $this->params['ELEMENT_NOT_LOAD_FORMATTING'] = 'N';
		if(!isset($this->params['ELEMENT_LOAD_IMAGES'])) $this->params['ELEMENT_LOAD_IMAGES'] = 'N';
		if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']!='Y' && $this->params['CHECK_CHANGES']=='N')
		{
			$this->params['ELEMENT_IMAGES_FORCE_UPDATE'] = 'Y';
		}
		
		if($this->params['PACKET_IMPORT']=='Y')
		{
			$this->isPacket = true;
			$this->params['PACKET_SIZE'] = trim($this->params['PACKET_SIZE']);
			if(is_numeric($this->params['PACKET_SIZE']))
			{
				$this->packetSize = max(5, min(5000, $this->params['PACKET_SIZE']));
			}
			if($this->maxReadRows < $this->packetSize) $this->maxReadRows = $this->packetSize;
			
			if($this->isPacket)
			{
				foreach($this->params['FIELDS_LIST'] as $k=>$v)
				{
					foreach($v as $k2=>$field)
					{
						if(strpos($field, 'OFFER_')===0)
						{
							$this->isPacket = false;
							break 2;
						}
					}
				}
			}
			if($this->isPacket && $this->params['ELEMENT_NOT_LOAD_STYLES']!='Y')
			{
				foreach($this->params['LIST_SETTINGS'] as $k=>$v)
				{
					foreach($v as $k2=>$v2)
					{
						if(strpos($k2, 'SET_SECTION_')===0 || strpos($k2, 'SET_PROPERTY_')===0)
						{
							$this->isPacket = false;
							break 2;
						}
					}
				}
			}
		}
	
		$this->logger = new CKDAImportLogger($params, $pid);
		if(!isset($stepparams['NOT_CHANGE_PROFILE']) || $stepparams['NOT_CHANGE_PROFILE']!='Y')
		{
			if(!isset($this->stepparams['loggerExecId'])) $this->stepparams['loggerExecId'] = 0;
			$this->logger->SetExecId($this->stepparams['loggerExecId']);
		}
		$this->conv = new \Bitrix\KdaImportexcel\Conversion($this);
		$this->cloud = new \Bitrix\KdaImportexcel\Cloud();
		$this->sftp = new \Bitrix\KdaImportexcel\Sftp();
		$this->el = new \Bitrix\KdaImportexcel\DataManager\IblockElementTable($params);
		
		$this->needCheckReqProps = (bool)(\Bitrix\Main\Config\Option::get(static::$moduleId, 'CHECK_REQUIRED_PROPS', 'N')=='Y');
		$this->imgExts = array_map('trim', explode(',', ToLower(\CFile::GetImageExtensions())));
		
		if(empty($this->rcurrencies))
		{
			$this->rcurrencies = array('#USD#', '#EUR#');
			if(Loader::includeModule('currency') && is_callable(array('\Bitrix\Currency\CurrencyTable', 'getList')))
			{
				$dbRes = \Bitrix\Currency\CurrencyTable::getList(array('select'=>array('CURRENCY')));
				while($arr = $dbRes->Fetch())
				{
					if(!in_array('#'.$arr['CURRENCY'].'#', $this->rcurrencies)) $this->rcurrencies[] = '#'.$arr['CURRENCY'].'#';
				}
			}
		}
		
		$this->SetZipClass();
		$this->saveProductWithOffers = (bool)(Loader::includeModule('catalog') && (string)(\Bitrix\Main\Config\Option::get('catalog', 'show_catalog_tab_with_offers')) == 'Y');
		AddEventHandler('iblock', 'OnBeforeIBlockElementUpdate', array($this, 'OnBeforeIBlockElementUpdateHandler'), 999999);
		AddEventHandler('main', 'OnFileSave', array($this, 'OnFileSaveHandler'), 1);
		
		$cm = new \Bitrix\KdaImportexcel\ClassManager($this);
		$this->pricer = $cm->GetPricer();
		$this->productor = $cm->GetProductor();
		
		/*Temp folders*/
		$this->filecnt = 0;
		$dir = $_SERVER["DOCUMENT_ROOT"].'/upload/tmp/'.static::$moduleId.'/'.static::$moduleSubDir;
		CheckDirPath($dir);
		if(!$this->stepparams['tmpdir'])
		{
			$i = 0;
			while(($tmpdir = $dir.$i.'/') && file_exists($tmpdir)){$i++;}
			$this->stepparams['tmpdir'] = $tmpdir;
			CheckDirPath($tmpdir);
		}
		$this->tmpdir = $this->stepparams['tmpdir'];
		$this->imagedir = $this->stepparams['tmpdir'].'images/';
		CheckDirPath($this->imagedir);
		$this->archivedir = $this->stepparams['tmpdir'].'archives/';
		CheckDirPath($this->archivedir);
		
		$this->tmpfile = $this->tmpdir.'params.txt';
		$oProfile = CKDAImportProfile::getInstance();
		$oProfile->SetImportParams($pid, $this->tmpdir, $stepparams, $this->params);
		/*/Temp folders*/
		
		if(file_exists($this->tmpfile) && filesize($this->tmpfile) > 0)
		{
			$this->stepparams = array_merge($this->stepparams, unserialize(file_get_contents($this->tmpfile)));
		}
		
		if(isset($this->stepparams['arDomainsConnect'])) \Bitrix\KdaImportexcel\HttpClient::setDomainsConnect($this->stepparams['arDomainsConnect']);
		if(isset($this->stepparams['skipSepProp'])) $this->skipSepProp = $this->stepparams['skipSepProp'];
		if(isset($this->stepparams['skipSepSection'])) $this->skipSepSection = $this->stepparams['skipSepSection'];
		if(isset($this->stepparams['skipSepSectionLevels'])) $this->skipSepSectionLevels = $this->stepparams['skipSepSectionLevels'];
		if(isset($this->stepparams['arSectionNames'])) $this->arSectionNames = $this->stepparams['arSectionNames'];
		if(!isset($this->stepparams['curstep'])) $this->stepparams['curstep'] = 'import';
		
		if(!isset($this->params['MAX_EXECUTION_TIME']) || $this->params['MAX_EXECUTION_TIME']!==0)
		{
			if(\Bitrix\Main\Config\Option::get(static::$moduleId, 'SET_MAX_EXECUTION_TIME')=='Y' && is_numeric(\Bitrix\Main\Config\Option::get(static::$moduleId, 'MAX_EXECUTION_TIME')))
			{
				$this->params['MAX_EXECUTION_TIME'] = intval(\Bitrix\Main\Config\Option::get(static::$moduleId, 'MAX_EXECUTION_TIME'));
				if(ini_get('max_execution_time') && $this->params['MAX_EXECUTION_TIME'] > ini_get('max_execution_time') - 5) $this->params['MAX_EXECUTION_TIME'] = ini_get('max_execution_time') - 5;
				if($this->params['MAX_EXECUTION_TIME'] < 5) $this->params['MAX_EXECUTION_TIME'] = 5;
				if($this->params['MAX_EXECUTION_TIME'] > 300) $this->params['MAX_EXECUTION_TIME'] = 300;
			}
			else
			{
				$this->params['MAX_EXECUTION_TIME'] = intval(ini_get('max_execution_time')) - 10;
				if($this->params['MAX_EXECUTION_TIME'] < 10) $this->params['MAX_EXECUTION_TIME'] = 15;
				if($this->params['MAX_EXECUTION_TIME'] > 50) $this->params['MAX_EXECUTION_TIME'] = 50;
			}
		}
		if($this->params['ONLY_UPDATE_MODE']=='Y')
		{
			$this->params['ONLY_UPDATE_MODE_ELEMENT'] = $this->params['ONLY_UPDATE_MODE_SECTION'] = 'Y';
		}
		if($this->params['ONLY_UPDATE_MODE_SEP']!='Y')
		{
			$this->params['ONLY_UPDATE_MODE_PRODUCT'] = $this->params['ONLY_UPDATE_MODE_OFFER'] = $this->params['ONLY_UPDATE_MODE_ELEMENT'];
		}
		if($this->params['ONLY_CREATE_MODE']=='Y')
		{
			$this->params['ONLY_CREATE_MODE_ELEMENT'] = $this->params['ONLY_CREATE_MODE_SECTION'] = 'Y';
		}
		if($this->params['ONLY_CREATE_MODE_SEP']!='Y')
		{
			$this->params['ONLY_CREATE_MODE_PRODUCT'] = $this->params['ONLY_CREATE_MODE_OFFER'] = $this->params['ONLY_CREATE_MODE_ELEMENT'];
		}
		
		if($pid!==false)
		{
			$this->procfile = $dir.$pid.'.txt';
			$this->errorfile = $dir.$pid.'_error.txt';
			if((int)$this->stepparams['import_started'] < 1)
			{
				$oProfile = CKDAImportProfile::getInstance();
				if(!isset($stepparams['NOT_CHANGE_PROFILE']) || $stepparams['NOT_CHANGE_PROFILE']!='Y')
				{
					if(!class_exists('\Bitrix\Main\SystemException'))
					{
						if($oProfile->OnStartImport()===false) $this->breakByEvent = true;
					}
					else
					{
						try
						{
							if($oProfile->OnStartImport()===false) $this->breakByEvent = true;
						}
						catch(\Bitrix\Main\SystemException $exception)
						{
							$this->errors[] = $exception->getMessage();
							$this->breakByEvent = true;
						}
					}
					if($this->breakByEvent) $this->stepparams['import_started'] = 1;
				}
				
				if(file_exists($this->procfile)) unlink($this->procfile);
				if(file_exists($this->errorfile)) unlink($this->errorfile);
			}
			$this->pid = $pid;
		}
	}	
	
	public function SetZipClass()
	{
		if(/*$this->params['OPTIMIZE_RAM']!='Y' &&*/ !isset($this->stepparams['optimizeRam']))
		{
			$this->stepparams['optimizeRam'] = 'N';
			$origFileSize = filesize($this->filename);
			if((true /*class_exists('XMLReader')*/ /*&& $origFileSize > 2*1024*1024*/) && ToLower(CKDAImportUtils::GetFileExtension($this->filename))=='xlsx')
			{
				$timeBegin = microtime(true);
				$needSize = $origFileSize*10;
				$tempPath = \CFile::GetTempName('', 'test_size.txt');
				CheckDirPath($tempPath);

				$fileSize = 0;
				$handle = fopen($tempPath, 'a');
				while($fileSize < $needSize && microtime(true) - $timeBegin < 3)
				{
					$partSize = min(5*1024*1024, $needSize - $fileSize);
					fwrite($handle, str_repeat('0', $partSize));
					$fileSize += $partSize;
				}
				fclose($handle);
				if($fileSize <= filesize($tempPath))
				{
					$this->stepparams['optimizeRam'] = 'Y';
				}
				unlink($tempPath);
				$dir = dirname($tempPath);
				if(count(array_diff(scandir($dir), array('.', '..')))==0)
				{
					rmdir($dir);
				}
			}
		}
		if(/*$this->params['OPTIMIZE_RAM']=='Y' ||*/ $this->stepparams['optimizeRam']=='Y')
		{
			KDAPHPExcel_Settings::setZipClass(KDAPHPExcel_Settings::KDAIEZIPARCHIVE);
		}
	}
	
	public function OnBeforeIBlockElementUpdateHandler(&$arFields)
	{
		if(isset($arFields['PROPERTY_VALUES'])) unset($arFields['PROPERTY_VALUES']);
	}
	
	public function OnFileSaveHandler(&$arFile, $strFileName, $strSavePath, $bForceMD5, $bSkipExt, $dirAdd)
	{
		if($arFile['SAVE_ORIGINAL_PATH']=='Y')
		{
			$uploadDir = trim(\Bitrix\Main\Config\Option::get("main", "upload_dir", "upload"), '/');
			$strFileName = $arFile["tmp_name"];
			$subdir = dirname(substr($strFileName, strlen(rtrim($_SERVER['DOCUMENT_ROOT'], '/'))));
			if(strpos($subdir.'/', '/'.$uploadDir.'/')===0) $subdir = substr($subdir, strlen('/'.$uploadDir.'/'));
			else $subdir = str_repeat('../', count(explode('/', trim($uploadDir, '/')))).ltrim($subdir, '/');
			$arFile["SUBDIR"] = $subdir;
			$arFile["FILE_NAME"] = $arFile["ORIGINAL_NAME"];
			$imgArray = \CFile::GetImageSize($strFileName, true);
			if(is_array($imgArray))
			{
				$arFile["WIDTH"] = $imgArray[0];
				$arFile["HEIGHT"] = $imgArray[1];
			}
			$arFile['size'] = filesize($strFileName);
			return true;
		}
		return false;
	}
	
	public function HaveTimeSetWorksheet($time)
	{
		$this->notHaveTimeSetWorksheet = ($this->params['MAX_EXECUTION_TIME'] && $this->params['TIME_READ_FILE'] && (time()-$time+$this->params['TIME_READ_FILE'] >= $this->params['MAX_EXECUTION_TIME'] || $this->memoryLimit - memory_get_peak_usage() < 16777224));
		return !$this->notHaveTimeSetWorksheet;
	}
	
	public function Import()
	{
		register_shutdown_function(array($this, 'OnShutdown'));
		set_error_handler(array($this, "HandleError"));
		set_exception_handler(array($this, "HandleException"));
		if(isset($this->stepparams['finishstatus']) && $this->stepparams['finishstatus']=='Y')
		{
			if($this->breakByEvent) return $this->GetBreakParams('afterfinish');
			else return $this->AfterFinish();
		}
		elseif($this->breakByEvent) return $this->GetBreakParams('finish');
		
		$this->stepparams['import_started'] = 1;
		$this->SaveStatusImport();
		
		if(is_callable(array('\CIBlock', 'disableClearTagCache'))) \CIBlock::disableClearTagCache();
		$time = $this->timeBeginImport = $this->timeBeginTagCache = $this->timeSaveResult = time();
		if($this->stepparams['curstep'] == 'import')
		{
			$i=0;
			while(0==$i++ || $this->GetNextImportFile())
			{
				if(!$this->ImportStep($time)) return $this->GetBreakParams();
			}
			$this->stepparams['curstep'] = 'import_end';
		}
		
		return $this->EndOfLoading($time);
	}
	
	public function ImportStep($time)
	{
		$this->InitImport();
		if($this->isPacket)
		{
			$arPacket = $this->arPacketOffers = array();
			$i = 0;
			while(($arItem = $this->GetNextRecord($time)) || is_array($arItem))
			{
				if(!is_array($arItem)) continue;
				$record = $this->SaveRecord($arItem, true);
				if(is_array($record) && !empty($record)) $arPacket[] = $record;
				if(++$i>=$this->packetSize)
				{
					if($this->SaveRecordMass($arPacket)===false) return false;
					$arPacket = array();
					$i = 0;
					$this->UpdateWorksheetCurrentRow();
				}
			}
			if($i > 0)
			{
				if($this->SaveRecordMass($arPacket)===false) return false;
			}
		}
		else
		{
			while(($arItem = $this->GetNextRecord($time)) || is_array($arItem))
			{
				if(is_array($arItem)) $this->SaveRecord($arItem);
				if($this->CheckTimeEnding($time)/* || ($this->stepparams['IMPORT_MODE']!='CRON' && $this->logger->GetFileErrors())*/)
				{
					return false;
				}
			}
		}
		if($this->CheckTimeEnding($time) || $this->notHaveTimeSetWorksheet) return false;
		return true;
	}
	
	public function EndOfLoading($time)
	{
		$this->conv->Disable();
		if($this->stepparams['section_added_line'] > 0 && (!isset($this->stepparams['deactivate_element_first']) || (int)$this->stepparams['deactivate_element_first']==0))
		{
			$arIblocks = array();
			foreach($this->params['IBLOCK_ID'] as $k=>$v)
			{
				if($this->params['LIST_ACTIVE'][$k]!='Y' || in_array($v, $arIblocks)) continue;
				\CIBlockSection::ReSort($v);
				$arIblocks[] = $v;
			}
		}
		
		$bSetDefaultProps = $bSetOfferDefaultProps = false;
		if(is_array($this->params['ADDITIONAL_SETTINGS']))
		{
			foreach($this->params['ADDITIONAL_SETTINGS'] as $key=>$val)
			{
				if(is_array($val))
				{
					if(!empty($val['OFFER_PROPERTIES_DEFAULT'])) $bSetOfferDefaultProps = true;
					if(!empty($val['ELEMENT_PROPERTIES_DEFAULT']) || $bSetOfferDefaultProps) $bSetDefaultProps = true;
				}
			}
		}
		$bSetDefaultProps2 = $bSetOfferDefaultProps2 = false;
		if($this->params['CELEMENT_MISSING_DEFAULTS'])
		{
			$arDefaults2 = $this->GetMissingDefaultVals($this->params['CELEMENT_MISSING_DEFAULTS']);
			if(!empty($arDefaults2)) $bSetDefaultProps2 = true;
		}
		if($this->params['OFFER_MISSING_DEFAULTS'])
		{
			$arDefaults2 = $this->GetMissingDefaultVals($this->params['OFFER_MISSING_DEFAULTS']);
			if(!empty($arDefaults2)) $bSetDefaultProps2 = $bSetOfferDefaultProps2 = true;
		}
		
		$bOffersDeactivate = (bool)($this->params['ELEMENT_MISSING_DEACTIVATE']=='Y' || $this->params['ELEMENT_MISSING_TO_ZERO']=='Y' || $this->params['ELEMENT_MISSING_REMOVE_PRICE']=='Y' || $this->params['OFFER_MISSING_DEACTIVATE']=='Y' || $this->params['OFFER_MISSING_TO_ZERO']=='Y' || $this->params['OFFER_MISSING_REMOVE_PRICE']=='Y' || $this->params['OFFER_MISSING_REMOVE_ELEMENT']=='Y');
		$bElemDeactivate = (bool)($bOffersDeactivate || $this->params['CELEMENT_MISSING_DEACTIVATE']=='Y' || $this->params['CELEMENT_MISSING_TO_ZERO']=='Y' || $this->params['CELEMENT_MISSING_REMOVE_PRICE']=='Y' || $this->params['CELEMENT_MISSING_REMOVE_ELEMENT']=='Y');
		$bOffersActions = (bool)($bOffersDeactivate || $bSetOfferDefaultProps || $bSetOfferDefaultProps2);
		
		if($bElemDeactivate || $bSetDefaultProps || $bSetDefaultProps2)
		{
			$bOnlySetDefaultProps = (bool)(($bSetDefaultProps || $bSetDefaultProps2) && !$bElemDeactivate);
			if($this->stepparams['curstep'] == 'import' || $this->stepparams['curstep'] == 'import_end')
			{
				$this->SaveStatusImport();
				if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
				$this->stepparams['curstep'] = 'deactivate_elements';
				$oProfile = CKDAImportProfile::getInstance();
				$this->stepparams['deactivate_element_last'] = $oProfile->GetLastImportId('E');
				$this->stepparams['deactivate_offer_last'] = $oProfile->GetLastImportId('O');
				$this->stepparams['deactivate_element_first'] = 0;
				$this->stepparams['deactivate_element_first2'] = array();
				$this->stepparams['deactivate_offer_first'] = 0;
				if(!$this->stepparams['deactivate_element_last'] && $this->params['MISSING_ACTIONS_FOR_EMPTY_FILE']=='Y')
				{
					$this->SaveElementId(0);
					$this->stepparams['deactivate_element_first'] = -1;
					$this->stepparams['deactivate_element_last'] = 0;
				}
				$this->worksheetCurrentRow = 0;
				$this->SaveStatusImport();
				if($this->CheckTimeEnding($time + 1000)) return $this->GetBreakParams();
			}
			
			$arFieldsList = array();
			$arOfferFilters = array();
			$arOffersExists = array();
			foreach($this->params['IBLOCK_ID'] as $k=>$v)
			{
				if($this->params['LIST_ACTIVE'][$k]!='Y' || ($this->stepparams['total_line_by_list'][$k] < 1 && $this->params['MISSING_ACTIONS_FOR_EMPTY_FILE']!='Y')) continue;
				if($bOnlySetDefaultProps && !$bSetDefaultProps2 && empty($this->params['ADDITIONAL_SETTINGS'][$k]['ELEMENT_PROPERTIES_DEFAULT']) && empty($this->params['ADDITIONAL_SETTINGS'][$k]['OFFER_PROPERTIES_DEFAULT'])) continue;
				
				if(count(preg_grep('/^OFFER_/', $this->params['FIELDS_LIST'][$k])) > 0)
				{
					$arOffersExists[$k] = true;
					$arOfferFilters[$k] = array();
				}
				
				$arFieldsList[$k] = array(
					'IBLOCK_ID' => $v,
					'CHECK_PERMISSIONS' => 'N'
				);
				if($this->params['SECTION_ID'][$k] && $this->params['MISSING_ACTIONS_IN_SECTION']!='N')
				{
					$arFieldsList[$k]['SECTION_ID'] = $this->params['SECTION_ID'][$k];
					$arFieldsList[$k]['INCLUDE_SUBSECTIONS'] = 'Y';
				}
				if(is_array($this->fparams[$k]))
				{
					$propsDef = $this->GetIblockProperties($v);
					foreach($this->fparams[$k] as $k2=>$ffilter)
					{
						if(!is_array($ffilter)) $ffilter = array();
						if(isset($this->stepparams['fparams'][$k][$k2]) && $ffilter['USE_FILTER_FOR_DEACTIVATE']=='Y')
						{
							$ffilter2 = $this->stepparams['fparams'][$k][$k2];
							if(is_array($ffilter2['UPLOAD_VALUES']))
							{
								if(!is_array($ffilter['UPLOAD_VALUES'])) $ffilter['UPLOAD_VALUES'] = array();
								$ffilter['UPLOAD_VALUES'] = array_unique(array_merge($ffilter['UPLOAD_VALUES'], $ffilter2['UPLOAD_VALUES']));
							}
							if(is_array($ffilter2['NOT_UPLOAD_VALUES']))
							{
								if(!is_array($ffilter['NOT_UPLOAD_VALUES'])) $ffilter['NOT_UPLOAD_VALUES'] = array();
								$ffilter['NOT_UPLOAD_VALUES'] = array_unique(array_merge($ffilter['NOT_UPLOAD_VALUES'], $ffilter2['NOT_UPLOAD_VALUES']));
							}
						}
						if($ffilter['USE_FILTER_FOR_DEACTIVATE']=='Y' && (!empty($ffilter['UPLOAD_VALUES']) || !empty($ffilter['NOT_UPLOAD_VALUES'])))
						{
							$field = false;
							if(isset($this->params['FIELDS_LIST'][$k][$k2])) $field = $this->params['FIELDS_LIST'][$k][$k2];
							elseif(is_array($this->params['LIST_SETTINGS'][$k]) && preg_match('/^__P(\d+)$/', $k2, $m) && array_key_exists('SET_PROPERTY_'.$m[1], $this->params['LIST_SETTINGS'][$k])) $field = 'IP_PROP'.$m[1];
							if($field)
							{
								if(strpos($field, 'OFFER_')===0)
								{
									if(isset($arOfferFilters[$k]))
									{
										$arOfferIblock = $this->GetCachedOfferIblock($v);
										$this->GetMissingFilterByField($arOfferFilters[$k], substr($field, 6), $arOfferIblock['OFFERS_IBLOCK_ID'], $ffilter);
									}
								}
								else
								{
									$this->GetMissingFilterByField($arFieldsList[$k], $field, $v, $ffilter);
								}
							}
						}
					}
				}
				CKDAImportUtils::AddFilter($arFieldsList[$k], $this->params['CELEMENT_MISSING_FILTER']);
			}

			while($this->stepparams['deactivate_element_first'] < $this->stepparams['deactivate_element_last'])
			{
				$oProfile = CKDAImportProfile::getInstance();
				$arUpdatedIds = $oProfile->GetUpdatedIds('E', $this->stepparams['deactivate_element_first']);
				if(empty($arUpdatedIds))
				{
					$this->stepparams['deactivate_element_first'] = $this->stepparams['deactivate_element_last'];
					continue;
				}
				$lastElement = end($arUpdatedIds);
				foreach($arFieldsList as $key=>$arFields)
				{
					$this->deactivateListKey = $key;
					if($this->stepparams['begin_time'])
					{
						$arFields['<TIMESTAMP_X'] = $this->stepparams['begin_time'];
					}
					
					$arSubFields = $this->GetMissingFilter(false, $arFields['IBLOCK_ID'], $arUpdatedIds);					
					if($arOffersExists && ($arOfferIblock = $this->GetCachedOfferIblock($arFields['IBLOCK_ID'])))
					{
						$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
						$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
						$arOfferFields = array("IBLOCK_ID" => $OFFERS_IBLOCK_ID);
						if(isset($arOfferFilters[$key]) && is_array($arOfferFilters[$key])) $arOfferFields = $arOfferFields + $arOfferFilters[$key];
						$arSubOfferFields = $this->GetMissingFilter(true, $OFFERS_IBLOCK_ID);
						if(!empty($arSubOfferFields) || count($arOfferFields) > 1)
						{
							if(count($arSubOfferFields) > 1) $arOfferFields[] = array_merge(array('LOGIC' => 'OR'), $arSubOfferFields);
							else $arOfferFields = array_merge($arOfferFields, $arSubOfferFields);
							$offerSubQuery = CIBlockElement::SubQuery('PROPERTY_'.$OFFERS_PROPERTY_ID, $arOfferFields);	
							if(array_key_exists('ID', $arSubFields))
							{
								$arSubFields[] = array('LOGIC' => 'OR', array('ID'=>$arSubFields['ID']), array('ID'=>$offerSubQuery));
								unset($arSubFields['ID']);
							}
							else
							{
								$arSubFields['ID'] = $offerSubQuery;	
							}
						}
					}
					
					while(!empty($arSubFields))
					{
						$arFields2 = $arFields;
						$arSubFields2 = $arSubFields;
						$maxPropInd = 20;
						$propInd = 0;
						$maxPriceInd = 2;
						$priceInd = 0;
						foreach($arSubFields2 as $k2=>$v2)
						{
							if(strpos($k2, 'PROPERTY_')!==false)
							{
								if($propInd < $maxPropInd) unset($arSubFields[$k2]);
								else unset($arSubFields2[$k2]);
								$propInd++;
							}
							elseif(is_numeric($k2) && is_array($v2) && isset($v2[0]) && is_array($v2[0]) && count(preg_grep('/CATALOG_PRICE_\d+/', array_keys($v2[0]))) > 0)
							{
								if($priceInd < $maxPriceInd) unset($arSubFields[$k2]);
								else unset($arSubFields2[$k2]);
								$priceInd++;
							}
						}
						if($propInd < $maxPropInd && $priceInd < $maxPriceInd) $arSubFields = array();
						
						if(count($arSubFields2) > 1) $arFields2[] = array_merge(array('LOGIC' => 'OR'), $arSubFields2);
						else $arFields2 = array_merge($arFields2, $arSubFields2);
						
						$arFields2['!ID'] = $arUpdatedIds;
						if($this->stepparams['deactivate_element_first'] > 0) $arFields2['>ID'] = $this->stepparams['deactivate_element_first'];
						if($this->stepparams['deactivate_element_first2'][$key] > $this->stepparams['deactivate_element_first'] && $this->stepparams['deactivate_element_first2'][$key] > 0) $arFields2['>ID'] = $this->stepparams['deactivate_element_first2'][$key];
						if($lastElement < $this->stepparams['deactivate_element_last']) $arFields2['<=ID'] = $lastElement;
						//$dbRes = CIblockElement::GetList(array('ID'=>'ASC'), $arFields2, false, false, array('ID'));
						$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFields2, array('ID'), array('ID'=>'ASC'));
						while($arr = $dbRes->Fetch())
						{
							if($this->params['CELEMENT_MISSING_REMOVE_ELEMENT']=='Y')
							{
								if($arOffersExists && $bOffersActions)
								{
									$this->DeactivateAllOffersByProductId($arr['ID'], $arFields2['IBLOCK_ID'], $arOfferFilters[$key], $time, true);
								}
								$this->DeleteElement($arr['ID'], $arFields2['IBLOCK_ID']);
								$this->stepparams['old_removed_line']++;
							}
							else
							{
								$this->MissingElementsUpdate($arr['ID'], $arFields2['IBLOCK_ID'], false);
								
								if($arOffersExists && $bOffersActions)
								{
									$this->DeactivateAllOffersByProductId($arr['ID'], $arFields2['IBLOCK_ID'], $arOfferFilters[$key], $time);
								}
							}
							$this->stepparams['deactivate_element_first2'][$key] = $arr['ID'];
							$this->SaveStatusImport();
							if($this->CheckTimeEnding($time))
							{
								return $this->GetBreakParams();
							}
						}
					}

					if($arOffersExists && $bOffersActions)
					{
						$ret = $this->DeactivateOffersByProductIds($arUpdatedIds, $arFields['IBLOCK_ID'], $arOfferFilters[$key], $time);
						if(is_array($ret)) return $ret;
					}
				}
				$this->stepparams['deactivate_element_first'] = $lastElement;
			}
			$this->SaveStatusImport();
			if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
		}
		
		if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
		if($this->params['SECTION_EMPTY_REMOVE']=='Y' && class_exists('\Bitrix\Iblock\SectionElementTable'))
		{
			$this->stepparams['curstep'] = 'deactivate_sections';
			foreach($this->params['IBLOCK_ID'] as $k=>$v)
			{
				if($this->params['LIST_ACTIVE'][$k]!='Y') continue;
		
				$sectionId = (int)$this->params['SECTION_ID'][$k];
				$arSectionsRes = $this->GetFESections($v, $sectionId);
				
				if(!empty($arSectionsRes['INACTIVE']))
				{
					$dbRes = CIBlockSection::GetList(array(), array('ID'=>$arSectionsRes['INACTIVE'], '!ID'=>$sectionId, 'CHECK_PERMISSIONS'=>'N'), false, array('ID', 'IBLOCK_ID'));
					while($arr = $dbRes->Fetch())
					{
						$this->BeforeSectionSave($sectId, "update");
						$this->DeleteSection($arr['ID'], $arr['IBLOCK_ID']);
						$this->stepparams['section_remove_line']++;
						$this->SaveStatusImport();
						if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
					}
				}
			}
		}
		if(($this->params['SECTION_EMPTY_DEACTIVATE']=='Y' || $this->params['SECTION_NOTEMPTY_ACTIVATE']=='Y') && class_exists('\Bitrix\Iblock\SectionElementTable'))
		{
			$this->stepparams['curstep'] = 'deactivate_sections';
			foreach($this->params['IBLOCK_ID'] as $k=>$v)
			{
				if($this->params['LIST_ACTIVE'][$k]!='Y') continue;
		
				$sectionId = (int)$this->params['SECTION_ID'][$k];
				$arSectionsRes = $this->GetFESections($v, $sectionId, array('ACTIVE' => 'Y'));
				
				$sect = new CIBlockSection();
				if($this->params['SECTION_NOTEMPTY_ACTIVATE']=='Y' && !empty($arSectionsRes['ACTIVE']))
				{
					$dbRes = CIBlockSection::GetList(array(), array('ID'=>$arSectionsRes['ACTIVE'], 'ACTIVE'=>'N', 'CHECK_PERMISSIONS'=>'N'), false, array('ID', 'IBLOCK_ID', 'ACTIVE'));
					while($arr = $dbRes->Fetch())
					{
						$this->UpdateSection($arr['ID'], $arr['IBLOCK_ID'], array('ACTIVE'=>'Y'), $arr);
						$this->SaveStatusImport();
						if($this->CheckTimeEnding($time)) return $this->GetBreakParams();						
					}
				}
				
				if($this->params['SECTION_EMPTY_DEACTIVATE']=='Y' && !empty($arSectionsRes['INACTIVE']))
				{
					$dbRes = CIBlockSection::GetList(array(), array('ID'=>$arSectionsRes['INACTIVE'], 'ACTIVE'=>'Y', 'CHECK_PERMISSIONS'=>'N'), false, array('ID', 'IBLOCK_ID', 'ACTIVE'));
					while($arr = $dbRes->Fetch())
					{
						$this->UpdateSection($arr['ID'], $arr['IBLOCK_ID'], array('ACTIVE'=>'N'), $arr);
						$this->stepparams['section_deactivate_line']++;
						$this->SaveStatusImport();
						if($this->CheckTimeEnding($time)) return $this->GetBreakParams();						
					}
				}
			}
		}
		
		if($this->params['BIND_PROPERTIES_TO_SECTIONS']=='Y')
		{
			if(!array_key_exists('bound_properties', $this->stepparams))
			{
				$arExclude = $this->params['BIND_PROPERTIES_TO_SECTIONS_EXCLUDE'];
				if(!is_array($arExclude)) $arExclude = array();
				$this->stepparams['bound_properties'] = array();
				foreach($this->params['IBLOCK_ID'] as $k=>$v)
				{
					if($this->params['LIST_ACTIVE'][$k]!='Y') continue;
					if(!array_key_exists($v, $this->stepparams['bound_properties'])) $this->stepparams['bound_properties'][$v] = array();
					foreach($this->params['FIELDS_LIST'][$k] as $k2=>$v2)
					{
						if(preg_match('/^IP_PROP(\d+)$/', $v2, $m) && !in_array($m[1], $this->stepparams['bound_properties'][$v]) && !in_array($v2, $arExclude))
						{
							$this->stepparams['bound_properties'][$v][] = $m[1];
						}
					}
				}
			}
			foreach($this->stepparams['bound_properties'] as $k=>$v)
			{
				foreach($v as $k2=>$v2)
				{
					$this->UpdateSectionPropertyLinks($k, $v2);
					unset($this->stepparams['bound_properties'][$k][$k2]);
					if($this->CheckTimeEnding($time)) return $this->GetBreakParams();	
				}
			}
		}
		
		if($this->params['REMOVE_EXPIRED_DISCOUNT']=='Y')
		{
			$this->RemoveExpiredDiscount();
		}
		
		if(is_callable(array('CIBlock', 'clearIblockTagCache')))
		{
			if(is_callable(array('\CIBlock', 'enableClearTagCache'))) \CIBlock::enableClearTagCache();
			foreach($this->params['IBLOCK_ID'] as $k=>$v)
			{
				if($this->params['LIST_ACTIVE'][$k]!='Y') continue;
				
				$bEventRes = true;
				foreach(GetModuleEvents(static::$moduleId, "OnBeforeClearCache", true) as $arEvent)
				{
					if(ExecuteModuleEventEx($arEvent, array($v))===false)
					{
						$bEventRes = false;
					}
				}
				if($bEventRes)
				{
					\CIBlock::clearIblockTagCache($v);
				}
			}
			if(is_callable(array('\CIBlock', 'disableClearTagCache'))) \CIBlock::disableClearTagCache();
		}
		
		if($this->params['REMOVE_COMPOSITE_CACHE']=='Y' && class_exists('\Bitrix\Main\Composite\Helper'))
		{
			require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/cache_files_cleaner.php");
			$obCacheCleaner = new CFileCacheCleaner('html');
			if($obCacheCleaner->InitPath(''))
			{
				$obCacheCleaner->Start();
				$space_freed = 0;
				while($file = $obCacheCleaner->GetNextFile())
				{
					if(
						is_string($file)
						&& !preg_match("/(\\.enabled|\\.size|.config\\.php)\$/", $file)
					)
					{
						$file_size = filesize($file);

						if(@unlink($file))
						{
							$space_freed+=$file_size;
						}
					}
					if($this->CheckTimeEnding($time))
					{
						\Bitrix\Main\Composite\Helper::updateCacheFileSize(-$space_freed);
						return $this->GetBreakParams();
					}
				}
				\Bitrix\Main\Composite\Helper::updateCacheFileSize(-$space_freed);
			}
			$page = \Bitrix\Main\Composite\Page::getInstance();
			$page->deleteAll();
		}
		
		$this->SaveStatusImport(true);
		
		$this->logger->FinishExec($this->stepparams);
		$oProfile = CKDAImportProfile::getInstance();
		$arEventData = $oProfile->OnEndImport($this->filename, $this->stepparams, $this->errors);
		$arEventData['FILE_SHEET_NAMES'] = $this->stepparams['listWorksheetNames'];
		$this->stepparams['onendeventdata'] = $arEventData;
		
		\Bitrix\KdaImportexcel\ZipArchive::RemoveFileDir($this->filename);
		
		if($this->stepparams['IMPORT_MODE']=='CRON') return $this->AfterFinish();
		return $this->GetBreakParams('finish');
	}
	
	public function AfterFinish()
	{
		$arEventData = (isset($this->stepparams['onendeventdata']) && is_array($this->stepparams['onendeventdata']) ? $this->stepparams['onendeventdata'] : array());
		foreach(GetModuleEvents(static::$moduleId, "OnEndImport", true) as $arEvent)
		{
			$bEventRes = ExecuteModuleEventEx($arEvent, array($this->pid, $arEventData));
			if($bEventRes['ACTION']=='REDIRECT')
			{
				$this->stepparams['redirect_url'] = $bEventRes['LOCATION'];
			}
		}
		return $this->GetBreakParams('afterfinish');
	}
	
	public function GetMissingDefaultVals($vals)
	{
		$arVals = unserialize(base64_decode($vals));
		if(!is_array($arVals)) $arVals = array();
		$pattern = '/(#DATETIME#)/';
		foreach($arVals as $k=>$v)
		{
			if(!is_array($v) && !is_bool($v))
			{
				$arVals[$k] = preg_replace_callback($pattern, array($this, 'ConversionReplaceValues'), $v);
			}
		}
		return $arVals;
	}
	
	public function GetFESections($IBLOCK_ID, $SECTION_ID=0, $arElemFilter=array())
	{
		$arFilterSections  = array('IBLOCK_ID' => $IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		$arFilterSE = array('IBLOCK_SECTION.IBLOCK_ID' => $IBLOCK_ID, 'IBLOCK_ELEMENT.IBLOCK_ID' => $IBLOCK_ID);
		foreach($arElemFilter as $k=>$v)
		{
			$arFilterSE['IBLOCK_ELEMENT.'.$k] = $v;
		}
		
		if($SECTION_ID)
		{
			$dbRes = CIBlockSection::GetList(array(), array('ID'=>$SECTION_ID, 'CHECK_PERMISSIONS'=>'N'), false, array('LEFT_MARGIN', 'RIGHT_MARGIN'));
			if($arr = $dbRes->Fetch())
			{
				$arFilterSections['>=LEFT_MARGIN'] = $arr['LEFT_MARGIN'];
				$arFilterSections['<=RIGHT_MARGIN'] = $arr['RIGHT_MARGIN'];
				$arFilterSE['>=IBLOCK_SECTION.LEFT_MARGIN'] = $arr['LEFT_MARGIN'];
				$arFilterSE['<=IBLOCK_SECTION.RIGHT_MARGIN'] = $arr['RIGHT_MARGIN'];
			}
			else
			{
				return array();
			}
		}
		
		$arListSections = array();
		$dbRes = CIBlockSection::GetList(array('DEPTH_LEVEL'=>'DESC'), $arFilterSections, false, array('ID', 'IBLOCK_SECTION_ID'));
		while($arr = $dbRes->Fetch())
		{
			$arListSections[$arr['ID']] = ($SECTION_ID==$arr['ID'] ? false : $arr['IBLOCK_SECTION_ID']);
		}
		
		$arActiveSections = array();
		$dbRes = \Bitrix\Iblock\SectionElementTable::GetList(array('filter'=>$arFilterSE, 'group'=>array('IBLOCK_SECTION_ID'), 'select'=>array('IBLOCK_SECTION_ID')));
		while($arr = $dbRes->Fetch())
		{
			$sid = $arr['IBLOCK_SECTION_ID'];
			$arActiveSections[] = $sid;
			while($sid = $arListSections[$sid])
			{
				$arActiveSections[] = $sid;
			}
		}
		$arInactiveSections = array_diff(array_keys($arListSections), $arActiveSections);
		return array(
			'ACTIVE' => $arActiveSections,
			'INACTIVE' => $arInactiveSections
		);
	}
	
	public function DeactivateAllOffersByProductId($ID, $IBLOCK_ID, $arFilter, $time, $deleteMode = false)
	{
		if(!($arOfferIblock = $this->GetCachedOfferIblock($IBLOCK_ID))) return false;
		$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
		$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
		
		$arFields = array(
			'IBLOCK_ID' => $OFFERS_IBLOCK_ID,
			'PROPERTY_'.$OFFERS_PROPERTY_ID => $ID,
			'CHECK_PERMISSIONS' => 'N'
		);
		if(is_array($arFilter)) $arFields = $arFields + $arFilter;
		$arSubFields = $this->GetMissingFilter(true, $OFFERS_IBLOCK_ID);
		
		if(!empty($arSubFields))
		{
			if(count($arSubFields) > 1) $arFields[] = array_merge(array('LOGIC' => 'OR'), $arSubFields);
			else $arFields = array_merge($arFields, $arSubFields);
						
			//$dbRes = CIblockElement::GetList(array('ID'=>'ASC'), $arFields, false, false, array('ID'));
			$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFields, array('ID'), array('ID'=>'ASC'));
			while($arr = $dbRes->Fetch())
			{
				if($deleteMode)
				{
					$this->DeleteElement($arr['ID'], $arFields['IBLOCK_ID']);
					$this->stepparams['offer_old_removed_line']++;
				}
				else
				{
					$this->MissingElementsUpdate($arr['ID'], $OFFERS_IBLOCK_ID, true);
				}
				if($this->CheckTimeEnding($time))
				{
					return $this->GetBreakParams();
				}
			}
		}
	}
	
	public function DeactivateOffersByProductIds(&$arElementIds, $IBLOCK_ID, $arFilter, $time)
	{
		if(!($arOfferIblock = $this->GetCachedOfferIblock($IBLOCK_ID))) return false;
		$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
		$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
		
		while($this->stepparams['deactivate_offer_first'] < $this->stepparams['deactivate_offer_last'])
		{
			$oProfile = CKDAImportProfile::getInstance();
			$arUpdatedIds = $oProfile->GetUpdatedIds('O', $this->stepparams['deactivate_offer_first']);
			if(empty($arUpdatedIds))
			{
				$this->stepparams['deactivate_offer_first'] = $this->stepparams['deactivate_offer_last'];
				continue;
			}
			$lastElement = end($arUpdatedIds);

			$arFields = array(
				'IBLOCK_ID' => $OFFERS_IBLOCK_ID,
				'PROPERTY_'.$OFFERS_PROPERTY_ID => $arElementIds,
				'!ID' => $arUpdatedIds,
				'CHECK_PERMISSIONS' => 'N'
			);
			if(is_array($arFilter) && !empty($arFilter))
			{
				unset($arFields['PROPERTY_'.$OFFERS_PROPERTY_ID]);
				$arFields = $arFields + $arFilter;
			}
			
			$arSubFields = $this->GetMissingFilter(true, $OFFERS_IBLOCK_ID);
			if(!empty($arSubFields))
			{
				if(count($arSubFields) > 1) $arFields[] = array_merge(array('LOGIC' => 'OR'), $arSubFields);
				else $arFields = array_merge($arFields, $arSubFields);
			}
			
			if($this->stepparams['begin_time'])
			{
				$arFields['<TIMESTAMP_X'] = $this->stepparams['begin_time'];
			}
			if($this->stepparams['deactivate_offer_first'] > 0) $arFields['>ID'] = $this->stepparams['deactivate_offer_first'];
			if($lastElement < $this->stepparams['deactivate_offer_last']) $arFields['<=ID'] = $lastElement;
			//$dbRes = CIblockElement::GetList(array('ID'=>'ASC'), $arFields, false, false, array('ID'));
			$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFields, array('ID'), array('ID'=>'ASC'));
			while($arr = $dbRes->Fetch())
			{
				if($this->params['OFFER_MISSING_REMOVE_ELEMENT']=='Y')
				{
					$this->DeleteElement($arr['ID'], $arFields['IBLOCK_ID']);
					$this->stepparams['offer_old_removed_line']++;
				}
				else
				{
					$this->MissingElementsUpdate($arr['ID'], $OFFERS_IBLOCK_ID, true);
				}
				$this->SaveStatusImport();
				if($this->CheckTimeEnding($time))
				{
					return $this->GetBreakParams();
				}
			}
			if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
			$this->stepparams['deactivate_offer_first'] = $lastElement;
		}
		$this->stepparams['deactivate_offer_first'] = 0;
	}
	
	public function MissingElementsUpdate($ID, $IBLOCK_ID, $isOffer = false)
	{
		if(!$ID) return;
		if($isOffer) $this->SetSkuMode(true, $ID, $IBLOCK_ID);
		$prefix = ($isOffer ? 'OFFER' : 'CELEMENT');
		$this->BeforeElementSave($ID, 'update');
		$arElementFields = array();
		$arProps = array();
		$arProduct = array();
		$arStores = array();
		$arPrices = array();
		if($this->params['ELEMENT_MISSING_DEACTIVATE']=='Y' || $this->params[$prefix.'_MISSING_DEACTIVATE']=='Y')
		{
			$arElementFields['ACTIVE'] = 'N';
			if($isOffer) $this->stepparams['offer_killed_line']++;
			else $this->stepparams['killed_line']++;
		}
		if($this->params['ELEMENT_MISSING_TO_ZERO']=='Y' || $this->params[$prefix.'_MISSING_TO_ZERO']=='Y')
		{
			$arProduct['QUANTITY'] = $arProduct['QUANTITY_RESERVED'] = 0;
			$dbRes2 = CCatalogStoreProduct::GetList(array(), array('PRODUCT_ID'=>$ID/*, '>AMOUNT'=>'0'*/), false, false, array('ID', 'STORE_ID'));
			while($arStore = $dbRes2->Fetch())
			{
				$arStores[$arStore["STORE_ID"]] = array('AMOUNT' => '');
			}
			if($isOffer) $this->stepparams['offer_zero_stock_line']++;
			else $this->stepparams['zero_stock_line']++;
		}
		if($this->params['ELEMENT_MISSING_REMOVE_PRICE']=='Y' || $this->params[$prefix.'_MISSING_REMOVE_PRICE']=='Y')
		{
			$dbRes = CCatalogGroup::GetList(array("SORT" => "ASC"));
			while($arPriceType = $dbRes->Fetch())
			{
				$arPrices[$arPriceType["ID"]] = array('PRICE' => '-');
			}
		}
		
		$key = $this->deactivateListKey;
		$arDefaults = array();
		if(is_array($this->params['ADDITIONAL_SETTINGS'][$key][($isOffer ? 'OFFER' : 'ELEMENT').'_PROPERTIES_DEFAULT']))
		{
			$arDefaults = $this->params['ADDITIONAL_SETTINGS'][$key][($isOffer ? 'OFFER' : 'ELEMENT').'_PROPERTIES_DEFAULT'];
		}
		if($this->params[$prefix.'_MISSING_DEFAULTS'])
		{
			$arDefaults2 = $this->GetMissingDefaultVals($this->params[$prefix.'_MISSING_DEFAULTS']);
			if(!empty($arDefaults2)) $arDefaults = $arDefaults + $arDefaults2;
		}
		if(!empty($arDefaults))
		{
			foreach($arDefaults as $propKey=>$propVal)
			{
				if(strpos($propKey, 'IE_')===0)
				{
					$arElementFields[substr($propKey, 3)] = $propVal;
				}
				elseif(preg_match('/ICAT_STORE(\d+)_AMOUNT/', $propKey, $m))
				{
					$arStores[$m[1]] = array('AMOUNT' => $propVal);
				}
				elseif(preg_match('/ICAT_PRICE(\d+)_PRICE/', $propKey, $m))
				{
					$arPrices[$m[1]] = array('PRICE' => $propVal);
				}
				elseif(strpos($propKey, 'ICAT_')===0)
				{
					$arProduct[substr($propKey, 5)] = $propVal;
				}
				else
				{
					$arProps[$propKey] = $propVal;
				}
			}
		}
		
		if(!empty($arProduct) || !empty($arPrices) || !empty($arStores))
		{
			$this->SaveProduct($ID, $IBLOCK_ID, $arProduct, $arPrices, $arStores);
		}
		if(!empty($arProps))
		{
			$this->SaveProperties($ID, $IBLOCK_ID, $arProps);
		}
		$this->AfterSaveProduct($arElementFields, $ID, $IBLOCK_ID, true, $isOffer);
		
		$arKeys = array_merge(array_keys($arElementFields), array('ID', 'MODIFIED_BY'));
		$arFilter = array('ID'=>$ID, 'IBLOCK_ID'=>$IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		//$dbRes = CIblockElement::GetList(array(), $arFilter, false, false, $arKeys);
		$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFilter, $arKeys);
		if($arElement = $dbRes->Fetch())
		{
			if($this->UpdateElement($ID, $IBLOCK_ID, $arElementFields, $arElement))
			{
				//$this->logger->SaveElementChanges($ID);
			}
			$this->logger->SaveElementChanges($ID);
		}
		$this->OnAfterSaveElement($ID);
		if($isOffer) $this->SetSkuMode(false);
	}
	
	public function GetMissingFilterByField(&$arFilter, $field, $iblockId, $ffilter)
	{
		$fieldName = '';
		if(strpos($field, 'IE_')===0)
		{
			$fieldName = substr($field, 3);
			if(strpos($fieldName, '|')!==false) $fieldName = current(explode('|', $fieldName));
		}
		elseif(strpos($field, 'IP_PROP')===0)
		{
			$propsDef = $this->GetIblockProperties($iblockId);
			$propId = substr($field, 7);
			$fieldName = 'PROPERTY_'.$propId;
			if($propsDef[$propId]['PROPERTY_TYPE']=='L')
			{
				$fieldName .= '_VALUE';
			}
			elseif($propsDef[$propId]['PROPERTY_TYPE']=='S' && $propsDef[$propId]['USER_TYPE']=='directory')
			{
				if(is_array($ffilter['UPLOAD_VALUES']))
				{
					foreach($ffilter['UPLOAD_VALUES'] as $k3=>$v3)
					{
						$ffilter['UPLOAD_VALUES'][$k3] = $this->GetHighloadBlockValue($propsDef[$propId], $v3);
					}
				}
				if(is_array($ffilter['NOT_UPLOAD_VALUES']))
				{
					foreach($ffilter['NOT_UPLOAD_VALUES'] as $k3=>$v3)
					{
						$ffilter['NOT_UPLOAD_VALUES'][$k3] = $this->GetHighloadBlockValue($propsDef[$propId], $v3);
					}
				}
			}
			elseif($propsDef[$propId]['PROPERTY_TYPE']=='E')
			{
				if(is_array($ffilter['UPLOAD_VALUES']))
				{
					foreach($ffilter['UPLOAD_VALUES'] as $k3=>$v3)
					{
						$ffilter['UPLOAD_VALUES'][$k3] = $this->GetIblockElementValue($propsDef[$propId], $v3, $ffilter);
					}
				}
				if(is_array($ffilter['NOT_UPLOAD_VALUES']))
				{
					foreach($ffilter['NOT_UPLOAD_VALUES'] as $k3=>$v3)
					{
						$ffilter['NOT_UPLOAD_VALUES'][$k3] = $this->GetIblockElementValue($propsDef[$propId], $v3, $ffilter);
					}
				}
			}
		}
		if(strlen($fieldName) > 0)
		{
			if(!empty($ffilter['UPLOAD_VALUES']))
			{
				$arFilter[$fieldName] = $ffilter['UPLOAD_VALUES'];
				if(is_array($ffilter['UPLOAD_VALUES']) && count($ffilter['UPLOAD_VALUES'])==1)
				{
					if(in_array('{empty}', $ffilter['UPLOAD_VALUES'])) $arFilter[$fieldName] = false;
					elseif(in_array('{not_empty}', $ffilter['UPLOAD_VALUES']))
					{
						unset($arFilter[$fieldName]);
						$arFilter['!'.$fieldName] = false;
					}
				}
			}
			elseif(!empty($ffilter['NOT_UPLOAD_VALUES']))
			{
				$arFilter['!'.$fieldName] = $ffilter['NOT_UPLOAD_VALUES'];
				if(is_array($ffilter['NOT_UPLOAD_VALUES']) && count($ffilter['NOT_UPLOAD_VALUES'])==1)
				{
					if(in_array('{empty}', $ffilter['NOT_UPLOAD_VALUES'])) $arFilter['!'.$fieldName] = false;
					elseif(in_array('{not_empty}', $ffilter['UPLOAD_VALUES']))
					{
						unset($arFilter['!'.$fieldName]);
						$arFilter[$fieldName] = false;
					}
				}
			}
		}
	}
	
	public function GetMissingFilter($isOffer = false, $IBLOCK_ID = 0, $arUpdatedIds=array())
	{
		$arSubFields = array();
		$prefix = ($isOffer ? 'OFFER' : 'CELEMENT');
		if($this->params[$prefix.'_MISSING_REMOVE_ELEMENT']=='Y') return ($isOffer ? $arSubFields : array('!ID'=>false));
		if($this->params['ELEMENT_MISSING_DEACTIVATE']=='Y' || $this->params[$prefix.'_MISSING_DEACTIVATE']=='Y') $arSubFields['ACTIVE'] = 'Y';
		if($this->params['ELEMENT_MISSING_TO_ZERO']=='Y' || $this->params[$prefix.'_MISSING_TO_ZERO']=='Y') $arSubFields[] = array('LOGIC'=>'OR', array('>CATALOG_QUANTITY'=>'0'), array('>QUANTITY_RESERVED'=>'0'));
		if($this->params['ELEMENT_MISSING_REMOVE_PRICE']=='Y' || $this->params[$prefix.'_MISSING_REMOVE_PRICE']=='Y') $arSubFields['!CATALOG_PRICE_'.$this->pricer->GetBasePriceId()] = false;
		
		$key = $this->deactivateListKey;
		$arDefaults = array();
		if(is_array($this->params['ADDITIONAL_SETTINGS'][$key][($isOffer ? 'OFFER' : 'ELEMENT').'_PROPERTIES_DEFAULT']))
		{
			$arDefaults = $this->params['ADDITIONAL_SETTINGS'][$key][($isOffer ? 'OFFER' : 'ELEMENT').'_PROPERTIES_DEFAULT'];
		}
		if($this->params[$prefix.'_MISSING_DEFAULTS'])
		{
			$arDefaults2 = $this->GetMissingDefaultVals($this->params[$prefix.'_MISSING_DEFAULTS']);
			if(!empty($arDefaults2)) $arDefaults = $arDefaults + $arDefaults2;
		}
		if($IBLOCK_ID > 0 && !empty($arDefaults))
		{
			$arProductFields = array();
			$propsDef = $this->GetIblockProperties($IBLOCK_ID);
			foreach($arDefaults as $origUid=>$arValUid)
			{
				if(isset($propsDef[$origUid]) && $propsDef[$origUid]['MULTIPLE']=='Y')
				{
					$this->GetMultiplePropertyChange($arValUid);
				}
				if(!is_array($arValUid)) $arValUid = array($arValUid);
				foreach($arValUid as $keyUid=>$valUid)
				{
					$uid = $origUid;
					if(strpos($uid, 'IE_')===0)
					{
						$uid = substr($uid, 3);
					}
					elseif(preg_match('/ICAT_STORE(\d+)_AMOUNT/', $uid, $m))
					{
						$uid = 'CATALOG_STORE_AMOUNT_'.$m[1];
						if(strlen($valUid)==0 || $valUid=='-') $valUid = false;
					}
					elseif(preg_match('/ICAT_PRICE(\d+)_PRICE/', $uid, $m))
					{
						$uid = 'CATALOG_PRICE_'.$m[1];
						if($valUid=='-') $valUid = false;
					}
					elseif($uid=='ICAT_QUANTITY')
					{
						$uid = 'CATALOG_QUANTITY';
					}
					elseif(strpos($uid, 'ICAT_')===0)
					{
						$field = substr($uid, 5);
						if(class_exists('\Bitrix\Catalog\ProductTable'))
						{
							if(in_array($field, array('QUANTITY_TRACE', 'CAN_BUY_ZERO', 'NEGATIVE_AMOUNT_TRACE', 'SUBSCRIBE')))
							{
								if($field=='NEGATIVE_AMOUNT_TRACE') $configName = 'allow_negative_amount';
								else $configName = 'default_'.ToLower($field);
								if($field=='SUBSCRIBE') $defaultVal = ((string)\Bitrix\Main\Config\Option::get('catalog', $configName) == 'N' ? 'N' : 'Y');
								else $defaultVal = ((string)\Bitrix\Main\Config\Option::get('catalog', $configName) == 'Y' ? 'Y' : 'N');
								$valUid = trim(ToUpper($valUid));
								if($valUid!='D') $valUid = $this->GetBoolValue($valUid);
								if($valUid==$defaultVal) $arProductFields['!'.$field] = array($valUid, 'D');
								else $arProductFields['!'.$field] = $valUid;
							}
							else
							{
								if(strlen($valUid)==0 || $valUid=='-') $valUid = false;
								$arProductFields['!'.$field] = $valUid;
							}
						}
						continue;
					}
					elseif($propsDef[$uid]['PROPERTY_TYPE']=='L')
					{
						if(strlen($valUid)==0) $valUid = false;
						$uid = 'PROPERTY_'.$uid.'_VALUE';
					}
					else
					{
						if($propsDef[$uid]['PROPERTY_TYPE']=='S' && $propsDef[$uid]['USER_TYPE']=='directory')
						{
							$valUid = $this->GetHighloadBlockValue($propsDef[$uid], $valUid);
						}
						elseif($propsDef[$uid]['PROPERTY_TYPE']=='E')
						{
							$valUid = $this->GetIblockElementValue($propsDef[$uid], $valUid, array());
						}
						if(strlen($valUid)==0) $valUid = false;
						$uid = 'PROPERTY_'.$uid;
					}
					if(strpos($keyUid, 'REMOVE_')===0) $fkey = '='.$uid;
					else $fkey = '!'.$uid;
					if(!isset($arSubFields[$fkey])) $arSubFields[$fkey] = $valUid;
					else
					{
						if(!is_array($arSubFields[$fkey])) $arSubFields[$fkey] = array($arSubFields[$fkey]);
						$arSubFields[$fkey][] = $valUid;
					}
				}
			}
			
			if(!empty($arProductFields) && !empty($arUpdatedIds) && $IBLOCK_ID > 0)
			{
				if(count($arProductFields) > 1)
				{
					$arProductFields = array(array_merge(array('LOGIC'=>'OR'), array_map(array('CKDAImportUtils', 'ArrayCombine'), array_keys($arProductFields), $arProductFields)));
				}
				$arProductFields['IBLOCK_ELEMENT.IBLOCK_ID'] = $IBLOCK_ID;
				$arProductFields['!ID'] = $arUpdatedIds;
				$lastElement = end($arUpdatedIds);
				if($this->stepparams['deactivate_element_first'] > 0) $arProductFields['>ID'] = $this->stepparams['deactivate_element_first'];
				if($lastElement < $this->stepparams['deactivate_element_last']) $arProductFields['<=ID'] = $lastElement;
				$dbRes = \Bitrix\Catalog\ProductTable::getList(array(
					'order' => array('ID'=>'ASC'),
					'select' => array('ID'),
					'filter' => $arProductFields
				));
				$arIds = array();
				while($arr = $dbRes->Fetch())
				{
					$arIds[] = $arr['ID'];
				}
				if(!empty($arIds))
				{
					$arSubFields['ID'] = $arIds;
				}elseif(empty($arSubFields)) $arSubFields['ID'] = 0;
			}
		}
		
		if(!$isOffer && !$this->saveProductWithOffers && defined('\Bitrix\Catalog\ProductTable::TYPE_SKU'))
		{
			foreach($arSubFields as $k=>$v)
			{
				if(preg_match('/^.?CATALOG_/', $k))
				{
					$arSubFields[] = array('LOGIC' => 'AND', array($k => $v), array('!CATALOG_TYPE'=>\Bitrix\Catalog\ProductTable::TYPE_SKU));
					unset($arSubFields[$k]);
				}
			}
		}
		
		return $arSubFields;
	}
	
	public function InitImport()
	{
		$this->objReader = KDAPHPExcel_IOFactory::createReaderForFile($this->filename);
		$this->worksheetNames = array();
		if(is_callable(array($this->objReader, 'listWorksheetNames')))
		{
			$this->worksheetNames = $this->objReader->listWorksheetNames($this->filename);
			$this->stepparams['listWorksheetNames'] = $this->worksheetNames;
		}
		
		$worksheetNum = $this->worksheetNum;
		$pattern = '/(#FILENAME#|#IMPORT_PROCESS_ID#|#SHEETNAME#)/';
		$cellPattern = '/#CELL_[A-Z]+(\d+)#/';
		$extraLines = array();
		foreach($this->fparams as $k=>$listParams)
		{
			$this->worksheetNum = $k;
			foreach($listParams as $k2=>$fs)
			{
				if(isset($fs['UPLOAD_VALUES']) && is_array($fs['UPLOAD_VALUES']))
				{
					foreach($fs['UPLOAD_VALUES'] as $k3=>$val)
					{
						$this->fparams[$k][$k2]['UPLOAD_VALUES'][$k3] = preg_replace_callback($pattern, array($this, 'ConversionReplaceValues'), $val);
					}
				}
				if(isset($fs['NOT_UPLOAD_VALUES']) && is_array($fs['NOT_UPLOAD_VALUES']))
				{
					foreach($fs['NOT_UPLOAD_VALUES'] as $k3=>$val)
					{
						$this->fparams[$k][$k2]['NOT_UPLOAD_VALUES'][$k3] = preg_replace_callback($pattern, array($this, 'ConversionReplaceValues'), $val);
					}
				}
				if(isset($fs['CONVERSION']) && is_array($fs['CONVERSION']))
				{
					foreach($fs['CONVERSION'] as $k2=>$v2)
					{
						if(preg_match_all($cellPattern, $v2['TO'].$v2['FROM'], $m)) $extraLines += array_unique($m[1]);
					}
				}
				if(isset($fs['EXTRA_CONVERSION']) && is_array($fs['EXTRA_CONVERSION']))
				{
					foreach($fs['EXTRA_CONVERSION'] as $k2=>$v2)
					{
						if(preg_match_all($cellPattern, $v2['TO'].$v2['FROM'], $m)) $extraLines += array_unique($m[1]);
					}
				}
			}
		}
		$this->worksheetNum = $worksheetNum;
		
		if($this->params['ELEMENT_NOT_LOAD_STYLES']=='Y' && $this->params['ELEMENT_NOT_LOAD_FORMATTING']=='Y')
		{
			$this->objReader->setReadDataOnly(true);
		}
		if(isset($this->params['CSV_PARAMS']))
		{
			$this->objReader->setCsvParams($this->params['CSV_PARAMS']);
		}
		$this->chunkFilter = new KDAChunkReadFilter();
		$this->chunkFilter->setParams($this->params, $this->stepparams['csv_position']);
		$this->chunkFilter->setLoadLines($extraLines);
		$this->objReader->setReadFilter($this->chunkFilter);
		
		$this->worksheetNum = (isset($this->stepparams['worksheetNum']) ? intval($this->stepparams['worksheetNum']) : 0);
		$this->worksheetCurrentRow = intval($this->stepparams['worksheetCurrentRow']);
		$this->GetNextWorksheetNum();
	}
	
	public function GetBreakParams($action = 'continue')
	{
		$this->ClearIblocksTagCache();
		$arStepParams = array(
			'params' => $this->GetStepParams(),
			'action' => $action,
			'errors' => $this->errors,
			'sessid' => bitrix_sessid(),
			//'file_errors' => $this->logger->GetFileErrors()
		);
		
		if($action == 'continue')
		{
			file_put_contents($this->tmpfile, serialize($arStepParams['params']));
			if(file_exists($this->imagedir))
			{
				DeleteDirFilesEx(substr($this->imagedir, strlen($_SERVER['DOCUMENT_ROOT'])));
			}
		}
		else
		{
			if(file_exists($this->procfile)) unlink($this->procfile);
			if(file_exists($this->tmpdir)) DeleteDirFilesEx(substr($this->tmpdir, strlen($_SERVER['DOCUMENT_ROOT'])));
		}
		
		unset($arStepParams['params']['currentelement']);
		unset($arStepParams['params']['currentelementitem']);
		return $arStepParams;
	}
	
	public function GetStepParams()
	{
		return array_merge($this->stepparams, array(
			'worksheetNum' => intval($this->worksheetNum),
			'worksheetCurrentRow' => $this->worksheetCurrentRow,
			'skipSepProp' => $this->skipSepProp,
			'skipSepSection' => $this->skipSepSection,
			'skipSepSectionLevels' => $this->skipSepSectionLevels,
			'arSectionNames' => $this->arSectionNames,
			'arDomainsConnect' => \Bitrix\KdaImportexcel\HttpClient::getDomainsConnect()
		));
	}
	
	public function SetWorksheet($worksheetNum, $worksheetCurrentRow)
	{
		$this->skipRows = 0;
		
		if(!file_exists($this->filename))
		{
			$oProfile = \CKDAImportProfile::getInstance();
			$sd = $s = false;
			$oProfile->Apply($sd, $s, $ID);
			$fid = $oProfile->GetParam('DATA_FILE');
			if($fid)
			{
				$arFile = \CFile::GetFileArray($fid);
				$this->filename = $_SERVER['DOCUMENT_ROOT'].$arFile['SRC'];
			}
		}
		
		$timeBegin = microtime(true);
		$this->chunkFilter->setRows($worksheetCurrentRow, $this->maxReadRows);
		$this->chunkFilter->setColumns($this->GetNeedFileColumns(false));
		if($this->efile)
		{
			\CTempFile::Cleanup();
			$this->efile->__destruct();
		}
		if($this->worksheetNames[$worksheetNum]) $this->objReader->setLoadSheetsOnly($this->worksheetNames[$worksheetNum]);
		if($this->stepparams['csv_position'] && is_callable(array($this->objReader, 'setStartFilePosRow')))
		{
			$this->objReader->setStartFilePosRow($this->stepparams['csv_position']);
		}
		$getCount = false;
		if(is_callable(array($this->objReader, 'setCountMode')))
		{
			$getCount = !isset($this->stepparams['total_file_lists_line'][$this->worksheetNum]);
			$this->objReader->setCountMode($getCount);
		}
		$this->efile = $this->objReader->load($this->filename);
		$this->worksheetIterator = $this->efile->getWorksheetIterator();
		$this->worksheet = $this->worksheetIterator->current();
		if($getCount)
		{
			$preloadCount = $this->params['LIST_LINES'][$this->worksheetNum];
			$this->stepparams['total_file_lists_line'][$this->worksheetNum] = $preloadCount;
			if(is_callable(array($this->worksheet, 'getRealHighestRow')))
			{
				$heghestRow = intval($this->worksheet->getRealHighestRow());
				$this->stepparams['total_file_lists_line'][$this->worksheetNum] = $heghestRow;
				$this->stepparams['total_file_line'] += $heghestRow - $preloadCount;
			}
		}
		$timeEnd = microtime(true);
		$this->params['TIME_READ_FILE'] = ceil($timeEnd - $timeBegin);
		
		$this->params['CURRENT_ELEMENT_UID'] = $this->params['ELEMENT_UID'];
		$this->params['CURRENT_ELEMENT_UID_SKU'] = $this->params['ELEMENT_UID_SKU'];
		if($this->params['CHANGE_ELEMENT_UID'][$this->worksheetNum]=='Y')
		{
			$this->params['CURRENT_ELEMENT_UID'] = $this->params['LIST_ELEMENT_UID'][$this->worksheetNum];
			$this->params['CURRENT_ELEMENT_UID_SKU'] = $this->params['LIST_ELEMENT_UID_SKU'][$this->worksheetNum];
		}
		
		$this->searchSections = false;
		if($this->params['SET_SEARCH_SECTIONS'][$this->worksheetNum]=='Y')
		{
			$this->searchSections = $this->params['SEARCH_SECTIONS'][$this->worksheetNum];
			if(!is_array($this->searchSections) || count($this->searchSections)==0) $this->searchSections =false;
		}
		
		$listSettings = $this->params['LIST_SETTINGS'][$this->worksheetNum];
		if(!is_array($listSettings)) $listSettings = array();
		$addedFields = array();
		foreach($listSettings as $k2=>$v2)
		{
			if(strpos($k2, 'SET_PROPERTY_')===0) $addedFields[] = 'IP_PROP'.intval(substr($k2, 13));
		}
				
		$filedList = $this->params['FIELDS_LIST'][$this->worksheetNum];
		$iblockId = $this->params['IBLOCK_ID'][$this->worksheetNum];
		if(count(array_diff((is_array($this->params['CURRENT_ELEMENT_UID']) ? $this->params['CURRENT_ELEMENT_UID'] : array($this->params['CURRENT_ELEMENT_UID'])), array_merge($filedList, $addedFields))) > 0
			&& (!$this->params['SECTION_UID'] || count(preg_grep('/^(ISECT\d*_'.$this->params['SECTION_UID'].'|ISECT_PATH_NAMES|IE_SECTION_PATH)$/', $filedList))==0))
		{
			if($this->worksheet->getHighestDataRow() > 0)
			{		
				$nofields = array_diff((is_array($this->params['CURRENT_ELEMENT_UID']) ? $this->params['CURRENT_ELEMENT_UID'] : array($this->params['CURRENT_ELEMENT_UID'])), array_merge($filedList, $addedFields));
				$fieldNames = $this->fl->GetFieldNames($iblockId);
				foreach($nofields as $k=>$field)
				{
					$nofields[$k] = '"'.$fieldNames[$field].'"';
				}
				$nofields = implode(', ', $nofields);
				$this->errors[] = sprintf(Loc::getMessage("KDA_IE_NOT_SET_UID"), $this->worksheetNum+1, $nofields);
			}
			if(!$this->GetNextWorksheetNum(true))
			{
				$this->worksheet = false;
				return false;
			}
			$pos = $this->GetNextLoadRow(1, $this->worksheetNum);
			$this->SetWorksheet($this->worksheetNum, $pos);
			return;
		}
		
		$this->iblockId = $iblockId;
		$this->fieldSettings = array();
		$this->fieldSettingsExtra = array();
		$this->fieldOnlyNew = array();
		$this->fieldOnlyNewOffer = array();
		$this->fieldsForSkuGen = array();
		$this->fieldsBindToGenSku = array();
		foreach($filedList as $k=>$field)
		{
			$fs = $this->fparams[$this->worksheetNum][$k];
			if(!is_array($fs)) $fs = array();
			if(preg_match('/^(ICAT_PRICE\d+_PRICE|ICAT_PURCHASING_PRICE)$/', $field) && $fs['PRICE_USE_EXT']=='Y')
			{
				$this->fieldSettings[$field.'|QUANTITY_FROM='.$fs['PRICE_QUANTITY_FROM'].'|QUANTITY_TO='.$fs['PRICE_QUANTITY_TO']] = $fs;
			}
			else
			{
				$this->fieldSettings[$field] = $fs;
				if(strpos($field, '|')!==false) $this->fieldSettings[substr($field, 0, strpos($field, '|'))] = $fs;
				if($fs['HLBL_FIELD']) $this->fieldSettings[$field.'/'.$fs['HLBL_FIELD']] = $fs;
			}
			$this->fieldSettingsExtra[$k] = $fs;
			if(isset($this->fparams[$this->worksheetNum]['SECTION_'.$k]))
			{
				$this->fieldSettingsExtra['SECTION_'.$k] = $this->fparams[$this->worksheetNum]['SECTION_'.$k];
			}
			if($this->fieldSettings[$field]['SET_NEW_ONLY']=='Y')
			{
				if(strpos($field, 'OFFER_')===0) $this->fieldOnlyNewOffer[] = substr($field, 6);
				else $this->fieldOnlyNew[] = $field;
			}
			if(strpos($field, 'OFFER_')===0)
			{
				if($this->fieldSettings[$field]['USE_FOR_SKU_GENERATE']=='Y')
				{
					$this->fieldsForSkuGen[] = (string)$k;
				}
				elseif($this->fieldSettings[$field]['BIND_TO_GENERATED_SKU']=='Y')
				{
					$this->fieldsBindToGenSku[] = (string)$k;
				}
			}
		}

		if(isset($this->worksheetNumForSave) && 
			$this->worksheetNumForSave != $this->worksheetNum && 
			isset($this->stepparams['cursections'.$iblockId]))
		{
			unset($this->stepparams['cursections'.$iblockId]);
			unset($this->stepparams['last_section']);
		}
		
		$sectExtraSettingsKeys = preg_grep('/^__P*\d+$/', array_keys($this->fparams[$this->worksheetNum]));
		foreach($sectExtraSettingsKeys as $k)
		{
			$this->fieldSettingsExtra[$k] = $this->fparams[$this->worksheetNum][$k];
		}
		
		if(!isset($this->stepparams['ELEMENT_NOT_LOAD_STYLES_ORIG']))
		{
			$this->stepparams['ELEMENT_NOT_LOAD_STYLES_ORIG'] = ($this->params['ELEMENT_NOT_LOAD_STYLES']=='Y' ? 'Y' : 'N');
		}
		else
		{
			$this->params['ELEMENT_NOT_LOAD_STYLES'] = $this->stepparams['ELEMENT_NOT_LOAD_STYLES_ORIG'];
		}
		
		$this->sectionstyles = array();
		$this->propertystyles = array();
		if($this->params['ELEMENT_NOT_LOAD_STYLES']!='Y')
		{
			foreach($listSettings as $k2=>$v2)
			{
				if(strpos($k2, 'SET_SECTION_')===0) $this->sectionstyles[md5($v2)] = intval(substr($k2, 12));
				elseif(strpos($k2, 'SET_PROPERTY_')===0) $this->propertystyles[md5($v2)] = intval(substr($k2, 13));
			}
			if(empty($this->sectionstyles) && empty($this->propertystyles)) $this->params['ELEMENT_NOT_LOAD_STYLES'] = 'Y';
			elseif(!empty($this->sectionstyles)) $this->sectionstylesFl = min($this->sectionstyles);
		}
		
		$this->sectioncolumn = false;
		if(isset($listSettings['SECTION_NAME_CELL']))
		{
			$this->sectioncolumn = (int)$listSettings['SECTION_NAME_CELL'] - 1;
		}
		$this->titlesRow = (isset($listSettings['SET_TITLES']) ? $listSettings['SET_TITLES'] : false);
		$this->hintsRow = (isset($listSettings['SET_HINTS']) ? $listSettings['SET_HINTS'] : false);

		$maxDrawCol = 0;
		$this->draws = array();
		if($this->params['ELEMENT_LOAD_IMAGES']=='Y')
		{
			$this->draws = self::GetWorksheetDraws($this->worksheet);
		}
		
		$this->useHyperlinks = false;
		$this->useNotes = false;
		foreach($this->fieldSettingsExtra as $k=>$v)
		{
			if(is_array($v['CONVERSION']))
			{
				foreach($v['CONVERSION'] as $k2=>$v2)
				{
					if(strpos($v2['TO'], '#CLINK#')!==false)
					{
						$this->useHyperlinks = true;
					}
					if(strpos($v2['TO'], '#CNOTE#')!==false)
					{
						$this->useNotes = true;
					}
				}
			}
		}
		$this->conv = new \Bitrix\KdaImportexcel\Conversion($this, $iblockId, $this->fieldSettings);
		
		$this->worksheetColumns = max(KDAPHPExcel_Cell::columnIndexFromString($this->worksheet->getHighestDataColumn()), $maxDrawCol);
		$this->worksheetRows = min($this->maxReadRows, $this->worksheet->getHighestDataRow()+1);
		$this->worksheetCurrentRow = $worksheetCurrentRow;
		if($this->worksheet)
		{
			$this->worksheetRows = min($worksheetCurrentRow+$this->maxReadRows, $this->worksheet->getHighestDataRow()+1);
		}
	}
	
	public static function GetWorksheetDraws(&$worksheet)
	{
		$draws = array();
		$drawCollection = $worksheet->getDrawingCollection();
		if($drawCollection)
		{
			$arMergedCells = array();
			$arMergedCellsPE = $worksheet->getMergeCells();
			if(is_array($arMergedCellsPE))
			{
				foreach($arMergedCellsPE as $coord)
				{
					list($coord1, $coord2) = explode(':', $coord, 2);
					$arCoords1 = KDAPHPExcel_Cell::coordinateFromString($coord1);
					$arCoords2 = KDAPHPExcel_Cell::coordinateFromString($coord2);
					$arMergedCells[$arCoords1[0]][$coord] = array($arCoords1[1], $arCoords2[1]);
					$arMergedCells[$arCoords2[0]][$coord] = array($arCoords1[1], $arCoords2[1]);
				}
			}
			
			foreach($drawCollection as $drawItem)
			{
				$coord = $drawItem->getCoordinates();
				$arPartsCoord = KDAPHPExcel_Cell::coordinateFromString($coord);
				$columIndex = KDAPHPExcel_Cell::columnIndexFromString($arPartsCoord[0]);
				$maxDrawCol = max($maxDrawCol, $columIndex);
				$arPartsCoordTo = array();
				if(is_callable(array($drawItem, 'getCoordinatesTo')) && ($coordTo = $drawItem->getCoordinatesTo()))
				{
					$arPartsCoordTo = KDAPHPExcel_Cell::coordinateFromString($coordTo);
				}				
				$arCoords = array();
				if(!empty($arPartsCoordTo))
				{
					for($i=$arPartsCoord[1]; $i<=$arPartsCoordTo[1]; $i++)
					{
						$arCoords[] = $arPartsCoord[0].$i;
					}
				}
				if(isset($arMergedCells[$arPartsCoord[0]]) && is_array($arMergedCells[$arPartsCoord[0]]))
				{
					foreach($arMergedCells[$arPartsCoord[0]] as $range)
					{
						if($arPartsCoord[1] >= $range[0] && $arPartsCoord[1] <= $range[1])
						{
							for($i=$range[0]; $i<=$range[1]; $i++)
							{
								$arCoords[] = $arPartsCoord[0].$i;
							}
						}
					}
				}
				if(empty($arCoords)) $arCoords[] = $coord;
				$arCoords = array_unique($arCoords);
				foreach($arCoords as $coord)
				{
					//if(array_key_exists($coord, $draws)) continue;
					if(is_callable(array($drawItem, 'getPath')))
					{
						$draws[$coord][] = $drawItem->getPath();
						if(is_callable($drawItem, 'getHypelink') && $drawItem->getHypelink())
						{
							$cell = $worksheet->getCellByColumnAndRow($columIndex - 1, $arPartsCoord[1]);
							if(!$cell->getHyperlink()->getUrl()) $cell->getHyperlink()->setUrl($drawItem->getHypelink());
						}
					}
					elseif(is_callable(array($drawItem, 'getImageResource')))
					{
						$draws[$coord][] = array(
							'IMAGE_RESOURCE' => $drawItem->getImageResource(),
							'RENDERING_FUNCTION' => $drawItem->getRenderingFunction(),
							'MIME_TYPE' => $drawItem->getMimeType(),
							'FILENAME' => $drawItem->getIndexedFilename()
						);
					}
				}
			}
		}
		return $draws;
	}
	
	public function SetFilePosition($pos, $time)
	{
		if($this->breakWorksheet)
		{
			$this->breakWorksheet = false;
			if(!$this->GetNextWorksheetNum(true)) return;
			if(!$this->HaveTimeSetWorksheet($time)) return false;
			$pos = $this->GetNextLoadRow(1, $this->worksheetNum);
			$this->SetWorksheet($this->worksheetNum, $pos);
		}
		else
		{
			$pos = $this->GetNextLoadRow($pos, $this->worksheetNum);
			if(($pos >= $this->worksheetRows) || !$this->worksheet)
			{
				if(!$this->HaveTimeSetWorksheet($time)) return false;
				if(!$this->GetNextWorksheetNum()) return;
				$this->SetWorksheet($this->worksheetNum, $pos);
				if($this->worksheetCurrentRow > $this->worksheetRows)
				{
					if(!$this->GetNextWorksheetNum(true)) return;
					if(!$this->HaveTimeSetWorksheet($time)) return false;
					$pos = $this->GetNextLoadRow(1, $this->worksheetNum);
					$this->SetWorksheet($this->worksheetNum, $pos);
				}
				$this->SaveStatusImport();
			}
			else
			{
				$this->worksheetCurrentRow = $pos;
			}
		}
		if(!$this->isPacket) $this->UpdateWorksheetCurrentRow();
	}
	
	public function UpdateWorksheetCurrentRow($row = false)
	{
		if($row!==false) $this->worksheetCurrentRow = $row;
		$this->stepparams['csv_position'] = $this->chunkFilter->getFilePosRow($this->worksheetCurrentRow);
	}
	
	public function GetNextWorksheetNum($inc = false)
	{
		if($inc)
		{
			$this->worksheetNum++;
			$this->titlesRow = false;
			$this->hintsRow = false;
		}
		$arLists = $this->params['LIST_ACTIVE'];
		while(isset($arLists[$this->worksheetNum]) && $arLists[$this->worksheetNum]!='Y')
		{
			$this->worksheetNum++;
		}
		if(!isset($arLists[$this->worksheetNum]))
		{
			$this->worksheet = false;
			return false;
		}
		return true;
	}
	
	public function UniCheckSkipLine($val, $p)
	{		
		$load = true;
		if($load && is_array($p['UPLOAD_VALUES']) && !empty($p['UPLOAD_VALUES']))
		{
			$subload = false;
			$val = ToLower(trim(is_array($val) ? implode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val) : $val));
			$keys = $p['UPLOAD_KEYS'];
			foreach($p['UPLOAD_VALUES'] as $kv=>$needval)
			{
				$key = (isset($keys[$kv]) ? $keys[$kv] : '');
				$needval = ToLower(trim($needval));
				if($this->CompareUploadValue($key, $val, $needval))
				{
					$subload = true;
				}
			}
			$load = ($load && $subload);
		}
		if($load && is_array($p['NOT_UPLOAD_VALUES']) && !empty($p['NOT_UPLOAD_VALUES']))
		{
			$subload = true;
			$val = ToLower(trim(is_array($val) ? implode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val) : $val));
			$keys = $p['NOT_UPLOAD_KEYS'];
			foreach($p['NOT_UPLOAD_VALUES'] as $kv=>$needval)
			{
				$key = (isset($keys[$kv]) ? $keys[$kv] : '');
				$needval = ToLower(trim($needval));
				if($this->CompareUploadValue($key, $val, $needval))
				{
					$subload = false;
				}
			}
			$load = ($load && $subload);
		}
		
		return !$load;
	}
	
	public function CompareUploadValue($key, $val, $needval)
	{
		if((!$key && $needval==$val)
			|| ($needval=='{empty}' && strlen($val)==0)
			|| ($needval=='{not_empty}' && strlen($val) > 0)
			|| ($key=='contain' && strpos($val, $needval)!==false)
			|| ($key=='begin' && mb_substr($val, 0, mb_strlen($needval))==$needval)
			|| ($key=='end' && mb_substr($val, -mb_strlen($needval))==$needval)
			|| ($key=='gt' && $this->GetFloatVal($val) > $this->GetFloatVal($needval))
			|| ($key=='lt' && $this->GetFloatVal($val) < $this->GetFloatVal($needval)))
		{
			return true;
		}else return false;
	}
	
	public function CheckSkipLine($currentRow, $worksheetNum, $checkValue = true)
	{
		$load = true;
		
		if($this->breakWorksheet ||
			(!$this->params['CHECK_ALL'][$worksheetNum] && !isset($this->params['IMPORT_LINE'][$worksheetNum][$currentRow - 1])) || 
			(isset($this->params['IMPORT_LINE'][$worksheetNum][$currentRow - 1]) && !$this->params['IMPORT_LINE'][$worksheetNum][$currentRow - 1])
			|| ($this->titlesRow!==false && $this->titlesRow==($currentRow - 1)))
		{
			$load = false;
		}
				
		if($load && !empty($this->params['ADDITIONAL_SETTINGS'][$worksheetNum]['LOADING_RANGE']))
		{
			$load = false;
			$arRanges = $this->params['ADDITIONAL_SETTINGS'][$worksheetNum]['LOADING_RANGE'];
			foreach($arRanges as $k=>$v)
			{
				$row = $currentRow;
				if(($v['FROM'] || $v['TO']) && ($row >= $v['FROM'] || !$v['FROM']) && ($row <= $v['TO'] || !$v['TO']))
				{
					$load = true;
				}
			}
		}
		
		if($load && $checkValue && is_array($this->fparams[$worksheetNum]) && $this->params['ELEMENT_NOT_LOAD_STYLES']!='Y' && (!empty($this->sectionstyles) || !empty($this->propertystyles)))
		{
			$valText = '';
			$column = 0;
			while(strlen($valText)==0 && $column < $this->worksheetColumns)
			{
				$val = $this->worksheet->getCellByColumnAndRow($column, $currentRow);
				$valText = trim($this->GetCalculatedValue($val));
				$column++;
			}
			if(strlen($valText) > 0)
			{
				$arStyle = md5(\KdaIE\Utils::PhpToJSObject($this->GetCellStyle($val)));
				if(isset($this->sectionstyles[$arStyle]) || isset($this->propertystyles[$arStyle]))
				{
					$checkValue = false;
				}
			}
		}
		
		if($load && $checkValue && is_array($this->fparams[$worksheetNum]))
		{
			foreach($this->fparams[$worksheetNum] as $k=>$v)
			{
				if(!is_array($v) || strpos($k, '__')===0) continue;
				if(is_array($v['UPLOAD_VALUES']) || is_array($v['NOT_UPLOAD_VALUES']) || $v['FILTER_EXPRESSION'])
				{
					$val = $this->worksheet->getCellByColumnAndRow($k, $currentRow);
					$valOrig = $this->GetCalculatedValue($val);
					$val = $this->ApplyConversions($valOrig, $v['CONVERSION'], array());
					if(is_array($val)) $val = array_map(array('CKDAImportUtils', 'TrimToLower'), $val);
					else $val = ToLower(trim($val));
				}
				else
				{
					$val = '';
				}
				
				if(is_array($v['UPLOAD_VALUES']))
				{
					$subload = false;
					foreach($v['UPLOAD_VALUES'] as $needval)
					{
						$needval = ToLower($this->Trim($needval));
						if($needval==$val
							|| (is_array($val) && in_array($needval, $val))
							|| ($needval=='{empty}' && ((!is_array($val) && strlen($val)==0) || (is_array($val) && count(array_diff(array_map(array($this, 'Trim'), $val), array('')))==0)))
							|| ($needval=='{not_empty}' && ((!is_array($val) && strlen($val) > 0) || (is_array($val) && count(array_diff(array_map(array($this, 'Trim'), $val), array(''))) > 0))))
						{
							$subload = true;
						}
					}
					$load = ($load && $subload);
				}
				
				if(is_array($v['NOT_UPLOAD_VALUES']))
				{
					$subload = true;
					foreach($v['NOT_UPLOAD_VALUES'] as $needval)
					{
						$needval = ToLower($this->Trim($needval));
						if($needval==$val
							|| (is_array($val) && in_array($needval, $val))
							|| ($needval=='{empty}' && ((!is_array($val) && strlen($val)==0) || (is_array($val) && count(array_diff(array_map(array($this, 'Trim'), $val), array('')))==0)))
							|| ($needval=='{not_empty}' && ((!is_array($val) && strlen($val) > 0) || (is_array($val) && count(array_diff(array_map(array($this, 'Trim'), $val), array(''))) > 0))))
						{
							$subload = false;
						}
					}
					$load = ($load && $subload);
				}
				
				if($v['FILTER_EXPRESSION'])
				{
					$load = ($load && $this->ExecuteFilterExpression($valOrig, $v['FILTER_EXPRESSION']));
				}
			}
		}
		if(!$load && isset($this->stepparams['currentelement']))
		{
			unset($this->stepparams['currentelement']);
		}
		return !$load;
	}
	
	public function ExecuteFilterExpression($val, $expression, $altReturn = true, $arParams = array(), $field = false)
	{
		foreach($arParams as $k=>$v)
		{
			${$k} = $v;
		}
		$this->phpExpression = $expression = trim($expression);
		$ret = '';
		try{				
			if(preg_match('/(^|\n)[\r\t\s]*return/is', $expression))
			{
				$command = $expression.';';
				$ret = eval($command);
			}
			elseif(preg_match('/\$val\s*=[^=]/', $expression))
			{
				$command = $expression.';';
				eval($command);
				$ret = $val;
			}
			else
			{
				$command = 'return '.$expression.';';
				$ret = eval($command);
			}
		}catch(Exception | Error $ex){
			if(is_array($field) && isset($field['NAME']))
			{
				$fieldName = $field['NAME'];
				if(strpos($fieldName, 'OFFER_')===0)
				{
					$fieldName = substr($fieldName, 6);
					$fieldNames = $this->fl->GetFieldNames($this->GetCurrentOfferIblock());
					$error = Loc::getMessage("KDA_IE_PHPEXPRESSION_OFFER_ERROR");
				}
				else
				{
					$fieldNames = $this->fl->GetFieldNames($this->GetCurrentIblock());
					$error = Loc::getMessage("KDA_IE_PHPEXPRESSION_ERROR");
				}
				
				if($fieldName = $fieldNames[$fieldName])
				{
					$this->phpExpression = null;
					$this->errors[] = sprintf($error, $fieldName, $this->worksheetCurrentRow, $this->worksheetNumForSave+1, $ex->getMessage(), htmlspecialcharsbx($expression));
				}
			}
			$ret = $altReturn;
		}
		$this->phpExpression = null;
		return $ret;
	}
	
	public function ExecuteOnAfterSaveHandler($handler, $ID)
	{
		try{
			$command = $handler.';';
			eval($command);
		}catch(Exception $ex){}
	}
	
	public function GetNextLoadRow($row, $worksheetNum)
	{
		$nextRow = $row;
		if(isset($this->params['LIST_ACTIVE'][$worksheetNum]))
		{
			while($this->CheckSkipLine($nextRow, $worksheetNum, false))
			{
				$nextRow++;
				if($nextRow - $row > 30000)
				{
					return $nextRow;
				}
			}
		}
		return $nextRow;
	}
	
	public function GetNextRecord($time)
	{		
		if($this->SetFilePosition($this->worksheetCurrentRow + 1, $time)===false) return false;
		while($this->worksheet && $this->CheckSkipLine($this->worksheetCurrentRow, $this->worksheetNum))
		{
			if($this->CheckTimeEnding($time)) return false;
			if($this->SetFilePosition($this->worksheetCurrentRow + 1, $time)===false) return false;
		}

		if(!$this->worksheet)
		{
			return false;
		}
		
		$arItem = array();
		$this->hyperlinks = array();
		$this->notes = array();
		//for($column = 0; $column < $this->worksheetColumns; $column++) 
		foreach($this->GetNeedFileColumns() as $column)
		{
			$val = $this->worksheet->getCellByColumnAndRow($column, $this->worksheetCurrentRow);
			$valText = $this->GetCalculatedValue($val);			
			$arItem[$column] = $this->Trim($valText);
			$arItem['~'.$column] = $valText;
			if(($htmlVal = $val->getHtmlValue())!==false) $arItem['html_'.$column] = $htmlVal;
			if($this->params['ELEMENT_NOT_LOAD_STYLES']!='Y' && (!isset($arItem['STYLE']) || ($this->sectioncolumn!==false && $this->sectioncolumn==$column)) && strlen(trim($valText))>0)
			{
				$arItem['STYLE'] = md5(\KdaIE\Utils::PhpToJSObject($this->GetCellStyle($val)));
			}	
			if($this->params['ELEMENT_LOAD_IMAGES']=='Y')
			{
				//$valCoord = $val->getCoordinate();
				$valCoord = \KDAPHPExcel_Cell::stringFromColumnIndex($column).$this->worksheetCurrentRow;
				if($this->draws[$valCoord] /*&& preg_replace('/\D/', '', $valCoord)==$this->worksheetCurrentRow*/)
				{
					$bAddItemVal = (bool)(strlen(trim($arItem[$column]))==0);
					foreach($this->draws[$valCoord] as $draw)
					{
						if(is_array($draw) && isset($draw['RENDERING_FUNCTION']))
						{
							$tmpsubdir = $this->imagedir.($this->filecnt++).'/';
							CheckDirPath($tmpsubdir);
							if(call_user_func($draw['RENDERING_FUNCTION'], $draw['IMAGE_RESOURCE'], $tmpsubdir.$draw['FILENAME']))
							{
								$draw = substr($tmpsubdir, strlen($_SERVER["DOCUMENT_ROOT"])).$draw['FILENAME'];
							}
							else $draw = '';
						}
						elseif(strpos($draw, '/')===0 && ($ex='exif'.'_read_data'/*bitrix.xscan*/) && function_exists($ex) && ($arExifData = call_user_func($ex, $draw)) && in_array($arExifData['Orientation'], array(3, 6, 8)) && in_array($arExifData['MimeType'], array('image/jpeg', 'image/png')))
						{
							if($arExifData['MimeType']=='image/jpeg') $image = imagecreatefromjpeg($draw);
							elseif($arExifData['MimeType']=='image/png') $image = imagecreatefrompng($draw);
							if($arExifData['Orientation']==8) imagerotate($image,90,0);
							elseif($arExifData['Orientation']==3) imagerotate($image,180,0);
							elseif($arExifData['Orientation']==6) imagerotate($image,-90,0);
							if($arExifData['MimeType']=='image/jpeg') imagejpeg($image, $draw, 100);
							elseif($arExifData['MimeType']=='image/png'){imageinterlace($image, false); imagepng($image, $draw, 9);}
							imagedestroy($image);
						}
						if(!isset($arItem['i~'.$column])) $arItem['i~'.$column] = $draw;
						if($bAddItemVal)
						{
							$arItem[$column] .= $draw.$this->params['ELEMENT_MULTIPLE_SEPARATOR'];
							$arItem['~'.$column] .= $draw.$this->params['ELEMENT_MULTIPLE_SEPARATOR'];
						}
					}
				}
			}
			
			if($this->useHyperlinks)
			{
				$this->hyperlinks[$column] = self::CorrectCalculatedValue($val->getHyperlink()->getUrl());
				if(!$this->hyperlinks[$column] && ($sourceVal = $val->getValue()) && preg_match('/^=HYPERLINK\("([^"]+)"/', $sourceVal, $m)) $this->hyperlinks[$column] = $m[1];
			}
			if($this->useNotes)
			{
				$comment = $this->worksheet->getCommentByColumnAndRow($column, $this->worksheetCurrentRow);
				if($comment->getImage()) $note = $comment->getImage();
				elseif(is_object($comment->getText())) $note = $comment->getText()->getPlainText();
				$this->notes[$column] = $note;
			}
		}

		$this->worksheetNumForSave = $this->worksheetNum;
		if(count($arItem) > 0)
		{
			$arItem['worksheetCurrentRow'] = $this->worksheetCurrentRow;
			$arItem['worksheetNumForSave'] = $this->worksheetNumForSave;
		}
		return $arItem;
	}
	
	public function SaveRecord($arItem, $isPacket=false)
	{
		if($this->hintsRow!==false && $this->hintsRow==$this->worksheetCurrentRow - 1)
		{
			return $this->SavePropertiesHints($arItem);
		}
		
		$saveReadRecord = (bool)(!isset($this->stepparams['lastoffergenkey']) && !$isPacket);
		
		if($saveReadRecord) $this->stepparams['total_read_line']++;
		
		$emptyItem = true;
		foreach($arItem as $k=>$v)
		{
			if((is_numeric($k) || strpos($k, '~')===0) && strlen(trim($v)) > 0)
			{
				$emptyItem = false;
				break;
			}
		}
		if($emptyItem)
		{
			$this->skipRows++;
			if($this->params['ADDITIONAL_SETTINGS'][$this->worksheetNum]['BREAK_LOADING']=='Y' || ($this->skipRows>=$this->maxReadRows - 1))
			{
				$this->breakWorksheet = true;
			}
			return false;
		}
		
		if($saveReadRecord)
		{
			$this->stepparams['total_line']++;
			$this->stepparams['total_line_by_list'][$this->worksheetNum]++;
		}

		$filedList = $this->params['FIELDS_LIST'][$this->worksheetNumForSave];
		$IBLOCK_ID = $this->params['IBLOCK_ID'][$this->worksheetNumForSave];
		$SECTION_ID = $this->params['SECTION_ID'][$this->worksheetNumForSave];
		$propsDef = $this->GetIblockProperties($IBLOCK_ID);
		
		if($arItem['STYLE'])
		{
			if(isset($this->sectionstyles[$arItem['STYLE']]))
			{
				if($this->SetSectionSeparate($arItem, $IBLOCK_ID, $SECTION_ID, $this->sectionstyles[$arItem['STYLE']]))
					$this->stepparams['correct_line']++;
				else
				{
					$this->Err(sprintf(Loc::getMessage("KDA_IE_NOT_SAVE_SECTION_SEPARATE"), $this->worksheetNumForSave+1, $this->worksheetCurrentRow));
				}
				return false;
			}
			elseif(isset($this->propertystyles[$arItem['STYLE']]))
			{
				$propId = $this->propertystyles[$arItem['STYLE']];
				$propVal = $this->GetStyleCellValue($arItem, 'P'.$propId);
				if(!isset($this->stepparams['sepproperties'])) $this->stepparams['sepproperties'] = array();
				if(!isset($this->stepparams['seppropertiesOrig'])) $this->stepparams['seppropertiesOrig'] = array();
				//$this->stepparams['sepproperties'][$propId] = $propVal;
				if(isset($this->stepparams['sepproperties'][$propId])) unset($this->stepparams['sepproperties'][$propId]);
				$propSettings = (isset($this->fieldSettingsExtra['__P'.$propId]) ? $this->fieldSettingsExtra['__P'.$propId] : array());
				$this->GetPropField($this->stepparams['sepproperties'], $this->stepparams['seppropertiesOrig'], $propSettings, $propsDef[$propId], $propId, $propVal, $propVal, $this->params['CURRENT_ELEMENT_UID']);
				$this->skipSepProp = $this->UniCheckSkipLine($propVal, $propSettings);
				$this->stepparams['correct_line']++;
				return false;
			}
		}
		if((!empty($this->sectionstyles) && $this->skipSepSection===true) || (!empty($this->propertystyles) && $this->skipSepProp===true)) return false;
		
		$arFieldsDef = $this->fl->GetFields($IBLOCK_ID);
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		$this->currentItemValues = $arItem;

		$arFieldsElement = array();
		$arFieldsElementOrig = array();
		$arFieldsPrices = array();
		$arFieldsProduct = array();
		$arFieldsProductStores = array();
		$arFieldsProductDiscount = array();
		$arFieldsProps = array();
		$arFieldsPropsOrig = array();
		$arFieldsSections = array();
		$arFieldsIpropTemp = array();
		$arFieldsReview = array();
		if(isset($this->stepparams['sepproperties']) && is_array($this->stepparams['sepproperties'])) $arFieldsProps = $arFieldsPropsOrig = $this->stepparams['sepproperties'];
		foreach($filedList as $key=>$field)
		{
			$k = $key;
			if(strpos($k, '_')!==false) $k = substr($k, 0, strpos($k, '_'));
			$value = $arItem[$k];
			if($this->fieldSettings[$field]['NOT_TRIM']=='Y') $value = $arItem['~'.$k];
			if($this->fieldSettings[$field]['EXCEL_STYLES_TO_HTML']=='Y') $value = $arItem['html_'.$k];
			$origValue = $arItem['~'.$k];
			
			$conversions = (isset($this->fieldSettingsExtra[$key]) ? $this->fieldSettingsExtra[$key]['CONVERSION'] : $this->fieldSettings[$field]['CONVERSION']);
			if(!empty($conversions))
			{
				$eqValues = (bool)($value===$origValue);
				$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$k, 'NAME'=>$field), $iblockFields);
				if($eqValues) $origValue = $value;
				else $origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$k, 'NAME'=>$field), $iblockFields);
				if($value===false) continue;
			}
			
			if(strpos($field, 'IE_')===0)
			{
				$fieldKey = substr($field, 3);
				if($fieldKey=='SECTION_PATH')
				{
					$tmpSep = $this->GetSeparator($this->fieldSettingsExtra[$key]['SECTION_PATH_SEPARATOR'] ? $this->fieldSettingsExtra[$key]['SECTION_PATH_SEPARATOR'] : '/');
					if($this->fieldSettingsExtra[$key]['SECTION_PATH_SEPARATED']=='Y')
						$arVals = explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $value);
					else $arVals = array($value);
					foreach($arVals as $subvalue)
					{
						$tmpVal = array_values(array_diff(array_map('trim', explode($tmpSep, $subvalue)), array('')));
						$arFieldsElement[$fieldKey][] = $tmpVal;
						$arFieldsElementOrig[$fieldKey][] = $tmpVal;
					}
				}
				elseif($this->params['ELEMENT_LOAD_IMAGES']=='Y' && in_array($fieldKey, array('DETAIL_PICTURE', 'PREVIEW_PICTURE')) && isset($arItem['i~'.$k]))
				{
						$arFieldsElement[$fieldKey] = $arItem['i~'.$k];
						$arFieldsElementOrig[$fieldKey] = $arItem['i~'.$k];
				}
				else
				{
					if(strpos($fieldKey, '|')!==false)
					{
						list($fieldKey, $adata) = explode('|', $fieldKey);
						$adata = explode('=', $adata);
						if(count($adata) > 1)
						{
							$arFieldsElement[$adata[0]] = $adata[1];
						}
					}
					if(isset($arFieldsElement[$fieldKey]) && in_array($field, $this->params['CURRENT_ELEMENT_UID']))
					{
						if(!is_array($arFieldsElement[$fieldKey]))
						{
							$arFieldsElement[$fieldKey] = array($arFieldsElement[$fieldKey]);
							$arFieldsElementOrig[$fieldKey] = array($arFieldsElementOrig[$fieldKey]);
						}
						$arFieldsElement[$fieldKey][] = $value;
						$arFieldsElementOrig[$fieldKey][] = $origValue;
					}
					else
					{
						$arFieldsElement[$fieldKey] = $value;
						$arFieldsElementOrig[$fieldKey] = $origValue;
					}
				}
			}
			elseif(strpos($field, 'ISECT')===0)
			{
				$adata = false;
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
				}
				$arSect = explode('_', substr($field, 5), 2);
				if(strlen($arSect[0])==0) $arSect[0] = 0;
				$arFieldsSections[$arSect[0]][$arSect[1]] = $value;
				
				if(is_array($adata) && count($adata) > 1)
				{
					$arFieldsSections[$arSect[0]][$adata[0]] = $adata[1];
				}
			}
			elseif(strpos($field, 'ICAT_PRICE')===0)
			{
				$val = $value;
				if(substr($field, -6)=='_PRICE')
				{
					if(!in_array($val, array('', '-')))
					{
						//$val = $this->GetFloatVal($val);
						$val = $this->ApplyMargins($val, $this->fieldSettingsExtra[$key]);
					}
				}
				elseif(substr($field, -6)=='_EXTRA')
				{
					$val = $this->GetFloatVal($val, 0, true);
				}
				
				$arPrice = explode('_', substr($field, 10), 2);
				$pkey = $arPrice[1];
				if($pkey=='PRICE' && $this->fieldSettingsExtra[$key]['PRICE_USE_EXT']=='Y')
				{
					$pkey = $pkey.'|QUANTITY_FROM='.$this->CalcFloatValuePhp($this->fieldSettingsExtra[$key]['PRICE_QUANTITY_FROM']).'|QUANTITY_TO='.$this->CalcFloatValuePhp($this->fieldSettingsExtra[$key]['PRICE_QUANTITY_TO']);
				}
				$arFieldsPrices[$arPrice[0]][$pkey] = $val;
			}
			elseif(strpos($field, 'ICAT_LIST_STORES')===0)
			{
				$this->GetStoreAmountList($arFieldsProductStores, $this->fieldSettingsExtra[$key], $value);
			}
			elseif(strpos($field, 'ICAT_STORE')===0)
			{
				$arStore = explode('_', substr($field, 10), 2);
				$arFieldsProductStores[$arStore[0]][$arStore[1]] = $value;
			}
			elseif(strpos($field, 'ICAT_DISCOUNT_')===0)
			{
				if(strpos($field, 'ICAT_DISCOUNT_VALUE')===0 && in_array(trim($value), array('', '0')) && isset($arFieldsProductDiscount['VALUE'])) continue;
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
					if(count($adata) > 1)
					{
						$arFieldsProductDiscount[$adata[0]] = $adata[1];
					}
				}
				$field = substr($field, 14);
				if($field=='VALUE' && isset($this->fieldSettingsExtra[$key]))
				{
					$fse = $this->fieldSettingsExtra[$key];
					if(!empty($fse['CATALOG_GROUP_IDS']))
					{
						$arFieldsProductDiscount['CATALOG_GROUP_IDS'] = $fse['CATALOG_GROUP_IDS'];
					}
					if(is_array($fse['SITE_IDS']) && !empty($fse['SITE_IDS']))
					{
						foreach($fse['SITE_IDS'] as $siteId)
						{
							$arFieldsProductDiscount['LID_VALUES'][$siteId] = array('VALUE'=>$value);
							if(isset($arFieldsProductDiscount['VALUE_TYPE'])) $arFieldsProductDiscount['LID_VALUES'][$siteId]['VALUE_TYPE'] = $arFieldsProductDiscount['VALUE_TYPE'];
						}
					}
				}
				$arFieldsProductDiscount[$field] = $value;
			}
			elseif(strpos($field, 'ICAT_')===0)
			{
				$val = $value;
				if($field=='ICAT_PURCHASING_PRICE')
				{
					if($val=='') continue;
					$val = $this->GetFloatVal($val);
				}
				$arFieldsProduct[substr($field, 5)] = $val;
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				$fieldName = substr($field, 7);
				if(substr($fieldName, -12)=='_DESCRIPTION') $currentPropDef = $propsDef[substr($fieldName, 0, -12)];
				else $currentPropDef = $propsDef[$fieldName];
				$this->GetPropField($arFieldsProps, $arFieldsPropsOrig, $this->fieldSettingsExtra[$key], $currentPropDef, $fieldName, $value, $origValue, $this->params['CURRENT_ELEMENT_UID']);
			}
			elseif(strpos($field, 'IP_LIST_PROPS')===0)
			{
				$this->GetPropList($arFieldsProps, $arFieldsPropsOrig, $this->fieldSettingsExtra[$key], $IBLOCK_ID, $value);
			}
			elseif(strpos($field, 'IPROP_TEMP_')===0)
			{
				$fieldName = substr($field, 11);
				$arFieldsIpropTemp[$fieldName] = $value;
			}
			elseif(strpos($field, 'REVIEW_')===0)
			{
				$fieldName = substr($field, 7);
				$arFieldsReview[$fieldName] = $value;
			}
		}

		$arUid = $this->GetFilterUids($arFieldsElement, $arFieldsElementOrig, $arFieldsProps, $arFieldsPropsOrig, $IBLOCK_ID);
		
		$emptyFields = array();
		foreach($arUid as $k=>$v)
		{
			if((is_array($v['valUid']) && count(array_diff(array_map(array($this, 'Trim'), $v['valUid']), array('')))==0)
				|| (!is_array($v['valUid']) && strlen($this->Trim($v['valUid']))==0)) $emptyFields[] = $v['nameUid'];
		}
		
		if(!empty($emptyFields) || empty($arUid))
		{
			$bEmptyElemFields = (bool)(count(array_diff($arFieldsElement, array('')))==0 && count(array_diff($arFieldsProps, array('')))==0);
			$res = false;
			
			if((empty($arUid) || count($emptyFields)==count($arUid)) && ($this->params['ONLY_DELETE_MODE']!='Y'))
			{
				/*If empty element try save SKU*/
				if($this->params['CURRENT_ELEMENT_UID_SKU'] && !empty($this->stepparams['currentelement']))
				{
					$arFieldsElementSKU = $this->stepparams['currentelement'];
					$res = $this->SaveSKUWithGenerate($arFieldsElementSKU['ID'], $arFieldsElementSKU['NAME'], $IBLOCK_ID, $arItem);
					if($res==='timesup') return false;
				}
				/*/If empty element try save SKU*/
				
				/*Maybe additional sections*/
				$arElementNEFields = array_diff($arFieldsElement, array(''));
				$arElementNEFieldsKeys = array_diff(array_keys($arElementNEFields), array('SECTION_PATH', 'DETAIL_TEXT_TYPE', 'PREVIEW_TEXT_TYPE'));
				if(!$res && !empty($arFieldsSections) && count($arElementNEFieldsKeys)==0)
				{
					$isElement = !empty($this->stepparams['currentelement']);
					if($this->params['ELEMENT_NOT_CHANGE_SECTIONS']!='Y' || !$isElement)
					{
						$this->GetSections($arFieldsElement, $IBLOCK_ID, $SECTION_ID, $arFieldsSections);
						if($isElement && is_array($arFieldsElement['IBLOCK_SECTION']) && !empty($arFieldsElement['IBLOCK_SECTION']))
						{
							$arTmpElem = $this->stepparams['currentelement'];
							if(!is_array($arTmpElem['IBLOCK_SECTION'])) $arTmpElem['IBLOCK_SECTION'] = array();
							$arNewSect = array_diff($arFieldsElement['IBLOCK_SECTION'], $arTmpElem['IBLOCK_SECTION']);
							if(count($arNewSect) > 0)
							{
								$arTmpElem['IBLOCK_SECTION'] = array_merge($arTmpElem['IBLOCK_SECTION'], $arNewSect);
								if($this->params['ONLY_CREATE_MODE_PRODUCT']!='Y')
								{
									$el = new CIblockElement();
									$el->Update($arTmpElem['ID'], array(
										'IBLOCK_SECTION' => $arTmpElem['IBLOCK_SECTION'], 
										'IBLOCK_SECTION_ID' => current($arTmpElem['IBLOCK_SECTION'])
									), false, true, true);
									$this->AddTagIblock($IBLOCK_ID);
								}
							}
							$this->stepparams['currentelement'] = $arTmpElem;
						}
					}
					$res = true;
				}
				/*/Maybe additional sections*/
			}
			
			//$res = (bool)($res && $bEmptyElemFields);
			$res = (bool)($res);
			
			if(!$res)
			{
				$this->Err(sprintf(Loc::getMessage("KDA_IE_NOT_SET_FIELD"), implode(', ', $emptyFields), $this->worksheetNumForSave+1, $this->worksheetCurrentRow));
			}
			else
			{
				$this->stepparams['correct_line']++;
			}
			$this->SaveStatusImport();
			return false;
		}
		
		$arDates = array('ACTIVE_FROM', 'ACTIVE_TO', 'DATE_CREATE');
		foreach($arDates as $keyDate)
		{
			if(isset($arFieldsElement[$keyDate]) && strlen($arFieldsElement[$keyDate]) > 0)
			{
				$arFieldsElement[$keyDate] = $this->GetDateVal($arFieldsElement[$keyDate]);
			}
		}
		
		if(isset($arFieldsElement['ACTIVE']))
		{
			$arFieldsElement['ACTIVE'] = $this->GetBoolValue($arFieldsElement['ACTIVE']);
		}
		elseif($this->params['ELEMENT_LOADING_ACTIVATE']=='Y')
		{
			$arFieldsElement['ACTIVE'] = 'Y';
		}
		
		$arKeys = array_merge(array('ID', 'NAME', 'IBLOCK_SECTION_ID', 'MODIFIED_BY', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'), array_keys($arFieldsElement));
		
		$arFilter = array('IBLOCK_ID'=>$IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		foreach($arUid as $v)
		{
			if(!$v['substring'])
			{
				if(is_array($v['valUid']))
				{
					$arSubfilter = $v['valUid'];
					if(is_array($v['valUid2'])) $arSubfilter = array_unique(array_merge($arSubfilter, $v['valUid2']));
					elseif(strlen($v['valUid2']) > 0) $arSubfilter[] = $v['valUid2'];
				}
				else 
				{
					$arSubfilter = array($this->Trim($v['valUid']));
					if($this->Trim($v['valUid']) != $v['valUid2'])
					{
						$arSubfilter[] = $this->Trim($v['valUid2']);
						if(strlen($v['valUid2']) != strlen($this->Trim($v['valUid2'])))
						{
							$arSubfilter[] = $v['valUid2'];
						}
					}
					if(strlen($v['valUid'])!=strlen($this->Trim($v['valUid']))) $arSubfilter[] = $v['valUid'];
					if((!defined('BX_UTF') || !BX_UTF) && strpos($v['valUid'], "\xA0")!==false) $arSubfilter[] = str_replace("\xA0", ' ', $v['valUid']);
				}
				
				if(count($arSubfilter) == 1)
				{
					$arSubfilter = $arSubfilter[0];
				}
				$arFilter['='.$v['uid']] = $arSubfilter;
			}
			else
			{
				if(is_array($v['valUid'])) $v['valUid'] = array_map(array($this, 'Trim'), $v['valUid']);
				else $v['valUid'] = $this->Trim($v['valUid']);
				if($v['substring']=='B') $arFilter[$v['uid']] = (is_array($v['valUid']) ? array_map(array('CKDAImportUtils', 'GetFilterBeginWith'), $v['valUid']) : $v['valUid'].'%');
				elseif($v['substring']=='E') $arFilter[$v['uid']] = (is_array($v['valUid']) ? array_map(array('CKDAImportUtils', 'GetFilterEndOn'), $v['valUid']) : '%'.$v['valUid']);
				else $arFilter['%'.$v['uid']] = $v['valUid'];
			}
		}

		if(!empty($arFieldsIpropTemp))
		{
			$arFieldsElement['IPROPERTY_TEMPLATES'] = $arFieldsIpropTemp;
		}

		if($this->searchSections!==false)
		{
			$arFilter['SECTION_ID'] = $this->searchSections;
			$arFilter['INCLUDE_SUBSECTIONS'] = 'Y';
		}
		
		$arElemFields = array(
			'ELEMENT' => $arFieldsElement,
			'PROPS' => $arFieldsProps,
			'SECTIONS' => $arFieldsSections,
			'PRODUCT' => $arFieldsProduct,
			'PRICES' => $arFieldsPrices,
			'STORES' => $arFieldsProductStores,
			'DISCOUNT' => $arFieldsProductDiscount,
			'REVIEWS' => $arFieldsReview,
			'ITEM' => $arItem
		);
		
		$allowCreate = (bool)($this->params['ONLY_DELETE_MODE']!='Y');
		if($allowCreate && $this->params['SEARCH_OFFERS_WO_PRODUCTS']=='Y')
		{
			$res = $this->SaveSKUWithGenerate(0, '', $IBLOCK_ID, $arItem);
			if($res==='timesup') return false;
			if($res===true) $allowCreate = false;
		}
		
		if($isPacket) return array(
			'ITEM' => $arItem,
			'FILTER' => $arFilter,
			'FIELDS' => $arElemFields
		);
		
		$duplicate = false;
		//$dbRes = CIblockElement::GetList(array(), $arFilter, false, false, $arKeys);
		$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFilter, $arKeys);
		while($arElement = $dbRes->Fetch())
		{
			$res = $this->SaveRecordUpdate($IBLOCK_ID, $SECTION_ID, $arElement, $arElemFields, array(), $duplicate);
			if($res==='timesup') return false;
			$duplicate = true; 
		}
		
		$allowCreate = (bool)($allowCreate && \Bitrix\KdaImportexcel\DataManager\IblockElementTable::SelectedRowsCountComp($dbRes)==0);
		
		if($allowCreate)
		{
			$res = $this->SaveRecordAdd($IBLOCK_ID, $SECTION_ID, $arElemFields, $arItem, $arFilter);
			if($res==='timesup') return false;
		}
		
		$this->stepparams['correct_line']++;
		$this->SaveStatusImport();
		$this->RemoveTmpImageDirs();
	}
	
	public function SaveRecordAfter($ID, $IBLOCK_ID, $arItem, $arFieldsElement, $isChanges=true, $saveOffers=true)
	{
		if(!$ID) return true;
		
		/*Maybe additional sections*/
		if($this->params['ELEMENT_NOT_CHANGE_SECTIONS']!='Y')
		{
			$arTmpElem = $this->stepparams['currentelement'];
			if(!empty($arTmpElem) && $arTmpElem['ID']==$ID && is_array($arTmpElem['IBLOCK_SECTION']) && !empty($arTmpElem['IBLOCK_SECTION']) && is_array($arFieldsElement['IBLOCK_SECTION']) && count(array_diff($arTmpElem['IBLOCK_SECTION'], $arFieldsElement['IBLOCK_SECTION'])) > 0)
			{
				$arFieldsElement['IBLOCK_SECTION'] = array_merge($arTmpElem['IBLOCK_SECTION'], $arFieldsElement['IBLOCK_SECTION']);
				if($this->params['ONLY_CREATE_MODE_PRODUCT']!='Y')
				{
					$el = new CIblockElement();
					$el->Update($ID, array('IBLOCK_SECTION'=>$arFieldsElement['IBLOCK_SECTION']), false, true, true);
					$this->AddTagIblock($IBLOCK_ID);
				}
			}
		}
		/*/Maybe additional sections*/

		$arFieldsElement['ID'] = $ID;
		$this->stepparams['currentelement'] = $arFieldsElement;
		$this->stepparams['currentelementitem'] = $arItem;
		if($saveOffers && $this->params['CURRENT_ELEMENT_UID_SKU'])
		{
			$res = $this->SaveSKUWithGenerate($ID, $arFieldsElement['NAME'], $IBLOCK_ID, $arItem);
			if($res==='timesup') return $res;
		}
		
		$this->OnAfterSaveElement($ID, $isChanges);
		return true;
	}
	
	public function OnAfterSaveElement($ID, $isChanges=true)
	{
		if($this->params['ONAFTERSAVE_HANDLER'])
		{
			$this->ExecuteOnAfterSaveHandler($this->params['ONAFTERSAVE_HANDLER'], $ID);
		}
		
		if($this->params['REMOVE_COMPOSITE_CACHE_PART']=='Y' && $isChanges)
		{
			if($arElement = \CIblockElement::GetList(array(), array('ID'=>$ID, 'CHECK_PERMISSIONS' => 'N'), false, false, array('DETAIL_PAGE_URL'))->GetNext())
			{
				$this->ClearCompositeCache($arElement['DETAIL_PAGE_URL']);
			}
		}
	}
	
	public function CheckIdForNewElement(&$arFieldsElement, $isOffer=false)
	{
		if(isset($arFieldsElement['ID']))
		{
			$ID = trim($arFieldsElement['ID']);
			$maxVal = 2147483647;
			$error = false;
			if(!class_exists('\Bitrix\Iblock\ElementTable')) $error = '';
			if($error===false && !preg_match('/^[1-9]\d*$/', $ID)) $error = Loc::getMessage("KDA_IE_ERROR_FORMAT_ID");
			if($error===false && $ID > $maxVal) $error = sprintf(Loc::getMessage("KDA_IE_ERROR_OUTOFRANGE_ID"), $maxVal);
			if($error===false && \Bitrix\Iblock\ElementTable::getList(array('filter'=>array('ID'=>$ID), 'select'=>array('ID')))->Fetch()) $error = Loc::getMessage("KDA_IE_ERROR_EXISTING_ID");
			if($error!==false)
			{
				$this->Err(sprintf(($isOffer ? Loc::getMessage("KDA_IE_NEW_OFFER_WITH_ID") : Loc::getMessage("KDA_IE_NEW_ELEMENT_WITH_ID")), $arFieldsElement['ID'], $error, $this->worksheetNumForSave+1, $this->worksheetCurrentRow));
				return false;
			}
			$arFieldsElement['TMP_ID'] = md5($ID);
			while(\Bitrix\Iblock\ElementTable::getList(array('filter'=>array('TMP_ID'=>$arFieldsElement['TMP_ID']), 'select'=>array('ID')))->Fetch())
			{
				$arFieldsElement['TMP_ID'] = md5($ID.'_'.mt_rand());
			}
		}
		return true;
	}
	
	public function PrepareElementPictures(&$arFieldsElement, $IBLOCK_ID, $arElement, $isOffer=false)
	{
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		if(isset($arFieldsElement['DETAIL_PICTURE']) && isset($iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']) && is_array($iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']))
		{
			$remove = (bool)((!is_array($arFieldsElement['DETAIL_PICTURE']) && trim($arFieldsElement['DETAIL_PICTURE'])=='-') || (is_array($arFieldsElement['DETAIL_PICTURE']) && in_array('-', $arFieldsElement['DETAIL_PICTURE'])));
			if((!$remove && $iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']['FROM_DETAIL']=='Y' && (!$arFieldsElement['PREVIEW_PICTURE'] || $iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']['UPDATE_WITH_DETAIL']=='Y'))
				|| ($remove && $iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']['DELETE_WITH_DETAIL']=='Y' && !$arFieldsElement['PREVIEW_PICTURE']))
			{
				$arFieldsElement['PREVIEW_PICTURE'] = $arFieldsElement['DETAIL_PICTURE'];
			}
		}
		$arPictures = array('PREVIEW_PICTURE', 'DETAIL_PICTURE');
		foreach($arPictures as $picName)
		{
			if($arFieldsElement[$picName])
			{
				$val = $arFieldsElement[$picName];
				$arFileParams = array('FILETYPE'=>'IMAGE', 'PICTURE_PROCESSING'=>isset($iblockFields[$picName]['DEFAULT_VALUE']) ? $iblockFields[$picName]['DEFAULT_VALUE'] : array());
				$arFile = $this->GetFileArray($val, $arFileParams, ($isOffer ? 'OFFER_' : '').'IE_'.$picName, $arElement[$picName]);
				$sep = $this->params['ELEMENT_MULTIPLE_SEPARATOR'];
				if(empty($arFile) && preg_match('/[;,\|\s'.preg_quote($sep, '/').']/s', $val))
				{
					if(strpos($val, $sep)!==false) $arVals = explode($sep, $val);
					else $arVals = preg_split('/[;,\|\s]+/s', $val);
					$arVals = array_diff(array_map('trim', $arVals), array(''));
					$arFile = false;
					while(!$arFile && count($arVals) > 0 && ($newVal = array_shift($arVals)))
					{
						$arFile = $this->GetFileArray($newVal, $arFileParams, ($isOffer ? 'OFFER_' : '').'IE_'.$picName, $arElement[$picName], $val);
					}
				}
				$arFieldsElement[$picName] = $arFile;
			}
			if(isset($arFieldsElement[$picName.'_DESCRIPTION']))
			{
				if(!is_array($arFieldsElement[$picName])) $arFieldsElement[$picName] = array();
				$arFieldsElement[$picName]['description'] = $arFieldsElement[$picName.'_DESCRIPTION'];
				unset($arFieldsElement[$picName.'_DESCRIPTION']);
			}
		}
		
		$arTexts = array('PREVIEW_TEXT', 'DETAIL_TEXT');
		foreach($arTexts as $keyText)
		{
			if($arFieldsElement[$keyText])
			{
				if($this->fieldSettings[($isOffer ? 'OFFER_' : '').'IE_'.$keyText]['LOAD_BY_EXTLINK']=='Y')
				{
					$arFieldsElement[$keyText] = \Bitrix\KdaImportexcel\IUtils::DownloadTextTextByLink($arFieldsElement[$keyText]);
				}
				else
				{
					$textFile = $_SERVER["DOCUMENT_ROOT"].$arFieldsElement[$keyText];
					if(file_exists($textFile) && is_file($textFile) && is_readable($textFile))
					{
						$arFieldsElement[$keyText] = file_get_contents($textFile);
					}
				}
			}
		}
		
		if(array_key_exists('SORT', $arFieldsElement))
		{
			$arFieldsElement['SORT'] = (int)$this->GetFloatVal($arFieldsElement['SORT']);
		}
	}
	
	public function SaveStatusImport($end = false)
	{
		if(($time = time())==$this->timeSaveResult) return;
		$this->timeSaveResult = $time;
		if($this->procfile)
		{
			$writeParams = $this->GetStepParams();
			unset($writeParams['currentelement']);
			unset($writeParams['currentelementitem']);
			$writeParams['action'] = ($end ? 'finish' : 'continue');
			file_put_contents($this->procfile, \KdaIE\Utils::PhpToJSObject($writeParams));
		}
	}
	
	public function PrepareSectionPictures(&$arFields, $arSection=array())
	{
		$arPictures = array('PICTURE', 'DETAIL_PICTURE');
		foreach($arPictures as $picName)
		{
			if($arFields[$picName])
			{
				$val = $arFields[$picName];
				if(is_array($val)) $val = current($val);
				$arFile = $this->GetFileArray($val, array('FILETYPE'=>'IMAGE'), '', $arSection[$picName]);
				if(empty($arFile) && strpos($val, $this->params['ELEMENT_MULTIPLE_SEPARATOR'])!==false)
				{
					$arVals = array_diff(array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val)), array(''));
					if(count($arVals) > 0 && ($val = current($arVals)))
					{
						$arFile = $this->GetFileArray($val, array('FILETYPE'=>'IMAGE'), '', $arSection[$picName]);
					}
				}
				$arFields[$picName] = $arFile;
			}
			else unset($arFields[$picName]);
		}
	}
	
	public function SetSkuMode($isSku, $ID=0, $IBLOCK_ID=0)
	{
		if($isSku)
		{
			$this->conv->SetSkuMode(true, $this->GetCachedOfferIblock($IBLOCK_ID), $ID);
			$this->offerParentId = $ID;
		}
		else
		{
			$this->conv->SetSkuMode(false);
			$this->offerParentId = null;
		}
	}
	
	public function SaveSKUWithGenerate($ID, $NAME, $IBLOCK_ID, $arItem)
	{
		$ret = false;
		$this->SetSkuMode(true, $ID, $IBLOCK_ID);
		$isChanges = false;
		if(!empty($this->fieldsForSkuGen))
		{
			$filedList = $this->params['FIELDS_LIST'][$this->worksheetNumForSave];
			$arItemParams = array();
			$arGenFields = array();
			foreach($this->fieldsForSkuGen as $key)
			{
				$conversions = (isset($this->fieldSettingsExtra[$key]) ? $this->fieldSettingsExtra[$key]['CONVERSION'] : $this->fieldSettings[$filedList[$key]]['CONVERSION']);
				if(strpos($key, '_') > 0 && !isset($arItem[$key]))
				{
					$pkey = substr($key, 0 , strpos($key, '_'));
					if(array_key_exists('~~'.$pkey, $arItem)) $arItem[$key] = $arItem['~~'.$pkey];
					elseif(array_key_exists($pkey, $arItem)) $arItem[$key] = $arItem[$pkey];
				}
				$arItem['~~'.$key] = $arItem[$key];
				$arItem[$key] = $this->ApplyConversions($arItem[$key], $conversions, $arItem, array('PARENT_ID'=>$ID));
				$arItemParams[$key] = array_diff(array_map(array($this, 'Trim'), explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arItem[$key])), array(''));
				if(count($arItemParams[$key])==0) $arItemParams[$key] = array('');
				$convertedFields[] = $key;
				$arGenFields[] = $filedList[$key];
			}
			$arItemSKUParams = array();
			$this->GenerateSKUParamsRecursion($arItemSKUParams, $arItemParams);
			
			$extraFields = array();
			foreach($filedList as $key=>$field)
			{
				if(in_array((string)$key, $this->fieldsForSkuGen)) continue;
				$conversions = $this->fieldSettings[$filedList[$key]]['CONVERSION'];
				$valOrig = (isset($arItem[$key]) ? $arItem[$key] : $arItem[current(explode('_', $key))]);
				$val = $this->ApplyConversions($valOrig, $conversions, $arItem, array('PARENT_ID'=>$ID));
				if((preg_match('/^OFFER_(IE_PREVIEW_PICTURE|IE_DETAIL_PICTURE|IE_ACTIVE|IE_SORT|ICAT_QUANTITY|ICAT_PURCHASING_PRICE|ICAT_PRICE\d+_PRICE|ICAT_STORE\d+_AMOUNT|ICAT_WEIGHT|ICAT_DISCOUNT_.*)$/', $field) || in_array($key, $this->fieldsBindToGenSku) || in_array($field, $arGenFields)) && (is_array($val) || strpos(preg_replace('/\{[^\}]*\}/', '', $val), $this->params['ELEMENT_MULTIPLE_SEPARATOR'])!==false))
				{
					$arItem['~~'.$key] = $valOrig;
					$arItem[$key] = $val;	
					$extraFields[$key] = (is_array($val) ? $val : array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val)));
					$convertedFields[] = $key;
				}
			}

			$firstKey = -1;
			if(isset($this->stepparams['lastoffergenkey']))
			{
				$firstKey = (int)$this->stepparams['lastoffergenkey'];
				unset($this->stepparams['lastoffergenkey']);
			}
			$lastKey = count($arItemSKUParams) - 1;
			foreach($arItemSKUParams as $k=>$v)
			{
				if($k <= $firstKey) continue;
				$arSubItem = $arItem;
				foreach($v as $k2=>$v2) $arSubItem[$k2] = $v2;
				foreach($extraFields as $k2=>$v2)
				{
					if(isset($extraFields[$k2][$k])) $arSubItem[$k2] = $extraFields[$k2][$k];
					else $arSubItem[$k2] = current($extraFields[$k2]);
				}
				$this->currentOfferGenKey = $k; //use in conversions
				$ret = (bool)($this->SaveSKU($ID, $NAME, $IBLOCK_ID, $arSubItem, $convertedFields) || $ret);
				$isChanges = (bool)($isChanges || $this->IsChangedElement());
				$this->SaveStatusImport();
				if($k < $lastKey && $this->CheckTimeEnding())
				{
					$this->stepparams['lastoffergenkey'] = $k;
					$this->UpdateWorksheetCurrentRow($this->worksheetCurrentRow - 1);
					return 'timesup';
				}
			}
		}
		else
		{
			$ret = $this->SaveSKU($ID, $NAME, $IBLOCK_ID, $arItem);
			$isChanges = (bool)($isChanges || $this->IsChangedElement());
		}
		if($ret && $isChanges)
		{
			CIBlockElement::UpdateSearch($ID, true);
			/*\Bitrix\KdaImportexcel\DataManager\IblockElementTable::updateElementIndex($IBLOCK_ID, $ID);*/
		}
		$this->SetSkuMode(false);
		return $ret;
	}
	
	public function GenerateSKUParamsRecursion(&$arItemSKUParams, $arItemParams, $arSubItem = array())
	{
		if(!empty($arItemParams))
		{
			$arKey = array_keys($arItemParams);
			$key = $arKey[0];
			$arCurParams = $arItemParams[$key];
			unset($arItemParams[$key]);
			foreach($arCurParams as $k=>$v)
			{
				$arSubItem[$key] = $v;
				$arSubItem['~'.$key] = $v;
				$this->GenerateSKUParamsRecursion($arItemSKUParams, $arItemParams, $arSubItem);
			}
		}
		else
		{
			$arItemSKUParams[] = $arSubItem;
		}
	}
	
	public function SaveSKU($ID, $NAME, $IBLOCK_ID, $arItem, $convertedFields=array())
	{
		//\Bitrix\Catalog\Product\Sku::disableUpdateAvailable();
		if(!($arOfferIblock = $this->GetCachedOfferIblock($IBLOCK_ID))) return false;
		$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
		$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
		
		$filedList = $this->params['FIELDS_LIST'][$this->worksheetNumForSave];
		$propsDef = $this->GetIblockProperties($OFFERS_IBLOCK_ID);
		$iblockFields = $this->GetIblockFields($OFFERS_IBLOCK_ID);
		$this->currentItemValues = $arItem;
		
		$arFieldsElement = array();
		$arFieldsElementOrig = array();
		$arFieldsPrices = array();
		$arFieldsProduct = array();
		$arFieldsProductStores = array();
		$arFieldsProductDiscount = array();
		if($ID > 0)
		{
			$arFieldsProps = array($OFFERS_PROPERTY_ID => $ID);
			$arFieldsPropsOrig = array($OFFERS_PROPERTY_ID => $ID);
		}
		else
		{
			$arFieldsProps = array();
			$arFieldsPropsOrig = array();
		}
		$arFieldsIpropTemp = array();
		$arFieldsForSkuGen = array_map('strval', $this->fieldsForSkuGen);
		foreach($filedList as $key=>$field)
		{
			if(strpos($field, 'OFFER_')!==0) continue;
			$conversions = (isset($this->fieldSettingsExtra[$key]) ? $this->fieldSettingsExtra[$key]['CONVERSION'] : $this->fieldSettings[$field]['CONVERSION']);
			$copyCell = (bool)($this->fieldSettings[$field]['COPY_CELL_ON_OFFERS']=='Y');
			$field = substr($field, 6);
			
			$k = $key;
			if(strpos($k, '_')!==false && !isset($arItem[$k])) $k = substr($k, 0, strpos($k, '_'));
			$value = $arItem[$k];
			if($this->fieldSettings[$field]['NOT_TRIM']=='Y') $value = $arItem['~'.$k];
			if($this->fieldSettings[$field]['EXCEL_STYLES_TO_HTML']=='Y') $value = $arItem['html_'.$k];
			$origValue = $arItem['~'.$k];
			if(!$value && $copyCell && $this->stepparams['currentelementitem'])
			{
				$value = $this->stepparams['currentelementitem'][$k];
				$origValue = $this->stepparams['currentelementitem']['~'.$k];
			}

			//if(!empty($conversions) && !in_array($key, $arFieldsForSkuGen))
			if(!empty($conversions) && !in_array($key, $convertedFields))
			{
				$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$k, 'NAME'=>$field, 'PARENT_ID'=>$ID), $iblockFields);
				$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$k, 'NAME'=>$field, 'PARENT_ID'=>$ID), $iblockFields);
				if($value===false) continue;
			}
			
			if(strpos($field, 'IE_')===0)
			{
				$fieldKey = substr($field, 3);
				if($this->params['ELEMENT_LOAD_IMAGES']=='Y' && in_array($fieldKey, array('DETAIL_PICTURE', 'PREVIEW_PICTURE')) && isset($arItem['i~'.$k]))
				{
						$arFieldsElement[$fieldKey] = $arItem['i~'.$k];
						$arFieldsElementOrig[$fieldKey] = $arItem['i~'.$k];
				}
				else
				{
					if(strpos($fieldKey, '|')!==false)
					{
						list($fieldKey, $adata) = explode('|', $fieldKey);
						$adata = explode('=', $adata);
						if(count($adata) > 1)
						{
							$arFieldsElement[$adata[0]] = $adata[1];
						}
					}
					$arFieldsElement[$fieldKey] = $value;
					$arFieldsElementOrig[$fieldKey] = $origValue;
				}
			}
			elseif(strpos($field, 'ICAT_PRICE')===0)
			{
				$val = $value;
				if(substr($field, -6)=='_PRICE')
				{
					if(!in_array($val, array('', '-')))
					{
						//$val = $this->GetFloatVal($val);
						$val = $this->ApplyMargins($val, $this->fieldSettingsExtra[$key]);
					}
				}
				elseif(substr($field, -6)=='_EXTRA')
				{
					$val = $this->GetFloatVal($val, 0, true);
				}
				
				$arPrice = explode('_', substr($field, 10), 2);
				$pkey = $arPrice[1];
				if($pkey=='PRICE' && $this->fieldSettingsExtra[$key]['PRICE_USE_EXT']=='Y')
				{
					$pkey = $pkey.'|QUANTITY_FROM='.$this->CalcFloatValuePhp($this->fieldSettingsExtra[$key]['PRICE_QUANTITY_FROM']).'|QUANTITY_TO='.$this->CalcFloatValuePhp($this->fieldSettingsExtra[$key]['PRICE_QUANTITY_TO']);
				}
				$arFieldsPrices[$arPrice[0]][$pkey] = $val;
			}
			elseif(strpos($field, 'ICAT_LIST_STORES')===0)
			{
				$this->GetStoreAmountList($arFieldsProductStores, $this->fieldSettingsExtra[$key], $value);
			}
			elseif(strpos($field, 'ICAT_STORE')===0)
			{
				$arStore = explode('_', substr($field, 10), 2);
				$arFieldsProductStores[$arStore[0]][$arStore[1]] = $value;
			}
			elseif(strpos($field, 'ICAT_DISCOUNT_')===0)
			{
				if(strpos($field, 'ICAT_DISCOUNT_VALUE')===0 && in_array(trim($value), array('', '0')) && isset($arFieldsProductDiscount['VALUE'])) continue;
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
					if(count($adata) > 1)
					{
						$arFieldsProductDiscount[$adata[0]] = $adata[1];
					}
				}
				$field = substr($field, 14);
				if($field=='VALUE' && isset($this->fieldSettingsExtra[$key]))
				{
					$fse = $this->fieldSettingsExtra[$key];
					if(!empty($fse['CATALOG_GROUP_IDS']))
					{
						$arFieldsProductDiscount['CATALOG_GROUP_IDS'] = $fse['CATALOG_GROUP_IDS'];
					}
					if(is_array($fse['SITE_IDS']) && !empty($fse['SITE_IDS']))
					{
						foreach($fse['SITE_IDS'] as $siteId)
						{
							$arFieldsProductDiscount['LID_VALUES'][$siteId] = array('VALUE'=>$value);
							if(isset($arFieldsProductDiscount['VALUE_TYPE'])) $arFieldsProductDiscount['LID_VALUES'][$siteId]['VALUE_TYPE'] = $arFieldsProductDiscount['VALUE_TYPE'];
						}
					}
				}
				$arFieldsProductDiscount[$field] = $value;
			}
			elseif(strpos($field, 'ICAT_')===0)
			{
				$val = $value;
				if($field=='ICAT_PURCHASING_PRICE')
				{
					if($val=='') continue;
					$val = $this->GetFloatVal($val);
				}
				$arFieldsProduct[substr($field, 5)] = $val;
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				$fieldName = substr($field, 7);
				if(substr($fieldName, -12)=='_DESCRIPTION') $currentPropDef = $propsDef[substr($fieldName, 0, -12)];
				else $currentPropDef = $propsDef[$fieldName];
				$this->GetPropField($arFieldsProps, $arFieldsPropsOrig, $this->fieldSettingsExtra[$key], $currentPropDef, $fieldName, $value, $origValue);
			}
			elseif(strpos($field, 'IP_LIST_PROPS')===0)
			{
				$this->GetPropList($arFieldsProps, $arFieldsPropsOrig, $this->fieldSettingsExtra[$key], $OFFERS_IBLOCK_ID, $value, array($OFFERS_PROPERTY_ID));
			}
			elseif(strpos($field, 'IPROP_TEMP_')===0)
			{
				$fieldName = substr($field, 11);
				$arFieldsIpropTemp[$fieldName] = $value;
			}
		}

		$arUid = $this->GetFilterUids($arFieldsElement, $arFieldsElementOrig, $arFieldsProps, $arFieldsPropsOrig, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID, $ID);

		$emptyFields = $notEmptyFields = array();
		foreach($arUid as $k=>$v)
		{
			if((is_array($v['valUid']) && count(array_diff($v['valUid'], array('')))>0)
				|| (!is_array($v['valUid']) && strlen(trim($v['valUid']))>0)) $notEmptyFields[] = $v['uid'];
			else $emptyFields[] = $v['uid'];
		}
		
		if(($ID > 0 && count($notEmptyFields) < 2) || ($ID <= 0 && (count($notEmptyFields) < 1 || count($emptyFields) > 0)))
		{
			return false;
		}
		
		if(array_key_exists($OFFERS_PROPERTY_ID, $arFieldsProps)) unset($arFieldsProps[$OFFERS_PROPERTY_ID]);
		$arDates = array('ACTIVE_FROM', 'ACTIVE_TO', 'DATE_CREATE');
		foreach($arDates as $keyDate)
		{
			if(isset($arFieldsElement[$keyDate]) && strlen($arFieldsElement[$keyDate]) > 0)
			{
				$arFieldsElement[$keyDate] = $this->GetDateVal($arFieldsElement[$keyDate]);
			}
		}
		
		if(isset($arFieldsElement['ACTIVE']))
		{
			$arFieldsElement['ACTIVE'] = $this->GetBoolValue($arFieldsElement['ACTIVE']);
		}
		elseif($this->params['ELEMENT_LOADING_ACTIVATE']=='Y')
		{
			$arFieldsElement['ACTIVE'] = 'Y';
		}
		
		$arKeys = array_merge(array('ID', 'NAME', 'IBLOCK_SECTION_ID', 'MODIFIED_BY', 'PREVIEW_PICTURE'), array_keys($arFieldsElement));
		if(!$ID) $arKeys[] = 'PROPERTY_'.$OFFERS_PROPERTY_ID;
		
		$arFilter = array('IBLOCK_ID'=>$OFFERS_IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		foreach($arUid as $v)
		{
			if(!$v['substring'])
			{
				if(is_array($v['valUid'])) $arSubfilter = array_map(array($this, 'Trim'), $v['valUid']);
				else 
				{
					$arSubfilter = array($this->Trim($v['valUid']));
					if($this->Trim($v['valUid']) != $v['valUid2'])
					{
						$arSubfilter[] = $this->Trim($v['valUid2']);
						if(strlen($v['valUid2']) != strlen($this->Trim($v['valUid2'])))
						{
							$arSubfilter[] = $v['valUid2'];
						}
					}
					if(strlen($v['valUid']) != strlen($this->Trim($v['valUid'])))
					{
						$arSubfilter[] = $v['valUid'];
					}
				}
				
				if(count($arSubfilter) == 1)
				{
					$arSubfilter = $arSubfilter[0];
				}
				$arFilter['='.$v['uid']] = $arSubfilter;
			}
			else
			{
				if(is_array($v['valUid'])) $v['valUid'] = array_map(array($this, 'Trim'), $v['valUid']);
				else $v['valUid'] = $this->Trim($v['valUid']);
				if($v['substring']=='B') $arFilter[$v['uid']] = (is_array($v['valUid']) ? array_map(array('CKDAImportUtils', 'GetFilterBeginWith'), $v['valUid']) : $v['valUid'].'%');
				elseif($v['substring']=='E') $arFilter[$v['uid']] = (is_array($v['valUid']) ? array_map(array('CKDAImportUtils', 'GetFilterEndOn'), $v['valUid']) : '%'.$v['valUid']);
				else $arFilter['%'.$v['uid']] = $v['valUid'];
			}
		}
		
		if(!empty($arFieldsIpropTemp))
		{
			$arFieldsElement['IPROPERTY_TEMPLATES'] = $arFieldsIpropTemp;
		}
		$arProductIds = array();
		if($ID) $arProductIds[] = $ID;

		$elemName = '';
		$duplicate = false;
		//$dbRes = CIblockElement::GetList(array(), $arFilter, false, false, $arKeys);
		$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFilter, $arKeys);
		while($arElement = $dbRes->Fetch())
		{
			$updated = false;
			$eid = $ID;
			if(!$eid) $eid = $arElement['PROPERTY_'.$OFFERS_PROPERTY_ID.'_VALUE'];
			$OFFER_ID = $arElement['ID'];
			$arFieldsProps2 = $arFieldsProps;
			$arFieldsElement2 = $arFieldsElement;
			$arFieldsProduct2 = $arFieldsProduct;
			$arFieldsPrices2 = $arFieldsPrices;
			$arFieldsProductStores2 = $arFieldsProductStores;
			$arFieldsProductDiscount2 = $arFieldsProductDiscount;
			if($this->conv->SetElementId($OFFER_ID, $duplicate)
				&& $this->conv->UpdateProperties($arFieldsProps2, $OFFER_ID)!==false
				&& $this->conv->UpdateElementFields($arFieldsElement2, $OFFER_ID)!==false
				&& $this->conv->UpdateProduct($arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $OFFER_ID)!==false
				&& $this->conv->UpdateDiscountFields($arFieldsProductDiscount2, $OFFER_ID)!==false
				&& $this->conv->SetElementId(0))
			{
				$this->BeforeElementSave($OFFER_ID, 'update');
				if($this->params['ONLY_CREATE_MODE_OFFER']!='Y')
				{
					$this->UnsetUidFields($arFieldsElement2, $arFieldsProps2, $this->params['CURRENT_ELEMENT_UID_SKU']);
					if(!empty($this->fieldOnlyNewOffer))
					{
						$this->UnsetExcessFields($this->fieldOnlyNewOffer, $arFieldsElement2, $arFieldsProps2, $arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $arFieldsProductDiscount2);
					}
					
					$this->RemoveProperties($OFFER_ID, $OFFERS_IBLOCK_ID, true);
					$this->SaveProperties($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProps2);
					$this->SaveProduct($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $eid);
					$this->AfterSaveProduct($arFieldsElement2, $OFFER_ID, $OFFERS_IBLOCK_ID, true);
					
					if($this->CheckRequiredProps($arFieldsProps2, $OFFERS_IBLOCK_ID, $OFFER_ID) && $this->UpdateElement($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsElement2, $arElement, true))
					{
						//$this->SetTimeBegin($OFFER_ID);
					}
					else
					{
						$this->Err(sprintf(Loc::getMessage("KDA_IE_UPDATE_OFFER_ERROR"), $this->GetLastError(), $this->worksheetNumForSave+1, $this->worksheetCurrentRow));
					}
						
					$elemName = $arElement['NAME'];
					$this->SaveDiscount($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProductDiscount2, $elemName, true);
					$updated = true;
				}
			}
			if($this->SaveElementId($OFFER_ID, 'O'))
			{
				if($updated)
				{
					$this->stepparams['sku_updated_line']++;
					if($this->IsChangedElement()) $this->stepparams['sku_changed_line']++;
				}
				if(!$ID && $eid)
				{
					$arProductIds[] = $eid;
					if($this->SaveElementId($eid)) $this->lastOffElemId = $eid;
				}
			}
			$duplicate = true;
		}
		if($elemName && !$arFieldsElement['NAME']) $arFieldsElement['NAME'] = $elemName;
		
		if(\Bitrix\KdaImportexcel\DataManager\IblockElementTable::SelectedRowsCountComp($dbRes)==0 && $ID && ($this->params['SEARCH_OFFERS_WO_PRODUCTS']!='Y' || $this->params['CREATE_NEW_OFFERS']=='Y'))
		{
			if($this->params['ONLY_UPDATE_MODE_OFFER']!='Y' || $this->params['CREATE_NEW_OFFERS']=='Y')
			{
				//$this->UnsetUidFields($arFieldsElement, $arFieldsProps, $this->params['CURRENT_ELEMENT_UID_SKU'], true);
				if(!$this->CheckIdForNewElement($arFieldsElement, true)) return false;

				if(strlen($arFieldsElement['NAME'])==0)
				{
					$arFieldsElement['NAME'] = $NAME;
				}
				if($this->params['ELEMENT_NEW_DEACTIVATE']=='Y' && !isset($arFieldsElement['ACTIVE']))
				{
					$arFieldsElement['ACTIVE'] = 'N';
				}
				elseif(!$arFieldsElement['ACTIVE'])
				{
					$arFieldsElement['ACTIVE'] = 'Y';
				}
				$arFieldsElement['IBLOCK_ID'] = $OFFERS_IBLOCK_ID;
				$this->GetDefaultElementFields($arFieldsElement, $iblockFields);

				if($this->CheckRequiredProps($arFieldsProps, $OFFERS_IBLOCK_ID) && ($OFFER_ID = $this->AddElement(array_merge($arFieldsElement, array('PROPERTY_VALUES'=>array($OFFERS_PROPERTY_ID => $ID))), true)))
				{
					$this->AddTagIblock($OFFERS_IBLOCK_ID);
					$this->BeforeElementSave($OFFER_ID, 'add');
					$this->logger->AddElementChanges('IE_', $arFieldsElement);
					//$this->SetTimeBegin($OFFER_ID);
					$this->SaveProperties($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProps, array(), true, $arFieldsElement);
					$this->PrepareProductAdd($arFieldsProduct, $OFFER_ID, $OFFERS_IBLOCK_ID);
					$this->SaveProduct($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProduct, $arFieldsPrices, $arFieldsProductStores, $ID);
					$this->AfterSaveProduct($arFieldsElement, $OFFER_ID, $OFFERS_IBLOCK_ID);
					$this->SaveDiscount($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProductDiscount, $arFieldsElement['NAME'], true);
					$this->AfterElementAdd($OFFERS_IBLOCK_ID, $OFFER_ID);
					if($this->SaveElementId($OFFER_ID, 'O')) $this->stepparams['sku_added_line']++;
				}
				else
				{
					$this->Err(sprintf(Loc::getMessage("KDA_IE_ADD_OFFER_ERROR"), $this->GetLastError(), $this->worksheetNumForSave+1, $this->worksheetCurrentRow));
					return false;
				}
			}
			else
			{
				$this->logger->AddElementMassChanges($arFieldsElement, $arFieldsProps, $arFieldsProduct, $arFieldsProductStores, $arFieldsPrices);
				$this->logger->SaveElementNotFound($arFilter, $this->worksheetCurrentRow);
			}
		}

		if($OFFER_ID)
		{
			if($this->params['ONAFTERSAVE_HANDLER'])
			{
				$this->ExecuteOnAfterSaveHandler($this->params['ONAFTERSAVE_HANDLER'], $OFFER_ID);
			}
		}
		
		/*Update product*/
		if($OFFER_ID && ($this->params['ELEMENT_NO_QUANTITY_DEACTIVATE']=='Y' || $this->params['ELEMENT_NO_PRICE_DEACTIVATE']=='Y' || ($this->params['ELEMENT_LOADING_ACTIVATE']=='Y' && !$ID)) && class_exists('\Bitrix\Catalog\ProductTable') && class_exists('\Bitrix\Catalog\PriceTable'))
		{
			foreach($arProductIds as $prodId)
			{
				$arOfferIds = array();
				$offersActive = false;
				$dbRes = CIblockElement::GetList(array(), array(
					'IBLOCK_ID' => $OFFERS_IBLOCK_ID, 
					'PROPERTY_'.$OFFERS_PROPERTY_ID => $prodId,
					'CHECK_PERMISSIONS' => 'N'), 
					false, false, array('ID', 'ACTIVE'));
				while($arr = $dbRes->Fetch())
				{
					$arOfferIds[] = $arr['ID'];
					$offersActive = (bool)($offersActive || ($arr['ACTIVE']=='Y'));
				}
				
				if(!empty($arOfferIds))
				{
					$active = false;
					if(!$offersActive) $active = 'N';
					else
					{
						if($this->params['ELEMENT_LOADING_ACTIVATE']=='Y') $active = 'Y';
						if($this->params['ELEMENT_NO_QUANTITY_DEACTIVATE']=='Y')
						{
							$existQuantity = \Bitrix\Catalog\ProductTable::getList(array(
								'select' => array('ID', 'QUANTITY'),
								'filter' => array('@ID' => $arOfferIds, '>QUANTITY' => '0'),
								'limit' => 1
							))->fetch();
							if(!$existQuantity)  $active = 'N';
						}
						if($this->params['ELEMENT_NO_PRICE_DEACTIVATE']=='Y')
						{
							$existPrice = \Bitrix\Catalog\PriceTable::getList(array(
								'select' => array('ID', 'PRICE'),
								'filter' => array('@PRODUCT_ID' => $arOfferIds, '>PRICE' => '0'),
								'limit' => 1
							))->fetch();
							if(!$existPrice)  $active = 'N';
						}
					}
					if($active!==false)
					{
						$arElem = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp(array('ID'=>$prodId, 'CHECK_PERMISSIONS' => 'N'), array('ACTIVE'))->Fetch();
						if($arElem['ACTIVE']!=$active)
						{
							$el = new CIblockElement();
							$el->Update($prodId, array('ACTIVE'=>$active, 'MODIFIED_BY' => $this->GetCurUserID()), false, true, true);
							$this->AddTagIblock($IBLOCK_ID);
						}
					}
				}
			}
		}
		if($ID && $OFFER_ID && defined('\Bitrix\Catalog\ProductTable::TYPE_SKU'))
		{
			$this->SaveProduct($ID, $IBLOCK_ID, array('TYPE'=>\Bitrix\Catalog\ProductTable::TYPE_SKU), array(), array());
		}
		/*/Update product*/
		
		return (bool)($OFFER_ID && $OFFER_ID > 0);
	}
	
	public function GetFilterUids($arFieldsElement, $arFieldsElementOrig, $arFieldsProps, $arFieldsPropsOrig, $IBLOCK_ID, $offerPropId=false, $parentId=0)
	{
		$arFieldsDef = $this->fl->GetFields($IBLOCK_ID);
		$propsDef = $this->GetIblockProperties($IBLOCK_ID);
		$currentUid = $this->params[$offerPropId===false ? 'CURRENT_ELEMENT_UID' : 'CURRENT_ELEMENT_UID_SKU'];
		if(!is_array($currentUid)) $currentUid = array($currentUid);
		if($offerPropId!==false && $parentId > 0 && !in_array('OFFER_IP_PROP'.$offerPropId, $currentUid)) $currentUid[] = 'OFFER_IP_PROP'.$offerPropId;
		
		$arUid = array();
		foreach($currentUid as $tuid)
		{
			$fs = $this->fieldSettings[$tuid];
			if($offerPropId!==false) $tuid = substr($tuid, 6);
			$uid = $valUid = $valUid2 = $nameUid = '';
			$canSubstring = true;
			if(strpos($tuid, 'IE_')===0)
			{
				$nameUid = $arFieldsDef['element']['items'][$tuid];
				$uid = substr($tuid, 3);
				if(strpos($uid, '|')!==false) $uid = current(explode('|', $uid));
				$valUid = $arFieldsElementOrig[$uid];
				$valUid2 = $arFieldsElement[$uid];
				
				if($uid == 'ACTIVE_FROM' || $uid == 'ACTIVE_TO')
				{
					$uid = 'DATE_'.$uid;
					$valUid = $this->GetDateVal($valUid);
					$valUid2 = $this->GetDateVal($valUid2);
				}
			}
			elseif(strpos($tuid, 'IP_PROP')===0)
			{
				$nameUid = $arFieldsDef['prop']['items'][$tuid];
				$uid = substr($tuid, 7);
				$valUid = $arFieldsPropsOrig[$uid];
				$valUid2 = $arFieldsProps[$uid];
				$p = $propsDef[$uid];
				if($p['MULTIPLE']=='Y')
				{
					if(!is_array($valUid))
					{
						$valUid = $this->GetMultipleProperty($valUid, $uid);
						$valUid2 = $this->GetMultipleProperty($valUid2, $uid);
					}
					elseif(array_key_exists('VALUE', $valUid) && !is_array($valUid['VALUE']))
					{
						$valUid['VALUE'] = $this->GetMultipleProperty($valUid['VALUE'], $uid);
						$valUid2['VALUE'] = $this->GetMultipleProperty($valUid2['VALUE'], $uid);
					}
				}
				if($p['PROPERTY_TYPE']=='L')
				{
					$uid = 'PROPERTY_'.$uid.'_VALUE';
					if(is_array($valUid))
					{
						if(array_key_exists('VALUE', $valUid)) $valUid = $valUid['VALUE'];
						elseif(($lval = $this->GetListPropertyValue($p, $valUid))!==false)
						{
							$valUid = $valUid2 = $lval;
							$uid = str_replace('_VALUE', '', $uid);
						}
						if(is_array($valUid2) && array_key_exists('VALUE', $valUid2)) $valUid2 = $valUid2['VALUE'];
					}					
				}
				elseif($p['PROPERTY_TYPE']=='N' && ((!is_array($valUid) && !is_numeric($this->Trim($valUid))) || (is_array($valUid) && count(preg_grep('/^\s*\d+(\.\d*)?\s*$/', $valUid))==0)))
				{
					$valUid = $valUid2 = '';
				}
				else
				{
					if($p['PROPERTY_TYPE']=='S')
					{
						if($p['USER_TYPE']=='directory')
						{
							$valUid = $this->GetHighloadBlockValue($p, $valUid);
							$valUid2 = $this->GetHighloadBlockValue($p, $valUid2);
							$canSubstring = false;
						}
						elseif($p['USER_TYPE']=='Date')
						{
							$valUid = $this->GetDateValToDB($valUid, 'PART');
							$valUid2 = $this->GetDateValToDB($valUid2, 'PART');
						}
						elseif($p['USER_TYPE']=='DateTime')
						{
							$valUid = $this->GetDateValToDB($valUid);
							$valUid2 = $this->GetDateValToDB($valUid2);
						}
						elseif($p['USER_TYPE']=='HTML')
						{
							$valUid = array($valUid, serialize(array('TEXT'=>$valUid, 'TYPE'=>'TEXT')), serialize(array('TEXT'=>$valUid, 'TYPE'=>'HTML')));
							$valUid2 = array($valUid2, serialize(array('TEXT'=>$valUid2, 'TYPE'=>'TEXT')), serialize(array('TEXT'=>$valUid2, 'TYPE'=>'HTML')));
						}
					}
					elseif($p['PROPERTY_TYPE']=='E' && $uid!=$offerPropId)
					{
						$valUid = $this->GetIblockElementValue($p, $valUid, $fs, true, true, true);
						$valUid2 = $this->GetIblockElementValue($p, $valUid2, $fs, true, true, true);
						if($valUid===false) $valUid = '';
						if($valUid2===false) $valUid2 = '';
						$canSubstring = false;
					}
					$uid = 'PROPERTY_'.$uid;
				}
			}
			if($uid)
			{
				$substringMode = $fs['UID_SEARCH_SUBSTRING'];
				if(!in_array($substringMode, array('Y', 'B', 'E'))) $substringMode = '';
				$arUid[] = array(
					'uid' => $uid,
					'nameUid' => $nameUid,
					'valUid' => $valUid,
					'valUid2' => $valUid2,
					'substring' => ($substringMode && $canSubstring ? $substringMode : '')
				);
			}
		}
		return $arUid;
	}
	
	public function GetElementSections($ID, $SECTION_ID, $unique=true)
	{
		$arSections = array();
		$main = 0;
		if($SECTION_ID > 0) $main = $SECTION_ID;
		$dbRes = \CIBlockElement::GetElementGroups($ID, true, array('ID'));
		if($unique)
		{
			if($SECTION_ID > 0) $arSections[] = $SECTION_ID;
			while($arr = $dbRes->Fetch())
			{
				if(!in_array($arr['ID'], $arSections)) $arSections[] = $arr['ID'];
			}
		}
		else
		{
			while($arr = $dbRes->Fetch())
			{
				if($arr['ID']==$main) array_unshift($arSections, $arr['ID']);
				else $arSections[] = $arr['ID'];
			}
		}
		return $arSections;
	}
	
	public function UnsetUidFields(&$arFieldsElement, &$arFieldsProps, $arUids, $saveVal=false)
	{
		$arFilter = array();
		foreach($arUids as $field)
		{
			if(strpos($field, 'OFFER_')===0) $field = substr($field, 6);
			if(strpos($field, 'IE_')===0)
			{
				$fieldKey = substr($field, 3);
				if(isset($arFieldsElement[$fieldKey]))
				{
					$arFilter[$field] = $arFieldsElement[$fieldKey];
					if(is_array($arFieldsElement[$fieldKey]))
					{
						if($saveVal)
						{
							$arFieldsElement[$fieldKey] = array_diff($arFieldsElement[$fieldKey], array(''));
							if(count($arFieldsElement[$fieldKey]) > 0) $arFieldsElement[$fieldKey] = end($arFieldsElement[$fieldKey]);
							else $arFieldsElement[$fieldKey] = '';
						}
						else unset($arFieldsElement[$fieldKey]);
					}
					elseif(!$saveVal)
					{
						unset($arFieldsElement[$fieldKey]);
					}
				}
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				$fieldKey = substr($field, 7);
				if(isset($arFieldsProps[$fieldKey]))
				{
					$arFilter[$field] = $arFieldsProps[$fieldKey];
					if(is_array($arFieldsProps[$fieldKey]))
					{
						if($saveVal)
						{
							$arFieldsProps[$fieldKey] = array_diff($arFieldsProps[$fieldKey], array(''));
							if(array_key_exists('PRIMARY', $arFieldsProps[$fieldKey]) || count(preg_grep('/\D/', array_keys($arFieldsProps[$fieldKey]))) > 0){}
							elseif(count($arFieldsProps[$fieldKey]) > 0) $arFieldsProps[$fieldKey] = end($arFieldsProps[$fieldKey]);
							else $arFieldsProps[$fieldKey] = '';
						}
						else unset($arFieldsProps[$fieldKey]);
					}
					elseif(!$saveVal)
					{
						unset($arFieldsProps[$fieldKey]);
					}
				}
			}
		}
		$this->logger->AddElementData('FILTER_', $arFilter);
	}
	
	public function UnsetExcessFields($fieldsList, &$arFieldsElement, &$arFieldsProps, &$arFieldsProduct, &$arFieldsPrices, &$arFieldsProductStores, &$arFieldsProductDiscount)
	{
		foreach($fieldsList as $field)
		{
			if(strpos($field, 'IE_')===0)
			{
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
					if(count($adata) > 1)
					{
						unset($arFieldsElement[$adata[0]]);
					}
				}
				unset($arFieldsElement[substr($field, 3)]);
			}
			elseif(strpos($field, 'ISECT')===0)
			{
				unset($arFieldsElement['IBLOCK_SECTION']);
			}
			elseif(strpos($field, 'ICAT_PRICE')===0)
			{
				$arPrice = explode('_', substr($field, 10), 2);
				unset($arFieldsPrices[$arPrice[0]][$arPrice[1]]);
				if(empty($arFieldsPrices[$arPrice[0]])) unset($arFieldsPrices[$arPrice[0]]);
			}
			elseif(strpos($field, 'ICAT_STORE')===0)
			{
				$arStore = explode('_', substr($field, 10), 2);
				unset($arFieldsProductStores[$arStore[0]][$arStore[1]]);
				if(empty($arFieldsProductStores[$arStore[0]])) unset($arFieldsProductStores[$arStore[0]]);
			}
			elseif(strpos($field, 'ICAT_DISCOUNT_')===0)
			{
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
					if(count($adata) > 1)
					{
						unset($arFieldsProductDiscount[$adata[0]]);
					}
				}
				unset($arFieldsProductDiscount[substr($field, 14)]);
			}
			elseif(strpos($field, 'ICAT_')===0)
			{
				unset($arFieldsProduct[substr($field, 5)]);
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				unset($arFieldsProps[substr($field, 7)]);
			}
			elseif(strpos($field, 'IPROP_TEMP_')===0)
			{
				unset($arFieldsElement['IPROPERTY_TEMPLATES'][substr($field, 11)]);
			}
		}
	}
	
	public function UnsetExcessSectionFields($fieldsList, &$arFieldsSections, &$arFieldsElement)
	{
		foreach($fieldsList as $field)
		{
			if(strpos($field, 'ISECT')===0)
			{
				$adata = false;
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
				}
				$arSect = explode('_', substr($field, 5), 2);
				unset($arFieldsSections[$arSect[0]][$arSect[1]]);
				
				if(is_array($adata) && count($adata) > 1)
				{
					unset($arFieldsSections[$arSect[0]][$adata[0]]);
				}
			}
			elseif($field=='IE_SECTION_PATH')
			{
				$field = substr($field, 3);
				unset($arFieldsElement[$field]);
			}
		}
	}
	
	public function GetPropField(&$arFieldsProps, &$arFieldsPropsOrig, $fieldSettingsExtra, $propDef, $fieldName, $value, $origValue, $arUids = array())
	{
		if(!isset($arFieldsProps[$fieldName])) $arFieldsProps[$fieldName] = null;
		if(!isset($arFieldsPropsOrig[$fieldName])) $arFieldsPropsOrig[$fieldName] = null;
		$arFieldsPropsItem = &$arFieldsProps[$fieldName];
		$arFieldsPropsOrigItem = &$arFieldsPropsOrig[$fieldName];
		
		if($propDef)
		{
			if($propDef['USER_TYPE']=='directory')
			{
				if($fieldSettingsExtra['HLBL_FIELD']) $key2 = $fieldSettingsExtra['HLBL_FIELD'];
				else $key2 = 'UF_NAME';
				if(!isset($arFieldsPropsItem[$key2])) $arFieldsPropsItem[$key2] = null;
				if(!isset($arFieldsPropsOrigItem[$key2])) $arFieldsPropsOrigItem[$key2] = null;
				$arFieldsPropsItem = &$arFieldsPropsItem[$key2];
				$arFieldsPropsOrigItem = &$arFieldsPropsOrigItem[$key2];
			}
			elseif($propDef['PROPERTY_TYPE']=='E' && $propDef['MULTIPLE']!='Y')
			{
				if($fieldSettingsExtra['REL_ELEMENT_EXTRA_FIELD']) $key1 = $fieldSettingsExtra['REL_ELEMENT_EXTRA_FIELD'];
				else $key1 = 'PRIMARY';
				if($fieldSettingsExtra['REL_ELEMENT_FIELD']) $key2 = $fieldSettingsExtra['REL_ELEMENT_FIELD'];
				else $key2 = 'IE_ID';
				if(!isset($arFieldsPropsItem[$key1][$key2])) $arFieldsPropsItem[$key1][$key2] = null;
				if(!isset($arFieldsPropsOrigItem[$key1][$key2])) $arFieldsPropsOrigItem[$key1][$key2] = null;
				$arFieldsPropsItem = &$arFieldsPropsItem[$key1][$key2];
				$arFieldsPropsOrigItem = &$arFieldsPropsOrigItem[$key1][$key2];
			}
			elseif($propDef['PROPERTY_TYPE']=='L')
			{
				if($fieldSettingsExtra['PROPLIST_FIELD']) $key2 = $fieldSettingsExtra['PROPLIST_FIELD'];
				else $key2 = 'VALUE';
				if(!isset($arFieldsPropsItem[$key2])) $arFieldsPropsItem[$key2] = null;
				if(!isset($arFieldsPropsOrigItem[$key2])) $arFieldsPropsOrigItem[$key2] = null;
				$arFieldsPropsItem = &$arFieldsPropsItem[$key2];
				$arFieldsPropsOrigItem = &$arFieldsPropsOrigItem[$key2];
			}
		}
		
		if(($propDef['MULTIPLE']=='Y' || in_array('IP_PROP'.$fieldName, $arUids)) && !is_null($arFieldsPropsItem))
		{
			if(is_array($arFieldsPropsItem))
			{
				if(isset($arFieldsPropsItem['VALUE'])) $arFieldsPropsItem = array($arFieldsPropsItem);
				if(isset($arFieldsPropsOrigItem['VALUE'])) $arFieldsPropsOrigItem = array($arFieldsPropsOrigItem);
				$arFieldsPropsItem[] = $value;
				$arFieldsPropsOrigItem[] = $origValue;
			}
			else
			{
				$arFieldsPropsItem = array($arFieldsPropsItem, $value);
				$arFieldsPropsOrigItem = array($arFieldsPropsOrigItem, $origValue);
			}
		}
		else
		{
			$arFieldsPropsItem = $value;
			$arFieldsPropsOrigItem = $origValue;
		}
	}
	
	public function GetPropList(&$arFieldsProps, &$arFieldsPropsOrig, $fieldSettingsExtra, $IBLOCK_ID, $value, $arExcluded=array())
	{
		if(strlen($fieldSettingsExtra['PROPLIST_PROPS_SEP'])==0 || strlen($fieldSettingsExtra['PROPLIST_PROPVALS_SEP'])==0) return;
		$propsSep = $this->GetSeparator($fieldSettingsExtra['PROPLIST_PROPS_SEP']);
		$propValsSep = $this->GetSeparator($fieldSettingsExtra['PROPLIST_PROPVALS_SEP']);
		$propDescSep = $this->GetSeparator($fieldSettingsExtra['PROPLIST_VALDESC_SEP']);
		$arProps = explode($propsSep, $value);
		foreach($arProps as $prop)
		{
			$arCurProp = explode($propValsSep, $prop);
			if(count($arCurProp) < 2) continue;
			$arCurProp = array_map('trim', $arCurProp);
			$name = array_shift($arCurProp);
			if(strlen($name)==0) continue;
			$createNew = ($fieldSettingsExtra['PROPLIST_CREATE_NEW']=='Y');
			$propDef = $this->GetIblockPropertyByName($name, $IBLOCK_ID, $createNew, $fieldSettingsExtra);
			if(!$createNew)
			{
				if($propDef===false) $propDef = $this->GetIblockPropertyByCode($name, $IBLOCK_ID);
				if($propDef===false) $propDef = $this->GetIblockPropertyById($name, $IBLOCK_ID);
			}
			if($propDef!==false && !in_array($propDef['ID'], $arExcluded))
			{
				while(count($arCurProp) > 0)
				{
					$val = array_shift($arCurProp);
					if(strlen($propDescSep) > 0 && strpos($val, $propDescSep)!==false)
					{
						if($propDef['MULTIPLE']=='Y') $arVals = explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val);
						else $arVals = array($val);
						$val = $desc = '';
						foreach($arVals as $k=>$subval)
						{
							list($subval, $subdesc) = explode($propDescSep, $subval, 2);
							if($k > 0)
							{
								$val .= $this->params['ELEMENT_MULTIPLE_SEPARATOR'];
								$desc .= $this->params['ELEMENT_MULTIPLE_SEPARATOR'];
							}
							$val .= $subval;
							$desc .= $subdesc;
						}
						$this->GetPropField($arFieldsProps, $arFieldsPropsOrig, array(), $propDef, $propDef['ID'].'_DESCRIPTION', $desc, $desc);
					}
					$this->GetPropField($arFieldsProps, $arFieldsPropsOrig, array(), $propDef, $propDef['ID'], $val, $val);
					if($propDef['PROPERTY_TYPE']=='E') $this->fieldSettings['IP_PROP'.$propDef['ID']]['REL_ELEMENT_FIELD'] = 'IE_NAME';
				}
			}
		}
	}
	
	public function GetStoreAmountList(&$arFieldsProductStores, $arParams, $value)
	{
		if(!class_exists('\Bitrix\Catalog\StoreTable')) return;
		if(!isset($this->storeKeys) || !is_array($this->storeKeys)) $this->storeKeys = array();
		$sep1 = (strlen(trim($arParams['STORELIST_STORES_SEP'])) > 0 ? trim($arParams['STORELIST_STORES_SEP']) : ';');
		$sep2 = (strlen(trim($arParams['STORELIST_STOREVALS_SEP'])) > 0 ? trim($arParams['STORELIST_STOREVALS_SEP']) : ':');
		$arStores = array_map('trim', explode($sep1, $value));
		foreach($arStores as $strStore)
		{
			$arStoreParts = array_map('trim', explode($sep2, $strStore, 2));
			$storeName = ToLower($arStoreParts[0]);
			if(count($arStoreParts) < 2 && strlen($storeName)==0) continue;
			if(!array_key_exists($storeName, $this->storeKeys))
			{
				$dbRes = \Bitrix\Catalog\StoreTable::getList(array('filter'=>array('LOGIC'=>'OR', array('TITLE'=>$storeName), array('ADDRESS'=>$storeName), array('CODE'=>$storeName), array('XML_ID'=>$storeName)), 'select'=>array('ID')));
				if($arr = $dbRes->Fetch()) $this->storeKeys[$storeName] = $arr['ID'];
				else $this->storeKeys[$storeName] = 0;
			}
			if($this->storeKeys[$storeName] > 0)
			{
				$arFieldsProductStores[$this->storeKeys[$storeName]]['AMOUNT'] = $this->GetFloatVal($arStoreParts[1]);
			}
		}
	}
	
	public function SaveElementId($ID, $type='E')
	{
		$oProfile = CKDAImportProfile::getInstance();
		$isNew = $oProfile->SaveElementId($ID, $type);
		if($type=='S') $this->logger->SaveSectionChanges($ID);
		else $this->logger->SaveElementChanges($ID);
		return $isNew;
	}
	
	public function IsChangedElement()
	{
		return $this->logger->IsChangedElement();
	}
	
	public function IsFacetChanges($val=null)
	{
		if(is_bool($val)) $this->facetChanges = $val;
		else return $this->facetChanges;
	}
	
	public function AfterElementAdd($IBLOCK_ID, $ID)
	{
		\Bitrix\KdaImportexcel\DataManager\InterhitedpropertyValues::ClearElementValues($IBLOCK_ID, $ID);
		if($this->IsFacetChanges()) \Bitrix\KdaImportexcel\DataManager\IblockElementTable::updateElementIndex($IBLOCK_ID, $ID);
	}
	
	public function BeforeElementSave($ID, $type="update")
	{
		$this->IsFacetChanges(false);
		$this->logger->SetNewElement($ID, $type, $this->worksheetCurrentRow);
	}
	
	public function DeleteElement($ID, $IBLOCK_ID)
	{
		$this->BeforeElementDelete($ID, $IBLOCK_ID);
		CIblockElement::Delete($ID);
		$this->AfterElementDelete($ID, $IBLOCK_ID);
	}
	
	public function BeforeElementDelete($ID, $IBLOCK_ID)
	{
		$this->logger->SetNewElement($ID, 'delete', $this->worksheetCurrentRow);
	}
	
	public function AfterElementDelete($ID, $IBLOCK_ID)
	{
		$this->AddTagIblock($IBLOCK_ID);
		$this->logger->AddElementChanges('IE_', array('ID'=>$ID));
		$this->logger->SaveElementChanges($ID);
	}
	
	public function BeforeSectionSave($ID, $type="update")
	{
		$this->logger->SetNewSection($ID, $type, $this->worksheetCurrentRow);
	}
	
	public function DeleteSection($ID, $IBLOCK_ID)
	{
		$this->BeforeSectionDelete($ID, $IBLOCK_ID);
		CIBlockSection::Delete($ID);
		$this->AfterSectionDelete($ID, $IBLOCK_ID);
	}
	
	public function BeforeSectionDelete($ID, $IBLOCK_ID)
	{
		$this->logger->SetNewSection($ID, 'delete', $this->worksheetCurrentRow);
	}
	
	public function AfterSectionDelete($ID, $IBLOCK_ID)
	{
		$this->AddTagIblock($IBLOCK_ID);
		$this->logger->AddSectionChanges(array('ID'=>$ID));
		$this->logger->SaveSectionChanges($ID);
	}
	
	public function AfterSectionSave($ID, $IBLOCK_ID, $arFields, $arSection=array())
	{
		$this->AddTagIblock($IBLOCK_ID);
		$this->logger->AddSectionChanges($arFields, $arSection);
		if(array_key_exists('SECTION_PROPERTIES', $arFields))
		{
			if(!isset($this->iblockSP) || !isset($this->iblockSP[$IBLOCK_ID]))
			{
				if(\CIBlock::GetArrayByID($IBLOCK_ID, "SECTION_PROPERTY") != "Y")
				{
					$ib = new \CIBlock;
					$ib->Update($IBLOCK_ID, array('SECTION_PROPERTY'=>'Y'));
				}
				$this->iblockSP[$IBLOCK_ID] = true;
				
				$this->sectionProps[$IBLOCK_ID] = array();
				$dbRes = \Bitrix\Iblock\SectionPropertyTable::getList(array("select" => array("SECTION_ID", "PROPERTY_ID"), "filter" => array("=IBLOCK_ID" => $IBLOCK_ID)));
				while($arr = $dbRes->Fetch())
				{
					$this->sectionProps[$IBLOCK_ID][$arr['SECTION_ID']][$arr['PROPERTY_ID']] = $arr['PROPERTY_ID'];
				}
				
				if(!isset($this->iblockProps)) $this->iblockProps = array();
				$this->iblockProps[$IBLOCK_ID] = array('IDS'=>array(), 'CODES'=>array(), 'NAMES'=>array());
				$arFilter = array('IBLOCK_ID'=>$IBLOCK_ID);
				if($arOfferIblock = $this->GetCachedOfferIblock($IBLOCK_ID))
				{
					$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
					$arFilter['IBLOCK_ID'] = array($IBLOCK_ID, $OFFERS_IBLOCK_ID);
				}
				$dbRes = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>$arFilter, 'select'=>array('ID', 'CODE', 'NAME', 'IBLOCK_ID')));
				while($arr = $dbRes->Fetch())
				{
					$this->iblockProps[$IBLOCK_ID]['IDS'][$arr['ID']] = $arr['ID'];
					if($arr['IBLOCK_ID']==$IBLOCK_ID || !isset($this->iblockProps[$IBLOCK_ID]['CODES'][$arr['CODE']])) $this->iblockProps[$IBLOCK_ID]['CODES'][$arr['CODE']] = $arr['ID'];
					if($arr['IBLOCK_ID']==$IBLOCK_ID || !isset($this->iblockProps[$IBLOCK_ID]['NAMES'][$arr['NAME']])) $this->iblockProps[$IBLOCK_ID]['NAMES'][$arr['NAME']] = $arr['ID'];
				}
			}
			
			$arPropCodes = array_diff(array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arFields['SECTION_PROPERTIES'])), array(''));
			$arPropIds = array();
			if(!empty($arPropCodes))
			{
				foreach($arPropCodes as $code)
				{
					$propId = 0;
					if(isset($this->iblockProps[$IBLOCK_ID]['IDS'][$code])) $propId = $this->iblockProps[$IBLOCK_ID]['IDS'][$code];
					elseif(isset($this->iblockProps[$IBLOCK_ID]['CODES'][$code])) $propId = $this->iblockProps[$IBLOCK_ID]['CODES'][$code];
					elseif(isset($this->iblockProps[$IBLOCK_ID]['NAMES'][$code])) $propId = $this->iblockProps[$IBLOCK_ID]['NAMES'][$code];
					if($propId > 0) $arPropIds[$propId] = $propId;
				}
			}
			if(!isset($this->sectionProps[$IBLOCK_ID][$ID])) $this->sectionProps[$IBLOCK_ID][$ID] = array();
			if(!empty($arPropIds))
			{
				$fs = $this->fieldSettings['ISECT_SECTION_PROPERTIES'];
				$arPropFields = array();
				if(strlen($fs['SECTPROPS_SMART_FILTER']) > 0) $arPropFields['SMART_FILTER'] = $fs['SECTPROPS_SMART_FILTER'];
				if(strlen($fs['SECTPROPS_DISPLAY_EXPANDED']) > 0) $arPropFields['DISPLAY_EXPANDED'] = $fs['SECTPROPS_DISPLAY_EXPANDED'];
				foreach($arPropIds as $propId)
				{
					if(isset($this->sectionProps[$IBLOCK_ID][0][$propId]))
					{
						\CIBlockSectionPropertyLink::Delete(0, $propId);
						unset($this->sectionProps[$IBLOCK_ID][0][$propId]);
					}
					if(!isset($this->sectionProps[$IBLOCK_ID][$ID][$propId]) || !empty($arPropFields))
					{
						\CIBlockSectionPropertyLink::Set($ID, $propId, $arPropFields);
						$this->sectionProps[$IBLOCK_ID][$ID][$propId] = $propId;
					}
				}
				foreach($this->sectionProps[$IBLOCK_ID][$ID] as $propId)
				{
					if(!isset($arPropIds[$propId]))
					{
						\CIBlockSectionPropertyLink::Delete($ID, $propId);
						unset($this->sectionProps[$IBLOCK_ID][$ID][$propId]);
					}
				}
			}
			elseif(in_array('-', $arPropCodes))
			{
				foreach($this->sectionProps[$IBLOCK_ID][$ID] as $propId)
				{
					\CIBlockSectionPropertyLink::Delete($ID, $propId);
					unset($this->sectionProps[$IBLOCK_ID][$ID][$propId]);
				}
			}
		}
		
		if($this->params['REMOVE_COMPOSITE_CACHE_PART']=='Y')
		{
			if($arSection = \CIblockSection::GetList(array(), array('ID'=>$ID, 'CHECK_PERMISSIONS' => 'N'), false, array('SECTION_PAGE_URL'))->GetNext())
			{
				$this->ClearCompositeCache($arSection['SECTION_PAGE_URL']);
			}
		}
	}
	
	public function ApplyMargins($val, $fieldKey)
	{
		if(is_array($fieldKey)) $arParams = $fieldKey;
		else $arParams = $this->fieldSettings[$fieldKey];
		$val = $this->GetFloatVal($val);
		$sval = $val;
		$margins = $arParams['MARGINS'];
		if(is_array($margins) && count($margins) > 0)
		{
			foreach($margins as $margin)
			{
				if((strlen(trim($margin['PRICE_FROM']))==0 || $sval >= $this->GetFloatVal($margin['PRICE_FROM']))
					&& (strlen(trim($margin['PRICE_TO']))==0 || $sval <= $this->GetFloatVal($margin['PRICE_TO'])))
				{
					if($margin['PERCENT_TYPE']=='F')
						$val += ($margin['TYPE'] > 0 ? 1 : -1)*$this->GetFloatVal($margin['PERCENT']);
					else
						$val *= (1 + ($margin['TYPE'] > 0 ? 1 : -1)*$this->GetFloatVal($margin['PERCENT'])/100);
				}
			}
		}
		
		/*Rounding*/
		$roundRule = $arParams['PRICE_ROUND_RULE'];
		$roundRatio = $arParams['PRICE_ROUND_COEFFICIENT'];
		$roundRatio = str_replace(',', '.', $roundRatio);
		if(!preg_match('/^[\d\.]+$/', $roundRatio)) $roundRatio = 1;
		
		if($roundRule=='ROUND')	$val = round($val / $roundRatio) * $roundRatio;
		elseif($roundRule=='CEIL') $val = ceil($val / $roundRatio) * $roundRatio;
		elseif($roundRule=='FLOOR') $val = floor($val / $roundRatio) * $roundRatio;
		/*/Rounding*/
		
		return $val;
	}
	
	public function SetTimeBegin($ID)
	{
		if($this->stepparams['begin_time']) return;
		$dbRes = CIblockElement::GetList(array(), array('ID'=>$ID, 'CHECK_PERMISSIONS' => 'N'), false, false, array('TIMESTAMP_X'));
		if($arr = $dbRes->Fetch())
		{
			$this->stepparams['begin_time'] = $arr['TIMESTAMP_X'];
		}
	}
	
	public function IsEmptyPrice($arPrices)
	{
		if(is_array($arPrices))
		{
			foreach($arPrices as $arPrice)
			{
				if($arPrice['PRICE'] > 0)
				{
					return false;
				}
			}
		}
		return true;
	}
	
	public function GetHLBoolValue($val)
	{
		$res = $this->GetBoolValue($val);
		if($res=='Y') return 1;
		else return 0;
	}
	
	public function GetBoolValue($val, $numReturn = false, $defaultValue = false)
	{
		$trueVals = array_map('trim', explode(',', Loc::getMessage("KDA_IE_FIELD_VAL_Y")));
		$falseVals = array_map('trim', explode(',', Loc::getMessage("KDA_IE_FIELD_VAL_N")));
		if(in_array(ToLower($val), $trueVals))
		{
			return ($numReturn ? 1 : 'Y');
		}
		elseif(in_array(ToLower($val), $falseVals))
		{
			return ($numReturn ? 0 : 'N');
		}
		else
		{
			return $defaultValue;
		}
	}
	
	public function GetFieldExtraKey($fieldName)
	{
		$key = '';
		if(strpos($fieldName, 'IP_PROP')===0) $key = 'P'.substr($fieldName, 7);
		if(strlen($key) > 0) $key = '__'.$key;
		return $key;
	}
	
	public function GetShareFieldSettings($fieldName)
	{
		if(strlen($fieldName)==0) return array();
		$fieldSettings = array();
		if(isset($this->fieldSettings[$fieldName]))
		{
			$fieldSettings = $this->fieldSettings[$fieldName];
		}
		elseif(($extraKey = $this->GetFieldExtraKey($fieldName)) && isset($this->fieldSettingsExtra[$extraKey]))
		{
			$fieldSettings = $this->fieldSettingsExtra[$extraKey];
		}
		if(!is_array($fieldSettings)) $fieldSettings = array();
		return $fieldSettings;
	}
	
	public function GetStyleCellValue($arItem, $level)
	{
		$sectName = '';
		$sectKey = -1;
		if($this->sectioncolumn!==false)
		{
			$sectName = $arItem[$this->sectioncolumn];
			$sectKey = $this->sectioncolumn;
		}
		else
		{
			foreach($arItem as $k=>$v)
			{
				if(is_numeric($k) && strlen($v) > 0)
				{
					$sectName = $v;
					$sectKey = $k;
					break;
				}
			}
		}
		$levelSettings = (isset($this->fieldSettingsExtra['__'.$level]) ? $this->fieldSettingsExtra['__'.$level] : array());
		
		$conversions = array();
		if($sectKey >= 0 && isset($this->fieldSettingsExtra['SECTION_'.$sectKey]))
			$conversions = $this->fieldSettingsExtra['SECTION_'.$sectKey]['CONVERSION'];
		elseif(isset($levelSettings['CONVERSION']))
			$conversions = $levelSettings['CONVERSION'];
		if(!empty($conversions))
		{
			$sectName = $this->ApplyConversions($sectName, $conversions, $arItem);
		}
		return $sectName;
	}
	
	public function SetSectionSeparate($arItem, $IBLOCK_ID, $SECTION_ID, $level)
	{
		$sectName = $this->GetStyleCellValue($arItem, $level);		
		if(!$sectName) return false;
		$pVersion = \CKDAImportProfile::getInstance()->GetImportParam('PROFILE_VERSION');
		if($pVersion > 2) $sectName = preg_replace("/^'(\s+)(\S)/", '$2', $sectName);
		
		$arFields = array();
		$filedList = $this->params['FIELDS_LIST'][$this->worksheetNumForSave];
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		$levelSettings = (isset($this->fieldSettingsExtra['__'.$level]) ? $this->fieldSettingsExtra['__'.$level] : array());
		foreach($filedList as $key=>$field)
		{
			if(!preg_match('/^ISECT'.(intval($level) > 0 ? '('.$level.')?' : '').'_/', $field)) continue;
			$k = $key;
			if(strpos($k, '_')!==false) $k = substr($k, 0, strpos($k, '_'));
			$value = $arItem[$k];
			if($this->fieldSettings[$field]['NOT_TRIM']=='Y') $value = $arItem['~'.$k];
			$origValue = $arItem['~'.$k];
			
			$conversions = (isset($this->fieldSettingsExtra[$key]) ? $this->fieldSettingsExtra[$key]['CONVERSION'] : $this->fieldSettings[$field]['CONVERSION']);
			if(!empty($conversions))
			{
				$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$k, 'NAME'=>$field), $iblockFields);
				$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$k, 'NAME'=>$field), $iblockFields);
				if($value===false) continue;
			}
			
			$fieldKey = preg_replace('/^ISECT\d*_/', '', $field);
			$adata = false;
			if(strpos($field, '|')!==false)
			{
				list($field, $adata) = explode('|', $field);
				$adata = explode('=', $adata);
			}
			$arSect = explode('_', substr($field, 5), 2);
			$arFields[$fieldKey] = $value;
			
			if(is_array($adata) && count($adata) > 1)
			{
				$arFields[$fieldKey] = $adata[1];
			}
		}
		
		foreach($this->arSectionNames as $l=>$n)
		{
			if($l > $level) unset($this->arSectionNames[$l]);
		}
		$this->arSectionNames[$level] = $sectName;
		if($this->skipSepSection && $level > 1)
		{
			for($i=$level-1; $i>0; $i--)
			{
				if($this->skipSepSectionLevels[$i]) return true;
			}
		}
		
		$this->skipSepSection = false;
		$this->skipSepSectionLevels[$level] = false;
		if($this->UniCheckSkipLine($sectName, $levelSettings))
		{
			//$this->stepparams['cursections'.$IBLOCK_ID] = array();
			//unset($this->stepparams['cursections'.$IBLOCK_ID]);
			$this->skipSepSection = true;
			$this->skipSepSectionLevels[$level] = true;
			return true;
		}
		
		$arSections = $this->stepparams['cursections'.$IBLOCK_ID];
		if(!is_array($arSections))
		{
			$arSections = array();
			if($SECTION_ID > 0)
			{
				$dbRes = CIBlockSection::GetList(array(), array('ID'=>$SECTION_ID, 'IBLOCK_ID'=>$IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N'), false, array('ID', 'DEPTH_LEVEL'));
				if($arr = $dbRes->Fetch())
				{
					$arSections[$arr['DEPTH_LEVEL']] = $arr['ID'];
					$this->stepparams['fsectionlevel'.$IBLOCK_ID] = $arr['DEPTH_LEVEL'];
				}
			}
		}

		$fLevel = (isset($this->stepparams['fsectionlevel'.$IBLOCK_ID]) ? $this->stepparams['fsectionlevel'.$IBLOCK_ID] : 0);
		
		/*Section path*/
		if($level==0)
		{
			$sep = $this->GetSeparator($levelSettings['SECTION_PATH_SEPARATOR']);
			if(strlen(trim($sep))==0) $sep = '/';
			$arNames = array_map('trim', explode($sep, $sectName));
			$this->stepparams['last_section'] = end($arNames);
			$parent = 0;
			if($fLevel > 0)
			{
				$parent = $arSections[$fLevel - 1];
				$level = $fLevel + 1;
			}
			foreach($arNames as $sectName)
			{
				$arFields = array_merge($arFields, array('NAME' => $sectName));
				$sectId = $this->SaveSection($arFields, $IBLOCK_ID, $parent);
				if(is_array($sectId))
				{
					if(count($sectId)==0) return true;
					$sectId = current($sectId);
				}
				if(!$sectId) return false;
				$arSections[$level] = $parent = $sectId;
				$level++;
			}
			foreach($arSections as $k=>$v)
			{
				if($k > $level-1) unset($arSections[$k]);
			}
			$this->stepparams['cursections'.$IBLOCK_ID] = $arSections;
			return true;
		}
		/*/Section path*/
		
		if($fLevel > 0 /*&& $this->sectionstylesFl <= $fLevel*/)
		{
			$level += $fLevel - $this->sectionstylesFl + 1;
		}
		
		$parent = 0;
		$diff = 1;
		while(!isset($arSections[$level - $diff]) && ($level - $diff) >= 0) $diff++;
		if($arSections[$level - $diff]) $parent = $arSections[$level - $diff];
		
		$this->stepparams['last_section'] = $sectName;
		$arFields = array_merge($arFields, array('NAME' => $sectName));
		$sectId = $this->SaveSection($arFields, $IBLOCK_ID, $parent, 0, $levelSettings);
		if(is_array($sectId))
		{
			if(count($sectId)==0) return true;
			$sectId = current($sectId);
		}
		if(!$sectId) return false;
		$arSections[$level] = $sectId;
		foreach($arSections as $k=>$v)
		{
			if($k > $level) unset($arSections[$k]);
		}
		$this->stepparams['cursections'.$IBLOCK_ID] = $arSections;
		return true;
	}
	
	public function SaveSection($arFields, $IBLOCK_ID, $parent=0, $level=0, $arParams=array())
	{
		$sectionFields = $this->GetIblockSectionFields($IBLOCK_ID);
		$sectId = false;
		
		if(isset($arFields['ACTIVE']))
		{
			$arFields['ACTIVE'] = $this->GetBoolValue($arFields['ACTIVE']);
		}
		
		$arTexts = array('DESCRIPTION');
		foreach($arTexts as $keyText)
		{
			if($arFields[$keyText])
			{
				$textFile = $_SERVER["DOCUMENT_ROOT"].$arFields[$keyText];
				if(file_exists($textFile) && is_file($textFile) && is_readable($textFile))
				{
					$arFields[$keyText] = file_get_contents($textFile);
				}
			}
		}
		
		foreach($arFields as $k=>$v)
		{
			$fieldSettings = array();
			if(isset($this->fieldSettings['ISECT'.$level.'_'.$k])) $fieldSettings = $this->fieldSettings['ISECT'.$level.'_'.$k];
			elseif($level==1 && isset($this->fieldSettings['ISECT_'.$k])) $fieldSettings = $this->fieldSettings['ISECT_'.$k];
			if(isset($sectionFields[$k]))
			{
				$sParams = $sectionFields[$k];
				if($sParams['MULTIPLE']=='Y')
				{
					$separator = $this->params['ELEMENT_MULTIPLE_SEPARATOR'];
					if($fieldSettings['CHANGE_MULTIPLE_SEPARATOR']=='Y')
					{
						$separator = $this->GetSeparator($fieldSettings['MULTIPLE_SEPARATOR']);
					}
					$arFields[$k] = array_map('trim', explode($separator, $arFields[$k]));
					$newVals = array();
					foreach($arFields[$k] as $k2=>$v2)
					{
						$arFields[$k][$k2] = $this->GetSectionField($v2, $sParams, $fieldSettings);
						if(is_array($arFields[$k][$k2]) && isset($arFields[$k][$k2]['VALUES']))
						{
							$newVals = array_merge($newVals, $arFields[$k][$k2]['VALUES']);
							unset($arFields[$k][$k2]);
						}
					}
					if(!empty($newVals)) $arFields[$k] = array_merge($arFields[$k], $newVals);
				}
				else
				{
					$arFields[$k] = $this->GetSectionField($arFields[$k], $sParams, $fieldSettings);
				}
			}
			if(strpos($k, 'IPROP_TEMP_')===0)
			{
				$arFields['IPROPERTY_TEMPLATES'][substr($k, 11)] = $v;
				unset($arFields[$k]);
			}
			elseif($k=='IBLOCK_SECTION_ID')
			{
				$arFields[$k] = $this->GetIblockSectionValue(array('LINK_IBLOCK_ID'=>$IBLOCK_ID), $v, $fieldSettings);
			}
		}
		
		if($parent > 0 && !$arFields['IBLOCK_SECTION_ID']) $arFields['IBLOCK_SECTION_ID'] = $parent;
		
		$sectionUid = $this->params['SECTION_UID'];
		if(!$arFields[$sectionUid]) $sectionUid = 'NAME';
		$arFilter = array(
			$sectionUid=>$arFields[$sectionUid],
			'IBLOCK_ID'=>$IBLOCK_ID,
			'CHECK_PERMISSIONS' => 'N'
		);
		if((!isset($arFields['IGNORE_PARENT_SECTION']) || $arFields['IGNORE_PARENT_SECTION']!='Y')
			&& ($arParams['SECTION_SEARCH_WITHOUT_PARENT']!='Y' || $parent > 0)) $arFilter['SECTION_ID'] = $parent;
		else unset($arFields['IGNORE_PARENT_SECTION']);
		
		if($arParams['SECTION_SEARCH_IN_SUBSECTIONS']=='Y')
		{
			if($parent && $arParams['SECTION_SEARCH_WITHOUT_PARENT']!='Y')
			{
				//$dbRes2 = CIBlockSection::GetList(array(), array('IBLOCK_ID'=>$IBLOCK_ID, 'ID'=>$parent, 'CHECK_PERMISSIONS' => 'N'), false, array('ID', 'LEFT_MARGIN', 'RIGHT_MARGIN'));
				$dbRes2 = $this->GetListSection(array('IBLOCK_ID'=>$IBLOCK_ID, 'ID'=>$parent, 'CHECK_PERMISSIONS' => 'N'), array('ID', 'LEFT_MARGIN', 'RIGHT_MARGIN'));
				if($arParentSection = $dbRes2->Fetch())
				{
					$arFilter['>LEFT_MARGIN'] = $arParentSection['LEFT_MARGIN'];
					$arFilter['<RIGHT_MARGIN'] = $arParentSection['RIGHT_MARGIN'];
				}
			}
			unset($arFilter['SECTION_ID']);
		}
		//$dbRes = CIBlockSection::GetList(array(), $arFilter, false, array_merge(array('ID'), array_keys($arFields)));
		$dbRes = $this->GetListSection($arFilter, array_merge(array('ID'), array_keys($arFields)));
		$arSections = array();
		$i = 0;
		while($arSect = $dbRes->Fetch())
		{
			$sectId = $arSect['ID'];
			if($this->params['ONLY_CREATE_MODE_SECTION']!='Y')
			{
				if(0===$i++) $this->PrepareSectionPictures($arFields, $arSect);
				if(($arParams['SECTION_SEARCH_IN_SUBSECTIONS']=='Y' || $arParams['SECTION_SEARCH_WITHOUT_PARENT']=='Y') && isset($arFields['IBLOCK_SECTION_ID']))
				{
					unset($arFields['IBLOCK_SECTION_ID']);
				}
				$this->UpdateSection($sectId, $IBLOCK_ID, $arFields, $arSect, $sectionUid);
			}
			$arSections[] = $sectId;
		}
		if(empty($arSections) && $this->params['ONLY_UPDATE_MODE_SECTION']!='Y' && ($parent > 0 || $level < 2))
		{
			if(!$arFields['NAME']) return false;
			$this->PrepareSectionPictures($arFields);
			$this->PrepareNewSectionFields($arFields, $IBLOCK_ID);
			$bs = new CIBlockSection;
			$sectId = $j = 0;
			$code = $arFields['CODE'];
			$jmax = ($sectionUid=='CODE' ? 1 : 1000);
			while($j<$jmax && !($sectId = $bs->Add($arFields, true, true, true)) && ($arFields['CODE'] = $code.strval(++$j))){}
			if($sectId)
			{
				$this->BeforeSectionSave($sectId, "add");
				\Bitrix\KdaImportexcel\DataManager\InterhitedpropertyValues::ClearSectionValues($IBLOCK_ID, $sectId, $arFields);
				$this->AfterSectionSave($sectId, $IBLOCK_ID, $arFields);
				$this->SaveElementId($sectId, 'S');
				$this->stepparams['section_added_line']++;
			}
			else
			{
				$this->errors[] = sprintf(Loc::getMessage("KDA_IE_ADD_SECTION_ERROR"), $arFields['NAME'], $bs->LAST_ERROR, $this->worksheetNumForSave+1, $this->worksheetCurrentRow);
			}
			$arSections[] = $sectId;
		}
		return $arSections;
	}
	
	public function PrepareNewSectionFields(&$arFields, $IBLOCK_ID)
	{
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		if(!isset($arFields['ACTIVE'])) $arFields['ACTIVE'] = 'Y';
		$arFields['IBLOCK_ID'] = $IBLOCK_ID;

		if(($iblockFields['SECTION_CODE']['IS_REQUIRED']=='Y' || $iblockFields['SECTION_CODE']['DEFAULT_VALUE']['TRANSLITERATION']=='Y') && strlen($arFields['CODE'])==0)
		{
			$arFields['CODE'] = $this->Str2Url($arFields['NAME'], $iblockFields['SECTION_CODE']['DEFAULT_VALUE']);
		}
		
		$sectionFields = $this->GetIblockSectionFields($IBLOCK_ID);
		foreach($sectionFields as $fname=>$arField)
		{
			if($arField['MANDATORY']=='Y' && !array_key_exists($fname, $arFields))
			{
				if(is_array($arField['SETTINGS']) && array_key_exists('DEFAULT_VALUE', $arField['SETTINGS']))
				{
					$arFields[$fname] = $arField['SETTINGS']['DEFAULT_VALUE'];
				}
				else
				{
					$userType = $arField['USER_TYPE_ID'];
					if($userType=='enumeration')
					{
						$arFields[$fname] = $this->GetUserFieldEnumDefaultVal($arField);
					}
				}
			}
		}
	}
	
	public function GetListSection($arFilter, $arSelect, $arOrder=array())
	{
		if(class_exists('\Bitrix\Iblock\SectionTable') && count(preg_grep('/^UF_/', array_merge(array_keys($arFilter), $arSelect)))==0)
		{
			if(array_key_exists('SECTION_ID', $arFilter))
			{
				$arFilter['IBLOCK_SECTION_ID'] = $arFilter['SECTION_ID'];
				unset($arFilter['SECTION_ID']);
			}
			$arFields = array_keys(\Bitrix\Iblock\SectionTable::getMap());
			$arKeys = array_flip($arFields);
			$arFilter = array_intersect_key($arFilter, $arKeys);
			$arSelect = array_intersect($arSelect, $arFields);
			$dbRes = \Bitrix\Iblock\SectionTable::GetList(array('filter'=>$arFilter, 'select'=>$arSelect, 'order'=>$arOrder));
		}
		else
		{
			$dbRes = \CIBlockSection::GetList($arOrder, $arFilter, false, $arSelect);
		}
		return $dbRes;
	}
	
	public function UpdateSection($ID, $IBLOCK_ID, $arFields, $arSection, $sectionUid=false)
	{
		$this->BeforeSectionSave($ID, "update");
		$sectionFields = $this->GetIblockSectionFields($IBLOCK_ID);
		foreach($arSection as $k=>$v)
		{
			if($k=='PICTURE' || $k=='DETAIL_PICTURE')
			{
				if(empty($arFields[$k]) || !$this->IsChangedImage($v, $arFields[$k])) unset($arFields[$k]);
			}
			elseif(isset($sectionFields[$k]) && $sectionFields[$k]['MULTIPLE']=='Y' && isset($this->fieldSettings['ISECT_'.$k]) && $this->fieldSettings['ISECT_'.$k]['MULTIPLE_SAVE_OLD_VALUES']=='Y')
			{
				if(!is_array($arFields[$k])) $arFields[$k] = array();
				if(!is_array($v)) $v = array();
				if($sectionFields[$k]['USER_TYPE_ID']=='file')
				{
					foreach($arFields[$k] as $fpk2=>$fpv2)
					{
						foreach($v as $fpk=>$fpv)
						{
							if(!$this->IsChangedImage($fpv, $fpv2))
							{
								unset($arFields[$k][$fpk2]);
								break;
							}
						}
					}
					$arFields[$k] = array_merge($v, $arFields[$k]);
					foreach($arFields[$k] as $fpk2=>$fpv2)
					{
						if(is_numeric($fpv2)) $arFields[$k][$fpk2] = self::MakeFileArray($fpv2);
					}
					$arFields[$k] = array_diff($arFields[$k], array(''));
				}
				else
				{
					$arFields[$k] = array_merge($v, $arFields[$k]);
					$arFields[$k] = array_diff($arFields[$k], array(''));
				}
			}
			elseif(isset($arFields[$k]) && ($arFields[$k]==$v || ($k=='NAME' && ToLower($arFields[$k])==ToLower($v)) || $k==$sectionUid)) unset($arFields[$k]);
		}
		if(isset($arFields['IPROPERTY_TEMPLATES']) && is_array($arFields['IPROPERTY_TEMPLATES']) && count($arFields['IPROPERTY_TEMPLATES']) > 0)
		{
			$ipropValues = new \Bitrix\Iblock\InheritedProperty\SectionTemplates($IBLOCK_ID, $ID);
			$arTemplates = $ipropValues->findTemplates();
			foreach($arFields['IPROPERTY_TEMPLATES'] as $k=>$v)
			{
				if(isset($arTemplates[$k]) && is_array($arTemplates[$k]) && isset($arTemplates[$k]['TEMPLATE']))
				{
					if(($arTemplates[$k]['ENTITY_TYPE']=='S' && $arTemplates[$k]['TEMPLATE']==$v) || ($arTemplates[$k]['ENTITY_TYPE']!='S' && strlen($v)==0)) unset($arFields['IPROPERTY_TEMPLATES'][$k]);
				}
			}
			if(empty($arFields['IPROPERTY_TEMPLATES'])) unset($arFields['IPROPERTY_TEMPLATES']);
		}
		if(!empty($arFields))
		{
			$bs = new CIBlockSection;
			if($bs->Update($ID, $arFields, true, true, true))
			{
				$this->AfterSectionSave($ID, $IBLOCK_ID, $arFields, $arSection);
				\Bitrix\KdaImportexcel\DataManager\InterhitedpropertyValues::ClearSectionValues($IBLOCK_ID, $ID, $arFields);
			}
			else
			{
				$this->errors[] = sprintf(Loc::getMessage("KDA_IE_UPDATE_SECTION_ERROR"), $ID, $bs->LAST_ERROR, $this->worksheetNumForSave+1, $this->worksheetCurrentRow);
			}
		}
		if($sectionUid)
		{
			if($this->SaveElementId($ID, 'S')) $this->stepparams['section_updated_line']++;
		}
		else
		{
			$this->logger->SaveSectionChanges($ID);
		}
	}
	
	public function GetSectionField($val, $sParams, $fieldSettings)
	{
		$userType = $sParams['USER_TYPE_ID'];
		if($userType=='file')
		{
			$val = $this->GetFileArray($val, array('MULTIPLE'=>$sParams['MULTIPLE']));
			if($sParams['MULTIPLE']!='Y' && is_array($val) && empty($val)) $val = '';
		}
		elseif($userType=='boolean')
		{
			$val = $this->GetBoolValue($val, true);
		}
		elseif($userType=='enumeration')
		{
			$val = $this->GetUserFieldEnum($val, $sParams);
		}
		elseif($userType=='iblock_element')
		{
			$arProp = array('LINK_IBLOCK_ID' => $sParams['SETTINGS']['IBLOCK_ID']);
			$val = $this->GetIblockElementValue($arProp, $val, $fieldSettings);
		}
		elseif($userType=='iblock_section')
		{
			$arProp = array('LINK_IBLOCK_ID' => $sParams['SETTINGS']['IBLOCK_ID']);
			$val = $this->GetIblockSectionValue($arProp, $val, $fieldSettings);
		}
		return $val;
	}
	
	public function GetSections(&$arElement, $IBLOCK_ID, $SECTION_ID, $arSections)
	{
		if(!empty($this->sectionstyles) && !empty($this->stepparams['cursections'.$IBLOCK_ID]))
		{
			$sid = end($this->stepparams['cursections'.$IBLOCK_ID]);
			if($this->params['ELEMENT_ADD_NEW_SECTIONS']=='Y' && is_array($arElement['IBLOCK_SECTION']))
				$arElement['IBLOCK_SECTION'][] = $sid;
			else
				$arElement['IBLOCK_SECTION'] = array($sid);
			return true;
		}
		
		$fromSectionWoLevel = (bool)(!empty($arSections) && count($arSections)==1 && isset($arSections[0]) && count(array_diff($arSections[0], array(''))) > 0);		
		$arMultiSections = array();
		if(isset($arSections[0]) && isset($arSections[0]['PATH_NAMES']))
		{
			if(!is_array($arElement['SECTION_PATH']) && $fromSectionWoLevel && strlen($arSections[0]['PATH_NAMES']) > 0)
			{
				$sep = (isset($this->fieldSettings['ISECT_PATH_NAMES']['SECTION_PATH_SEPARATOR']) && strlen($this->fieldSettings['ISECT_PATH_NAMES']['SECTION_PATH_SEPARATOR']) > 0 ? $this->fieldSettings['ISECT_PATH_NAMES']['SECTION_PATH_SEPARATOR'] : '/');
				$arSectionPaths = array_diff(array_map('trim', explode($sep, $arSections[0]['PATH_NAMES'])), array(''));
				if(count($arSectionPaths) > 0) $arElement['SECTION_PATH'] = array($arSectionPaths);
			}
			unset($arSections[0]['PATH_NAMES']);
		}
		if(is_array($arElement['SECTION_PATH']))
		{
			foreach($arElement['SECTION_PATH'] as $sectionPath)
			{
				if(is_array($sectionPath))
				{
					$tmpSections = array();
					foreach($sectionPath as $k=>$name)
					{
						$tmpSections[$k+1]['NAME'] = $name;
					}
					$arMultiSections[] = $tmpSections;
				}
			}
			unset($arElement['SECTION_PATH']);
		}

		/*if no 1st level*/
		if($SECTION_ID > 0 && !empty($arSections) && !isset($arSections[1]) && !$fromSectionWoLevel)
		{
			$minKey = min(array_keys($arSections));
			$arSectionsOld = $arSections;
			$arSections = array();
			foreach($arSectionsOld as $k=>$v)
			{
				$arSections[$k - $minKey + 1] = $v;
			}
		}
		/*/if no 1st level*/
		
		if((empty($arSections) /*|| !isset($arSections[1]) || count(array_diff($arSections[1], array('')))==0*/) && empty($arMultiSections) && !$fromSectionWoLevel)
		{
			if($SECTION_ID > 0)
			{
				if($this->params['ELEMENT_ADD_NEW_SECTIONS']=='Y' && is_array($arElement['IBLOCK_SECTION']))
					$arElement['IBLOCK_SECTION'][] = $SECTION_ID;
				else
					$arElement['IBLOCK_SECTION'] = array($SECTION_ID);
				return true;
			}
			return false;
		}
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);

		if(empty($arMultiSections))
		{
			if(isset($arSections[0]) && count($arSections) > 1)
			{
				while(count($arSections) > 1 && ($lkey = max(array_keys($arSections))) && !$arSections[$lkey][$this->params['SECTION_UID']] && !$arSections[$lkey][$this->params['NAME']])
				{
					unset($arSections[$lkey]);
				}				
				$lkey = max(array_keys($arSections));
				$arSections[$lkey] = array_merge($arSections[$lkey], $arSections[0]);
				unset($arSections[0]);
			}
			$arMultiSections[] = $arSections;
			$fromSectionPath = false;
		}
		else
		{
			if(count($arMultiSections) > 0 && !empty($arSections))
			{
				foreach($arMultiSections as $k=>$v)
				{
					foreach($arSections as $k2=>$v2)
					{
						$lkey = $k2;
						if($v2[$this->params['SECTION_UID']])
						{
							$fsKey = 'ISECT'.$k2.'_'.$this->params['SECTION_UID'];
							if($this->fieldSettings[$fsKey]['SECTION_SEARCH_IN_SUBSECTIONS'] == 'Y')
							{
								$lkey = max(array_keys($v));
								$v2['IGNORE_PARENT_SECTION'] = 'Y';
							}
						}
						if($lkey==0 && count($v) > 0) $lkey = max(array_keys($v));
						if(isset($v[$lkey]))
						{
							$arMultiSections[$k][$lkey] = array_merge($v[$lkey], $v2);
						}
						elseif($v2[$this->params['SECTION_UID']])
						{
							$arMultiSections[$k][$lkey] = $v2;
						}
					}
				}
			}
			$fromSectionPath = true;
		}
		
		foreach($arMultiSections as $arSections)
		{
			$parent = $i = 0;
			$arParents = array();
			if($SECTION_ID)
			{
				$parent = $SECTION_ID;
				$arParents[] = $SECTION_ID;
			}
			if($fromSectionWoLevel && !$fromSectionPath)
			{	
				$arSections = array(1 => array_merge($arSections[0], array('IGNORE_PARENT_SECTION'=>($SECTION_ID ? 'N' : 'Y'))));	
			}
			while(++$i && !empty($arSections[$i]))
			{
				$sectionUid = $this->params['SECTION_UID'];
				if(!isset($arSections[$i][$sectionUid]) || strlen($arSections[$i][$sectionUid])==0) $sectionUid = 'NAME';
				if(!isset($arSections[$i][$sectionUid]) || strlen($arSections[$i][$sectionUid])==0) continue;

				if($fromSectionPath) $fsKey = 'IE_SECTION_PATH';
				else
				{
					$ii = $i;
					if($SECTION_ID > 0 && isset($minKey)) $ii = $i + $minKey - 1;
					$fsKey = 'ISECT'.$ii.'_'.$sectionUid;
				}
				
				if(($this->fieldSettings[$fsKey]['SECTION_UID_SEPARATED']=='Y' || $fromSectionWoLevel) /*&& empty($arSections[$i+1])*/)
				{
					$arNames = array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arSections[$i][$sectionUid]));
					$arNames = array_diff($arNames, array(''));
				}
				else
				{
					$arNames = array($arSections[$i][$sectionUid]);
				}
				if(empty($arNames)) continue;
				$arParents = array();
				
				$parentLvl = array();
				$parent2 = (is_array($parent) ? $parent : array($parent));
				foreach($parent2 as $parent)
				{
					foreach($arNames as $name)
					{
						if(isset($this->sections[$parent][$name]) && !empty($this->sections[$parent][$name]) && count($arSections[$i]) < 2)
						{
							$parentLvl = array_merge($parentLvl, $this->sections[$parent][$name]);
						}
						else
						{				
							$arFields = $arSections[$i];
							$arFields[$sectionUid] = $name;
							$sectId = $this->SaveSection($arFields, $IBLOCK_ID, $parent, $i, $this->fieldSettings[$fsKey]);
							$this->sections[$parent][$name] = $sectId;
							if(!empty($sectId)) $parentLvl = array_merge($parentLvl, $sectId);
						}
						$arParents = array_merge($arParents, $parentLvl);
					}
				}
				$parent = array_diff($parentLvl, array(0, false));
				if(is_array($parent) && count($parent)==1) $parent = current($parent);
				if(!$parent)
				{
					$parent = 0;
					/*continue;*/ break;
				}
			}
			
			if(!empty($arParents))
			{
				if(!is_array($arElement['IBLOCK_SECTION'])) $arElement['IBLOCK_SECTION'] = array();
				$arElement['IBLOCK_SECTION'] = array_unique(array_merge($arElement['IBLOCK_SECTION'], $arParents));
				$arElement['IBLOCK_SECTION_ID'] = current($arElement['IBLOCK_SECTION']);
			}
			elseif($sectionUid=='ID' && count($arSections)==1 && isset($arSections[1][$sectionUid]) && $arSections[1][$sectionUid]==='0')
			{
				$arElement['IBLOCK_SECTION_ID'] = 0;
			}
		}
	}
	
	public function SaveBlogComment($ID, $IBLOCK_ID, $arComment)
	{
		if(!\Bitrix\Main\Loader::IncludeModule('blog') || !$this->params['REVIEWS_BLOG']) return;
		if(strlen(trim($arComment['AUTHOR_NAME']))==0 || strlen(trim($arComment['POST_TEXT']))==0) return;

		$blogId = $this->params['REVIEWS_BLOG'];
		$arBlog = \CBlog::GetByID($blogId);

		if($arBlog && ($arElement = \CIblockElement::GetList(array(), array('ID'=>$ID), false, false, array('ID', 'IBLOCK_ID', 'CREATED_BY', 'DETAIL_PAGE_URL', 'NAME', 'PREVIEW_TEXT', 'PROPERTY_BLOG_POST_ID', 'PROPERTY_BLOG_COMMENTS_CNT'))->GetNext()))
		{
			$postID = 0;
			if($arElement['PROPERTY_BLOG_POST_ID_VALUE'])
			{
				$postID = $arElement['PROPERTY_BLOG_POST_ID_VALUE'];
				if(!\CBlogPost::GetByID($postID)) $postID = 0;
			}
			
			if(!$postID)
			{
				$conn = \Bitrix\Main\Application::getConnection();
				$helper = $conn->getSqlHelper();

				$arFields = array(
					'TITLE' => $arElement['~NAME'],
					'DETAIL_TEXT' =>
						"[URL=http://".$_SERVER['HTTP_HOST'].$arElement["~DETAIL_PAGE_URL"]."]".$arElement["~NAME"]."[/URL]\n".
						($arElement["~PREVIEW_TEXT"] != '' ? $arElement["~PREVIEW_TEXT"] : '')."\n",
					'PUBLISH_STATUS' => BLOG_PUBLISH_STATUS_PUBLISH,
					"PERMS_POST" => array(),
					"PERMS_COMMENT" => array(),
					"=DATE_CREATE" => $helper->getCurrentDateTimeFunction(),
					"=DATE_PUBLISH" => $helper->getCurrentDateTimeFunction(),
					"AUTHOR_ID" => $arElement['CREATED_BY'],
					"BLOG_ID" => $blogId,
					"ENABLE_TRACKBACK" => "N"
				);
				$postID = (int)\CBlogPost::Add($arFields);
				if ($postID > 0)
				{
					\CIBlockElement::SetPropertyValuesEx($arElement['ID'], $arElement['IBLOCK_ID'], array('BLOG_POST_ID'=>$postID));
				}
			}
			
			if($postID)
			{
				//\Bitrix\Main\Text\Emoji::encode($arComment['POST_TEXT']);
				//\Bitrix\Main\Text\Emoji::decode($arComment['POST_TEXT']);
				$path = (\CMain::IsHTTPS() ? 'https://' : 'http://').$this->GetIblockDomain($IBLOCK_ID).$arElement['~DETAIL_PAGE_URL'];
				$UserIP = \CBlogUser::GetUserIP();
				$arFields = Array(
					"POST_ID" => $postID,
					"BLOG_ID" => $arBlog["ID"],
					"POST_TEXT" => trim($arComment['POST_TEXT']),
					"DATE_CREATE" => ConvertTimeStamp(time()+\CTimeZone::GetOffset(), "FULL"),
					"AUTHOR_IP" => $UserIP[0],
					"AUTHOR_IP1" => $UserIP[1],
					"URL" => $arBlog["URL"],
					"PUBLISH_STATUS" => BLOG_PUBLISH_STATUS_PUBLISH,
					"PATH" => $path.(mb_strpos($path, "?")!==false ? '&' : '?')."commentId=#comment_id###comment_id#"
				);
				if(strlen(trim($arComment['AUTHOR_NAME'])) > 0) $arFields['AUTHOR_NAME'] = $arComment['AUTHOR_NAME'];
				//if(strlen(trim($authorId)) > 0) $arFields['AUTHOR_ID'] = $authorId;
				//if(strlen(trim($authorEmail)) > 0) $arFields['AUTHOR_EMAIL'] = $authorEmail;
				if(strlen(trim($arComment['DATE_CREATE'])) > 0 && ($dateCreate = $this->GetDateVal($arComment['DATE_CREATE']))) $arFields['DATE_CREATE'] = $dateCreate;
				
				if($arDbComment = \CBlogComment::GetList(array(), array("BLOG_ID" => $arFields["BLOG_ID"], "POST_ID" => $arFields["POST_ID"], "AUTHOR_NAME" => $arFields["AUTHOR_NAME"], "POST_TEXT" => $arFields["POST_TEXT"]), false, array("nTopCount" => 1), array("ID"))->Fetch())
				{
					$commentID = $arDbComment['ID'];
					if($arFields['DATE_CREATE'])
					{
						\CBlogComment::Update($commentID, $arFields);
					}
				}
				elseif($commentID = \CBlogComment::Add($arFields))
				{
					\CIBlockElement::SetPropertyValuesEx($arElement['ID'], $arElement['IBLOCK_ID'], array('BLOG_COMMENTS_CNT'=>(int)$this->GetFloatVal($arElement['PROPERTY_BLOG_COMMENTS_CNT_VALUE']) + 1));
				}
				if($commentID && array_key_exists('UF_ASPRO_COM_RATING', $arComment) && strlen(trim($arComment['UF_ASPRO_COM_RATING'])) > 0)
				{
					$GLOBALS["USER_FIELD_MANAGER"]->Update("BLOG_COMMENT", $commentID, array('UF_ASPRO_COM_RATING'=>trim($arComment['UF_ASPRO_COM_RATING'])));
				}
			}
		}
	}
	
	public function GetIblockDomain($IBLOCK_ID)
	{
		if(!isset($this->arIblockDomains)) $this->arIblockDomains = array();
		if(!$this->arIblockDomains[$IBLOCK_ID])
		{
			$host = '';
			$find = false;
			$rsIBlockSites = CIBlock::GetSite($IBLOCK_ID);
			while($arIBlockSite = $rsIBlockSites->Fetch())
			{
				if($arIBlockSite['SERVER_NAME'] && (!$host || ($arIBlockSite['SERVER_NAME']==$_SERVER['HTTP_HOST'] && ($find = true)) || (!$find && $arIBlockSite['DEF']=='Y')))
				{
					$host = $arIBlockSite['SERVER_NAME'];
				}
			}
			if(!$host && $_SERVER['HTTP_HOST']) $host = $_SERVER['HTTP_HOST'];
			$this->arIblockDomains[$IBLOCK_ID] = $host;
		}
		return $this->arIblockDomains[$IBLOCK_ID];
	}
	
	public function GetIblockDefaultProperties($IBLOCK_ID)
	{
		if(!array_key_exists($IBLOCK_ID, $this->defprops))
		{
			$arSectionProps = array();
			if(class_exists('\Bitrix\Iblock\SectionPropertyTable'))
			{
				$dbRes = \Bitrix\Iblock\SectionPropertyTable::getList(array('filter'=>array(
					'IBLOCK_ID' => $IBLOCK_ID,
					'>SECTION_ID' => 0
				), 
				'select'=>array('PROPERTY_ID'), 'group'=>array('PROPERTY_ID')));
				$arSectionProps = array();
				while($arr = $dbRes->Fetch())
				{
					$arSectionProps[$arr['PROPERTY_ID']] = $arr['PROPERTY_ID'];
				}
			}
			$arDefProps = array();
			$arListsId = array();
			$arProps = $this->GetIblockProperties($IBLOCK_ID);
			foreach($arProps as $arProp)
			{
				if(isset($arSectionProps[$arProp['ID']])) continue;
				if($arProp['PROPERTY_TYPE']=='L')
				{
					$arListsId[] = $arProp['ID'];
				}
				elseif($arProp['USER_TYPE']=='directory')
				{
					$val = $this->GetHighloadBlockValue($arProp, array('UF_DEF'=>1));
					if(!is_array($val) && $val!==false && strlen($val) > 0 && $val!='purple') $arDefProps[$arProp['ID']] = $val;
				}
				elseif(!is_array($arProp['DEFAULT_VALUE']) && strlen(trim($arProp['DEFAULT_VALUE'])) > 0)
				{
					$arDefProps[$arProp['ID']] = $arProp['DEFAULT_VALUE'];
				}
			}
			if(count($arListsId) > 0 && class_exists('\Bitrix\Iblock\PropertyEnumerationTable'))
			{
				$dbRes = \Bitrix\Iblock\PropertyEnumerationTable::getList(array('filter'=>array('PROPERTY_ID'=>$arListsId, 'DEF'=>'Y'), 'select'=>array('PROPERTY_ID', 'ID')));
				while($arr = $dbRes->Fetch())
				{
					$arDefProps[$arr['PROPERTY_ID']] = $arr['ID'];
				}
			}
			$this->defprops[$IBLOCK_ID] = $arDefProps;
		}
		return $this->defprops[$IBLOCK_ID];
	}
	
	public function GetIblockProperties($IBLOCK_ID, $byName = false)
	{
		if(!$this->props[$IBLOCK_ID])
		{
			$this->props[$IBLOCK_ID] = array();
			$this->propsByNames[$IBLOCK_ID] = array();
			$this->propsByCodes[$IBLOCK_ID] = array();
			$dbRes = CIBlockProperty::GetList(array(), array('IBLOCK_ID'=>$IBLOCK_ID));
			while($arProp = $dbRes->Fetch())
			{
				$this->props[$IBLOCK_ID][$arProp['ID']] = $arProp;
				$this->propsByNames[$IBLOCK_ID][ToLower($arProp['NAME'])] = $arProp;
				$this->propsByCodes[$IBLOCK_ID][ToLower($arProp['CODE'])] = $arProp;
			}
		}
		if(is_string($byName) && $byName=='CODE') return $this->propsByCodes[$IBLOCK_ID];
		elseif($byName) return $this->propsByNames[$IBLOCK_ID];
		else return $this->props[$IBLOCK_ID];
	}
	
	public function GetIblockPropertyByName($name, $IBLOCK_ID, $createNew = false, $params = array())
	{
		$lowerName = ToLower($name);
		$arProps = $this->GetIblockProperties($IBLOCK_ID, true);
		if(isset($arProps[$lowerName])) return $arProps[$lowerName];
		$arPropsByCode = $this->GetIblockProperties($IBLOCK_ID, 'CODE');
		if(isset($arPropsByCode[$lowerName])) return $arPropsByCode[$lowerName];
		if($createNew)
		{
			$arParams = array(
				'max_len' => 50,
				'change_case' => 'U',
				'replace_space' => '_',
				'replace_other' => '_',
				'delete_repeat_replace' => 'Y',
			);
			$code = CUtil::translit($name, LANGUAGE_ID, $arParams);
			$code = preg_replace('/[^a-zA-Z0-9_]/', '', $code);
			$code = preg_replace('/^[0-9_]+/', '', $code);
			if(isset($params['PROPLIST_NEWPROP_PREFIX']) && is_string($params['PROPLIST_NEWPROP_PREFIX']))
			{
				$code = trim($params['PROPLIST_NEWPROP_PREFIX']).$code;
			}
			if(isset($arPropsByCode[ToLower($code)])) return $arPropsByCode[ToLower($code)];
			
			$arFields = Array(
				"NAME" => $name,
				"ACTIVE" => "Y",
				"CODE" => $code,
				"PROPERTY_TYPE" => "S",
				"IBLOCK_ID" => $IBLOCK_ID
			);
			if(isset($params['PROPLIST_NEWPROP_SORT']) && strlen(trim($params['PROPLIST_NEWPROP_SORT'])) > 0) $arFields['SORT'] = (int)$params['PROPLIST_NEWPROP_SORT'];
			if(isset($params['PROPLIST_NEWPROP_TYPE']))
			{
				if(in_array($params['PROPLIST_NEWPROP_TYPE'], array('S', 'N', 'L'))) $arFields['PROPERTY_TYPE'] = $params['PROPLIST_NEWPROP_TYPE'];
				elseif(strpos($params['PROPLIST_NEWPROP_TYPE'], ':')!==false)
				{
					$arFields['PROPERTY_TYPE'] = current(explode(':', $params['PROPLIST_NEWPROP_TYPE']));
					$arFields['USER_TYPE'] = end(explode(':', $params['PROPLIST_NEWPROP_TYPE']));
				}
			}
			$ibp = new CIBlockProperty;
			if(isset($params['PROPLIST_NEWPROP_MULTIPLE']) && $params['PROPLIST_NEWPROP_MULTIPLE']=='Y') $arFields['MULTIPLE'] = 'Y';
			if(isset($params['PROPLIST_NEWPROP_SMART_FILTER']) && $params['PROPLIST_NEWPROP_SMART_FILTER']=='Y')
			{
				$arFields['SMART_FILTER'] = 'Y';
				if(\CIBlock::GetArrayByID($arFields["IBLOCK_ID"], "SECTION_PROPERTY") != "Y")
				{
					$ib = new \CIBlock;
					$ib->Update($arFields["IBLOCK_ID"], array('SECTION_PROPERTY'=>'Y'));
				}
			}
			if(isset($params['PROPLIST_NEWPROP_DISPLAY_EXPANDED']) && $params['PROPLIST_NEWPROP_DISPLAY_EXPANDED']=='Y') $arFields['DISPLAY_EXPANDED'] = 'Y';
			if(strlen($arFields['CODE']) > 0)
			{
				$index = 0;
				while(($dbRes2 = CIBlockProperty::GetList(array(), array('CODE'=>$arFields['CODE'], 'IBLOCK_ID'=>$arFields['IBLOCK_ID']))) && ($arr2 = $dbRes2->Fetch()))
				{
					$index++;
					$arFields['CODE'] = substr($arFields['CODE'], 0, 50 - strlen($index)).$index;
				}
			}
			$propID = $ibp->Add($arFields);
			if(!$propID) return false;
			
			if(is_callable(array('\Bitrix\Iblock\Model\PropertyFeature', 'isEnabledFeatures')) && \Bitrix\Iblock\Model\PropertyFeature::isEnabledFeatures())
			{
				$arFeaturesFields = array();
				$arFeaturesKeys = preg_grep('/^PROPLIST_NEWPROP_FEATURE_.+:.+/', array_keys($params));
				foreach($arFeaturesKeys as $fKey)
				{
					if($params[$fKey]!='Y') continue;
					$fKey = substr($fKey, 25);
					$arKeys = explode(':', $fKey);
					$arFeaturesFields[$fKey] = array(
						'PROPERTY_ID' => $propID,	
						'MODULE_ID' => $arKeys[0],	
						'FEATURE_ID' => $arKeys[1],	
						'IS_ENABLED' => 'Y'
					);
				}
				if(!empty($arFeaturesFields)) \Bitrix\Iblock\Model\PropertyFeature::setFeatures($propID, $arFeaturesFields);
			}
			
			$dbRes = CIBlockProperty::GetList(array(), array('ID'=>$propID));
			if($arProp = $dbRes->Fetch())
			{
				$this->props[$IBLOCK_ID][$arProp['ID']] = $arProp;
				$this->propsByNames[$IBLOCK_ID][ToLower($arProp['NAME'])] = $arProp;
				return $arProp;
			}
		}
		return false;
	}
	
	public function GetIblockPropertyByCode($code, $IBLOCK_ID)
	{
		$code = trim($code);
		$lowerCode = ToLower($code);
		$arProps = $this->GetIblockProperties($IBLOCK_ID, 'CODE');
		if(isset($arProps[$lowerCode])) return $arProps[$lowerCode];
		return false;
	}
	
	public function GetIblockPropertyById($id, $IBLOCK_ID)
	{
		$id = (int)$id;
		$arProps = $this->GetIblockProperties($IBLOCK_ID);
		if(isset($arProps[$id])) return $arProps[$id];
		return false;
	}
	
	public function RemoveProperties($ID, $IBLOCK_ID, $isOffer=false)
	{
		if($this->conv->IsAlreadyLoaded($ID))
		{
			if($this->lastOffElemId==$ID) $this->lastOffElemId = 0;
			else return false;
		}
		if(is_array($this->params['ADDITIONAL_SETTINGS'][$this->worksheetNum]['ELEMENT_PROPERTIES_REMOVE']))
		{
			$arIds = $this->params['ADDITIONAL_SETTINGS'][$this->worksheetNum]['ELEMENT_PROPERTIES_REMOVE'];
		}
		else
		{
			$arIds = $this->params['ELEMENT_PROPERTIES_REMOVE'];
		}
		if(is_array($arIds) && !empty($arIds))
		{
			$arIblockProps = $this->GetIblockProperties($IBLOCK_ID);
			$arProps = $arFieldsProductStores = $arFieldsProduct = $arFieldsPrices = array();
			foreach($arIds as $k=>$v)
			{
				if(strpos($v, 'ICAT_STORE')===0)
				{
					$arStore = explode('_', substr($v, 10), 2);
					$arFieldsProductStores[$arStore[0]][$arStore[1]] = '-';
				}
				else
				{
					if(strpos($v, 'IP_PROP')===0) $pid = (int)substr($v, strlen('IP_PROP'));
					else $pid = (int)$v;
					if($pid > 0)
					{
						if($arIblockProps[$pid]['PROPERTY_TYPE']=='F') $arProps[$pid] = array("del"=>"Y");
						else $arProps[$pid] = false;
					}
				}
			}
			if(!empty($arProps) && !$isOffer)
			{
				\CIBlockElement::SetPropertyValuesEx($ID, $IBLOCK_ID, $arProps);
			}
			if(!empty($arFieldsProductStores))
			{
				$this->SaveProduct($ID, $IBLOCK_ID, $arFieldsProduct, $arFieldsPrices, $arFieldsProductStores);
			}
		}
	}
	
	public function GetMultiplePropertyChange(&$val)
	{
		if(is_array($val))
		{
			if(isset($val['VALUE']) && !is_array($val['VALUE']))
			{
				$val2 = $val['VALUE'];
				$valOrig = $val;
				if($this->GetMultiplePropertyChangeItem($val2))
				{
					$val = array();
					foreach($val2 as $k=>$v)
					{
						$val[$k] = array_merge($valOrig, array('VALUE'=>$v));
					}
					return true;
				}
			}
			else
			{
				$newVals = array();
				foreach($val as $k=>$v)
				{
					if(is_numeric($k) && $this->GetMultiplePropertyChange($v))
					{
						$newVals = array_merge($newVals, $v);
						unset($val[$k]);
					}
				}
				if(count($newVals) > 0)
				{
					$val = array_merge($val, $newVals);
					return true;
				}
			}
		}
		else
		{
			if($this->GetMultiplePropertyChangeItem($val)) return true;
		}
		return false;
	}
	
	public function GetMultiplePropertyChangeItem(&$val)
	{
		if(preg_match_all('/(\+|\-)\s*\{\s*(((["\'])(.*)\4[,\s]*)+)\s*\}/Uis', $val, $m))
		{
			$rest = $val;
			foreach($m[0] as $k=>$v)
			{
				$rest = str_replace($v, '', $rest);
			}
			if(strlen(trim($rest))==0)
			{
				$addVals = array();
				$removeVals = array();
				foreach($m[0] as $k=>$v)
				{
					if(preg_match_all('/(["\'])(.*)\1/Uis', $v, $m2))
					{
						$sign = $m[1][$k];
						foreach($m2[2] as $v2)
						{
							if($sign=='+') $addVals[] = $v2;
							elseif($sign=='-') $removeVals[] = $v2;
						}
					}
				}
				if(count($addVals) > 0 || count($removeVals) > 0)
				{
					$val = array();
					foreach($addVals as $av) $val['ADD_'.md5($av)] = $av;
					foreach($removeVals as $rv) $val['REMOVE_'.md5($rv)] = $rv;
					return true;
				}
			}
		}
		return false;
	}
	
	public function GetMultipleProperty($val, $k)
	{
		$separator = $this->params['ELEMENT_MULTIPLE_SEPARATOR'];
		$fsKey = ($this->conv->GetSkuMode() ? 'OFFER_' : '').'IP_PROP'.$k;
		if($this->fieldSettings[$fsKey]['CHANGE_MULTIPLE_SEPARATOR']=='Y')
		{
			$separator = $this->GetSeparator($this->fieldSettings[$fsKey]['MULTIPLE_SEPARATOR']);
		}
		if(is_array($val))
		{
			if(count(preg_grep('/\D/', array_keys($val))) > 0 && count(preg_grep('/^\d+$/', array_keys($val))) == 0)
			{
				/*Exception for user types*/
				$arVal = array($val);
			}
			else
			{
				$arVal = array();
				foreach($val as $subval)
				{
					if(is_array($subval)) $arVal[] = $subval;
					else $arVal = array_merge($arVal, array_map('trim', explode($separator, $subval)));
				}
			}
		}
		else
		{
			if(is_array($val)) $arVal = $val;
			else $arVal = array_map('trim', explode($separator, $val));
		}
		return $arVal;
	}
	
	public function CheckRequiredProps($arProps, $IBLOCK_ID, $ID=false)
	{
		if($this->needCheckReqProps)
		{
			$arErrors = array();
			$arReqProps = $this->GetRequiredProps($IBLOCK_ID);
			foreach($arReqProps as $propId=>$propName)
			{
				if(array_key_exists($propId, $arProps) && $this->IsEmptyVal($arProps[$propId]))
				{
					$arErrors[] = sprintf(Loc::getMessage("KDA_IE_REQPROP_EMPTY"), $propName);
				}
				elseif($ID==false && !array_key_exists($propId, $arProps))
				{
					$arErrors[] = sprintf(Loc::getMessage("KDA_IE_REQPROP_EMPTY_NOT_SET"), $propName);
				}
			}
			if(count($arErrors) > 0)
			{
				$this->SetLastError(implode('<br>', $arErrors));
				return false;
			}
		}
		return true;
	}
	
	public function IsEmptyVal($propVal)
	{
		return (bool)((!is_array($propVal) && strlen($propVal)==0) || (is_array($propVal) && count(array_diff($propVal, array('')))==0));
	}
	
	public function GetRequiredProps($IBLOCK_ID)
	{
		if(!isset($this->arRequiredProperties)) $this->arRequiredProperties = array();
		if(!isset($this->arRequiredProperties[$IBLOCK_ID]))
		{
			$arProps = array();
			if(class_exists('\Bitrix\Iblock\PropertyTable'))
			{
				$dbRes = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>array('IBLOCK_ID'=>$IBLOCK_ID, 'IS_REQUIRED'=>'Y'), 'select'=>array('ID', 'NAME')));
				while($arr = $dbRes->fetch())
				{
					$arProps[$arr['ID']] = $arr['NAME'];
				}
			}
			$this->arRequiredProperties[$IBLOCK_ID] = $arProps;
		}
		return $this->arRequiredProperties[$IBLOCK_ID];
	}
	
	public function PropReplaceId(&$v, $k=false)
	{
		if(strpos($v, '#ID#')!==false) $v = str_replace('#ID#', $this->propReplaceProdId, $v);
	}
	
	public function SaveProperties($ID, $IBLOCK_ID, $arProps, $arOldVals=array(), $needUpdate = false, $arFieldsElement=array())
	{
		if(empty($arProps)/* && !$needUpdate*/) return false;
		$propsDef = $this->GetIblockProperties($IBLOCK_ID);
		$fieldList = $this->params['FIELDS_LIST'][$this->worksheetNumForSave];
		if(!is_array($fieldList)) $fieldList = array();
		$this->propReplaceProdId = $ID;
		
		foreach($arProps as $k=>$prop)
		{
			if(!is_array($prop)) $this->PropReplaceId($arProps[$k]);
			else array_walk_recursive($arProps[$k], array($this, 'PropReplaceId'));
			if(!is_numeric($k)) continue;
			if(($propsDef[$k]['USER_TYPE']=='directory' || $propsDef[$k]['PROPERTY_TYPE']=='L') && $propsDef[$k]['MULTIPLE']=='Y' && is_array($prop))
			{
				$newProp = array();
				foreach($prop as $k2=>$v2)
				{
					$arVal = $this->GetMultipleProperty($v2, $k);
					foreach($arVal as $k3=>$v3)
					{
						$newProp[$k3][$k2] = $v3;
					}
				}
				$arProps[$k] = $newProp;
			}
			//if($propsDef[$k]['PROPERTY_TYPE']=='F' && $propsDef[$k]['MULTIPLE']=='Y' && is_array($prop))
			if($propsDef[$k]['ACTIVE']=='N')
			{
				unset($arProps[$k]);
			}
		}
		
		if(!empty($arProps))
		{
			$arOldProps = array();
			$arOldPropIds = array();
			if(empty($arOldVals))
			{
				$dbRes = CIBlockElement::GetProperty($IBLOCK_ID, $ID, array(), Array("ID"=>array_keys($arProps)));
				while($arr = $dbRes->Fetch())
				{
					$arOldVals[] = $arr;
				}
			}
			else
			{
				foreach($arOldVals as $arrKey=>$arr)
				{
					if(isset($arProps[$arr['ID']]) && $arr['MULTIPLE']=='Y' && isset($arr['VALUES']) && is_array($arr['VALUES']))
					{
						foreach($arr['VALUES'] as $valKey=>$valVal)
						{
							$newPropVal = array_merge($arr, $valVal);
							unset($newPropVal['VALUES']);
							$arOldVals[$arrKey.'_'.$valKey] = $newPropVal;
						}
						unset($arOldVals[$arrKey]);
					}
				}
			}
			foreach($arOldVals as $arrKey=>$arr)
			{
				if(isset($arProps[$arr['ID']]))
				{
					$propVal = $arr['VALUE'];
					$propValId = $arr['PROPERTY_VALUE_ID'];
					if(is_array($propVal)) $propVal = serialize($propVal);
					$newPropVal = $arProps[$arr['ID']];
					if(is_array($newPropVal) && isset($newPropVal[0])) $newPropVal = $newPropVal[0];
					if(!is_array($newPropVal) && isset($arProps[$arr['ID'].'_DESCRIPTION']))
					{
						$newPropVal = array('VALUE'=>$newPropVal, 'DESCRIPTION'=>$arProps[$arr['ID'].'_DESCRIPTION']);
						if(is_array($newPropVal['DESCRIPTION']) && isset($newPropVal['DESCRIPTION'][0])) $newPropVal['DESCRIPTION'] = $newPropVal['DESCRIPTION'][0];
					}
					if(is_array($newPropVal))
					{
						if(isset($newPropVal['VALUE'], $newPropVal['DESCRIPTION']))
						{
							$propVal = array(
								'VALUE' => $arr['VALUE'],
								'DESCRIPTION' => (is_array($newPropVal['DESCRIPTION']) && is_array(unserialize($arr['DESCRIPTION'])) ? unserialize($arr['DESCRIPTION']) : $arr['DESCRIPTION']),
							);
						}
						elseif(isset($newPropVal['VALUE'])) $propVal = array('VALUE' => $arr['VALUE']);
					}
					if($arr['MULTIPLE']=='Y')
					{
						if(!is_array($arOldProps[$arr['ID']])) $arOldProps[$arr['ID']] = array();
						if(!is_array($arOldPropIds[$arr['ID']])) $arOldPropIds[$arr['ID']] = array();
						//Fix error with some similar values
						if(/*(!is_string($propVal) || !in_array($propVal, $arOldProps[$arr['ID']]))
							&&*/ ($arr['PROPERTY_TYPE']!='F' || !empty($propVal)))
						{
							$arOldProps[$arr['ID']][] = $propVal;
							$arOldPropIds[$arr['ID']][] = $propValId;
						}
					}
					else
					{
						$arOldProps[$arr['ID']] = $propVal;
						$arOldPropIds[$arr['ID']] = $propValId;
					}
				}
			}

			foreach($arProps as $pk=>$pv)
			{
				if(!array_key_exists($pk, $arOldProps) && is_numeric($pk)) $arOldProps[$pk] = '';
			}
		}
		
		foreach($arProps as $k=>$prop)
		{
			if(strpos($k, '_DESCRIPTION')!==false) continue;
			if($propsDef[$k]['MULTIPLE']=='Y')
			{
				$isChanges = $this->GetMultiplePropertyChange($prop);
				if($propsDef[$k]['USER_TYPE']=='directory'  || $propsDef[$k]['PROPERTY_TYPE']=='L') $arVal = (is_array($prop) ? $prop : array($prop));
				elseif($isChanges && is_array($prop)) $arVal = $prop;
				else $arVal = $this->GetMultipleProperty($prop, $k);
				$origVals = $arVal;
				
				$limitVals = false;
				$fsKey = ($this->conv->GetSkuMode() ? 'OFFER_' : '').'IP_PROP'.$k;
				$fromValue = $this->fieldSettings[$fsKey]['MULTIPLE_FROM_VALUE'];
				$toValue = $this->fieldSettings[$fsKey]['MULTIPLE_TO_VALUE'];
				if(is_numeric($fromValue) || is_numeric($toValue))
				{
					$from = (is_numeric($fromValue) ? ((int)$fromValue >= 0 ? ((int)$fromValue - 1) : (int)$fromValue) : 0);
					$to = (is_numeric($toValue) ? ((int)$toValue >= 0 ? ((int)$toValue - max(0, $from)) : (int)$toValue) : 0);
					$limitVals = true;
				}
				if($limitVals && $propsDef[$k]['PROPERTY_TYPE']=='F' && count(preg_grep('/^[^\{\}\*#]+\.[\w]{2,4}$/', $arVal))==count($arVal))
				{
					if($to!=0) $arVal = array_slice($arVal, $from, $to);
					else $arVal = array_slice($arVal, $from);
					$limitVals = false;
				}
				
				$newVals = array();
				foreach($arVal as $k2=>$val)
				{
					$arVal[$k2] = $this->GetPropValue($propsDef[$k], (is_string($val) ? trim($val) : $val), $arOldProps[$k], $origVals);
					if(is_array($arVal[$k2]) && isset($arVal[$k2]['VALUES']))
					{
						$newVals = array_merge($newVals, $arVal[$k2]['VALUES']);
						unset($arVal[$k2]);
					}
					elseif((is_array($arVal[$k2]) && empty($arVal[$k2])) && (count($arVal) > 1 || $propsDef[$k]['PROPERTY_TYPE']=='F'))
					{
						unset($arVal[$k2]);
						if(is_string($arProps[$k.'_DESCRIPTION']) && strlen($arProps[$k.'_DESCRIPTION']) > 0)
						{
							$arProps[$k.'_DESCRIPTION'] = $this->GetMultipleProperty($arProps[$k.'_DESCRIPTION'], $k.'_DESCRIPTION');
						}
						if(is_array($arProps[$k.'_DESCRIPTION']) && array_key_exists($k2, $arProps[$k.'_DESCRIPTION']))
						{
							unset($arProps[$k.'_DESCRIPTION'][$k2]);
						}
					}					
				}
				if(!empty($newVals)) $arVal = array_merge($arVal, $newVals);
				
				if($limitVals)
				{
					if($to!=0) $arVal = array_slice($arVal, $from, $to);
					else $arVal = array_slice($arVal, $from);
				}
				if($this->fieldSettings[$fsKey]['EXCLUDE_CURRENT_ELEMENT']=='Y') $arVal = array_diff($arVal, array($ID));
				
				$arProps[$k] = ($isChanges ? $arVal : array_values($arVal));
				if(is_array($arProps[$k.'_DESCRIPTION'])) $arProps[$k.'_DESCRIPTION'] = array_values($arProps[$k.'_DESCRIPTION']);
				
				/*$oldPropVal = $arOldProps[$k];
				if(is_array($oldPropVal) && isset($oldPropVal[0])) $oldPropVal = $oldPropVal[0];
				if(is_array($oldPropVal) && isset($oldPropVal['VALUE']))
				{
					foreach($arProps[$k] as $k2=>$v2)
					{
						if(!array_key_exists('VALUE', $v2))
						{
							$arProps[$k][$k2] = array('VALUE'=>$v2);
						}
					}
				}*/
			}
			else
			{
				$arProps[$k] = $this->GetPropValue($propsDef[$k], $prop, $arOldProps[$k]);
			}
			
			if($propsDef[$k]['PROPERTY_TYPE']=='F' && is_array($arProps[$k]) && count(array_diff($arProps[$k], array('')))==0)
			{
				unset($arProps[$k]);
			}
			elseif($propsDef[$k]['PROPERTY_TYPE']=='S' && $propsDef[$k]['USER_TYPE']=='video')
			{
				\CIBlockElement::SetPropertyValueCode($ID, $k, $arProps[$k]);
				unset($arProps[$k]);
			}
		}
		
		foreach($arProps as $k=>$prop)
		{
			if(strpos($k, '_DESCRIPTION')!==false)
			{
				$pk = substr($k, 0, strpos($k, '_'));
				if(!isset($arProps[$pk]))
				{
					$dbRes = CIBlockElement::GetProperty($IBLOCK_ID, $ID, array(), Array("ID"=>$pk));
					while($arPropValue = $dbRes->Fetch())
					{
						if($propsDef[$pk]['MULTIPLE']=='Y')
						{
							$arProps[$pk][] = $arPropValue['VALUE'];
						}
						else
						{
							$arProps[$pk] = $arPropValue['VALUE'];
						}
					}
					if(isset($arProps[$pk]))
					{
						if($propsDef[$pk]['PROPERTY_TYPE']=='F')
						{
							if(is_array($arProps[$pk]))
							{
								foreach($arProps[$pk] as $k2=>$v2)
								{
									$arProps[$pk][$k2] = self::MakeFileArray($v2);
								}
							}
							else
							{
								$arProps[$pk] = self::MakeFileArray($arProps[$pk]);
							}
						}
					}
				}
				if(isset($arProps[$pk]))
				{
					if($propsDef[$pk]['MULTIPLE']=='Y')
					{
						$arVal = $this->GetMultipleProperty($prop, $pk);
						foreach($arProps[$pk] as $k2=>$v2)
						{
							if(isset($arVal[$k2]))
							{
								if(is_array($v2) && isset($v2['VALUE']))
								{
									$v2['DESCRIPTION'] = $arVal[$k2];
									$arProps[$pk][$k2] = $v2;
								}
								else
								{
									$arProps[$pk][$k2] = array(
										'VALUE' => $v2,
										'DESCRIPTION' => $arVal[$k2]
									);
								}
								if($propsDef[$pk]['PROPERTY_TYPE']=='F' && empty($arProps[$pk][$k2]['VALUE'])) unset($arProps[$pk][$k2]);
								elseif(!is_array($arProps[$pk][$k2]['DESCRIPTION']) && strlen(trim($arProps[$pk][$k2]['DESCRIPTION'])) > 0 && !is_array($arProps[$pk][$k2]['VALUE']) && strlen($arProps[$pk][$k2]['VALUE'])==0) $arProps[$pk][$k2]['VALUE'] = ' ';
							}
						}
					}
					else
					{
						if(is_array($arProps[$pk]) && isset($arProps[$pk]['VALUE']))
						{
							$arProps[$pk]['DESCRIPTION'] = $prop;
						}
						else
						{
							$arProps[$pk] = array(
								'VALUE' => $arProps[$pk],
								'DESCRIPTION' => $prop
							);
						}
					}
				}
				unset($arProps[$k]);
			}
			
			if($propsDef[$pk]['USER_TYPE'] && ($mname = 'GetPropValue'.preg_replace('/\W/', '', $propsDef[$pk]['PROPERTY_TYPE'].$propsDef[$pk]['USER_TYPE'])) && is_callable(array($this, $mname)))
			{
				 $arProps[$pk] = call_user_func(array($this, $mname), $propsDef[$pk], $arProps[$pk]);
			}
		}

		/*Delete unchanged props*/
		if(!empty($arProps))
		{
			foreach($arOldProps as $pk=>$pv)
			{
				$fsKey = ($this->conv->GetSkuMode() ? 'OFFER_' : '').'IP_PROP'.$pk;
				$saveOldVals = false;
				if($propsDef[$pk]['MULTIPLE']=='Y')
				{
					$saveOldVals = (bool)($this->fieldSettings[$fsKey]['MULTIPLE_SAVE_OLD_VALUES']=='Y');
					if(!in_array($fsKey, $fieldList) && $this->fieldSettings['IP_LIST_PROPS']['PROPLIST_NEWPROP_SAVE_OLD_VALUES']=='Y') $saveOldVals = true;
					if(!$saveOldVals && isset($arProps[$pk]) && is_array($arProps[$pk]) && count(preg_grep('/^(ADD|REMOVE)_/', array_keys($arProps[$pk])))>0) $saveOldVals = true;
				}
				if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']=='Y' && !$saveOldVals) continue;

				if($propsDef[$pk]['MULTIPLE']=='Y')
				{
					$isEmptyVals = false;
					foreach($arProps[$pk] as $fpk2=>$fpv2)
					{
						if(count($arProps[$pk]) > 1 && ((!is_array($fpv2) && strlen($fpv2)==0) || (is_array($fpv2) && isset($fpv2['VALUE']) && !is_array($fpv2['VALUE']) && strlen($fpv2['VALUE'])==0)))
						{
							$isEmptyVals = true;
							unset($arProps[$pk][$fpk2]);
						}
					}
					if($isEmptyVals) $arProps[$pk] = array_values($arProps[$pk]);
				
					if($propsDef[$pk]['PROPERTY_TYPE']!='F' && $saveOldVals)
					{
						$pv2 = $pv;
						foreach($arProps[$pk] as $fpk2=>$fpv2)
						{
							foreach($pv2 as $fpk=>$fpv)
							{
								if($this->IsEqProps($fpv, $fpv2) || (is_array($fpv) && is_array($fpv2) && $fpv['VALUE']==$fpv2['VALUE']))
								{
									if(strpos($fpk2, 'REMOVE_')===0) unset($pv2[$fpk]);
									unset($arProps[$pk][$fpk2]);
									break;
								}
							}
							if(strpos($fpk2, 'REMOVE_')===0) unset($arProps[$pk][$fpk2]);
						}
						$arProps[$pk] = array_merge($pv2, $arProps[$pk]);
						$arProps[$pk] = array_diff($arProps[$pk], array(''));
						if($propsDef[$pk]['PROPERTY_TYPE']=='L') $arProps[$pk] = array_unique($arProps[$pk], SORT_REGULAR);
						if(count($arProps[$pk])==0 && count($pv) > 0) $arProps[$pk] = false;
					}
				}
				
				if($this->IsEqProps($arProps[$pk], $pv, $saveOldVals))
				{
					unset($arProps[$pk]);
				}
				elseif(in_array($propsDef[$pk]['PROPERTY_TYPE'], array('L', 'E', 'G')) && $propsDef[$pk]['MULTIPLE']=='Y' && is_array($arProps[$pk]) && is_array($pv) && !isset($pv['VALUE']) && (count($arProps[$pk])==count($pv) || (($arProps[$pk]=CKDAImportUtils::ArrayUnique($arProps[$pk])) && count($arProps[$pk])==count($pv))))
				{
					$newVal1 = array();
					$newVal2 = array();
					foreach($arProps[$pk] as $tmpKey=>$tmpVal)
					{
						if(!is_array($tmpVal) || !array_key_exists('VALUE', $tmpVal)) $tmpVal = array('VALUE'=>$tmpVal);
						if(is_array($tmpVal)){ksort($tmpVal); $tmpVal = serialize($tmpVal);}
						$newVal1[$tmpKey] = $tmpVal;
					}
					foreach($pv as $tmpKey=>$tmpVal)
					{
						if(!is_array($tmpVal) || !array_key_exists('VALUE', $tmpVal)) $tmpVal = array('VALUE'=>$tmpVal);
						if(is_array($tmpVal)){ksort($tmpVal); $tmpVal = serialize($tmpVal);}
						$newVal2[$tmpKey] = $tmpVal;
					}
					if(count(array_diff($newVal1, $newVal2))==0 && count(array_diff($newVal2, $newVal1))==0) unset($arProps[$pk]);
				}
				elseif($propsDef[$pk]['PROPERTY_TYPE']=='S' && $propsDef[$pk]['USER_TYPE']=='HTML')
				{
					if((!is_array($pv) && strlen($pv) > 0 && is_array($newVal2 = unserialize($pv))) || (is_array($pv) && ($newVal2 = $pv)))
					{
						if((!is_array($arProps[$pk]) && $arProps[$pk]==$newVal2['TEXT']) 
							|| ($arProps[$pk]['VALUE']==$newVal2 || (!isset($arProps[$pk]['VALUE']['TYPE']) && isset($arProps[$pk]['VALUE']['TEXT']) && $arProps[$pk]['VALUE']['TEXT']==$newVal2['TEXT'])))
						{
							unset($arProps[$pk]);
						}
					}
				}
				elseif($propsDef[$pk]['PROPERTY_TYPE']=='F')
				{
					if($propsDef[$pk]['MULTIPLE']=='Y')
					{
						if($saveOldVals)
						{
							foreach($arProps[$pk] as $fpk2=>$fpv2)
							{
								foreach($pv as $fpk=>$fpv)
								{
									if(!$this->IsChangedImage($fpv, $fpv2))
									{
										unset($arProps[$pk][$fpk2]);
										break;
									}
								}
							}
							if(!is_array($arProps[$pk])) $arProps[$pk] = array();
							$arProps[$pk] = array_merge($pv, $arProps[$pk]);
							foreach($arProps[$pk] as $fpk2=>$fpv2)
							{
								if(is_numeric($fpv2)) $arProps[$pk][$fpk2] = self::MakeFileArray($fpv2);
							}
							$arProps[$pk] = array_diff($arProps[$pk], array(''));
						}
						
						$isChange = false;
						$arTmpProp = array();
						foreach($arProps[$pk] as $fpk=>$fpv)
						{
							$isOneChange = true;
							foreach($pv as $fpk2=>$fpv2)
							{
								if(!$this->IsChangedImage($fpv2, $fpv))
								{
									$arTmpProp[$arOldPropIds[$pk][$fpk2]] = array('VALUE'=>array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0), 'DESCRIPTION'=>(isset($arOldProps[$pk][$fpk2]['DESCRIPTION']) ? $arOldProps[$pk][$fpk2]['DESCRIPTION'] : ''));
									$isOneChange = false;
									if($fpk!=$fpk2) $isChange = true;
									unset($pv[$fpk2]);
									break;
								}
							}
							if($isOneChange) 
							{
								$arTmpProp['n'.$fpk] = $fpv;
								$isChange = true;
							}
						}
						if(count($pv) > 0)
						{
							$isChange = true;
							foreach($pv as $fpk=>$fpv)
							{
								$arTmpProp[$arOldPropIds[$pk][$fpk]] = array('VALUE'=>array('del'=>'Y'));
							}
						}
						if(!$isChange) unset($arProps[$pk]);
						else $arProps[$pk] = $arTmpProp;
					}
					else
					{
						if(!$this->IsChangedImage($pv, $arProps[$pk]))
						{
							unset($arProps[$pk]);
						}
					}
				}
			}
		}
		/*/Delete unchanged props*/
		
		$isProps = !empty($arProps);
		if($isProps)
		{
			CIBlockElement::SetPropertyValuesEx($ID, $IBLOCK_ID, $arProps);
			$this->logger->AddElementChanges('IP_PROP', $arProps, $arOldProps);
		}
		
		if($needUpdate)
		{
			/*$this->conv->SetElementId($ID);
			$this->conv->GetChangedElementFields($arFieldsElement, $ID);
			$this->conv->SetElementId(0);*/
			$arFieldsElement = array();
			if($isProps || !empty($arFieldsElement))
			{
				$el = new CIblockElement();
				$this->el->UpdateComp($ID, $arFieldsElement, false, true);
				$this->AddTagIblock($IBLOCK_ID);
			}
		}
		elseif($isProps && $this->params['ELEMENT_NOT_UPDATE_WO_CHANGES']=='Y')
		{
			$arFilterProp = $this->GetFilterProperties($IBLOCK_ID);
			if(!empty($arFilterProp) && count(array_intersect(array_keys($arProps), $arFilterProp)) > 0)
			{
				$this->IsFacetChanges(true);
			}
			$arSearchProp = $this->GetSearchProperties($IBLOCK_ID);
			if(!empty($arSearchProp) && count(array_intersect(array_keys($arProps), $arSearchProp)) > 0)
			{
				\CIBlockElement::UpdateSearch($ID, true);
			}
		}
	}
	
	public function IsEqProps($v1, $v2, $saveOldVals=false)
	{
		$eq = true;
		if(is_array($v1) || is_array($v2))
		{
			if(!is_array($v1))
			{
				if(is_array($v2) && array_key_exists('VALUE', $v2))
				{
					$v1 = array('VALUE'=>$v1);
					if(array_key_exists('DESCRIPTION', $v2)) $v1['DESCRIPTION'] = '';
				}
				else $v1 = array($v1);
			}
			if(!is_array($v2))
			{
				if(is_array($v1) && array_key_exists('VALUE', $v1))
				{
					$v2 = array('VALUE'=>$v2);
					if(array_key_exists('DESCRIPTION', $v1)) $v2['DESCRIPTION'] = '';
				}
				else $v2 = array($v2);
			}
			if($saveOldVals)
			{
				if(isset($v1['TYPE'])) unset($v1['TYPE']);
				if(isset($v2['TYPE'])) unset($v2['TYPE']);
			}
			if(count($v1)==count($v2))
			{
				foreach($v1 as $k=>$v)
				{
					if(!$this->IsEqProps($v, $v2[$k])) $eq = false;
				}
			} else $eq = false;
		}
		//else $eq = (bool)($v1==$v2 && (is_array($v1) || is_array($v2) || strlen($v1)==strlen($v2)));
		else $eq = (bool)((string)$v1==(string)$v2 && strlen($v1)==strlen($v2));
		return $eq;
	}
	
	public function GetFilterProperties($IBLOCK_ID)
	{
		if(!isset($this->arFilterProperties)) $this->arFilterProperties = array();
		if(!isset($this->arFilterProperties[$IBLOCK_ID]))
		{
			$arProps = array();
			if(class_exists('\Bitrix\Iblock\SectionPropertyTable'))
			{
				$filterIblockId = $IBLOCK_ID;
				if(($arOfferIblock = \CKDAImportUtils::GetOfferIblockByOfferIblock($IBLOCK_ID)) && isset($arOfferIblock['IBLOCK_ID']) && $arOfferIblock['IBLOCK_ID'] > 0)
				{
					$filterIblockId = array(
						$filterIblockId,
						$arOfferIblock['IBLOCK_ID']
					);
				}
				$dbRes = \Bitrix\Iblock\SectionPropertyTable::getList(array('filter'=>array('IBLOCK_ID'=>$filterIblockId, 'SMART_FILTER'=>'Y'), 'group'=>array('PROPERTY_ID'), 'select'=>array('PROPERTY_ID')));
				while($arr = $dbRes->fetch())
				{
					$arProps[] = $arr['PROPERTY_ID'];
				}
			}
			$this->arFilterProperties[$IBLOCK_ID] = $arProps;
		}
		return $this->arFilterProperties[$IBLOCK_ID];
	}
	
	public function GetSearchProperties($IBLOCK_ID)
	{
		if(!isset($this->arSearchProperties)) $this->arSearchProperties = array();
		if(!isset($this->arSearchProperties[$IBLOCK_ID]))
		{
			$arProps = array();
			if(class_exists('\Bitrix\Iblock\PropertyTable'))
			{
				$dbRes = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>array('IBLOCK_ID'=>$IBLOCK_ID, 'SEARCHABLE'=>'Y'), 'select'=>array('ID')));
				while($arr = $dbRes->fetch())
				{
					$arProps[] = $arr['ID'];
				}
			}
			$this->arSearchProperties[$IBLOCK_ID] = $arProps;
		}
		return $this->arSearchProperties[$IBLOCK_ID];
	}
	
	public function GetPropValue($arProp, $val, $oldVal=false, $arOrigVals=array())
	{
		$fieldName = (isset($this->fieldSettings['OFFER_IP_PROP'.$arProp['ID']]) ? 'OFFER_' : '').'IP_PROP'.$arProp['ID'];
		$fieldSettings = $this->GetShareFieldSettings($fieldName);
		if($arProp['PROPERTY_TYPE']=='F')
		{
			$arFile = $this->GetFileArray($val, $arProp, $fieldName, $oldVal);
			if(empty($arFile) && mb_strlen($val) > 1 && isset($arOrigVals[0]) && preg_match('#^(http|https|ftp|ftps)://#i', trim($arOrigVals[0])) && !preg_match('#^(http|https|ftp|ftps)://#', trim($val)))
			{
				$newVal = preg_replace('#^((http|https|ftp|ftps)://[^/]*)/.*$#i', '$1', trim($arOrigVals[0])).trim($val);
				$arFile = $this->GetFileArray($newVal, $arProp, $fieldName, $oldVal, $val);
			}
			if(empty($arFile) && strpos($val, $this->params['ELEMENT_MULTIPLE_SEPARATOR'])!==false)
			{
				$arVals = array_diff(array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val)), array(''));
				if(count($arVals) > 0 && ($newVal = current($arVals)))
				{
					$arFile = $this->GetFileArray($newVal, $arProp, $fieldName, $oldVal, $val);
				}
			}
			$val = $arFile;
		}
		elseif($arProp['PROPERTY_TYPE']=='L')
		{
			$val = $this->GetListPropertyValue($arProp, $val, (bool)($fieldSettings['PROPLIST_NOT_CREATE_VALS']!='Y'));
		}
		elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='directory')
		{
			$val = $this->GetHighloadBlockValue($arProp, $val, true);
		}
		elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='HTML')
		{
			if($fieldSettings['TEXT_HTML']=='text') $val = array('VALUE'=>array('TEXT'=>$val, 'TYPE'=>'TEXT'));
			elseif($fieldSettings['TEXT_HTML']=='html') $val = array('VALUE'=>array('TEXT'=>$val, 'TYPE'=>'HTML'));
			else $val = array('VALUE'=>array('TEXT'=>$val));
		}
		elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='UserID')
		{
			if(!is_array($val) && strlen(trim($val)) > 0 && $fieldSettings['USER_REL_FIELD'] && is_callable('\Bitrix\Main\UserTable', 'getList'))
			{
				if($arUser = \Bitrix\Main\UserTable::getList(array('filter'=>array($fieldSettings['USER_REL_FIELD']=>trim($val)), 'select'=>array('ID')))->Fetch())
				{
					$val = $arUser['ID'];
				}
				else $val = false;
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='video')
		{
			if(!is_array($val))
			{
				$width = (int)$this->GetFloatVal($fieldSettings['VIDEO_WIDTH']);
				$height = (int)$this->GetFloatVal($fieldSettings['VIDEO_HEIGHT']);
				$val = Array('VALUE' => Array(
					'PATH' => $val,
					'WIDTH' => ($width > 0 ? $width : 400),
					'HEIGHT' => ($height > 0 ? $height : 300),
					'TITLE' => '',
					'DURATION' => '',
					'AUTHOR' => '',
					'DATE' => '',
					'DESC' => ''
				));
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='SAsproMaxRegionPhone')
		{
			if(!is_array($val) && strlen($val) > 0)$val = array('VALUE'=>\KdaIE\Utils::JsObjectToPhp($val));
		}
		elseif($arProp['USER_TYPE']=='DateTime' || $arProp['USER_TYPE']=='Date')
		{
			$val = $this->GetDateVal($val, ($arProp['USER_TYPE']=='Date' ? 'PART' : 'FULL'));
		}
		elseif($arProp['PROPERTY_TYPE']=='N' && $arProp['USER_TYPE']=='ym_service_category')
		{
			$val = $this->GetYMCategoryValue($val);
		}
		elseif($arProp['PROPERTY_TYPE']=='N' && $arProp['USER_TYPE']=='mcart_property_with_measure_units')
		{
			$lib = intval($arProp["LINK_IBLOCK_ID"]);
            $fm = trim($arProp["USER_TYPE_SETTINGS"]["FIELD_MULTIPLIER"]); 
			$fb = trim($arProp["USER_TYPE_SETTINGS"]["FIELD_BASE"]); 
			$ei = trim($arProp["USER_TYPE_SETTINGS"]["ELEMENT_ID"]);
			if($lib && $ei && strlen($fm) > 0 && strlen($fb) > 0)
			{
				if(!isset($this->mcartPropMeasure)) $this->mcartPropMeasure = array();
				if(!isset($this->mcartPropMeasure[$arProp['ID']]))
				{
					$arMes = array();
					$dbRes = \CIblockElement::GetList(array(), array('IBLOCK_ID'=>$lib, array('LOGIC'=>'OR', array('PROPERTY_'.$fb=>$ei), array('ID'=>$ei))), false, array('nTopCount'=>100), array('ID', 'NAME', 'PROPERTY_'.$fm, 'PROPERTY_ALTERNATIVE'));
					while($arr = $dbRes->Fetch())
					{
						if(!array_key_exists($arr['ID'], $arMes))
						{
							$arMes[$arr['ID']] = array(
								'NAMES' => array($arr['NAME']),
								'MULTIPLIER' => $arr['PROPERTY_'.$fm.'_VALUE'],
							);
						}
						if(strlen($arr['PROPERTY_ALTERNATIVE_VALUE']) > 0)
						{
							$arMes[$arr['ID']]['NAMES'][] = $arr['PROPERTY_ALTERNATIVE_VALUE'];
						}
					}
					$this->mcartPropMeasure[$arProp['ID']] = $arMes;
				}
				$vName = $vMpl = $vId = '';
				foreach($this->mcartPropMeasure[$arProp['ID']] as $mesId=>$mes)
				{
					foreach($mes['NAMES'] as $key=>$name)
					{
						if((strpos(ToLower($val), ToLower($name))!==false && strlen($name) > strlen($vName)) || (strpos($val, $name)!==false && strlen($name)==strlen($vName)))
						{
							$vName = $name;
							$vMpl = $mes['MULTIPLIER'];
							$vId = $mesId;
						}
					}
				}
				if(strlen($vName) > 0)
				{
					$valWoUnit = trim(str_replace(ToLower($vName), '', ToLower($val)));
					$val = array('VALUE'=>array('VALUE'=>$valWoUnit, 'ELEMENT_ID'=>$vId, 'BASE_VALUE'=>$this->GetFloatVal($valWoUnit)*$this->GetFloatVal($vMpl)));
				}
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='N')
		{
			if(strlen($val) > 0 && (int)$arProp['VERSION']==2)
			{
				if(preg_match('/\d/', $val)) $val = $this->GetFloatVal($val);
				else $val = '';
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='E' || $arProp['USER_TYPE']=='ElementXmlID')
		{
			$isMultiple = (bool)($arProp['MULTIPLE']=='Y');
			$allowNF = !(bool)($fieldSettings['REL_ELEMENT_ALLOW_ORIG'] == 'Y');
			$val = $this->GetIblockElementValue($arProp, $val, $fieldSettings, true, $allowNF, $isMultiple);
			if($isMultiple && is_array($val))
			{
				$val = array('VALUES'=>$val);
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='G')
		{
			$val = $this->GetIblockSectionValue($arProp, $val, $fieldSettings, true);
		}
		elseif($arProp['PROPERTY_TYPE']=='R' && $arProp['USER_TYPE']=='RegionsList' && \Bitrix\Main\Loader::includeModule("sotbit.regions"))
		{
			if(strlen($val) > 0)
			{
				if($arRegion = \Sotbit\Regions\Internals\RegionsTable::getList(array('filter'=>array('LOGIC'=>'OR', array('=NAME'=>trim($val)), array('ID'=>trim($val))), 'select'=>array('ID')))->Fetch()) $val = $arRegion['ID'];
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='C')
		{
			if(!is_array($val) && strlen($val) > 0 && function_exists('json_decode')) $val = json_decode($val, true);
			if(!is_array($val)) $val = array();
			if(!empty($val) && !array_key_exists('VALUE', $val)) $val = array('VALUE' => $val);
		}

		return $val;
	}
	
	public function GetPropValueEclickonset($arProp, $val)
	{
		if($arProp['MULTIPLE'] && is_array($val) && isset($val[0]))
		{
			foreach($val as $k=>$v)
			{
				$val[$k] = $this->GetPropValueEclickonset($arProp, $v);
			}
		}
		else
		{
			
			if(!is_array($val))
			{
				$val = array(
					'VALUE'=>$val,
					'DESCRIPTION'=>''
				);
			}
			$val['VALUE'] = array('item' => (array_key_exists('VALUE', $val) ? $val['VALUE'] : ''));
			if(array_key_exists('DESCRIPTION', $val) && ($arData = unserialize($val['DESCRIPTION'])) && is_array($arData))
			{
				$val['VALUE'] = array_merge($val['VALUE'], $arData);
			}
		}
		return $val;
	}
	
	public function GetDefaultElementFields(&$arElement, $iblockFields)
	{
		$arDefaultFields = array('ACTIVE', 'ACTIVE_FROM', 'ACTIVE_TO', 'NAME', 'PREVIEW_TEXT_TYPE', 'PREVIEW_TEXT', 'DETAIL_TEXT_TYPE', 'DETAIL_TEXT');
		foreach($arDefaultFields as $fieldName)
		{
			if(!isset($arElement[$fieldName]) && $iblockFields[$fieldName]['IS_REQUIRED']=='Y' && isset($iblockFields[$fieldName]['DEFAULT_VALUE']) && is_string($iblockFields[$fieldName]['DEFAULT_VALUE']) && strlen($iblockFields[$fieldName]['DEFAULT_VALUE']) > 0)
			{
				$arElement[$fieldName] = $iblockFields[$fieldName]['DEFAULT_VALUE'];
				if($fieldName=='ACTIVE_FROM')
				{
					if($arElement[$fieldName]=='=now') $arElement[$fieldName] = ConvertTimeStamp(false, "FULL");
					elseif($arElement[$fieldName]=='=today') $arElement[$fieldName] = ConvertTimeStamp(false, "SHORT");
					else unset($arElement[$fieldName]);
				}
				elseif($fieldName=='ACTIVE_TO')
				{
					if((int)$arElement[$fieldName] > 0) $arElement[$fieldName] = ConvertTimeStamp(time()+(int)$arElement[$fieldName]*24*60*60, "FULL");
				}
			}
		}
		$this->GenerateElementCode($arElement, $iblockFields);
	}
	
	public function GenerateElementCode(&$arElement, $iblockFields)
	{
		if(($iblockFields['CODE']['IS_REQUIRED']=='Y' || $iblockFields['CODE']['DEFAULT_VALUE']['TRANSLITERATION']=='Y') && strlen($arElement['CODE'])==0 && strlen($arElement['NAME'])>0)
		{
			$arElement['CODE'] = $this->Str2Url($arElement['NAME'], $iblockFields['CODE']['DEFAULT_VALUE']);
			if($iblockFields['CODE']['DEFAULT_VALUE']['UNIQUE']=='Y')
			{
				$i = 0;
				while(($tmpCode = $arElement['CODE'].($i ? '-'.mt_rand() : '')) && \Bitrix\KdaImportexcel\DataManager\IblockElementTable::ExistsElement(array('IBLOCK_ID'=>$arElement['IBLOCK_ID'], '=CODE'=>$tmpCode)) && ++$i){}
				$arElement['CODE'] = $tmpCode;
			}
		}
	}
	
	public function GetIblockFields($IBLOCK_ID)
	{
		if(!$this->iblockFields[$IBLOCK_ID])
		{
			$this->iblockFields[$IBLOCK_ID] = CIBlock::GetFields($IBLOCK_ID);
		}
		return $this->iblockFields[$IBLOCK_ID];
	}
	
	public function GetIblockSectionFields($IBLOCK_ID)
	{
		if(!isset($this->iblockSectionFields[$IBLOCK_ID]))
		{
			$dbRes = CUserTypeEntity::GetList(array(), array('ENTITY_ID' => 'IBLOCK_'.$IBLOCK_ID.'_SECTION'));
			$arProps = array();
			while($arr = $dbRes->Fetch())
			{
				$arProps[$arr['FIELD_NAME']] = $arr;
			}
			$this->iblockSectionFields[$IBLOCK_ID] = $arProps;
		}
		return $this->iblockSectionFields[$IBLOCK_ID];
	}
	
	public function GetIblockElementValue($arProp, $val, $fsettings, $bAdd = false, $allowNF = false, $allowMultiple = false)
	{
		if(is_array($val) && count(preg_grep('/\D/', array_keys($val)))==0)
		{
			foreach($val as $k=>$v)
			{
				$val[$k] = $this->GetIblockElementValue($arProp, $v, $fsettings, $bAdd, $allowNF);
			}
			return $val;
		}
		if($arProp['USER_TYPE']=='ElementXmlID')
		{
			$bAdd = false;
			if(!$arProp['LINK_IBLOCK_ID'])
			{
				if($fsettings['CHANGE_LINKED_IBLOCK']=='Y' && !empty($fsettings['LINKED_IBLOCK'])) $arProp['LINK_IBLOCK_ID'] = $fsettings['LINKED_IBLOCK'];
				else $arProp['LINK_IBLOCK_ID'] = $this->iblockId;
			}
		}
		if(is_array($val) && isset($val['PRIMARY'])) return $this->GetIblockElementValueEx($arProp, $val, $bAdd, $allowNF, $allowMultiple);
		if(is_array($val)) $val = current($val);
		if(strlen($val)==0) return $val;
		$relField = $fsettings['REL_ELEMENT_FIELD'];
		if((!$relField || $relField=='IE_ID') && !is_numeric($val))
		{
			$relField = 'IE_NAME';
			$bAdd = false;
		}
		if(!$relField) $relField = 'IE_ID';
		if(($relField && $arProp['LINK_IBLOCK_ID']) || $relField=='IE_ID')
		{
			$IBLOCK_ID = (int)(is_array($arProp['LINK_IBLOCK_ID']) ? current($arProp['LINK_IBLOCK_ID']) : $arProp['LINK_IBLOCK_ID']);
			$propsDef = ($IBLOCK_ID > 0 ? $this->GetIblockProperties($IBLOCK_ID) : array());
			$arFilter = ($arProp['LINK_IBLOCK_ID'] ? array('IBLOCK_ID'=>$arProp['LINK_IBLOCK_ID']) : array());
			$filterVal = $val;
			if(!is_array($filterVal) && strlen($this->Trim($filterVal))!=strlen($filterVal)) $filterVal = array($filterVal, $this->Trim($filterVal));
			if(strpos($relField, 'IE_')===0)
			{
				$arFilter['='.substr($relField, 3)] = $filterVal;
			}
			elseif(strpos($relField, 'IP_PROP')===0)
			{
				$uid = substr($relField, 7);
				if($propsDef[$uid]['PROPERTY_TYPE']=='L')
				{
					$arFilter['=PROPERTY_'.$uid.'_VALUE'] = $filterVal;
				}
				else
				{
					/*if($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='directory')
					{
						$val = $this->GetHighloadBlockValue($arProp, $val);
					}*/
					$arFilter['=PROPERTY_'.$uid] = $filterVal;
				}
			}

			$resField = ($arProp['USER_TYPE']=='ElementXmlID' ? 'XML_ID' : 'ID');
			//$dbRes = CIblockElement::GetList(array('ID'=>'ASC'), $arFilter, false, array('nTopCount'=>1), array('ID'));
			$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFilter, array('ID', 'XML_ID'), array('ID'=>'ASC'), ($allowMultiple ? false : 1));
			if($arElem = $dbRes->Fetch())
			{
				$val = $arElem[$resField];
				if($allowMultiple)
				{
					$arVals = array();
					while($arElem = $dbRes->Fetch())
					{
						$arVals[] = $arElem[$resField];
					}
					if(count($arVals) > 0)
					{
						array_unshift($arVals, $val);
						$val = array_values($arVals);
					}
				}
			}
			elseif($bAdd && ($arFilter['NAME'] || $arFilter['=NAME']) && ($arFilter['IBLOCK_ID'] || $arFilter['=IBLOCK_ID']))
			{
				$arFields = array();
				foreach($arFilter as $k=>$v)
				{
					$arFields[str_replace('=', '', $k)] = $v;
				}
				$iblockFields = $this->GetIblockFields($arFields['IBLOCK_ID']);
				$this->GenerateElementCode($arFields, $iblockFields);
				$el = new CIblockElement();
				$val = $el->Add($arFields, false, true, true);
				$this->AddTagIblock($arFields['IBLOCK_ID']);
			}
			elseif($allowNF)
			{
				return false;
			}
		}

		return $val;
	}
	
	public function GetIblockElementValueEx($arProp, $val, $bAdd = false, $allowNF = false, $allowMultiple = false)
	{
		$IBLOCK_ID = (int)$arProp['LINK_IBLOCK_ID'];
		$propsDef = ($IBLOCK_ID > 0 ? $this->GetIblockProperties($IBLOCK_ID) : array());
		$defaultVal = current($val['PRIMARY']);
		$arElemFields = $arPropFields = $arElemFields2 = $arPropFields2 = array();
		if(isset($val['EXTRA']) && is_array($val['EXTRA']))
		{
			foreach($val['EXTRA'] as $fn=>$fv)
			{
				if(strpos($fn, 'IE_')===0)
				{
					$uid = substr($fn, 3);
					if($uid!=='ID') $arElemFields[$uid] = $fv;
				}
				elseif(strpos($fn, 'IP_PROP')===0)
				{
					$uid = substr($fn, 7);
					$arPropFields[$uid] = $fv;
				}
			}
			$arElemFields2 = $arElemFields;
			$arPropFields2 = $arPropFields;
		}
		$arFilter = array();
		foreach($val['PRIMARY'] as $fn=>$fv)
		{
			if(!is_array($fv) && strlen($this->Trim($fv))!=strlen($fv)) $fv = array($fv, $this->Trim($fv));
			elseif(!is_array($fv) && strlen($fv)==0) continue;
			if(strpos($fn, 'IE_')===0)
			{
				$uid = substr($fn, 3);
				//so slow 
				/*if($uid=='ID') $arFilter[] = array('LOGIC'=>'OR', array('=ID' => $fv), array('=NAME' => $fv));
				else
				{*/
					if($uid=='ID' && !is_numeric($fv)){$uid = 'NAME'; $bAdd = false;}
					$arFilter['='.$uid] = $fv;
					$arElemFields2[$uid] = $fv;
				//}
			}
			elseif(strpos($fn, 'IP_PROP')===0)
			{
				$uid = substr($fn, 7);
				if($propsDef[$uid]['MULTIPLE']=='Y' && !is_array($fv) && strpos($fv, $this->params['ELEMENT_MULTIPLE_SEPARATOR'])!==false)
				{
					$fv = array_map(array($this, 'Trim'), explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $fv));
				}
				if($propsDef[$uid]['PROPERTY_TYPE']=='L')
				{
					$arFilter['=PROPERTY_'.$uid.'_VALUE'] = $fv;
				}
				elseif($propsDef[$uid]['PROPERTY_TYPE']=='S' && $propsDef[$uid]['USER_TYPE']=='directory')
				{
					$arFilter['=PROPERTY_'.$uid] = $this->GetHighloadBlockValue($propsDef[$uid], $fv);
				}
				elseif($propsDef[$uid]['PROPERTY_TYPE']=='E')
				{
					$arFilter['=PROPERTY_'.$uid] = $this->GetIblockElementValue($propsDef[$uid], $fv, $this->fieldSettings[$fn]);
				}
				else $arFilter['=PROPERTY_'.$uid] = $fv;
				$arPropFields2[$uid] = $fv;
			}
		}
		if(count($arFilter) > 0 && $IBLOCK_ID > 0)
		{
			$arFilter['IBLOCK_ID'] = $arElemFields2['IBLOCK_ID'] = $IBLOCK_ID;
			$arFilter['CHECK_PERMISSIONS'] = 'N';
		}
		else return $defaultVal;
		
		$this->logger->SetDisableLog();
		$resField = ($arProp['USER_TYPE']=='ElementXmlID' ? 'XML_ID' : 'ID');
		$arKeys = array_merge(array('ID', 'XML_ID', 'NAME', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE'), array_keys($arElemFields));
		$dbRes = \Bitrix\KdaImportexcel\DataManager\IblockElementTable::GetListComp($arFilter, $arKeys, array('ID'=>'ASC'), ($allowMultiple ? false : 1));
		if($arElem = $dbRes->Fetch())
		{
			$val = $arElem[$resField];
			if($allowMultiple)
			{
				$arVals = array();
				while($arElem = $dbRes->Fetch())
				{
					$arVals[] = $arElem[$resField];
				}
				if(count($arVals) > 0)
				{
					array_unshift($arVals, $val);
					$val = array_values($arVals);
				}
			}
			else
			{
				if(count($arElemFields) > 0)
				{
					$this->UpdateElement($arElem['ID'], $IBLOCK_ID, $arElemFields, $arElem);
				}
				if(count($arPropFields) > 0) $this->SaveProperties($arElem['ID'], $IBLOCK_ID, $arPropFields);
			}				
		}
		elseif($bAdd && $arElemFields2['NAME'] && $IBLOCK_ID > 0)
		{
			$iblockFields = $this->GetIblockFields($IBLOCK_ID);
			$this->GetDefaultElementFields($arElemFields2, $iblockFields);
			if($val = $this->AddElement($arElemFields2))
			{
				if(count($arPropFields2) > 0) $this->SaveProperties($val, $IBLOCK_ID, $arPropFields2);
				$this->AddTagIblock($IBLOCK_ID);
			}
		}
		elseif($allowNF) $val = false;
		else $val = $defaultVal;
		$this->logger->SetEnableLog();
		return $val;
	}
	
	public function GetIblockSectionValue($arProp, $val, $fsettings, $bAdd = false, $allowNF = false)
	{
		$relField = $fsettings['REL_SECTION_FIELD'];
		if((!$relField || $relField=='ID') && !is_numeric($val))
		{
			$bAdd = false;
			$relField = 'NAME';
		}
		if($relField && $relField!='ID' && $val && $arProp['LINK_IBLOCK_ID'])
		{
			$IBLOCK_ID = $arProp['LINK_IBLOCK_ID'];
			$arFilter = array(
				'IBLOCK_ID' => $IBLOCK_ID ,
				$relField => $val,
				'CHECK_PERMISSIONS' => 'N'
			);
			$dbRes = CIblockSection::GetList(array('ID'=>'ASC'), $arFilter, false, array('ID'), array('nTopCount'=>1));
			if($arElem = $dbRes->Fetch())
			{
				$val = $arElem['ID'];
			}
			elseif($bAdd && $relField=='NAME')
			{
				$arFields = array(
					"IBLOCK_ID" => $IBLOCK_ID ,
					"NAME" => $val
				);
				$iblockFields = $this->GetIblockFields($IBLOCK_ID );
				if(($iblockFields['SECTION_CODE']['IS_REQUIRED']=='Y' || $iblockFields['SECTION_CODE']['DEFAULT_VALUE']['TRANSLITERATION']=='Y') && strlen($arFields['CODE'])==0)
				{
					$arFields['CODE'] = $this->Str2Url($arFields['NAME'], $iblockFields['SECTION_CODE']['DEFAULT_VALUE']);
				}
				$bs = new CIBlockSection;
				$sectId = $j = 0;
				$code = $arFields['CODE'];
				while($j<1000 && !($sectId = $bs->Add($arFields, true, true, true)) && ($arFields['CODE'] = $code.strval(++$j))){}
				$val = $sectId;
			}
			else $val = '';
		}
		return $val;
	}
	
	public function GetUserFieldEnum($val, $fieldParam)
	{		
		if(!isset($this->ufEnum)) $this->ufEnum = array();
		if(!$this->ufEnum[$fieldParam['ID']])
		{
			$arEnumVals = array();
			$fenum = new \CUserFieldEnum();
			$dbRes = $fenum->GetList(array(), array('USER_FIELD_ID'=>$fieldParam['ID']));
			while($arr = $dbRes->Fetch())
			{
				$arEnumVals[trim($arr['VALUE'])] = $arr['ID'];
			}
			$this->ufEnum[$fieldParam['ID']] = $arEnumVals;
		}
		
		$val = trim($val);
		$arEnumVals = $this->ufEnum[$fieldParam['ID']];
		if(!isset($arEnumVals[$val]))
		{
			$fenum = new \CUserFieldEnum();
			$arEnumValsOrig = array();
			$dbRes = $fenum->GetList(array(), array('USER_FIELD_ID'=>$fieldParam['ID']));
			while($arr = $dbRes->Fetch())
			{
				$arEnumValsOrig[$arr['ID']] = $arr;
			}
			$arEnumValsOrig['n0'] = array('VALUE'=>$val);
			$fenum->SetEnumValues($fieldParam['ID'], $arEnumValsOrig);

			$arEnumVals = array();
			$dbRes = $fenum->GetList(array(), array('USER_FIELD_ID'=>$fieldParam['ID']));
			while($arr = $dbRes->Fetch())
			{
				$arEnumVals[trim($arr['VALUE'])] = $arr['ID'];
			}
			$this->ufEnum[$fieldParam['ID']] = $arEnumVals;
		}
		return $arEnumVals[$val];
	}
	
	public function GetUserFieldEnumDefaultVal($fieldParam)
	{		
		if(!isset($this->ufEnumDefault)) $this->ufEnumDefault = array();
		if(!array_key_exists($fieldParam['ID'], $this->ufEnumDefault))
		{
			$val = ($fieldParam['MULTIPLE']=='Y' ? array() : '');
			$fenum = new \CUserFieldEnum();
			$dbRes = $fenum->GetList(array(), array('USER_FIELD_ID'=>$fieldParam['ID'], 'DEF'=>'Y'));
			while($arr = $dbRes->Fetch())
			{
				if($fieldParam['MULTIPLE']=='Y') $val[] = $arr['ID'];
				else $val = $arr['ID'];
			}
			$this->ufEnumDefault[$fieldParam['ID']] = $val;
		}
		return $this->ufEnumDefault[$fieldParam['ID']];
	}
	
	public function GetYMCategoryValue($val)
	{
		if($val && Loader::includeModule('yandex.market') && is_callable('\Yandex\Market\Ui\UserField\ServiceCategory\Provider', 'GetList'))
		{
			if(!isset($this->ymCategories) || !is_array($this->ymCategories))
			{
				$arResult = \Yandex\Market\Ui\UserField\ServiceCategory\Provider::GetList();
				$arCategories = array();
				$currentTree = array();
				$currentTreeDepth = 0;
				foreach ($arResult as $sectionKey => $section)
				{
					if ($section['DEPTH_LEVEL'] < $currentTreeDepth)
					{
						array_splice($currentTree, $section['DEPTH_LEVEL']);
					}
					$currentTree[$section['DEPTH_LEVEL']] =  $section['NAME'];
					$currentTreeDepth = $section['DEPTH_LEVEL'];
					$arCategories[implode(' / ', $currentTree)] = $section['ID'];
				}
				$this->ymCategories = $arCategories;
			}
			return (isset($this->ymCategories[$val]) ? $this->ymCategories[$val] : $val);
		}
		return $val;
	}
	
	public function GetListPropertyValue($arProp, $val, $create=true)
	{
		if(!is_array($val)) $val = array('VALUE'=>$val);
		if($val['VALUE']!==false && strlen($val['VALUE']) > 0)
		{
			$cacheVals = $val['VALUE'];
			if(!isset($this->propVals[$arProp['ID']][$cacheVals]))
			{
				$dbRes = $this->GetIblockPropEnum(array("PROPERTY_ID"=>$arProp['ID'], "=VALUE"=>$val['VALUE']));
				if($arPropEnum = $dbRes->Fetch())
				{
					$arPropFields = $val;
					unset($arPropFields['VALUE']);
					$this->CheckXmlIdOfListProperty($arPropFields, $arProp['ID']);
					if(count($arPropFields) > 0)
					{
						$ibpenum = new CIBlockPropertyEnum;
						$ibpenum->Update($arPropEnum['ID'], $arPropFields);
					}
					$this->propVals[$arProp['ID']][$cacheVals] = $arPropEnum['ID'];
				}
				else
				{
					$this->propVals[$arProp['ID']][$cacheVals] = false;
					if($create)
					{
						if(!isset($val['XML_ID'])) $val['XML_ID'] = $this->Str2Url($val['VALUE']);
						$this->CheckXmlIdOfListProperty($val, $arProp['ID']);
						$ibpenum = new CIBlockPropertyEnum;
						if($propId = $ibpenum->Add(array_merge($val, array('PROPERTY_ID'=>$arProp['ID']))))
						{
							$this->propVals[$arProp['ID']][$cacheVals] = $propId;
						}
					}
				}
			}
			$val = $this->propVals[$arProp['ID']][$cacheVals];
		}
		elseif(!isset($val['VALUE']) && strlen($val['XML_ID']) > 0)
		{
			$cacheVals = 'XML_ID|||'.$val['XML_ID'];
			if(!isset($this->propVals[$arProp['ID']][$cacheVals]))
			{
				$dbRes = $this->GetIblockPropEnum(array("PROPERTY_ID"=>$arProp['ID'], "=XML_ID"=>$val['XML_ID']));
				if($arPropEnum = $dbRes->Fetch())
				{
					$this->propVals[$arProp['ID']][$cacheVals] = $arPropEnum['ID'];
				}
				else
				{
					$this->propVals[$arProp['ID']][$cacheVals] = false;
				}
			}
			$val = $this->propVals[$arProp['ID']][$cacheVals];
		}
		return (!is_array($val) ? $val : false);
	}
	
	public function CheckXmlIdOfListProperty(&$val, $propID)
	{
		if(isset($val['XML_ID']))
		{
			$val['XML_ID'] = trim($val['XML_ID']);
			if(strlen($val['XML_ID'])==0)
			{
				unset($val['XML_ID']);
			}
			else
			{
				$dbRes2 = $this->GetIblockPropEnum(array("PROPERTY_ID"=>$propID, "=XML_ID"=>$val['XML_ID']));
				if($arPropEnum2 = $dbRes2->Fetch())
				{
					unset($val['XML_ID']);
				}
			}
		}
	}
	
	public function GetHighloadBlockValue($arProp, $val, $bAdd=false)
	{
		if(is_array($val))
		{
			if(count($val)==1 && array_key_exists('UF_NAME', $val) && is_array($val['UF_NAME']))
			{
				$val = $val['UF_NAME'];
			}
			if(count(preg_grep('/\D/', array_keys($val)))==0)
			{
				foreach($val as $k=>$v)
				{
					$val[$k] = $this->GetHighloadBlockValue($arProp, $v, $bAdd);
				}
				return $val;
			}
		}

		if($val && Loader::includeModule('highloadblock') && isset($arProp['USER_TYPE_SETTINGS']['TABLE_NAME']) && $arProp['USER_TYPE_SETTINGS']['TABLE_NAME'])
		{
			$arFields = $val;
			if(!is_array($arFields))
			{
				$arFields = array('UF_NAME'=>$arFields);
			}
			if($arFields['UF_XML_ID']) $cacheKey = 'UF_XML_ID_'.$arFields['UF_XML_ID'];
			elseif($arFields['UF_NAME']) $cacheKey = 'UF_NAME_'.$arFields['UF_NAME'];
			else $cacheKey = 'CUSTOM_'.md5(serialize($arFields));

			if(!isset($this->propVals[$arProp['ID']][$cacheKey]))
			{
				if(!$this->hlbl[$arProp['ID']] || !$this->hlblFields[$arProp['ID']])
				{
					$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('TABLE_NAME'=>$arProp['USER_TYPE_SETTINGS']['TABLE_NAME'])))->fetch();
					if(!$hlblock) return false;
					if(!$this->hlbl[$arProp['ID']])
					{
						$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
						$this->hlbl[$arProp['ID']] = $entity->getDataClass();
					}
					if(!$this->hlblFields[$arProp['ID']])
					{
						$dbRes = CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
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
				foreach($arFields as $k=>$v)
				{
					if(!array_key_exists($k, $arHLFields)) unset($arFields[$k]);
				}
				/*if(count($arFields) > 1 && !$arFields['UF_NAME'] && !$arFields['UF_XML_ID'] || (!isset($arHLFields['UF_NAME']) || !isset($arHLFields['UF_XML_ID']))) return false;*/
				if(empty($arFields) || !isset($arHLFields['UF_XML_ID'])) return false;
				
				$arFilter = array();
				if(count($arFields)==1)
				{
					$this->PrepareHighLoadBlockFields($arFields, $arHLFields);
					//$arFilter = $arFields;
					foreach($arFields as $k=>$v) $arFilter['='.$k] = $v;
				}
				elseif(isset($arFields['UF_XML_ID']) && strlen($arFields['UF_XML_ID']) > 0) $arFilter = array("=UF_XML_ID"=>$arFields['UF_XML_ID']);
				elseif(isset($arFields['UF_NAME']) && strlen($arFields['UF_NAME']) > 0) $arFilter = array("=UF_NAME"=>$arFields['UF_NAME']);
				if(count($arFilter)==0) return false;
				$dbRes2 = $entityDataClass::GetList(array('filter'=>$arFilter, 'select'=>array_merge(array('ID', 'UF_XML_ID'), array_keys($arFields)), 'limit'=>1));
				if($arr2 = $dbRes2->Fetch())
				{
					if(count($arFields) > 1)
					{
						$this->PrepareHighLoadBlockFields($arFields, $arHLFields, $arr2);
						$entityDataClass::Update($arr2['ID'], $arFields);
					}
					$this->propVals[$arProp['ID']][$cacheKey] = $arr2['UF_XML_ID'];
				}
				else
				{
					$this->PrepareHighLoadBlockFields($arFields, $arHLFields);
					if(!$arFields['UF_NAME']) return false;
					if(!$arFields['UF_XML_ID']) $arFields['UF_XML_ID'] = $this->Str2Url($arFields['UF_NAME'], array(), false);
					if(!$bAdd) return $arFields['UF_XML_ID'];
					if(!array_key_exists('UF_XML_ID', $arFilter) && !array_key_exists('=UF_XML_ID', $arFilter))
					{
						$xmlId = $arFields['UF_XML_ID'];
						while($entityDataClass::GetList(array('filter'=>array('=UF_XML_ID'=>$arFields['UF_XML_ID']), 'select'=>array('ID'), 'limit'=>1))->Fetch())
						{
							$arFields['UF_XML_ID'] = $xmlId.'-'.mt_rand();
						}
					}
					$dbRes = $entityDataClass::Add($arFields);
					if($dbRes->isSuccess())
					{
						$this->propVals[$arProp['ID']][$cacheKey] = $arFields['UF_XML_ID'];
					}
					else
					{
						$this->propVals[$arProp['ID']][$cacheKey] = false;
						$this->Err(sprintf(Loc::getMessage("KDA_IE_ADD_HLBLELEM_ERROR"), implode(', ', $dbRes->GetErrorMessages()), $arProp['NAME'], $this->worksheetNumForSave+1, $this->worksheetCurrentRow));
					}
				}
			}
			return $this->propVals[$arProp['ID']][$cacheKey];
		}
		return $val;
	}
	
	public function PrepareHighLoadBlockFields(&$arFields, $arHLFields, $arOldVals=array())
	{
		foreach($arFields as $k=>$v)
		{
			if(!isset($arHLFields[$k]))
			{
				unset($arFields[$k]);
				continue;
			}
			$type = $arHLFields[$k]['USER_TYPE_ID'];
			$settings = $arHLFields[$k]['SETTINGS'];
			if($arHLFields[$k]['MULTIPLE']=='Y')
			{
				$v = array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $v));
				$arFields[$k] = array();
				foreach($v as $k2=>$v2)
				{
					$arFields[$k][$k2] = $this->GetHighLoadBlockFieldVal($v2, $type, $settings, $arOldVals[$k]);
				}
				if($type=='file' && count(array_diff($arFields[$k], array('')))==0) unset($arFields[$k]);
			}
			else
			{
				$arFields[$k] = $this->GetHighLoadBlockFieldVal($v, $type, $settings, $arOldVals[$k]);
				if($type=='file' && !is_array($arFields[$k])) unset($arFields[$k]);
			}
		}
	}
	
	public function GetHighLoadBlockFieldVal($v, $type, $settings, $oldVal='')
	{
		if($type=='file')
		{
			$arFile = $this->GetFileArray($v, array(), '', $oldVal);
			if(empty($arFile) || array_key_exists('old_id', $arFile))
			{
				$arFile = '';
			}
			elseif($oldVal)
			{
				$arFile['del'] = 'Y';
				$arFile['old_id'] = $oldVal;
			}
			return $arFile;
		}
		elseif($type=='integer' || $type=='double')
		{
			return $this->GetFloatVal($v);
		}
		elseif($type=='datetime')
		{
			return $this->GetDateVal($v);
		}
		elseif($type=='date')
		{
			return $this->GetDateVal($v, 'PART');
		}
		elseif($type=='boolean')
		{
			return $this->GetHLBoolValue($v);
		}
		elseif($type=='hlblock')
		{
			return $this->GetHLHLValue($v, $settings);
		}
		else
		{
			return $v;
		}
	}
	
	public function GetHLHLValue($val, $arSettings)
	{
		if(!Loader::includeModule('highloadblock')) return $val;
		$hlblId = $arSettings['HLBLOCK_ID'];
		$fieldId = $arSettings['HLFIELD_ID'];
		if($val && $hlblId && $fieldId)
		{
			if(!is_array($this->hlhlbl)) $this->hlhlbl = array();
			if(!is_array($this->hlhlblFields)) $this->hlhlblFields = array();
			if(!is_array($this->hlPropVals)) $this->hlPropVals = array();

			if(!isset($this->hlPropVals[$fieldId][$val]))
			{
				if(!$this->hlhlbl[$hlblId] || !$this->hlhlblFields[$hlblId])
				{
					$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('ID'=>$hlblId)))->fetch();
					if(!$this->hlhlbl[$hlblId])
					{
						$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
						$this->hlhlbl[$hlblId] = $entity->getDataClass();
					}
					if(!$this->hlhlblFields[$hlblId])
					{
						$dbRes = CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
						$arHLFields = array();
						while($arHLField = $dbRes->Fetch())
						{
							$arHLFields[$arHLField['ID']] = $arHLField;
						}
						$this->hlhlblFields[$hlblId] = $arHLFields;
					}
				}
				
				$entityDataClass = $this->hlhlbl[$hlblId];
				$arHLFields = $this->hlhlblFields[$hlblId];
				
				if(!$arHLFields[$fieldId]) return false;
				
				$dbRes2 = $entityDataClass::GetList(array('filter'=>array($arHLFields[$fieldId]['FIELD_NAME']=>$val), 'select'=>array('ID'), 'limit'=>1));
				if($arr2 = $dbRes2->Fetch())
				{
					$this->hlPropVals[$fieldId][$val] = $arr2['ID'];
				}
				else
				{
					$arFields = array($arHLFields[$fieldId]['FIELD_NAME']=>$val);
					$dbRes2 = $entityDataClass::Add($arFields);
					$this->hlPropVals[$fieldId][$val] = $dbRes2->GetID();
				}
			}
			return $this->hlPropVals[$fieldId][$val];
		}
		return $val;
	}
	
	public function PrepareProductAdd(&$arFieldsProduct, $ID, $IBLOCK_ID)
	{
		if(!empty($arFieldsProduct)) return;
		if(!isset($this->catalogIblocks)) $this->catalogIblocks = array();
		if(!isset($this->catalogIblocks[$IBLOCK_ID]))
		{
			$this->catalogIblocks[$IBLOCK_ID] = false;
			if(is_callable(array('\Bitrix\Catalog\CatalogIblockTable', 'getList')))
			{
				if($arCatalog = \Bitrix\Catalog\CatalogIblockTable::getList(array('filter'=>array('IBLOCK_ID'=>$IBLOCK_ID), 'limit'=>1))->Fetch())
				{
					$this->catalogIblocks[$IBLOCK_ID] = true;
				}				
			}
		}
		if($this->catalogIblocks[$IBLOCK_ID]) $arFieldsProduct['ID'] = $ID;
	}
	
	public function AfterSaveProduct(&$arFieldsElement, $ID, $IBLOCK_ID, $isUpdate=false, $isOffer=false)
	{
		$this->SetProductQuantity($ID, $IBLOCK_ID);
		
		if(($this->params['ELEMENT_NO_QUANTITY_DEACTIVATE']=='Y' && floatval($this->productor->GetProductQuantity($ID, $IBLOCK_ID))<=0)
			|| ($this->params['ELEMENT_NO_PRICE_DEACTIVATE']=='Y' && floatval($this->productor->GetProductPrice($ID, $IBLOCK_ID))<=0))
		{
			if($isUpdate) $arFieldsElement['ACTIVE'] = 'N';
			elseif(!isset($arFieldsElement['ACTIVE']) || $arFieldsElement['ACTIVE']!='N')
			{
				$el = new \CIblockElement();
				$el->Update($ID, array('ACTIVE'=>'N', 'MODIFIED_BY' => $this->GetCurUserID()), false, true, true);
				$this->AddTagIblock($IBLOCK_ID);
				
				if($isOffer && ($arOfferIblock = CKDAImportUtils::GetOfferIblockByOfferIblock($IBLOCK_ID)))
				{
					$propId = $arOfferIblock['OFFERS_PROPERTY_ID'];
					$arOffer = \CIblockElement::GetList(array(), array('ID'=>$ID), false, false, array('PROPERTY_'.$propId, 'PROPERTY_'.$propId.'.ACTIVE'))->Fetch();
					if($arOffer['PROPERTY_'.$propId.'_VALUE'] > 0)
					{
						$arElem = array('ACTIVE'=>$arOffer['PROPERTY_'.$propId.'ACTIVE']);
						$this->AfterSaveProduct($arElem, $arOffer['PROPERTY_'.$propId.'_VALUE'], $arOfferIblock['IBLOCK_ID']);
					}
				}
			}
		}
	}

	public function UpdateSectionPropertyLinks($IBLOCK_ID, $propId)
	{
		$arSectionIds = array();
		$dbRes = \CIblockElement::GetList(array(), array('IBLOCK_ID'=>$IBLOCK_ID, '!PROPERTY_'.$propId=>false, '!IBLOCK_SECTION_ID'=>false), array('IBLOCK_SECTION_ID'), false, array('IBLOCK_SECTION_ID'));
		while($arr = $dbRes->Fetch())
		{
			$arSectionIds[] = $arr['IBLOCK_SECTION_ID'];
		}
		if(1 || !empty($arSectionIds))
		{
			$dbRes = \Bitrix\Iblock\SectionPropertyTable::getList(array("select" => array("SECTION_ID"), "filter" => array("=IBLOCK_ID" => $IBLOCK_ID, "=PROPERTY_ID"=>$propId)));
			while($arr = $dbRes->Fetch())
			{
				if(!in_array($arr['SECTION_ID'], $arSectionIds))
				{
					\Bitrix\Iblock\SectionPropertyTable::delete(array("IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_ID"=>$propId, "SECTION_ID"=>$arr['SECTION_ID']));
				}
				else
				{
					$arSectionIds = array_diff($arSectionIds, array($arr['SECTION_ID']));
				}
			}
			foreach($arSectionIds as $sectionId)
			{
				\Bitrix\Iblock\SectionPropertyTable::add(array("IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_ID"=>$propId, "SECTION_ID"=>$sectionId));
			}
		}
	}
	
	public function SaveProduct($ID, $IBLOCK_ID, $arProduct, $arPrices=array(), $arStores=array(), $parentID=false, $arOldData=array())
	{		
		$this->productor->SaveProduct($ID, $IBLOCK_ID, $arProduct, $arPrices, $arStores, $parentID, $arOldData);
	}
	
	public function SetProductQuantity($ID, $IBLOCK_ID=0)
	{
		$this->productor->SetProductQuantity($ID, $IBLOCK_ID);
	}
	
	public function SaveDiscount($ID, $IBLOCK_ID, $arFieldsProductDiscount, $name="", $isOffer = false)
	{
		$this->GetDiscountManager()->SaveDiscount($ID, $IBLOCK_ID, $arFieldsProductDiscount, $name, $isOffer);
	}
	
	public function RemoveExpiredDiscount()
	{
		$this->GetDiscountManager()->RemoveExpiredDiscount();
	}
	
	public function GetDiscountManager()
	{
		if(!isset($this->discountManager)) $this->discountManager = new \Bitrix\KdaImportexcel\DataManager\Discount($this);
		return $this->discountManager;
	}
	
	public function GetMeasureByStr($val)
	{
		if(!$val) return $val;
		if(!isset($this->measureList) || !is_array($this->measureList))
		{
			$this->measureList = array();
			$dbRes = CCatalogMeasure::getList(array(), array());
			while($arr = $dbRes->Fetch())
			{
				$this->measureList[$arr['ID']] = array_map('ToLower', $arr);
			}
		}
		$valCmp = trim(ToLower($val));
		foreach($this->measureList as $k=>$v)
		{
			if(in_array($valCmp, array($v['CODE'], $v['MEASURE_TITLE'], $v['SYMBOL_RUS'], $v['SYMBOL_INTL'], $v['SYMBOL_LETTER_INTL'])))
			{
				return $k;
			}
		}
		if(array_key_exists($val, $this->measureList)) return $val;
		else return '';
	}
	
	public function GetCurrencyRates()
	{
		if(!isset($this->currencyRates))
		{
			$arRates = unserialize(\Bitrix\Main\Config\Option::get(static::$moduleId, 'CURRENCY_RATES', ''));
			if(!is_array($arRates)) $arRates = array();
			if(!isset($arRates['TIME']) || $arRates['TIME'] < time() - 6*60*60)
			{
				$arRates2 = array();
				$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>20, 'disableSslVerification'=>true));
				$res = $client->get('http://www.cbr.ru/scripts/XML_daily.asp');
				if($res)
				{
					$xml = simplexml_load_string($res);
					if($xml->Valute)
					{
						foreach($xml->Valute as $val)
						{
							$numVal = $this->GetFloatVal((string)$val->Value);
							if($numVal > 0)$arRates2[(string)$val->CharCode] = (string)$numVal;
						}
					}
				}
				if(count($arRates2) > 1)
				{
					$arRates = $arRates2;
					$arRates['TIME'] = time();
					\Bitrix\Main\Config\Option::set(static::$moduleId, 'CURRENCY_RATES', serialize($arRates));
				}
			}
			if(Loader::includeModule('currency') && is_callable(array('\Bitrix\Currency\CurrencyTable', 'getList')))
			{
				$dbRes = \Bitrix\Currency\CurrencyTable::getList(array('select'=>array('CURRENCY')));
				while($arr = $dbRes->Fetch())
				{
					if(!isset($arRates[$arr['CURRENCY']])) $arRates[$arr['CURRENCY']] = \CCurrencyRates::ConvertCurrency(1, $arr['CURRENCY'], 'RUB');
				}
			}
			$this->currencyRates = $arRates;
		}
		return $this->currencyRates;
	}
	
	public function ConversionReplaceValuesFloat($m)
	{
		return $this->GetFloatVal($this->ConversionReplaceValues($m));
	}
	
	public function ConversionReplaceValues($m)
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
		
		if(preg_match('/#CELL\d+#/', $paramName))
		{
			$k = intval(substr($paramName, 5, -1)) - 1;
			if(is_array($this->currentItemValues) && isset($this->currentItemValues[$k])) $value = $this->currentItemValues[$k];
			elseif($this->worksheet && ($val = $this->worksheet->getCellByColumnAndRow($k, $this->worksheetCurrentRow)))
			{
				$value = $this->GetCalculatedValue($val);
			}
		}
		elseif(preg_match('/#CELL(\d+)([\-\+]\d+)#/', $paramName, $m2))
		{
			if($this->worksheet && ($val = $this->worksheet->getCellByColumnAndRow((int)$m2[1] - 1, $this->worksheetCurrentRow + (int)$m2[2])))
			{
				$value = $this->GetCalculatedValue($val);
			}
		}
		elseif(preg_match('/#CELL_([A-Z]+\d+)#/', $paramName, $m2))
		{
			if($this->worksheet && ($val = $this->worksheet->getCell($m2[1])))
			{
				$value = $this->GetCalculatedValue($val);
			}
		}
		elseif(preg_match('/#CELL(~+)(\d+)#/', $paramName, $m2))
		{
			$k = $m2[1].(intval($m2[2]) - 1);
			if(is_array($this->currentItemValues) && isset($this->currentItemValues[$k])) $value = $this->currentItemValues[$k];
		}
		elseif($paramName=='#VAL#')
		{
			$value = $this->currentItemFieldVal;
		}
		elseif($paramName=='#CLINK#')
		{
			if($this->useHyperlinks && strlen($this->currentFieldKey) > 0)
			{
				$value = $this->hyperlinks[$this->currentFieldKey];
			}
		}
		elseif($paramName=='#CNOTE#')
		{
			if($this->useNotes && strlen($this->currentFieldKey) > 0)
			{
				$value = $this->notes[$this->currentFieldKey];
			}
		}
		elseif($paramName=='#SEP_SECTION#')
		{
			$value = $this->stepparams['last_section'];
		}
		elseif($paramName=='#HASH#')
		{
			$hash = md5(serialize($this->currentItemValues).serialize($this->params['FIELDS_LIST'][$this->worksheetNumForSave]));
			$value = $hash;
		}
		elseif($paramName=='#FILENAME#')
		{
			$value = bx_basename($this->filename);
		}
		elseif($paramName=='#FILEDATE#')
		{
			$value = $this->GetImportFileDate();
		}
		elseif($paramName=='#SHEETNAME#')
		{
			if(array_key_exists($this->worksheetNum, $this->worksheetNames)) $value = $this->worksheetNames[$this->worksheetNum];
			else $value = (is_callable(array($this->worksheet, 'getTitle')) ? $this->worksheet->getTitle() : '');
		}
		elseif($paramName=='#ROWNUMBER#')
		{
			$value = $this->worksheetCurrentRow;
		}
		elseif($paramName=='#IMPORT_PROCESS_ID#')
		{
			$value = $this->stepparams['loggerExecId'];
		}
		elseif($paramName=='#DATETIME#')
		{
			$value = ConvertTimeStamp(false, 'FULL');
		}
		elseif(in_array($paramName, $this->rcurrencies))
		{
			$arRates = $this->GetCurrencyRates();
			$k = trim($paramName, '#');
			$value = (isset($arRates[$k]) ? floatval($arRates[$k]) : 1);
		}
		
		if($isVar)
		{
			$this->extraConvParams[$paramName] = $value;
			return '$this->extraConvParams['.$quot.$paramName.$quot.']';
		}
		else return $value;
	}
	
	public function ApplyConversions($val, $arConv, $arItem, $field=false, $iblockFields=array())
	{
		$arExpParams = array();
		$fieldName = $fieldKey = false;
		if(!is_array($field))
		{
			$fieldName = $field;
		}
		else
		{
			if($field['NAME']) $fieldName = $field['NAME'];
			if(strlen($field['KEY']) > 0) $fieldKey = $field['KEY'];
			if(strlen($field['PARENT_ID']) > 0) $arExpParams['PARENT_ID'] = $field['PARENT_ID'];
		}
		
		if(is_array($arConv))
		{
			$execConv = false;
			$this->currentItemValues = $arItem;
			$prefixPattern = '#CELL~*\d+#|#CELL\d+[\-\+]\d+#|#CELL_[A-Z]+\d+#|#VAL#|#CLINK#|#CNOTE#|#HASH#|#FILENAME#|#FILEDATE#|#SHEETNAME#|#ROWNUMBER#|#IMPORT_PROCESS_ID#|#SEP_SECTION#|#DATETIME#|'.implode('|', $this->rcurrencies);
			$prefixPattern = '/(\$\{[\'"]('.$prefixPattern.')[\'"]\}|('.$prefixPattern.'))/';
			foreach($arConv as $k=>$v)
			{
				$this->currentItemFieldVal = $val;
				$condVal = (string)$val;
				if((int)$v['CELL'] > 0)
				{
					$numCell = (int)$v['CELL'] - 1;
					if(array_key_exists($numCell, $arItem))
					{
						$condVal = (array_key_exists('~~'.$numCell, $arItem) ? $arItem['~~'.$numCell] : $arItem[$numCell]);
					}
					else
					{
						$condVal = $this->GetCalculatedValue($this->worksheet->getCellByColumnAndRow($numCell, $this->worksheetCurrentRow));
					}
				}
				if(strlen($v['FROM']) > 0) $v['FROM'] = preg_replace_callback($prefixPattern, array($this, 'ConversionReplaceValues'), $v['FROM']);
				if($v['CELL']=='ELSE') $v['WHEN'] = '';
				if(($v['CELL']=='ELSE' && !$execConv)
					|| ($v['WHEN']=='EQ' && $condVal==$v['FROM'])
					|| ($v['WHEN']=='NEQ' && $condVal!=$v['FROM'])
					|| (in_array($v['WHEN'], array('GT', 'LT', 'GEQ', 'LEQ', 'BETWEEN')) && $this->CompareNums($condVal, $v['FROM'], $v['WHEN']))
					|| ($v['WHEN']=='CONTAIN' && strpos($condVal, $v['FROM'])!==false)
					|| ($v['WHEN']=='NOT_CONTAIN' && strpos($condVal, $v['FROM'])===false)
					|| ($v['WHEN']=='BEGIN_WITH' && strpos($condVal, $v['FROM'])===0)
					|| ($v['WHEN']=='ENDS_IN' && mb_substr($condVal, -mb_strlen($v['FROM']))===$v['FROM'])
					|| ($v['WHEN']=='REGEXP' && preg_match('/'.ToLower($v['FROM']).'/i'.CKDAImportUtils::getUtfModifier(), ToLower($condVal)))
					|| ($v['WHEN']=='NOT_REGEXP' && !preg_match('/'.ToLower($v['FROM']).'/i'.CKDAImportUtils::getUtfModifier(), ToLower($condVal)))
					|| ($v['WHEN']=='EMPTY' && strlen($condVal)==0)
					|| ($v['WHEN']=='NOT_EMPTY' && strlen($condVal) > 0)
					|| ($v['WHEN']=='ANY'))
				{
					$this->currentFieldKey = $fieldKey;
					if(strlen($v['TO']) > 0) $v['TO'] = preg_replace_callback($prefixPattern, array($this, 'ConversionReplaceValues'), $v['TO']);
					if($v['THEN']=='REPLACE_TO') $val = $v['TO'];
					elseif($v['THEN']=='REMOVE_SUBSTRING' && strlen($v['TO']) > 0) $val = str_replace($v['TO'], '', $val);
					elseif($v['THEN']=='REPLACE_SUBSTRING_TO' && strlen($v['FROM']) > 0)
					{
						if($v['WHEN']=='REGEXP')
						{
							if(preg_match('/'.$v['FROM'].'/i'.CKDAImportUtils::getUtfModifier(), $val)) $val = preg_replace('/'.$v['FROM'].'/i'.CKDAImportUtils::getUtfModifier(), $v['TO'], $val);
							else $val = preg_replace('/'.ToLower($v['FROM']).'/i'.CKDAImportUtils::getUtfModifier(), $v['TO'], $val);
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
					elseif($v['THEN']=='MATH_MULTIPLY') $val = \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) * $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_DIVIDE') $val = ($this->GetFloatVal($v['TO'])==0 ? 0 : \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) / $this->GetFloatVal($v['TO'])));
					elseif($v['THEN']=='MATH_ADD') $val = \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) + $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_SUBTRACT') $val = \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) - $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_ADD_PERCENT') $val = (strlen($val) > 0 ? \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) * (1 + $this->GetFloatVal($v['TO'])/100)) : '');
					elseif($v['THEN']=='MATH_SUBTRACT_PERCENT') $val = (strlen($val) > 0 ? \Bitrix\KdaImportexcel\IUtils::GetFloatRoundVal($this->GetFloatVal($val) * (1 - $this->GetFloatVal($v['TO'])/100)) : '');
					elseif($v['THEN']=='MATH_FORMULA') $val = $this->CalcFloatValue($v['TO']);
					elseif($v['THEN']=='NOT_LOAD') $val = false;
					elseif($v['THEN']=='EXPRESSION') $val = $this->ExecuteFilterExpression($val, $v['TO'], false, $arExpParams, $field);
					elseif($v['THEN']=='STRIP_TAGS') $val = strip_tags($val);
					elseif($v['THEN']=='CLEAR_TAGS') $val = preg_replace('/<([a-z][a-z0-9:]*)[^>]*(\/?)>/i','<$1$2>', $val);
					elseif($v['THEN']=='TRANSLIT')
					{
						$arParams = array();
						if($fieldName && !empty($iblockFields))
						{
							$paramName = '';
							if($fieldName=='IE_CODE') $paramName = 'CODE';
							if(preg_match('/^ISECT\d*_CODE$/', $fieldName)) $paramName = 'SECTION_CODE';
							if($paramName && $iblockFields[$paramName]['DEFAULT_VALUE']['TRANSLITERATION']=='Y')
							{
								$arParams = $iblockFields[$paramName]['DEFAULT_VALUE'];
							}
						}
						if(strlen($v['TO']) > 0) $val = $v['TO'];
						$val = $this->Str2Url($val, $arParams);
					}
					elseif($v['THEN']=='DOWNLOAD_BY_LINK')
					{
						$val = \Bitrix\KdaImportexcel\IUtils::DownloadTextTextByLink($val, $v['TO']);
					}
					elseif($v['THEN']=='DOWNLOAD_IMAGES')
					{
						$val = \Bitrix\KdaImportexcel\IUtils::DownloadImagesFromText($val, $v['TO']);
					}
					$execConv = true;
				}
			}
		}
		return $val;
	}
	
	public function CompareNums($v1, $v2, $c)
	{
		if(preg_match('/(\d{2}\.\d{2}\.\d{4}|\d{4}\-\d{2}\-\d{2})/', $v1) && ($v1time = strtotime($v1))
			&& preg_match('/(\d{2}\.\d{2}\.\d{4}|\d{4}\-\d{2}\-\d{2})/', $v2) && ($v2time = strtotime($v2)))
		{
			$v1 = $v1time;
			$v2 = $v2time;
		}
		else
		{
			$v1 = $this->GetFloatVal($v1);
			if($c!='BETWEEN') $v2 = $this->GetFloatValWithCalc($v2);
		}
		if($c=='GT') return (bool)($v1 > $v2);
		if($c=='LT') return (bool)($v1 < $v2);
		if($c=='GEQ') return (bool)($v1 >= $v2);
		if($c=='LEQ') return (bool)($v1 <= $v2);
		if($c=='BETWEEN') return (bool)($v1 >= $this->GetFloatVal(explode('-', $v2)[0]) && $v1 <= $this->GetFloatVal(explode('-', $v2)[1]));
	}
	
	public function CalcFloatValue($val)
	{
		$val = preg_replace_callback('/(#VAL#|#CELL\d+#)/', array($this, 'ConversionReplaceValuesFloat'), $val);
		$val = preg_replace('/&#\d+;/', '', $val);
		$val = preg_replace('/[^\d\.,+\-\/*%\(\)]/', '', $val);
		if(preg_match('/[+\-\/*]/', $val))
		{
			if(!preg_match('/^\(.*\)$/', $val)) $val = '('.$val.')';
			while(preg_match_all('/\(([^\(\)]*)\)/', $val, $m))
			{
				foreach($m[1] as $k=>$v)
				{
					$subval = 0;
					while(preg_match_all('/([\d\.,]+)([\/*])(\-?[\d\.,]+)/', $v, $m2))
					{
						foreach($m2[0] as $k2=>$v2)
						{
							$subval2 = 0;
							if($m2[2][$k]=='*') $subval2 = $this->GetFloatVal($m2[1][$k])*$this->GetFloatVal($m2[3][$k]);
							elseif($m2[2][$k]=='/') $subval2 = ($this->GetFloatVal($m2[3][$k])!=0 ? $this->GetFloatVal($m2[1][$k])/$this->GetFloatVal($m2[3][$k]) : 0);
							$v = str_replace($v2, $subval2, $v);
						}
					}
					if(preg_match_all('/(^|[+\-])([\d\.,]+)%?/', $v, $m2))
					{
						$subval2 = 0;
						foreach($m2[0] as $k2=>$v2)
						{
							if(strpos($v2, '%')!==false) $subval = $subval * (1 + ($this->GetFloatVal($m2[2][$k2])/100)*($m2[1][$k2]=='-' ? -1 : 1));
							else $subval += $this->GetFloatVal($v2);

						}
					}
					$v = $subval;
					$val = str_replace($m[0][$k], $subval, $val);
				}
			}
		}
		if(strlen($val) > 0) $val = $this->GetFloatVal($val);
		return $val;
	}
	
	public function CalcFloatValuePhp($val)
	{
		$val = preg_replace_callback('/(#VAL#|#CELL\d+#)/', array($this, 'ConversionReplaceValues'), $val);
		if(preg_match('/[+\-\/*]/', $val))
		{
			try{
				$val = eval('return '.str_replace(',', '.', $val).';');
			}catch(ParseError | Error $ex){
				$val = $this->CalcFloatValue($val);
			}
		}
		if(strlen(trim($val)) > 0) $val = $this->GetFloatVal($val);
		return $val;
	}
	
	public function GetCurrentItemValues()
	{
		if(is_array($this->currentItemValues)) return $this->currentItemValues;
		else return array();
	}
	
	public static function GetPreviewData($file, $showLines, $arParams=array(), $colsCount=false, $pid=false, $list=false)
	{
		if($pid!==false) \CKDAImportProfile::getInstance()->SetImportParams($pid, '', array(), $arParams);
		$selfobj = new CKDAImportExcelStatic($arParams, $file);
		$file = $_SERVER['DOCUMENT_ROOT'].$file;
		$objReader = KDAPHPExcel_IOFactory::createReaderForFile($file);		
		if($arParams['ELEMENT_NOT_LOAD_STYLES']=='Y' && $arParams['ELEMENT_NOT_LOAD_FORMATTING']=='Y')
		{
			$objReader->setReadDataOnly(true);
		}
		if(isset($arParams['CSV_PARAMS']))
		{
			$objReader->setCsvParams($arParams['CSV_PARAMS']);
		}
		$chunkFilter = new KDAChunkReadFilter();
		$objReader->setReadFilter($chunkFilter);
		$maxLine = 1000;
		if(!$colsCount) $maxLine = max($showLines + 50, 50);
		$chunkFilter->setRows(1, $maxLine);

		$efile = $objReader->load($file);
		$arWorksheets = array();
		foreach($efile->getWorksheetIterator() as $k=>$worksheet) 
		{
			if($list!==false && $list!=$k) continue;
			$maxDrawCol = 0;
			$draws = array();
			if($arParams['ELEMENT_LOAD_IMAGES']=='Y')
			{
				$drawCollection = $worksheet->getDrawingCollection();
				if($drawCollection)
				{
					foreach($drawCollection as $drawItem)
					{
						$coord = $drawItem->getCoordinates();
						$arCoords = KDAPHPExcel_Cell::coordinateFromString($coord);
						$maxDrawCol = max($maxDrawCol, KDAPHPExcel_Cell::columnIndexFromString($arCoords[0]));
					}
				}
				//$draws = self::GetWorksheetDraws($worksheet);
				//$worksheet->getMergeCells();
			}

			$columns_count = max(KDAPHPExcel_Cell::columnIndexFromString($worksheet->getHighestDataColumn()), $maxDrawCol);
			$columns_count = min($columns_count, 16384);
			$rows_count = $worksheet->getHighestDataRow();

			$arLines = array();
			$cntLines = $emptyLines = 0;
			for ($row = 0; ($row < $rows_count && count($arLines) < min($showLines+$emptyLines, $maxLine)); $row++) 
			{
				$arLine = array();
				$bEmpty = true;
				for ($column = 0; $column < $columns_count; $column++) 
				{
					$val = $worksheet->getCellByColumnAndRow($column, $row+1);					
					$valText = $selfobj->GetCalculatedValue($val);
					if(strlen(trim($valText)) > 0) $bEmpty = false;
					
					$curLine = array('VALUE' => $valText);
					if($arParams['ELEMENT_NOT_LOAD_STYLES']!='Y')
					{
						$curLine['STYLE'] = $selfobj->GetCellStyle($val, true);
					}
					$arLine[] = $curLine;
				}

				$arLines[$row] = $arLine;
				if($bEmpty)
				{
					$emptyLines++;
				}
				$cntLines++;
			}
			
			if($colsCount)
			{
				$columns_count = $colsCount;
				$arLines = array();
				$lastEmptyLines = 0;
				for ($row = $cntLines; $row < $rows_count; $row++) 
				{
					$arLine = array();
					$bEmpty = true;
					for ($column = 0; $column < $columns_count; $column++) 
					{
						$val = $worksheet->getCellByColumnAndRow($column, $row+1);
						$valText = $selfobj->GetCalculatedValue($val);
						if(strlen(trim($valText)) > 0) $bEmpty = false;
						
						$curLine = array('VALUE' => $valText);
						if($arParams['ELEMENT_NOT_LOAD_STYLES']!='Y')
						{
							$curLine['STYLE'] = $selfobj->GetCellStyle($val, true);
						}
						$arLine[] = $curLine;
					}
					if($bEmpty) $lastEmptyLines++;
					else $lastEmptyLines = 0;
					$arLines[$row] = $arLine;
				}
				
				if($lastEmptyLines > 0)
				{
					$arLines = array_slice($arLines, 0, -$lastEmptyLines, true);
				}
			}
			
			$arCells = explode(':', $worksheet->getSelectedCells());
			$heghestRow = intval(preg_replace('/\D+/', '', end($arCells)));
			if(is_callable(array($worksheet, 'getRealHighestRow'))) $heghestRow = intval($worksheet->getRealHighestRow());
			elseif($worksheet->getHighestDataRow() > $heghestRow) $heghestRow = intval($worksheet->getHighestDataRow());
			if(stripos($file, '.csv'))
			{
				$heghestRow = CKDAImportUtils::GetFileLinesCount($file);
			}

			$arWorksheets[$k] = array(
				'title' => self::CorrectCalculatedValue($worksheet->GetTitle()),
				'show_more' => ($row < $rows_count),
				'lines_count' => $heghestRow,
				'lines' => $arLines
			);
		}
		return $arWorksheets;
	}
	
	public function GetOfferParentId()
	{
		return (isset($this->offerParentId) ? $this->offerParentId : false);
	}
	
	public function GetFieldSettings($key)
	{
		$fieldSettings = $this->fieldSettings[$key];
		if(!is_array($fieldSettings)) $fieldSettings = array();
		return $fieldSettings;
	}
	
	public function GetCurrentIblock()
	{
		return $this->iblockId;
	}
	
	public function GetCurrentOfferIblock()
	{
		if($arOfferIblock = $this->GetCachedOfferIblock($this->GetCurrentIblock()))
		{
			return $arOfferIblock['OFFERS_IBLOCK_ID'];
		}
		return false;
	}
	
	public function GetCachedOfferIblock($IBLOCK_ID)
	{
		if(!$this->iblockoffers || !isset($this->iblockoffers[$IBLOCK_ID]))
		{
			$this->iblockoffers[$IBLOCK_ID] = CKDAImportUtils::GetOfferIblock($IBLOCK_ID, true);
		}
		return $this->iblockoffers[$IBLOCK_ID];
	}
	
	public function SavePropertiesHints($arItem)
	{
		$filedList = $this->params['FIELDS_LIST'][$this->worksheetNumForSave];
		$IBLOCK_ID = $this->params['IBLOCK_ID'][$this->worksheetNumForSave];		
		foreach($filedList as $key=>$field)
		{
			if(strpos($field, 'IP_PROP')!==0 && substr($field, -12)=='_DESCRIPTION') continue;
			$k = $key;
			if(strpos($k, '_')!==false) $k = substr($k, 0, strpos($k, '_'));
			$value = $arItem[$k];
			$propId = substr($field, 7);
			$ibp = new CIBlockProperty;
			$ibp->Update($propId, array('HINT'=>$value));
			$dbRes2 = \Bitrix\Iblock\SectionPropertyTable::getList(array("select" => array("SECTION_ID", "PROPERTY_ID"), "filter" => array("=IBLOCK_ID" => $IBLOCK_ID ,"=PROPERTY_ID" => $propId)));
			while($arr2 = $dbRes2->Fetch())
			{
				CIBlockSectionPropertyLink::Set($arr2['SECTION_ID'], $arr2['PROPERTY_ID'], array('FILTER_HINT'=>$value));
			}
		}
		return false;
	}
	
	public function ClearCompositeCache($link='')
	{
		if(!class_exists('\Bitrix\Main\Composite\Helper')) return;
		require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/cache_files_cleaner.php");
		
		if(!isset($this->compositDomains) || !is_array($this->compositDomains))
		{
			$compositeOptions = \CHTMLPagesCache::getOptions();
			$compositDomains = $compositeOptions['DOMAINS'];
			if(!is_array($compositDomains)) $compositDomains = array();
			$this->compositDomains = $compositDomains;
		}
		
		if(strlen($link) > 0 && !empty($this->compositDomains))
		{
			foreach($this->compositDomains as $host)
			{
				$page = new \Bitrix\Main\Composite\Page($link, $host);
				$page->delete();	
			}
		}
	}
	
	public function AddTagIblock($IBLOCK_ID)
	{
		$IBLOCK_ID = (int)$IBLOCK_ID;
		if($IBLOCK_ID <= 0) return;
		$this->tagIblocks[$IBLOCK_ID] = $IBLOCK_ID;
	}
	
	public function ClearIblocksTagCache($checkTime = false)
	{
		if($this->params['REMOVE_CACHE_AFTER_IMPORT']=='Y') return;
		if($checkTime && (time() - $this->timeBeginTagCache < 60))  return;
		if(is_callable(array('\CIBlock', 'clearIblockTagCache')))
		{
			if(is_callable(array('\CIBlock', 'enableClearTagCache'))) \CIBlock::enableClearTagCache();
			foreach($this->tagIblocks as $IBLOCK_ID)
			{
				\CIBlock::clearIblockTagCache($IBLOCK_ID);
			}
			if(is_callable(array('\CIBlock', 'disableClearTagCache'))) \CIBlock::disableClearTagCache();
		}
		$this->tagIblocks = array();
		$this->timeBeginTagCache = time();
	}
	
	public function GetIblockPropEnum($arFilter)
	{
		if(class_exists('\Bitrix\Iblock\PropertyEnumerationTable')) $dbRes = \Bitrix\Iblock\PropertyEnumerationTable::getList(array('filter'=>$arFilter));
		else 
		{
			foreach(array('XML_ID', 'TMP_ID', 'VALUE') as $key)
			{
				if(isset($arFilter['='.$key]) && !isset($arFilter[$key]))
				{
					$arFilter[$key] = $arFilter['='.$key];
					unset($arFilter['='.$key]);
				}
			}
			$dbRes = \CIBlockPropertyEnum::GetList(array(), $arFilter);
		}
		return $dbRes;
	}
	
	public function GetCellStyle($val, $modify = false)
	{
		$style = $val->getStyle();
		if(!is_object($style)) return array();
		$arStyle = array(
			'COLOR' => $style->getFont()->getColor()->getRGB(),
			'FONT-FAMILY' => $style->getFont()->getName(),
			'FONT-SIZE' => $style->getFont()->getSize(),
			'FONT-WEIGHT' => $style->getFont()->getBold(),
			'FONT-STYLE' => $style->getFont()->getItalic(),
			'TEXT-DECORATION' => $style->getFont()->getUnderline(),
			'BACKGROUND' => ($style->getFill()->getFillType()=='solid' ? $style->getFill()->getStartColor()->getRGB() : ''),
		);
		$outlineLevel = (int)$val->getWorksheet()->getRowDimension($val->getRow())->getOutlineLevel();
		if($outlineLevel > 0)
		{
			$arStyle['TEXT-INDENT'] = $outlineLevel;
		}
		$pVersion = \CKDAImportProfile::getInstance()->GetImportParam('PROFILE_VERSION');
		$indent = (int)$style->getAlignment()->getIndent();
		if($indent==0 && $pVersion > 2)
		{
			$strVal = $val->getCalculatedValue();
			if(preg_match("/^'(\s+)\S/", $strVal, $m)) $indent = strlen($m[1]);
		}
		if($indent > 0 && $pVersion > 1) $arStyle['PADDING-LEFT'] = $indent;
		
		if($modify)
		{
			$arStyle['EXT'] = array(
				'COLOR' => $style->getFont()->getColor()->getRealRGB(),
				'BACKGROUND' => ($style->getFill()->getFillType()=='solid' ? $style->getFill()->getStartColor()->getRealRGB() : ''),
			);
		}
		
		$arExclude = (isset($this->params['ELEMENT_NOT_LOAD_STYLES_LIST']) && is_array($this->params['ELEMENT_NOT_LOAD_STYLES_LIST']) ? $this->params['ELEMENT_NOT_LOAD_STYLES_LIST'] : array());
		foreach($arExclude as $ex)
		{
			if(array_key_exists($ex, $arStyle)) unset($arStyle[$ex]);
			if(array_key_exists('EXT', $arStyle) && array_key_exists($ex, $arStyle['EXT'])) unset($arStyle['EXT'][$ex]);
		}
		
		return $arStyle;
	}
	
	public function GetStyleByColumn($column, $param)
	{
		$val = $this->worksheet->getCellByColumnAndRow($column, $this->worksheetCurrentRow);
		$arStyle = $this->GetCellStyle($val);
		if(isset($arStyle[$param])) return $arStyle[$param];
		else return '';
	}
	
	public function GetOrigValueByColumn($column)
	{
		$val = $this->worksheet->getCellByColumnAndRow($column, $this->worksheetCurrentRow);
		return $val->getValue();
	}
	
	public function GetValueByColumn($column)
	{
		$val = $this->worksheet->getCellByColumnAndRow($column, $this->worksheetCurrentRow);
		$valOrig = $this->GetCalculatedValue($val);
		return $valOrig;
	}
	
	public function GetCalculatedValue($val)
	{
		try{
			if($this->params['ELEMENT_NOT_LOAD_FORMATTING']=='Y') $val = $val->getCalculatedValue();
			else $val = $val->getFormattedValue();
		}catch(Exception $ex){}
		/*$encoding = (isset($this->worksheet) && is_callable(array($this->worksheet, 'getDataEncoding')) ? $this->worksheet->getDataEncoding() : false);*/
		return self::CorrectCalculatedValue($val);
	}
	
	public static function CorrectCalculatedValue($val, $encoding='UTF-8')
	{
		$val = str_ireplace('_x000D_', '', $val);
		if((!defined('BX_UTF') || !BX_UTF) && ($encoding=='UTF-8' || \CUtil::DetectUTF8($val)))
		{
			$val = self::ReplaceCpSpecChars($val);
			if(function_exists('iconv'))
			{
				$newVal = iconv("UTF-8", "CP1251//IGNORE", $val);
				if(strlen(trim($newVal))==0 && strlen(trim($val))>0)
				{
					$newVal2 = \Bitrix\Main\Text\Encoding::convertEncoding($val, "UTF-8", "Windows-1251");
					if(strpos(trim($newVal2), '?')!==0) $newVal = $newVal2;
				}
				$val = $newVal;
			}
			else $val = \Bitrix\Main\Text\Encoding::convertEncoding($val, "UTF-8", "Windows-1251");
		}
		return $val;
	}
	
	public static function ReplaceCpSpecChars($val)
	{
		return \Bitrix\KdaImportexcel\IUtils::ReplaceCpSpecChars($val);
	}
	
	public function GetFloatVal($val, $precision=0, $allowEmpty=false)
	{
		if(is_array($val)) $val = current($val);
		$val = preg_replace('/&#\d+;/', '', $val);
		if(preg_match('/^\s*\d+(,\s*\d{3})+\.\d{2}\s*$/', $val)) $val = str_replace(',', '', $val);
		$val = trim(preg_replace('/[^\d\.\-]+/', '', str_replace(',', '.', $val)), '.');
		if($allowEmpty && strlen($val)==0) return $val;
		$val = floatval($val);
		if($precision > 0) $val = round($val, $precision);
		return $val;
	}
	
	public function GetFloatValWithCalc($val)
	{
		return $this->GetFloatVal($this->CalcFloatValue($val));
	}
	
	public function GetDateVal($val, $format = 'FULL')
	{
		$time = strtotime($val);
		if($time!==false)
		{
			return ConvertTimeStamp($time, $format);
		}
		return false;
	}
	
	public function GetDateValToDB($val, $format = 'FULL')
	{
		if(is_array($val))
		{
			foreach($val as $k=>$v)
			{
				$val[$k] = $this->GetDateValToDB($v, $format);
			}
			return $val;
		}

		$time = strtotime($val);
		if($time!==false)
		{
			return date('Y-m-d'.($format=='FULL' ? ' H:i:s' : ''), $time);
		}
		return false;
	}
	
	public function GetSeparator($sep)
	{
		return strtr((string)$sep, array('\r'=>"\r", '\n'=>"\n", '\t'=>"\t"));
	}

	public function Trim($str)
	{
		return \Bitrix\KdaImportexcel\IUtils::Trim($str);
	}
	
	public function TrimToLower(&$str)
	{
		$str = ToLower($this->Trim($str));
	}
	
	public function Str2Url($string, $arParams=array(), $allowEmpty=true)
	{
		return \Bitrix\KdaImportexcel\IUtils::Str2Url($string, $arParams, $allowEmpty);
	}
	
	public function Translate($string, $langFrom, $langTo=false)
	{
		return \Bitrix\KdaImportexcel\IUtils::Translate($string, $langFrom, $langTo);
	}
	
	public function GetCurUserID()
	{
		return \Bitrix\KdaImportexcel\IUtils::GetCurUserID();
	}
	
	public function Err($e)
	{
		if(++$this->stepparams['error_line'] <= 10000)
			$this->errors[] = $e;
	}
	
	public function SetLastError($error=false)
	{
		$this->lastError = $error;
	}

	public function GetLastError()
	{
		return $this->lastError;
	}
	
	public function OnShutdown()
	{
		$arError = error_get_last();
		if(!is_array($arError) || !isset($arError['type']) || !in_array($arError['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) return;
		
		if($this->worksheetCurrentRow > 0)
		{
			$this->EndWithError(sprintf(Loc::getMessage("KDA_IE_FATAL_ERROR_IN_LINE"), $this->worksheetNumForSave+1, $this->worksheetCurrentRow, $arError['type'], $arError['message'], $arError['file'], $arError['line']));
		}
		else
		{
			$this->EndWithError(sprintf(Loc::getMessage("KDA_IE_FATAL_ERROR"), $arError['type'], $arError['message'], $arError['file'], $arError['line']));
		}
	}
	
	public function HandleError($code, $message, $file, $line)
	{
		return true;
	}
	
	public function HandleException($exception)
	{
		$error = '';
		if($this->worksheetCurrentRow > 0)
		{
			$error .= sprintf(Loc::getMessage("KDA_IE_ERROR_LINE"), $this->worksheetNumForSave+1, $this->worksheetCurrentRow);
		}
		if(is_callable(array('\Bitrix\Main\Diag\ExceptionHandlerFormatter', 'format')) && mb_strpos($exception->getMessage(), $_SERVER['DOCUMENT_ROOT'])===false)
		{
			$error .= (isset($this->phpExpression) ? "<br>".htmlspecialcharsbx($this->phpExpression)."<br>" : "").\Bitrix\Main\Diag\ExceptionHandlerFormatter::format($exception);
		}
		else
		{
			$error .= sprintf(Loc::getMessage("KDA_IE_FATAL_ERROR"), '', $exception->getMessage(), $exception->getFile(), $exception->getLine());
		}
		$this->EndWithError($error);
	}
	
	public function EndWithError($error)
	{
		global $APPLICATION;
		$APPLICATION->RestartBuffer();
		ob_end_clean();
		$this->errors[] = $error;
		$this->SaveStatusImport();
		$oProfile = CKDAImportProfile::getInstance();
		$oProfile->OnBreakImport($error);
		echo '<!--module_return_data-->'.\KdaIE\Utils::PhpToJSObject($this->GetBreakParams());
		die();
	}
}

class CKDAImportExcelStatic extends CKDAImportExcel
{
	function __construct($params, $file='')
	{
		$this->params = $params;
		$this->filename = $_SERVER['DOCUMENT_ROOT'].$file;
		$this->SetZipClass();
	}
}

class KDAChunkReadFilter implements KDAPHPExcel_Reader_IReadFilter
{
	private $_startRow = 0;
	private $_endRow = 0;
	private $_arColumns = null;
	private $_arFilePos = array();
	private $_arMerge = array();
	private $_arLines = array();
	private $_params = array();
	/**  Set the list of rows that we want to read  */

	public function setParams($arParams=array(), $csvPosition=false)
	{
		$this->_params = $arParams;
		if(is_array($csvPosition) && !empty($csvPosition))
		{
			$this->setFilePosRow($csvPosition['row'], $csvPosition['pos']);
		}
	}
	
	public function getParam($paramName)
	{
		return (array_key_exists($paramName, $this->_params) ? $this->_params[$paramName] : false);
	}
	
	public function setLoadLines($arLines)
	{
		$this->_arLines = $arLines;
	}
	
	public function getLoadLines()
	{
		return $this->_arLines;
	}
	
	public function setMergeCells($mergeRef)
	{
		if(preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', trim($mergeRef), $m) && $m[2]!=$m[4])
		{
			/*$this->_arMerge[$m[1]][$m[2].':'.$m[4]] = array($m[2], $m[4]);
			$this->_arMerge[$m[3]][$m[2].':'.$m[4]] = array($m[2], $m[4]);*/
			$this->_arMerge[$m[2].':'.$m[4]] = array($m[2], $m[4]);
		}
	}
	
	public function setColumns($arColumns)
	{
		if(!is_array($arColumns)) $arColumns = array($arColumns);
		$arLetters = range('A', 'Z');
		$cnt = count($arLetters);
		foreach($arColumns as $k=>$v)
		{
			$v++;
			$col = '';
			$arNums = array();
			$i = 0;
			while($v > 0 && $i<10)
			{
				if($i > 0) $v = $v/$cnt;
				$res = ($v-1)%$cnt;
				$arNums[] = $arLetters[$res];
				$v = $v - $res - 1;
				$i++;
			}
			$col = implode('', array_reverse($arNums));
			$arColumns[$k] = $col;
		}
		if(count($arColumns) > 0) $this->_arColumns = $arColumns;
		else $this->_arColumns = null;
	}
	
	public function getColumns()
	{
		return $this->_arColumns; 
	}

	public function setRows($startRow, $chunkSize) {
		$this->_startRow = $startRow;
		$this->_endRow = $startRow + $chunkSize;
		$this->_arMerge = array();
	}

	public function readCell($column, $row, $worksheetName = '') {
		//  Only read the heading row, and the rows that are configured in $this->_startRow and $this->_endRow
		if (($row == 1) || ($row >= $this->_startRow && $row < $this->_endRow) || in_array($row, $this->_arLines)){
			return true;
		}
		elseif(count($this->_arMerge) > 0){
			foreach($this->_arMerge as $range){
				if($row >= $range[0] && $row <= $range[1] && (($this->_startRow >= $range[0] && $this->_startRow <= $range[1]) || ($this->_endRow >= $range[0] && $this->_endRow <= $range[1]))){
					return true;
				}
			}
		}
		return false;
	}
	
	public function getStartRow()
	{
		return $this->_startRow;
	}
	
	public function getEndRow()
	{
		return $this->_endRow;
	}
	
	public function setFilePosRow($row, $pos)
	{
		$this->_arFilePos[$row] = $pos;
	}
	
	public function getFilePosRow($row)
	{
		$nextRow = $row + 1;
		$pos = 0;
		if(!empty($this->_arFilePos))
		{
			if(isset($this->_arFilePos[$nextRow])) $pos = (int)$this->_arFilePos[$nextRow];
			else
			{
				$arKeys = array_keys($this->_arFilePos);
				if(!empty($arKeys))
				{
					$maxKey = max($arKeys);
					if($nextRow > $maxKey);
					{
						$nextRow = $maxKey;
						$pos = (int)$this->_arFilePos[$maxKey];
					}
				}
			}
		}
		return array(
			'row' => $nextRow,
			'pos' => $pos
		);
	}
}	
?>