<?php
namespace Bitrix\KdaImportexcel;

class ZipArchive
{
	private $tmpDir = '';
	private $removeOnClose = false;
	private $sStringFile = false;
	private $strIndexes = array();
	static $arFileIds = array();
	
	public function __construct()
	{

	}
	
	public function __destruct()
	{
		$this->close();
	}
	
	public function close()
	{
		if(strlen($this->tmpDir) > 0 && file_exists($this->tmpDir) && $this->removeOnClose)
		{
			static::RemoveFileDir($this->tmpDir);
		}
		$this->removeOnClose = false;
		$this->sStringFile = false;
		$this->strIndexes = array();
		$this->tmpDir = '';
	}
	
	public static function RemoveFileDir($dir)
	{
		if(is_file($dir)) $dir = static::GetFileDir($dir);
		elseif(is_numeric($dir)) $dir = $_SERVER["DOCUMENT_ROOT"].'/upload/tmp/'.IUtils::$moduleId.'/'.IUtils::$moduleSubDir.'_archives/'.$dir.'/';
		if($dir && is_dir($dir))
		{
			if(strpos($dir, $_SERVER['DOCUMENT_ROOT'])===0)
			{
				DeleteDirFilesEx(mb_substr($dir, mb_strlen($_SERVER['DOCUMENT_ROOT'])));
			}
			else
			{
				self::DeleteDirFiles($dir);
			}
			$pDir = dirname($dir);
			if(($arFiles = scandir($pDir)) && is_array($arFiles) && count(array_diff($arFiles, array('.', '..')))==0) rmdir($pDir);
		}
	}
	
	public static function DeleteDirFiles($path)
	{
		if(strlen($path) == 0 || $path == '/')
			return false;

		$full_path = $path;
		$full_path = preg_replace("#[\\\\\\/]+#", "/", $full_path);

		$f = true;
		if(is_file($full_path) || is_link($full_path))
		{
			if(@unlink($full_path))
				return true;
			return false;
		}
		elseif(is_dir($full_path))
		{
			if($handle = opendir($full_path))
			{
				while(($file = readdir($handle)) !== false)
				{
					if($file == "." || $file == "..")
						continue;

					if(!self::DeleteDirFiles($path."/".$file))
						$f = false;
				}
				closedir($handle);
			}
			if(!@rmdir($full_path))
				return false;
			return $f;
		}
		return false;
	}
	
	public static function GetFileDir($pFilename)
	{
		if(($pos = mb_strpos($pFilename, '/'.IUtils::$moduleId.'/'))!==false)
		{
			$filePath = \Bitrix\Main\IO\Path::convertPhysicalToLogical(mb_substr($pFilename, $pos + 1));
			$fileName = bx_basename($filePath);
			$subDir = mb_substr($filePath, 0, -mb_strlen($fileName) - 1);
			if(strlen($fileName) > 0 && strlen($subDir) > 0)
			{
				$fileKey = $subDir.'|'.$fileName;
				if(!array_key_exists($fileKey, self::$arFileIds))
				{
					self::$arFileIds[$fileKey] = false;
					if($arFile = \CFile::GetList(array(), array('SUBDIR'=>$subDir, 'FILE_NAME'=>$fileName))->Fetch())
					{
						self::$arFileIds[$fileKey] = $arFile['ID'];
					}
				}
				if(self::$arFileIds[$fileKey])
				{
					return $_SERVER["DOCUMENT_ROOT"].'/upload/tmp/'.IUtils::$moduleId.'/'.IUtils::$moduleSubDir.'_archives/'.self::$arFileIds[$fileKey].'/';
				}
			}
		}
		return false;
	}
	
