<?php
namespace Bitrix\KdaImportexcel;
require_once(dirname(__FILE__).'/PHPExcel/PHPExcel.php');
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class ExcelViewer 
{
	protected $arXPathsMulti = array();
	protected $arParamNames = array();
	protected static $cpSpecCharLetters = null;
	
	public function __construct($DATA_FILE_NAME='', $SETTINGS_DEFAULT=array())
	{
		$this->filename = $_SERVER['DOCUMENT_ROOT'].$DATA_FILE_NAME;
		$this->params = $SETTINGS_DEFAULT;
		$this->fileEncoding = 'utf-8';
		$this->siteEncoding = \CKDAImportUtils::getSiteEncoding();
	}
	
	public function GetColumnVals($listNumber, $colNumber, $useConv, $arFieldParams=array(), $arProfileParams=array())
	{
		if($useConv)
		{
			$arExtra = array();
			\CKDAImportExtrasettings::HandleParams($arExtra, $arFieldParams);
			for($i=0; $i<2; $i++)
			{
				if(count($arExtra) > 0) $arExtra = current($arExtra);
			}
			if(isset($arExtra['CONVERSION'])) $arConv = $arExtra['CONVERSION'];
			else $arConv = array();
			
			$arColumns = array($colNumber);
			$prefixPattern = '/(\$\{[\'"])?(#CELL~*\d+#|#CELL\d+[\-\+]\d+#|#CELL_[A-Z]+\d+#|#CLINK#|#CNOTE#|#HASH#|#FILENAME#|#SHEETNAME#)([\'"]\})?/';
			foreach($arConv as $k=>$v)
			{
				foreach($v as $k2=>$v2)
				{
					if(!is_array($v2) && preg_match_all($prefixPattern, (string)$v2, $m))
					{
						foreach($m[0] as $r)
						{
							if(preg_match('/#CELL(\d+)#/', $r, $m2))
							{
								$c = $m2[1] - 1;
								if(!in_array($c, $arColumns)) $arColumns[] = $c;
							}
						}
					}
				}
				if(is_numeric($v['CELL']))
				{
					$c = $v['CELL'] - 1;
					if(!in_array($c, $arColumns)) $arColumns[] = $c;
				}
			}
			
			$ie = new \CKDAImportExcel(substr($this->filename, strlen($_SERVER['DOCUMENT_ROOT'])), $arProfileParams, array(), array());
			$rows = $this->GetColumnRows($listNumber, $colNumber, $arColumns, $ie);
			
			$arVals = array();
			if(is_array($rows))
			{
				foreach($rows as $k=>$row)
				{
					$val = $row;
					$ie->worksheetCurrentRow = $k + 1;
					$val = $ie->ApplyConversions($val, $arConv, array());
					$val = mb_substr((string)$val, 0, 1000);
					if(strlen($val) > 0 && !in_array($val, $arVals))
					{
						$arVals[] = $val;
						if(count($arVals) >= 10000) break;
					}
				}
			}
			elseif($rows!==false)
			{
				$arVals[] = (string)$rows;
			}

			return $arVals;
		}
		else
		{
			$rows = $this->GetColumnRows($listNumber, $colNumber);
			$arVals = array();
			if(is_array($rows))
			{
				foreach($rows as $row)
				{
					$val = $row;
					$val = mb_substr((string)$val, 0, 1000);
					if(strlen($val) > 0 && !in_array($val, $arVals))
					{
						$arVals[] = $val;
						if(count($arVals) >= 10000) break;
					}
				}
			}
			elseif($rows!==false)
			{
				$arVals[] = (string)$rows;
			}

			return $arVals;
		}
	}
	
	public function GetColumnRows($listNumber, $colNumber, $arColumns=array(), $ie=false)
	{
		$arRows = array();
		$file = \Bitrix\Main\IO\Path::convertLogicalToPhysical($this->filename);
		$objReader = \KDAPHPExcel_IOFactory::createReaderForFile($file);
		$oneList = false;
		if(is_callable(array($objReader, 'listWorksheetNames')))
		{
			$worksheetNames = $objReader->listWorksheetNames($file);
			if($worksheetNames[$listNumber])
			{
				$objReader->setLoadSheetsOnly($worksheetNames[$listNumber]);
				$oneList = true;
			}
		}
		if($this->params['ELEMENT_NOT_LOAD_STYLES']=='Y' && $this->params['ELEMENT_NOT_LOAD_FORMATTING']=='Y')
		{
			$objReader->setReadDataOnly(true);
		}
		if(isset($this->params['CSV_PARAMS']))
		{
			$objReader->setCsvParams($this->params['CSV_PARAMS']);
		}
		$chunkFilter = new ChunkReadFilter();
		$objReader->setReadFilter($chunkFilter);
		$maxLine = 1000000;
		$chunkFilter->setRows(1, $maxLine);
		if(!in_array($colNumber, $arColumns)) $arColumns[] = $colNumber;
		$chunkFilter->setColumns($arColumns);
		
		$efile = $objReader->load($file);
		$arWorksheets = array();
		foreach($efile->getWorksheetIterator() as $k=>$worksheet) 
		{
			if($listNumber!==false && $listNumber!=$k && !$oneList) continue;
			if(is_object($ie)) $ie->worksheet = $worksheet;
			$rows_count = $worksheet->getHighestDataRow();

			$arLines = array();
			$cntLines = $emptyLines = 0;
			for($row = 0; $row < $rows_count; $row++) 
			{				
				$val = $worksheet->getCellByColumnAndRow($colNumber, $row+1);					
				$valText = $this->GetCalculatedValue($val);
				$arRows[] = $valText;
			}
		}
		return $arRows;
	}
	
	public function GetCalculatedValue($val)
	{
		try{
			if($this->params['ELEMENT_NOT_LOAD_FORMATTING']=='Y') $val = $val->getCalculatedValue();
			else $val = $val->getFormattedValue();
		}catch(Exception $ex){}
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
		$specChars = array('Ø'=>'&#216;', '™'=>'&#153;', '®'=>'&#174;', '©'=>'&#169;');
		if(!isset(static::$cpSpecCharLetters))
		{
			$cpSpecCharLetters = array();
			foreach($specChars as $char=>$code)
			{
				$letter = false;
				$pos = 0;
				for($i=192; $i<255; $i++)
				{
					$tmpLetter = \Bitrix\Main\Text\Encoding::convertEncodingArray(chr($i), 'CP1251', 'UTF-8');
					$tmpPos = strpos($tmpLetter, $char);
					if($tmpPos!==false)
					{
						$letter = $tmpLetter;
						$pos = $tmpPos;
					}
				}
				$cpSpecCharLetters[$char] = array('letter'=>$letter, 'pos'=>$pos);
			}
			static::$cpSpecCharLetters = $cpSpecCharLetters;
		}
		
		foreach($specChars as $char=>$code)
		{
			if(strpos($val, $char)===false) continue;
			$letter = static::$cpSpecCharLetters[$char]['letter'];
			$pos = static::$cpSpecCharLetters[$char]['pos'];

			if($letter!==false)
			{
				if($pos==0) $val = preg_replace('/'.substr($letter, 0, 1).'(?!'.substr($letter, 1, 1).')/', $code, $val);
				elseif($pos==1) $val = preg_replace('/(?<!'.substr($letter, 0, 1).')'.substr($letter, 1, 1).'/', $code, $val);
			}
			else
			{
				$val = str_replace($char, $code, $val);
			}
		}
		return $val;
	}
}

class ChunkReadFilter implements \KDAPHPExcel_Reader_IReadFilter
{
	private $_startRow = 0;
	private $_endRow = 0;
	private $_arColumns = null;
	private $_arFilePos = array();
	private $_arMerge = array();
	private $_arLines = array();
	private $_params = array();
	/**  Set the list of rows that we want to read  */

	public function setParams($arParams=array())
	{
		$this->_params = $arParams;
	}
	
	public function getParam($paramName)
	{
		return (array_key_exists($paramName, $this->_params) ? $this->_params[$paramName] : false);
	}
	
	public function setLoadLines($arLines)
	{
		$this->_arLines = $arLines;
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
		$this->_arColumns = $arColumns;
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
		if ((($row == 1) || ($row >= $this->_startRow && $row < $this->_endRow) || in_array($row, $this->_arLines))
			&& (!isset($this->_arColumns) || in_array($column, $this->_arColumns))){
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