	public function open($pFilename)
	{
		$this->tmpDir = '';
		$this->removeOnClose = false;
		$this->sStringFile = false;
		$this->strIndexes = array();
		if($dir = static::GetFileDir($pFilename))
		{
			$this->tmpDir = $dir;
			if(file_exists($this->tmpDir))
			{
				if(filemtime($this->tmpDir) < max(filemtime($pFilename), time()-24*60*60) || $this->calcCheckSum()!=$this->getCheckSum())
				{
					DeleteDirFilesEx(mb_substr($this->tmpDir, mb_strlen($_SERVER['DOCUMENT_ROOT'])));
					rmdir(dirname($this->tmpDir));
				}
				else
				{
					return true;
				}
			}
			if(!file_exists($this->tmpDir))
			{
				\Bitrix\Main\IO\Directory::createDirectory($this->tmpDir);
			}
		}
				
		if(strlen($this->tmpDir)==0)
		{
			$this->removeOnClose = true;
			$temp_path = \CFile::GetTempName('', bx_basename($pFilename));
			$tmpDir = \Bitrix\Main\IO\Path::getDirectory($temp_path);
			\Bitrix\Main\IO\Directory::createDirectory($tmpDir);
			while(($this->tmpDir = $tmpDir.'/'.md5(mt_rand()).'/') && file_exists($this->tmpDir) && $i<1000)
			{
				$i++;
			}
		}
		
		if(class_exists('\ZipArchive') && ($zipObj = new \ZipArchive) && ($zipObj->open($pFilename) === true) && $zipObj->numFiles > 0)
		{
			$zipObj->extractTo($this->tmpDir);
			$zipObj->close();
			$this->setCheckSum();
			return true;
		}
		else
		{
			$io = \CBXVirtualIo::GetInstance();
			$pFilename2 = $io->GetLogicalName($pFilename);
			$zipObj = \CBXArchive::GetArchive($pFilename2, 'ZIP');
			if($zipObj->Unpack($this->tmpDir)!==false)
			{
				$this->setCheckSum();
				return true;
			}
			elseif(($arFile = \CFile::MakeFileArray($pFilename)) && in_array($arFile['type'], array('application/zip', 'application/x-zip-compressed', 'application/octet-stream')))
			{
				if(function_exists('exec'))
				{
					$command = 'unzip "'.$pFilename.'" -d '.$this->tmpDir;
					@exec($command);
				}
				if(count(array_diff(scandir($this->tmpDir), array('.', '..')))==0)
				{
					$this->setCheckSum();
					return true;
				}
			}
		}
		return false;
	}
	
	public function setCheckSum()
	{
		$sum = $this->calcCheckSum();
		file_put_contents($this->tmpDir.'/.checksum', $sum);
	}
	
	public function getCheckSum()
	{
		if(!file_exists($this->tmpDir.'/.checksum')) return '';
		return file_get_contents($this->tmpDir.'/.checksum');
	}
	
	public function calcCheckSum($dir='')
	{
		if(strlen($dir)==0) $dir = $this->tmpDir;
		$dir = rtrim($dir, '/').'/';
		$arFiles = scandir($dir);
		$arFiles = array_diff($arFiles, preg_grep('/^(\.+|\.checksum|.*\.cache)$/i', $arFiles));
		$sum = implode('#', $arFiles);
		foreach($arFiles as $k=>$v)
		{
			if(is_dir($dir.$v))
			{
				$sum .= '###'.$this->calcCheckSum($dir.$v);
			}
		}
		return md5($sum);
	}
	
	public function getFromName($name, $length=0, $flags=0)
	{
		$content = file_get_contents($this->tmpDir.$name);
		if($length > 0) $content = substr($content, 0, $length);
		return $content;
	}
	
	public function normalizeFileContent($fn)
	{
		//<x:tags fixes
		if(strpos(file_get_contents($fn, false, null, 0, 1000), '<x:')!==false)
		{
			$fnTmp = $fn.'.tmp';
			copy($fn, $fnTmp);
			$handle = fopen($fnTmp, 'r');
			$handle2 = fopen($fn, 'w');
			$buffer = $bufferPart = '';
			while(!feof($handle)) 
			{
				$buffer = $bufferPart.fread($handle, 4*1024*1024);
				if(($pos = strrpos($buffer, '<'))!==false)
				{
					$bufferPart = substr($buffer, $pos);
					$buffer = substr($buffer, 0, $pos);
				}
				else $bufferPart = '';
				$buffer = strtr($buffer, array('<x:'=>'<', '</x:'=>'</'));
				fwrite($handle2, $buffer);
			}
			fclose($handle2);
			fclose($handle);
			unlink($fnTmp);
		}
	}
	
	public function getSimpleXmlForSheet($name, $readFilter = null, $countMode = false)
	{
		$fn = $this->tmpDir.$name;
		$xmlObj = new \SimpleXMLElement('<d></d>');

		if(!file_exists($fn)) return $xmlObj;
		$this->normalizeFileContent($fn);
		
		$time0 = time();
		if(defined("BX_CRONTAB") && BX_CRONTAB) $shareMaxTime = 86400;
		else
		{
			$shareMaxTime = intval(ini_get('max_execution_time')) - 10;
			if($shareMaxTime==0) $shareMaxTime = 50;
			elseif($shareMaxTime < 10) $shareMaxTime = 15;
			elseif($shareMaxTime > 50) $shareMaxTime = 50;
		}
		$xmlClass = XmlUtils::getXmlReaderClass();
		$xml = XmlUtils::open($fn);
		if($xml===false) return $xmlObj;

		$fnCache = $fn.'.cache';
		$writeCache = true;
		$firstRow = (is_callable(array($readFilter, 'getStartRow')) ? $readFilter->getStartRow() : 1);
		$lastRow = (is_callable(array($readFilter, 'getEndRow')) ? $readFilter->getEndRow() : 999999);
		$extraLines = (is_callable(array($readFilter, 'getLoadLines')) ? $readFilter->getLoadLines() : array());
		$arColumns = (is_callable(array($readFilter, 'getColumns')) ? $readFilter->getColumns() : null);
		$arObjects = array();
		$arObjectNames = array();
		$curDepth = 0;
		$arObjects[$curDepth] = &$xmlObj;
		$rowNum = 0;
		$isRead = false;
		while($isRead || $xml->read())
		{
			$isRead = false;
			if($xml->nodeType == $xmlClass::ELEMENT) 
			{
				if($arObjectNames[1]=='sheetData' && $xml->name=='row' && $xml->depth==2)
				{
					$arObjectNames[$xml->depth] = $xml->name;
					$this->SetRowNum($rowNum, $xml);
					if($rowNum > 1)
					{
						while($rowNum < $firstRow-1 && !in_array($rowNum, $extraLines) && ($isRead = true) && $xml->next('row'))
						{
							$this->SetRowNum($rowNum, $xml);
						}
						if($xml->nodeType != $xmlClass::ELEMENT) continue;
						//if(($rowNum > $lastRow) || (time()-$time0 > $shareMaxTime/5))
						if(($rowNum > $lastRow) || (time()-$time0 > $shareMaxTime - 10 && $rowNum > $firstRow + ($lastRow - $firstRow)/5))
						{
							$maxTime = ($countMode ? $shareMaxTime : 0);
							while($xml->read() && ($xml->nodeType != $xmlClass::ELEMENT || $xml->depth > 1) && time()-$time0 < $maxTime)
							{
								if($xml->nodeType == $xmlClass::ELEMENT && $xml->name=='row' && $xml->depth==2){$rowNum++;}
							}
							$xmlObj->addChild('rowsMaxIndex', $rowNum);
							if(time()-$time0 >= $maxTime)
							{
								if((!$xmlObj->hyperlinks || !$xmlObj->drawing || !$xmlObj->mergeCells) && file_exists($fnCache))
								{
									$writeCache = false;
									$xml2 = XmlUtils::open($fnCache);
									if($xml2!==false)
									{
										$isRead2 = false;
										while($isRead2 || $xml2->read())
										{
											$isRead2 = false;
											if($xml2->nodeType == $xmlClass::ELEMENT)
											{
												$arAttributes = XmlUtils::getNodeAttributes($xml2);

												if($xml2->depth > 0)
												{
													$curDepth = $xml2->depth;
													$arObjectNames[$curDepth] = $curName = $xml2->name;
													$curValue = null;
													$curNamespace = ($xml2->namespaceURI ? $xml2->namespaceURI : null);
													$curValue = XmlUtils::getNodeValue($isRead2, $xml2);

													$curValue = str_replace('&', '&amp;', $curValue);
													$arObjects[$curDepth] = $arObjects[$curDepth - 1]->addChild($curName, $curValue, $curNamespace);
												}

												foreach($arAttributes as $arAttr)
												{
													if(strpos($arAttr['name'], ':')!==false && $arAttr['namespaceURI']) $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value'], $arAttr['namespaceURI']);
													else $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value']);
												}
											}
										}
									}									
								}
								break;
							}
							//while($xml->next('row')){$rowNum++;}
							$isRead = true;
							continue;
						}
					}
				}
				elseif(isset($arColumns) && is_array($arColumns) && $arObjectNames[2]=='row' && $xml->name=='c' && $xml->depth==3)
				{					
					$arObjectNames[$xml->depth] = $xml->name;
					while($xml->name=='c' && !in_array(preg_replace('/\d+/', '', XmlUtils::getNodeAttribute($xml, 'r', false)), $arColumns))
					{
						$isRead = true;
						while($xml->read() && ($xml->nodeType != $xmlClass::ELEMENT || (!($xml->name=='row' && $xml->depth==2) && !($xml->name=='c' && $xml->depth==3)))){
							
						}
					}
					if($xml->name!='c') continue;
				}
				/*if($arObjectNames[1]=='sheetData' && $arObjectNames[2]=='row' && $xml->depth>=2)
				{
					if(is_callable(array($readFilter, 'readCell')) && !$readFilter->readCell(1, $rowNum)) continue;
				}*/

				$arAttributes = XmlUtils::getNodeAttributes($xml);

				if($xml->depth > 0)
				{
					$curDepth = $xml->depth;
					$arObjectNames[$curDepth] = $xml->name;
					$curName = $xml->name;
					$curValue = null;
					$curNamespace = ($xml->namespaceURI ? $xml->namespaceURI : null);
					$curValue = XmlUtils::getNodeValue($isRead, $xml);

					$curValue = str_replace('&', '&amp;', $curValue);
					$arObjects[$curDepth] = $arObjects[$curDepth - 1]->addChild($curName, $curValue, $curNamespace);
				}

				foreach($arAttributes as $arAttr)
				{
					if(strpos($arAttr['name'], ':')!==false && $arAttr['namespaceURI']) $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value'], $arAttr['namespaceURI']);
					else $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value']);
				}
			}
		}
		$xml->close();

		if($writeCache && ($xmlObj->hyperlinks || $xmlObj->drawing || $xmlObj->mergeCells))
		{
			file_put_contents($fnCache, '<worksheet>'.
					($xmlObj->hyperlinks ? $xmlObj->hyperlinks->asXML() : '').
					($xmlObj->drawing ? $xmlObj->drawing->asXML() : '').
					($xmlObj->mergeCells ? $xmlObj->mergeCells->asXML() : '').
				'</worksheet>');
		}
		
		$strIndexes = array();
		if(isset($xmlObj->sheetData) && isset($xmlObj->sheetData->row))
		{
			foreach($xmlObj->sheetData->row as $row)
			{
				if(isset($row->c))
				{
					foreach($row->c as $cell)
					{
						if(isset($cell->v))
						{
							$strIndexes[(int)$cell->v] = (int)$cell->v;
						}
					}
				}
			}
		}
		$this->strIndexes = $strIndexes;

		return $xmlObj;
	}
	
	public function SetRowNum(&$rowNum, $xml)
	{
		$r = XmlUtils::getNodeAttribute($xml, 'r', false);
		if($r!==false) $rowNum = $r;
		else $rowNum++;
	}
	
	public function setSharedStringsFile($name)
	{
		$fn = $this->tmpDir.$name;

		if(!file_exists($fn))
		{
			$fname = bx_basename($fn);
			$fchar = substr($fname, 0, 1);
			if(strtoupper($fchar) == $fchar) $fchar = strtolower($fchar);
			else $fchar = strtoupper($fchar);
			$fname = $fchar.substr($fname, 1);
			$fn = substr($fn, 0, -strlen($fname)).$fname;
		}

		if(file_exists($fn))
		{
			$this->normalizeFileContent($fn);
			$this->sStringFile = $fn;
		}
	}
	
	public function getSharedStringsFromIndexes($reader)
	{
		$sharedStrings = array();
		if($this->sStringFile===false || !file_exists($this->sStringFile) || !is_array($this->strIndexes) || empty($this->strIndexes)) return $sharedStrings;

		$xmlClass = XmlUtils::getXmlReaderClass();
		$xml = XmlUtils::open($this->sStringFile);
		if($xml===false) return $this->sStringFile;

		/*No method readOuterXml in custom class*/
		/*$find = false;
		while($xml->read() && !($xml->nodeType==$xmlClass::ELEMENT && $xml->name=='si' && $xml->depth==1 && ($find = true))){}
		if(!$find) return $sharedStrings;
		
		$ind = -1;
		while(++$ind==0 || $xml->next('si'))
		{
			if(!isset($this->strIndexes[$ind])) continue;
			$val = simplexml_load_string($xml->readOuterXml());
		
			if (isset($val->t)) {
				$sharedStrings[$ind] = \KDAPHPExcel_Shared_String::ControlCharacterOOXML2PHP( (string) $val->t );
			} elseif (isset($val->r)) {
				$sharedStrings[$ind] = (is_callable(array($reader, 'publicParseRichText')) ? $reader->publicParseRichText($val) : '');
			}
		}*/
		
		$e = $xmlClass::ELEMENT;
		$strIndexes = $this->strIndexes;
		$ind = -1;
		$isRead = false;
		while(!empty($strIndexes) && ($isRead || $xml->read()))
		{
			$isRead = false;
			if($xml->nodeType==$e && $xml->name=='si' && $xml->depth==1)
			{
				if(!isset($strIndexes[++$ind])) continue;
				unset($strIndexes[$ind]);
				$find = false;
				while(!$find && !$isRead && $xml->read())
				{
					if($xml->nodeType==$e)
					{
						if($xml->name=='t')
						{
							while($xml->read() && $xml->nodeType == $xmlClass::SIGNIFICANT_WHITESPACE){}
							if($xml->nodeType == $xmlClass::TEXT || $xml->nodeType == $xmlClass::CDATA)
							{
								$sharedStrings[$ind] = \KDAPHPExcel_Shared_String::ControlCharacterOOXML2PHP( (string)$xml->value );
								$find = true;
							}
						}
						elseif($xml->name=='r')
						{
							$find = true;
							$val = new \SimpleXMLElement('<si></si>');
							$arObjects = array();
							$arObjects[$xml->depth - 1] = &$val;
							$j = -1;
							$isSubRead = false;
							while(++$j==0 || $isSubRead || (!$isRead && $xml->read()))
							{
								$isSubRead = false;
								if($xml->nodeType==$e)
								{
									if($xml->name=='si')
									{
										$isRead = true;
										continue;
									}
									$curDepth = $xml->depth;
									$curName = $xml->name;
									$arAttributes = XmlUtils::getNodeAttributes($xml);
									$curValue = XmlUtils::getNodeValue($isSubRead, $xml);

									$curValue = str_replace('&', '&amp;', $curValue);
									if(isset($arObjects[$curDepth - 1]))
									{
										$arObjects[$curDepth] = $arObjects[$curDepth - 1]->addChild($curName, $curValue);
										foreach($arAttributes as $arAttr)
										{
											$arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value']);
										}
									}
								}
							}
							$sharedStrings[$ind] = (is_callable(array($reader, 'publicParseRichText')) ? $reader->publicParseRichText($val) : '');
						}
						elseif($xml->name=='si')
						{
							$isRead = true;
						}
					}
				}
			}
		}

		$xml->close();

		return $sharedStrings;
	}
	
	public function getSharedStringsFromString($str, $reader)
	{
		$tmpDir = $this->tmpDir;
		$name = 'sharedStrings.xml';
		$tempPath = \CFile::GetTempName('', $name);
		$dir = \Bitrix\Main\IO\Path::getDirectory($tempPath);
		\Bitrix\Main\IO\Directory::createDirectory($dir);
		$this->tmpDir = rtrim($dir, '/').'/';
		file_put_contents($tempPath, $str);
		$sharedStrings = $this->getSharedStrings($name, $reader, false);
		unlink($tempPath);
		if(($arFiles = scandir($dir)) && is_array($arFiles) && count(array_diff($arFiles, array('.', '..')))==0) rmdir($dir);
		$this->tmpDir = $tmpDir;
		return $sharedStrings;
	}
	
	public function getSharedStrings($name, $reader, $bCache=false)
	{
		$fn = $this->tmpDir.$name;
		$sharedStrings = array();

		if(!file_exists($fn))
		{
			$fname = bx_basename($fn);
			$fchar = substr($fname, 0, 1);
			if(strtoupper($fchar) == $fchar) $fchar = strtolower($fchar);
			else $fchar = strtoupper($fchar);
			$fname = $fchar.substr($fname, 1);
			$fn = substr($fn, 0, -strlen($fname)).$fname;
		}
		
		if(!file_exists($fn))
		{
			return $sharedStrings;
		}
		
		$fnCache = $fn.'.cache';
		if(!$bCache || !file_exists($fnCache) || filemtime($fn) > filemtime($fnCache))
		{
			$xmlClass = XmlUtils::getXmlReaderClass();
			$xml = XmlUtils::open($fn);

			while ($xml->read()) {
				if($xml->nodeType == $xmlClass::ELEMENT && $xml->name == 'si' && $xml->depth == 1) 
				{
					$val = new \SimpleXMLElement('<si></si>');
					$arObjects = array();
					$arObjectNames = array();
					$curDepth = $xml->depth;
					$arObjects[$curDepth] = &$val;
					$isRead = false;
					while (($isRead || $xml->read())
						&& !($xml->nodeType == $xmlClass::END_ELEMENT && $xml->name == 'si' && $xml->depth == 1)) {
						$isRead = false;
						if($xml->nodeType == $xmlClass::ELEMENT) 
						{
							$arAttributes = array();
							if($xml->moveToFirstAttribute())
							{
								$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
								while($xml->moveToNextAttribute ())
								{
									$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
								}
							}
							$xml->moveToElement();
					
							if($xml->depth > 1)
							{
								$curDepth = $xml->depth;
								$arObjectNames[$curDepth] = $xml->name;
								$curName = $xml->name;
								$curValue = null;
								$curNamespace = ($xml->namespaceURI ? $xml->namespaceURI : null);

								while($xml->read() && $xml->nodeType == $xmlClass::SIGNIFICANT_WHITESPACE){}
								if($xml->nodeType == $xmlClass::TEXT || $xml->nodeType == $xmlClass::CDATA)
								{
									$curValue = $xml->value;
								}
								else
								{
									$isRead = true;
								}

								$curValue = str_replace('&', '&amp;', $curValue);
								$arObjects[$curDepth] = $arObjects[$curDepth - 1]->addChild($curName, $curValue, $curNamespace);
							}
							
							foreach($arAttributes as $arAttr)
							{
								if(strpos($arAttr['name'], ':')!==false && $arAttr['namespaceURI']) $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value'], $arAttr['namespaceURI']);
								else $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value']);
							}
						}
					}
					
					if (isset($val->t)) {
						$sharedStrings[] = \KDAPHPExcel_Shared_String::ControlCharacterOOXML2PHP( (string) $val->t );
					} elseif (isset($val->r)) {
						$sharedStrings[] = (is_callable(array($reader, 'publicParseRichText')) ? $reader->publicParseRichText($val) : '');
					}
				}
			}
			$xml->close();
			
			if($bCache)
			{
				if(file_exists($fnCache)) unlink($fnCache);
				$handle = fopen($fnCache, 'a');
				foreach($sharedStrings as $k=>$str)
				{
					fwrite($handle, ($k > 0 ? "\r\n" : '').base64_encode(serialize($str)));
				}
				fclose($handle);
			}
		}
		else
		{
			$handle = fopen($fnCache, "r");
			while(!feof($handle))
			{
				$buffer = fgets($handle, 65536);
				$sharedStrings[] = unserialize(base64_decode($buffer));
			}
			fclose($handle);

		}
		return $sharedStrings;
	}
	
	public function locateName($name, $flags=0)
	{
		if(file_exists($this->tmpDir.$name))
		{
			return 1;
		}
		return false;
	}
	
	public function statName($name, $flags=0)
	{
		if(file_exists($this->tmpDir.$name))
		{
			return array(
				'name' => $name,
				'index' => 1,
				'crc' => crc32(file_get_contents($this->tmpDir.$name)),
				'size' => filesize($this->tmpDir.$name),
				'mtime' => filemtime($this->tmpDir.$name),
				'comp_size' => filesize($this->tmpDir.$name),
				'comp_method' => 8
			);
		}
		return false;
	}
	
	public function getZipFilePath($subpath, $createTmp = false)
	{
		$subpath = str_replace('\\', '/', $subpath);
		$subpath = ltrim($subpath, '/');
		$path = $this->tmpDir.$subpath;
		if($createTmp)
		{
			$temp_path = \CFile::GetTempName('', bx_basename($path));
			$dir = \Bitrix\Main\IO\Path::getDirectory($temp_path);
			\Bitrix\Main\IO\Directory::createDirectory($dir);
			copy($path, $temp_path);
			return $temp_path;
		}
		else
		{
			return $path;
		}
	}
}

class XmlReader
{
	const NONE = 0;
	const ELEMENT = 1;
	const ATTRIBUTE = 2;
	const TEXT = 3;
	const CDATA = 4;
	const ENTITY_REF = 5;
	const ENTITY = 6;
	const PI = 7;
	const COMMENT = 8;
	const DOC = 9;
	const DOC_TYPE = 10;
	const DOC_FRAGMENT = 11;
	const NOTATION = 12;
	const WHITESPACE = 13;
	const SIGNIFICANT_WHITESPACE = 14;
	const END_ELEMENT = 15;
	const END_ENTITY = 16;
	const XML_DECLARATION = 17;

	private $filename = null;
	private $parser = null;
	private $handle = null;
	private $end = false;
	private $arData = array();
	private $depthInner = -1;
	private $inElement = false;
	private $attributes = array();
	private $attributeKey = 0;
	private $arNode = null;
	private $textData = null;
	private $lastNode = null;
	private $index = 0;
	private $cnt = 0;

	public $nodeType = null;
	public $depth = null;
	public $name = null;
	public $value = null;
	public $namespaceURI = null;

	public function __construct()
	{
	}
	
	public function open($filename, $encoding = null, $flags = 0)
	{
		if(!file_exists($filename) || !is_file($filename)) return false;
		if(!($handle = fopen($filename, 'r'))) return false;
		$this->handle = $handle;
		
		$this->filename = $filename;
		$this->parser = xml_parser_create();
		
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, true);
		xml_set_element_handler($this->parser, array($this, "startElement"), array($this, "endElement"));
		xml_set_character_data_handler($this->parser, array($this, "characterData"));
		
		return true;
	}
	
	public function close()
	{
		if(!isset($this->parser)) return false;
		xml_parser_free($this->parser);
		fclose($this->handle);
		$this->parser = null;
		$this->handle = null;
		return true;
	}
	
	public function read()
	{
		if($this->cnt > 0 && $this->index > $this->cnt - 1) $this->cnt = 0;
		while($this->cnt==0 && !$this->end)
		{
			$this->arData = array();
			$data = fread($this->handle, 8192);
			$this->end = feof($this->handle);
			if(!xml_parse($this->parser, $data, $this->end))
			{
				/*die(sprintf("XML error: %s at line %d",
							xml_error_string(xml_get_error_code($this->parser));
							xml_get_current_line_number($this->parser)));*/
			}
			$this->index = 0;
			$this->cnt = count($this->arData);
		}

		if($this->cnt==0) return false;
		//$this->arNode = $arNode = array_shift($this->arData);
		$this->arNode = $this->arData[$this->index++];
		$this->moveToElement();

		return true;
	}

	public function next()
	{
		$this->moveToElement();
		$depth = $this->depth;
		$nodeName = $this->name;
		while($this->read())
		{
			if($this->depth < $depth) return $this->decrementIndex();
			if($this->depth==$depth)
			{
				if($this->name==$nodeName) return true;
				elseif($this->nodeType==self::TEXT) continue;
				else return $this->decrementIndex();
			}
		}
		return false;
	}
	
	public function decrementIndex()
	{
		$this->index--;
		return false;
	}

	public function moveToFirstAttribute()
	{
		if(!is_array($this->attributes) || count($this->attributes)==0) return false;
		reset($this->attributes);
		$this->nodeType = self::ATTRIBUTE;
		$this->depth = $this->depthInner;
		$this->value = current($this->attributes);
		$this->name = key($this->attributes);
		$this->namespaceURI = '';
		$this->attributeKey = 1;
		return true;
	}

	public function moveToNextAttribute()
	{
		if(!is_array($this->attributes) || count($this->attributes) < $this->attributeKey + 1) return false;
		$this->nodeType = self::ATTRIBUTE;
		$this->depth = $this->depthInner;
		$this->value = next($this->attributes);
		$this->name = key($this->attributes);
		$this->namespaceURI = '';
		$this->attributeKey++;
		return true;
	}

	public function moveToElement()
	{
		if(!isset($this->arNode)) return false;
		$this->attributeKey = 0;
		$arNode = $this->arNode;
		$this->nodeType = (array_key_exists('nodeType', $arNode) ? $arNode['nodeType'] : null);
		$this->depth = (array_key_exists('depth', $arNode) ? $arNode['depth'] : null);
		$this->name = (array_key_exists('name', $arNode) ? $arNode['name'] : null);
		$this->value = (array_key_exists('value', $arNode) ? $arNode['value'] : null);
		$this->namespaceURI = (array_key_exists('namespaceURI', $arNode) ? $arNode['namespaceURI'] : null);
		$this->attributes = (array_key_exists('attributes', $arNode) ? $arNode['attributes'] : null);
		return true;
	}

	public function startElement($parser, $name, $attrs)
	{
		$this->depthInner++;
		$this->inElement = true;
		$this->textData = '';
		$this->arData[] = array(
			'nodeType' => self::ELEMENT,
			'depth' => $this->depthInner,
			'name' => $name,
			'attributes' => $attrs
		);
		$this->lastNode = $name.'|'.$this->depthInner;
	}

	public function endElement($parser, $name)
	{
		if($this->lastNode==$name.'|'.$this->depthInner /*&& strlen(trim($this->textData)) > 0*/)
		{
			$this->arData[] = array(
				'nodeType' => self::TEXT,
				'depth' => $this->depthInner,
				'value' => $this->textData
			);
		}
		$this->depthInner--;
		$this->inElement = false;
	}

	public function characterData($parser, $data)
	{
		$this->textData .= $data;
	}
}

class XmlUtils
{
	public static function getXmlReaderClass()
	{
		return (class_exists('\XMLReader') ? '\XMLReader' : '\Bitrix\KdaImportexcel\XMLReader');
	}
	
	public static function open($fn)
	{
		$className = self::getXmlReaderClass();
		if(defined('PHP_VERSION_ID') && PHP_VERSION_ID>=80000 && $className=='\XMLReader' && is_callable(array($className, 'open')))
		{
			$xml = $className::open($fn);
		}
		else
		{
			$xml = new $className();
			if(!$xml->open($fn)) return false;
		}
		return $xml;
	}
	
	public static function getNodeAttributes($xml)
	{
		$arAttributes = array();
		if($xml->moveToFirstAttribute())
		{
			$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
			while($xml->moveToNextAttribute())
			{
				$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
			}
		}
		$xml->moveToElement();
		return $arAttributes;
	}
	
	public static function getNodeAttributesValues($xml)
	{
		$arAttrs = self::getNodeAttributes($xml);
		$arVals = array();
		foreach($arAttrs as $attr)
		{
			$arVals[$attr['name']] = $attr['value'];
		}
		return $arVals;
	}
	
	public static function getNodeAttribute($xml, $k, $v=false)
	{
		$r = $v;
		if($xml->moveToFirstAttribute())
		{
			if($xml->name==$k) $r = $xml->value;
			while($r===false && $xml->moveToNextAttribute())
			{
				if($xml->name==$k) $r = $xml->value;
			}
		}
		$xml->moveToElement();
		return $r;
	}
	
	public static function getNodeValue(&$isRead, $xml)
	{
		$xml->read();
		if($xml->nodeType == XmlReader::TEXT || $xml->nodeType == XmlReader::SIGNIFICANT_WHITESPACE)
		{
			return $xml->value;
		}
		else
		{
			$isRead = true;
		}
		return null;
	}
}