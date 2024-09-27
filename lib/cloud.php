<?php
namespace Bitrix\KdaImportexcel;

use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class Cloud
{
	protected static $lastResult = array();
	protected $extractZip = false;
	protected $maxTime = 30;
	protected $apiLastFile = false;
	protected $services = array(
		'yadisk' => array(
			'/^https?:\/\/yadi\.sk\//i',
			'/^https:\/\/disk\.yandex\.\w{2,3}\//i',
			'/^https:\/\/disk\.yandex\.\w{2,3}\.\w{2,3}\//i'
		),
		'mailru' => '/^https?:\/\/cloud\.mail\.ru\/public\//i',
		'gdrive' => array(
			'/^https?:\/\/drive\.google\.com\/open\?id=/i',
			'/^https?:\/\/drive\.google\.com\/file\/d\/[^\/]+(\/|$)/i',
			'/^https?:\/\/docs\.google\.com\/spreadsheets.*\/d\/[^\/]+(\/|$)/i',
			'/^https?:\/\/www\.google\.com\/.*https?:\/\/docs\.google\.com\/spreadsheets.*\/d\/[^\/]+(\/|$)/i',
			'/^https?:\/\/drive\.google\.com\/drive\/folders\/[^\/\?]+(\?|$)/i',
			'/^https?:\/\/[^.]*.google.com\/u\/0\/d\//i'
		),
		'dropbox' => array(
			'/^https?:\/\/www\.dropbox\.com\/.*\?dl=\d(\D|$)/i',
			'/^https?:\/\/www\.dropbox\.com\/[^?]*$/i'
		)
		,
		'lightshot' => array(
			'/^https?:\/\/prntscr\.com\//i',
			'/^https?:\/\/prnt\.sc\//i'
		),
		'ibb' => array(
			'/^https?:\/\/ibb\.co\//i',
		),
		'postimg' => array(
			'/^https?:\/\/i\.postimg\.cc\//i',
		),
		'cloudfarphor' => array(
			'/^https?:\/\/cloud\.farphor\.ru\/d\//i',
		),
		'bitrix24' => array(
			'/^https?:\/\/[^\.]+\.bitrix24\.ru\/~[^\/]+(#|$)/',
			'/^https:\/\/bitrix24public\.com\//i'
		)
	);
	
	public function __construct($maxTime=false)
	{
		if($maxTime!==false) $this->maxTime = $maxTime;
	}
	
	public function GetService($link, $apiLastFile=false)
	{
		$this->apiLastFile = $apiLastFile;
		foreach($this->services as $k=>$v)
		{
			if(is_array($v))
			{
				foreach($v as $v2)
				{
					if(preg_match($v2, $link)) return $k;
				}
			}			
			elseif(preg_match($v, $link)) return $k;
		}
		return false;
	}
	
	public function NeedZipExtract()
	{
		return $this->extractZip;
	}
	
	public function MakeFileArray($service, $path, $fromFile=false)
	{
		$this->extractZip = false;
		$this->params = array();
		if(is_array($fromFile))
		{
			$this->params = $fromFile;
			$fromFile = true;
		}
		$method = ucfirst($service).'GetFile';
		if(!is_callable(array($this, $method))) return false;
		
		$tmpPath = static::GetTmpFilePath($path);
		if($res = call_user_func_array(array($this, $method), array(&$tmpPath, $path, $fromFile)))
		{
			if(is_array($res)) return $res;
			$arFile = \CFile::MakeFileArray($tmpPath);
			if(!$arFile) $arFile = \CFile::MakeFileArray(\Bitrix\Main\IO\Path::convertLogicalToPhysical($tmpPath));
			if(strlen($arFile["type"])<=0 || (ToLower(mb_substr($arFile['tmp_name'], -4))=='xlsx' && strpos(ToLower($arFile["type"]), 'zip')!==false))
				$arFile["type"] = "unknown";
			return $arFile;
		}
		else
		{
			return false;
		}
	}
	
	public static function GetTmpFilePath($path)
	{
		$urlComponents = parse_url($path);
		if ($urlComponents && strlen($urlComponents["path"]) > 0)
		{
			$urlComponents["path"] = urldecode($urlComponents['path']);
			$tmpPath = \CFile::GetTempName('', bx_basename($urlComponents["path"]));
		}
		else
			$tmpPath = \CFile::GetTempName('', bx_basename($path));
		
		$dir = \Bitrix\Main\IO\Path::getDirectory($tmpPath);
		\Bitrix\Main\IO\Directory::createDirectory($dir);
		return $tmpPath;
	}
	
	public static function YadiskGetLinksByMask($path)
	{
		$token = \Bitrix\Main\Config\Option::get(IUtils::$moduleId, 'YANDEX_APIKEY', '');
		if(!$token) return false;
		
		$arUrl = parse_url($path);
		$fragment = $arUrl['fragment'];
		
		$path = trim(preg_replace('/[#|\?].*$/', '', $path), '/');
		$pathOrig = rtrim($path, '/');
		$arUrl = parse_url($path);
		$subPath = '';
		if(strpos($arUrl['path'], '/d/')===0 && preg_match('/^\/d\/[^\/]*\/./', $arUrl['path']))
		{
			$subPath = preg_replace('/^\/d\/[^\/]*\//', '/', $arUrl['path']);
			if($subPath && strlen($subPath) < strlen($arUrl['path']))
			{
				$path = substr($path, 0, -strlen($subPath));
			}
		}
		
		$arFiles = array();
		if(strlen($fragment) > 0)
		{
			$pattern = self::GetPatternForRegexp(preg_replace('/^\s*(\/\*)*\//', '', $fragment));
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>15, 'disableSslVerification'=>true));
			$client->setHeader('Authorization', "OAuth ".$token);
			$res = $client->get('https://cloud-api.yandex.net/v1/disk/public/resources?public_key='.urlencode($path).(strlen($subPath) > 0 ? '&path='.urlencode($subPath) : '').'&limit=99999');
			$arRes = \CUtil::JsObjectToPhp($res);
			$arItems = $arRes['_embedded']['items'];
			if(is_array($arItems))
			{
				foreach($arItems as $arItem)
				{
					if($arItem['type']=='file' && preg_match($pattern, $arItem['name']))
					{
						$arFiles[] = $pathOrig.$arItem['name'];
					}
				}
			}
		}
		return $arFiles;
	}
	
	public function YadiskGetFile(&$tmpPath, $path, $fromFile=false)
	{
		$token = \Bitrix\Main\Config\Option::get(IUtils::$moduleId, 'YANDEX_APIKEY', '');
		if(!$token) return array('ERROR_MESSAGE'=>sprintf(Loc::getMessage("KDA_IE_YANDEX_APIKEY_NOT_DEFINED"), '/bitrix/admin/settings.php?lang=ru&mid_menu=1&mid='.IUtils::$moduleId.'#yandex_token'));
		$origPath = $path;
		$path = rawurldecode($path);
		$arUrl = parse_url($path);
		$fragment = $arUrl['fragment'];
		$allowDirectLink = true;
		if(strpos($fragment, '#')===0)
		{
			$allowDirectLink = false;
			$fragment = ltrim($fragment, '#');
		}
		
		$path = trim(preg_replace('/[#|\?].*$/', '', $path), '/');
		$arUrl = parse_url($path);
		
		/*Albums*/
		if(strpos($arUrl['path'], '/a/')===0)
		{
			$arFiles = array();
			$arFilePaths = array();
			$ua = \CKDAImportUtils::GetUserAgent();
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>15, 'disableSslVerification'=>true));
			$client->setHeader('User-Agent', $ua);
			$res = $client->get($path);
			if(preg_match("/preview\.src\s*=\s*'([^']+)'/Uis", $res, $m))
			{
				$arFilePaths[] = trim($m[1], ' &').'&size=1280x1280';
			}
			elseif(preg_match_all('/"albumItemId"\s*:\s*"([^"]+)"/Uis', $res, $m))
			{
				$arItemIds = $m[1];
				if($this->params['MULTIPLE']!='Y') $arItemIds = array_slice($arItemIds, 0, 1);
				foreach($arItemIds as $itemId)
				{
					$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>15, 'disableSslVerification'=>true));
					$client->setHeader('User-Agent', $ua);
					$res = $client->get(rtrim($path, '/').'/'.$itemId);
					if(preg_match("/preview\.src\s*=\s*'([^']+)'/Uis", $res, $m))
					{
						$arFilePaths[] = trim($m[1], ' &').'&size=1280x1280';
					}
				}
			}
			
			foreach($arFilePaths as $fp)
			{
				if(preg_match('/filename=([^\&]+)/is', $fp, $m)) $fn = urldecode($m[1]);
				else $fn = $fp;
				$tmpPath = static::GetTmpFilePath($fn);
				$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>15, 'disableSslVerification' => true));
				$client->setHeader('User-Agent', $ua);
				if($res = $client->download($fp, $tmpPath))
				{
					$arFiles[] = $res = $tmpPath;
				}
			}
			if(count($arFiles)==0) return current($arFiles);
			return $arFiles;
		}
		/*/Albums*/
		
		$subPath = '';
		if(strpos($arUrl['path'], '/d/')===0)
		{
			if(ToLower(substr($arUrl['path'], -4)!=='.zip')) $this->extractZip = true;
			if(preg_match('/^\/d\/[^\/]*\/./', $arUrl['path']))
			{
				$subPath = preg_replace('/^\/d\/[^\/]*\//', '/', $arUrl['path']);
				if($subPath && strlen($subPath) < strlen($arUrl['path']))
				{
					$path = substr($path, 0, -strlen($subPath));
				}
			}
		}
		
		$fileLink = '';
		if($fragment=='yandex_preview' && strlen($subPath)==0)
		{
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$client->setHeader('Authorization', "OAuth ".$token);
			$res = $client->get('https://cloud-api.yandex.net/v1/disk/public/resources?public_key='.urlencode($path).'&preview_size=XXXL');
			$arRes = \CUtil::JsObjectToPhp($res);
			if(is_array($arRes) && $arRes['preview'])
			{
				$fileLink = $arRes['preview'];
			}
		}
		elseif(strlen($fragment) > 0 && ((strpos($fragment, '*')!==false || strpos($fragment, '?')!==false || (strpos($fragment, '{')!==false && strpos($fragment, '}')!==false))))
		{
			$pattern = self::GetPatternForRegexp(preg_replace('/^\s*(\/\*)*\//', '', $fragment));
			$listlink = 'https://cloud-api.yandex.net/v1/disk/public/resources?public_key='.urlencode($path).(strlen($subPath) > 0 ? '&path='.urlencode($subPath) : '').'&limit=9999';
			if(isset(static::$lastResult) && static::$lastResult['LINK']==$listlink)
			{
				$arItems = static::$lastResult['RESULT'];
			}
			else
			{
				$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
				$client->setHeader('Authorization', "OAuth ".$token);
				$res = $client->get('https://cloud-api.yandex.net/v1/disk/public/resources?public_key='.urlencode($path).(strlen($subPath) > 0 ? '&path='.urlencode($subPath) : '').'&limit=9999'.($fromFile ? '' : '&sort=-created'));
				$arRes = \CUtil::JsObjectToPhp($res);
				$arItems = $arRes['_embedded']['items'];
			}
			if(is_array($arItems))
			{
				$arFiles = array();
				foreach($arItems as $arItem)
				{
					if($arItem['type']=='file' && preg_match($pattern, $arItem['name']))
					{
						$arFiles[] = $fileLink = $arItem['file'];
						if(!$fromFile) break;
					}
				}
				if(count($arFiles) > 1)
				{
					$arLocalFiles = array();
					foreach($arFiles as $fileLink)
					{
						$tmpPath2 = '';
						if($this->YadiskGetFileByYaLink($tmpPath2, $fileLink))
						{
							$arLocalFiles[] = $tmpPath2;
						}
					}
					if(!empty($arLocalFiles))
					{
						/*$tmpPath = static::GetTmpFilePath('achive.zip');
						self::ArchiveFiles($tmpPath, $arLocalFiles);
						return true;*/
						return $arLocalFiles;
					}
				}
				$allowDirectLink = false;
				static::$lastResult = array('LINK'=>$listlink, 'RESULT'=>$arItems);
			}
		}
		
		if(strlen($fileLink)==0 && $allowDirectLink)
		{
			$loop = 5;
			while(($loop--) > 0)
			{
				$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
				$client->setHeader('Authorization', "OAuth ".$token);
				$res = $client->get('https://cloud-api.yandex.net/v1/disk/public/resources/download?public_key='.urlencode($path).(strlen($subPath) > 0 ? '&path='.urlencode($subPath) : ''));
				$arRes = \CUtil::JsObjectToPhp($res);
				if($arRes['error']=='DiskNotFoundError' && strlen($subPath)==0 && preg_match('#(^.*/i/[^/]*)/.+$#', $path, $m))
				{
					$path = $m[1];
				}
				elseif($arRes['error']=='TooManyRequestsError')
				{
					usleep(1000000);
				}
				else $loop = 0;
			}
			if(is_array($arRes) && $arRes['href'])
			{
				$fileLink = $arRes['href'];
			}
			//usleep(100000);
		}
		
		return $this->YadiskGetFileByYaLink($tmpPath, $fileLink);
	}
	
	public function YadiskGetFileByYaLink(&$tmpPath, $fileLink)
	{
		$token = \Bitrix\Main\Config\Option::get(IUtils::$moduleId, 'YANDEX_APIKEY', '');
		if(!$token) return false;
		if(strlen($fileLink) > 0)
		{
			$arUrl = parse_url($fileLink);
			$filename = preg_grep('/^filename=/', explode('&', $arUrl['query']));
			if(count($filename)==1)
			{
				$filename = urldecode(substr(current($filename), 9));
				if((!defined('BX_UTF') || !BX_UTF)) $filename = $GLOBALS['APPLICATION']->ConvertCharset($filename, 'UTF-8', 'CP1251');
				$tmpPath = static::GetTmpFilePath($filename);
			}
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$client->setHeader('Authorization', "OAuth ".$token);
			if($client->download($fileLink, $tmpPath))
			{
				$tmpPath = \Bitrix\Main\IO\Path::convertLogicalToPhysical($tmpPath);
				return true;
			}
		}
		return false;
	}
	
	public static function GetPatternForRegexp($pattern, $addDelimiter=true)
	{
		$pattern = preg_quote($pattern, '/');
		$pattern = preg_replace_callback('/\\\{([^\}]*)\\\}/', array(__CLASS__, 'GetPatternCallback'), $pattern);
		$pattern = strtr($pattern, array('\*'=>'.*', '\?'=>'.'));
		if($addDelimiter) return '/^'.$pattern.'$/';
		else return $pattern;
	}
	
	public static function GetPatternCallback($m)
	{
		return "(".str_replace(",", "|", $m[1]).")";
	}
	
	public static function ArchiveFiles($tmpPath, $arLocalFiles)
	{
		$tmpdir = rtrim(\Bitrix\Main\IO\Path::getDirectory($tmpPath), '/').'/_archive/';
		\Bitrix\Main\IO\Directory::createDirectory($tmpdir);
		foreach($arLocalFiles as $k=>$fn)
		{
			copy(\Bitrix\Main\IO\Path::convertLogicalToPhysical($fn), \Bitrix\Main\IO\Path::convertLogicalToPhysical($tmpdir.bx_basename($fn)));
			unlink(\Bitrix\Main\IO\Path::convertLogicalToPhysical($fn));
		}
		include_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/classes/general/zip.php');
		$zipObj = \CBXArchive::GetArchive($tmpPath, 'ZIP');
		$zipObj->SetOptions(array(
			"COMPRESS" =>true,
			"ADD_PATH" => false,
			"REMOVE_PATH" => $tmpdir,
			"CHECK_PERMISSIONS" => false
		));
		$zipObj->Pack($tmpdir);
		DeleteDirFilesEx(substr($tmpdir, strlen($_SERVER['DOCUMENT_ROOT'])));
	}
	
	public function MailruGetFile(&$tmpPath, $path, $fromFile=false)
	{
		$path = trim(rawurldecode($path));
		if(preg_match('/.(\w{2,5})([\?\#]|$)/', $path, $m) && !in_array($m[1], array('zip', 'rar', 'gz')))
		{
			$this->extractZip = true;
		}
		$arUrl = parse_url($path);
		if(isset($arUrl['fragment']) && strlen($arUrl['fragment']) > 0)
		{
			$path = substr($path, 0, -strlen($arUrl['fragment']) - 1);
		}
		$mr = \Bitrix\KdaImportexcel\Cloud\MailRu::GetInstance();
		return $mr->download($tmpPath, $path, (isset($arUrl['fragment']) ? $arUrl['fragment'] : ''));
	}
	
	public function GdriveGetFile(&$tmpPath, $path, $fromFile=false)
	{
		$path2 = '';
		if(preg_match('/^https?:\/\/drive\.google\.com\/drive\/folders\/([^\/\?#]+)(\?|#|$)/i', $path, $m))
		{
			//folder
			$folderId = $m[1];
			$arFiles = array();
			$arFolder = array();
			if($this->GdriveGetAccessToken($arFolder, $folderId, 'folder'))
			{
				if(is_array($arFolder) && isset($arFolder['files']) && is_array($arFolder['files']))
				{
					$arUrl = parse_url($path);
					$fragment = $arUrl['fragment'];
					foreach($arFolder['files'] as $apiFile)
					{
						if(strlen($fragment) > 0 && ((strpos($fragment, '*')!==false || strpos($fragment, '?')!==false || (strpos($fragment, '{')!==false && strpos($fragment, '}')!==false)) || preg_match('/^.+\.[a-z]{2,4}$/i', $fragment)!==false))
						{
							$pattern = self::GetPatternForRegexp(preg_replace('/^\s*(\/\*)*\//', '', $fragment));
							if(!preg_match($pattern, $apiFile['name'])) continue;
						}
		
						$tmpPath = static::GetTmpFilePath($apiFile['name']);
						$path = $this->GdriveGetDownloadLink($tmpPath, $apiFile['id']);
						$client = $this->GdriveGetHttpClient($path);
						if($res = $client->download($path, $tmpPath))
						{
							$arFiles[] = $res = $tmpPath;
						}
						if(!$fromFile) return $res;
					}
				}
			}
			return $arFiles;
		}
		elseif(preg_match('/^https?:\/\/docs\.google\.com\/spreadsheets.*\?.*?output=(xlsx|xls|csv)/i', $path, $m))
		{
			$path = $path;
		}
		elseif(preg_match('/^https?:\/\/docs\.google\.com\/spreadsheets.*\/d\/([^\/]+)(\/|$)/i', $path, $m)
			|| preg_match('/^https?:\/\/www\.google\.com\/.*https?:\/\/docs\.google\.com\/spreadsheets.*\/d\/([^\/]+)(\/|$)/i', $path, $m))
		{
			$tmpPath = static::GetTmpFilePath($m[1].'.xlsx');
			list($path, $path2) = $this->GdriveGetDownloadLink($tmpPath, $m[1], true);
		}
		elseif(preg_match('/^https?:\/\/drive\.google\.com\/file.*\/d\/([^\/]+)(\/|$)/i', $path, $m))
		{
			$tmpPath = static::GetTmpFilePath($m[1].'.xlsx');
			list($path, $path2) = $this->GdriveGetDownloadLink($tmpPath, $m[1], true);
		}
		elseif(preg_match('/id=([^&]+)/i', $path, $m)
			|| preg_match('/^https?:\/\/[^.]*.google.com\/u\/0\/d\/([^&?=]+)/i', $path, $m))
		{
			if(!$fromFile)
			{
				$tmpPath = static::GetTmpFilePath($m[1].'.xlsx');
				list($path, $path2) = $this->GdriveGetDownloadLink($tmpPath, $m[1], true);
			}
			else
			{
				$tmpPath = static::GetTmpFilePath($m[1].'.tmp');
				$path = $this->GdriveGetDownloadLink($tmpPath, $m[1]);
				$path2 = '';
			}
		}
		$client = $this->GdriveGetHttpClient($path);
		$res = $client->download($path, $tmpPath);
		if(!$res || $client->getStatus()==404 || stripos(file_get_contents($tmpPath, false, null, 0, 100), '<html')!==false)
		{
			$client = $this->GdriveGetHttpClient($path2);
			if($path2) $res = $client->download($path2, $tmpPath);
			if($res && filesize($tmpPath)<300*1024 && preg_match('/<a[^>]*id="uc\-download\-link"[^>]*href="([^"]+)"/Uis', file_get_contents($tmpPath), $m))
			{
				$arCookies = $client->getCookies()->toArray();
				$path2 = html_entity_decode($m[1]);
				if(substr($path2, 0, 1)=='/') $path2 = 'https://drive.google.com'.$path2;
				$client = $this->GdriveGetHttpClient($path2);
				$client->setCookies($arCookies);
				$res = $client->download($path2, $tmpPath);
			}
		}
		if($res && $client->getStatus()!=404)
		{
			$hcd = $client->getHeaders()->get('content-disposition');
			if($hcd && stripos($hcd, 'filename='))
			{
				$hcdParts = array_map('trim', explode(';', $hcd));
				$hcdParts1 = preg_grep('/filename\*=UTF\-8\'\'/i', $hcdParts);
				$hcdParts2 = preg_grep('/filename=/i', $hcdParts);
				if(count($hcdParts1) > 0)
				{
					$hcdParts1 = explode("''", current($hcdParts1));
					$fn = urldecode(trim(end($hcdParts1), '"\' '));
					if((!defined('BX_UTF') || !BX_UTF)) $fn = $GLOBALS['APPLICATION']->ConvertCharset($fn, 'UTF-8', 'CP1251');
					$fn = preg_replace('/[?]/', '', $fn);
					$fn = \Bitrix\Main\IO\Path::convertLogicalToPhysical($fn);
					if(strpos($tmpPath, $fn)===false)
					{
						$tmpPath = \CKDAImportUtils::ReplaceFile($tmpPath, preg_replace('/\/[^\/]+$/', '/'.$fn, $tmpPath));
					}
				}
				elseif(count($hcdParts2) > 0)
				{
					$hcdParts2 = explode('=', current($hcdParts2));
					$fn = trim(end($hcdParts2), '"\' ');
					if(strpos($tmpPath, $fn)===false)
					{
						$tmpPath = \CKDAImportUtils::ReplaceFile($tmpPath, preg_replace('/\/[^\/]+$/', '/'.$fn, $tmpPath));
					}
				}
			}
			return true;
		}
		return false;
	}
	
	public function GdriveGetHttpClient($path)
	{
		$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>$this->maxTime, 'streamTimeout'=>$this->maxTime, 'disableSslVerification'=>true));
		//if(strpos($path, 'googleapis.com')!==false)
		if($this->gdriveAccessToken)
		{
			$client->setHeader('Authorization', "Bearer ".$this->gdriveAccessToken);
		}
		return $client;
	}
	
	public function GdriveGetAccessToken(&$arFile, $id, $type='file')
	{
		$refreshToken = \Bitrix\Main\Config\Option::get(IUtils::$moduleId, 'GOOGLE_APIKEY', '');
		$accessToken = \Bitrix\Main\Config\Option::get(IUtils::$moduleId, 'GOOGLE_ACCESS_TOKEN', '');
		if($type=='folder') $apiPath = 'https://www.googleapis.com/drive/v3/files/?q="'.$id.'"+in+parents+and+trashed=false&fields=files(id,name),nextPageToken&includeItemsFromAllDrives=true&supportsAllDrives=true&pageSize=1000';
		else $apiPath = 'https://www.googleapis.com/drive/v3/files/'.$id.'?fields=id,webContentLink,name';
		if(isset(static::$lastResult) && static::$lastResult['LINK']==$apiPath)
		{
			$arFile = static::$lastResult['RESULT'];
			return $accessToken;
		}
		if($accessToken)
		{
			$ob = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>20, 'disableSslVerification'=>true));
			$ob->setHeader('Authorization', "Bearer ".$accessToken);
			$res = $ob->get($apiPath);
			$arFile = \CUtil::JsObjectToPhp($res);
			if($arFile['error'])
			{
				$accessToken = '';
				\Bitrix\Main\Config\Option::set(IUtils::$moduleId, 'GOOGLE_ACCESS_TOKEN', $accessToken);
			}
		}
		if(!$accessToken && $refreshToken)
		{
			$ob = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>20, 'disableSslVerification'=>true));
			$res = $ob->post('https://esolutions.su/marketplace/oauth.php', array('refresh_token'=> $refreshToken));
			$arRes = \CUtil::JsObjectToPhp($res);
			if($arRes['access_token'])
			{
				$accessToken = $arRes['access_token'];
				\Bitrix\Main\Config\Option::set(IUtils::$moduleId, 'GOOGLE_ACCESS_TOKEN', $accessToken);
				
				$ob = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>20, 'disableSslVerification'=>true));
				$ob->setHeader('Authorization', "Bearer ".$accessToken);
				$res = $ob->get($apiPath);
				$arFile = \CUtil::JsObjectToPhp($res);
			}
		}
		if($type=='folder')
		{
			$arFilePage = $arFile;
			$i = 10;
			while(is_array($arFilePage) && isset($arFilePage['nextPageToken']) && isset($arFilePage['files']) && is_array($arFilePage['files']) && count($arFilePage['files']) >= 999 && 0<$i--)
			{
				$ob = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>20, 'disableSslVerification'=>true));
				$ob->setHeader('Authorization', "Bearer ".$accessToken);
				$res = $ob->get($apiPath.'&pageToken='.$arFilePage['nextPageToken']);
				$arFilePage = \CUtil::JsObjectToPhp($res);
				if(is_array($arFilePage) && isset($arFilePage['files']) && is_array($arFilePage['files']))
				{
					$arFile['files'] = array_merge($arFile['files'], $arFilePage['files']);
				}
			}
			static::$lastResult = array('LINK'=>$apiPath, 'RESULT'=>$arFile);
		}

		return $accessToken;
	}
	
	public function GdriveGetDownloadLink(&$tmpPath, $id, $isExcel=false)
	{
		$path1 = 'https://docs.google.com/spreadsheets/d/'.$id.'/export?format=xlsx&id='.$id;
		$path2 = 'https://drive.google.com/uc?authuser=0&id='.$id.'&export=download&confirm=1';
		$arFile = array();
		if($accessToken = $this->GdriveGetAccessToken($arFile, $id))
		{
			$this->gdriveAccessToken = $accessToken;
			if(!empty($arFile))
			{
				if($arFile['name']) $tmpPath = static::GetTmpFilePath($arFile['name']);
				if($arFile['webContentLink'] && !$isExcel)
				{
					$path1 = $arFile['webContentLink'];
					//if(strpos($path1, 'id='.$id)===false) $path1 .= (strpos($path1, '?') ? '&' : '?').'id='.$id;
				}
			}

			//$path2 = 'https://www.googleapis.com/drive/v3/files/'.$id.'?alt=media&key='.$apiKey;
			$path2 = 'https://www.googleapis.com/drive/v3/files/'.$id.'?alt=media';
		}
		if($isExcel) return array($path1, $path2);
		else return $path2;
	}
	
	public function DropboxGetFile(&$tmpPath, $path, $fromFile=false)
	{
		if(preg_match('/\?dl=\d/', $path))
		{
			$path = preg_replace('/(\?dl=\d)(\D|$)/i', '?dl=1$2', $path);
		}
		else
		{
			$path .= '?dl=1';
		}
		$siteEncoding = \CKDAImportUtils::getSiteEncoding();
		if($siteEncoding!='utf-8')
		{
			$path = \Bitrix\Main\Text\Encoding::convertEncoding($path, $siteEncoding, 'utf-8');
		}
		$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>30, 'disableSslVerification'=>true, 'redirect'=>false));
		$client->setHeader('User-Agent', 'BitrixSM HttpClient class');
		$client->get($path);
		$arCookies = $client->getCookies()->toArray();
		if($client->getHeaders()->get('location'))
		{
			$path = preg_replace('/^([^\/]*\/\/[^\/]+\/).*$/', '$1', $path).trim($client->getHeaders()->get('location'), '/');
		}
		$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>30, 'disableSslVerification'=>true));
		$client->setHeader('User-Agent', 'BitrixSM HttpClient class');
		$client->setCookies($arCookies);
		if($client->download($path, $tmpPath))
		{
			$hcd = $client->getHeaders()->get('content-disposition');
			if($hcd && stripos($hcd, 'filename='))
			{
				$hcdParts = array_map('trim', explode(';', $hcd));
				$hcdParts1 = preg_grep('/filename\*=UTF\-8\'\'/i', $hcdParts);
				$hcdParts2 = preg_grep('/filename=/i', $hcdParts);
				if(count($hcdParts1) > 0)
				{
					$hcdParts1 = explode("''", current($hcdParts1));
					$fn = urldecode(trim(end($hcdParts1), '"\' '));
					if($siteEncoding!='utf-8') $fn = \Bitrix\Main\Text\Encoding::convertEncoding($fn, 'utf-8', $siteEncoding);
					//$fn = \Bitrix\Main\IO\Path::convertLogicalToPhysical($fn);
					if(strpos($tmpPath, $fn)===false)
					{
						$tmpPath = \CKDAImportUtils::ReplaceFile($tmpPath, preg_replace('/\/[^\/]+$/', '/'.$fn, $tmpPath));
					}
				}
				elseif(count($hcdParts2) > 0)
				{
					$hcdParts2 = explode('=', current($hcdParts2));
					$fn = trim(end($hcdParts2), '"\' ');
					if(strpos($tmpPath, $fn)===false)
					{
						$tmpPath = \CKDAImportUtils::ReplaceFile($tmpPath, preg_replace('/\/[^\/]+$/', '/'.$fn, $tmpPath));
					}
				}
			}
			return true;
		}
		return false;
	}
	
	public function LightshotGetFile(&$tmpPath, $path, $fromFile=false)
	{
		$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
		$client->setHeader('User-Agent', \CKDAImportUtils::GetUserAgent());
		$res = $client->get($path);
		if(preg_match('/<img[^>]+id\s*=\s*["\']screenshot\-image["\'][^>]+>/Uis', $res, $m) && preg_match('/src\s*=\s*["\']([^"\']+)["\']/Uis', $m[0], $m2))
		{
			$loc = $m2[1];
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$client->setHeader('User-Agent', \CKDAImportUtils::GetUserAgent());
			$res = $client->download($loc, $tmpPath);
			if($res && $client->getStatus()!=404) return true;
		}
		return false;
	}
	
	public function IbbGetFile(&$tmpPath, $path, $fromFile=false)
	{
		$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
		$client->setHeader('User-Agent', \CKDAImportUtils::GetUserAgent());
		$res = $client->get($path);
		if(preg_match('/<div[^>]+id\s*=\s*["\']image\-viewer\-container["\'][^>]+>\s*<img[^>]+src\s*=\s*["\']([^"\']+)["\']/Uis', $res, $m))
		{
			$loc = $m[1];
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$client->setHeader('User-Agent', \CKDAImportUtils::GetUserAgent());
			$res = $client->download($loc, $tmpPath);
			if($res && $client->getStatus()!=404) return true;
		}
		return false;
	}
	
	public function PostimgGetFile(&$tmpPath, $path, $fromFile=false)
	{
		$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
		$client->setHeader('User-Agent', \CKDAImportUtils::GetUserAgent());
		$client->setHeader('Accept', 'image/webp,*/*;q=0.8');
		$res = $client->download($path, $tmpPath);
		if($res && $client->getStatus()!=404) return true;
		return false;
	}
	
	public function CloudfarphorGetFile(&$tmpPath, $path, $fromFile=false)
	{
		if(!function_exists('json_decode')) return false;
		$arFiles = array();
		$arFileNames = array();
		if(preg_match('/^https?:\/\/cloud\.farphor\.ru\/d\/([^\/]+)\/(\?|$)/i', $path, $m))
		{
			$client = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification' => true));
			$client->setHeader('User-Agent', \CKDAImportUtils::GetUserAgent());
			$res = $client->get('https://cloud.farphor.ru/api/v2.1/share-links/'.$m[1].'/dirents/?thumbnail_size=48&path=/');
			$arResult = json_decode($res, true);
			if(is_array($arResult) && isset($arResult['dirent_list']) && is_array($arResult['dirent_list']))
			{
				$arFileNames = $arResult['dirent_list'];
				usort($arFileNames, array(__CLASS__, 'CloudfarphorSort'));
				foreach($arFileNames as $k=>$v)
				{
					$arFileNames[$k] = $v['file_name'];
				}
			}
		}
		elseif(preg_match('/^https?:\/\/cloud\.farphor\.ru\/d\/([^\/]+)\/files\/\?.*p=([^&=]*)/i', $path, $m))
		{
			$arFileNames[] = urldecode($m[2]);
		}
		
		foreach($arFileNames as $fn)
		{
			$tmpPath = static::GetTmpFilePath($fn);
			$path = 'https://cloud.farphor.ru/d/613b4c3e98974fa1a086/files/?p='.urlencode($fn).'&dl=1';
			$client = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification' => true));
			$client->setHeader('User-Agent', \CKDAImportUtils::GetUserAgent());
			if($res = $client->download($path, $tmpPath))
			{
				$arFiles[] = $res = $tmpPath;
			}
			if(!$fromFile || $this->params['MULTIPLE']!='Y') return $res;
		}
		
		return $arFiles;
	}
	
	public static function CloudfarphorSort($a, $b)
	{
		return ToLower($a["file_name"])>ToLower($b["file_name"]) ? 1 : -1;
	}
	
	public function Bitrix24GetFile(&$tmpPath, $path, $fromFile=false)
	{
		$arUrl = parse_url($path);
		$userAgent = \CKDAImportUtils::GetUserAgent();
		$loc = $path;
		$arCookies = array();
		while(strlen($loc) > 0)
		{
			$arUrl = parse_url($loc);
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true, 'redirect'=>false));
			$client->setHeader('User-Agent', $userAgent);
			$res = $client->get($loc);
			$arCookies = array_merge($arCookies, $client->getCookies()->toArray());
			
			$loc = $client->getHeaders()->get("Location");
			if(strlen($loc) > 0 && stripos($loc, 'http')!==0)
			{
				if(strpos($loc, '/')===0)
				{
					$loc = $arUrl['scheme'].'://'.$arUrl['host'].$loc;
				}
				else
				{
					if($loc=='.') $loc = '';
					$dir = preg_replace('/[\/]+/', '/', preg_replace('/(^|\/)[^\/]*$/', '', $arUrl['path']).'/');
					$loc = $arUrl['scheme'].'://'.$arUrl['host'].$dir.$loc;
				}
			}
		}

		if($arUrl['fragment'] && preg_match('/<a[^>]*href="([^"]*)"[^>]*>[^<]*'.self::GetPatternForRegexp(preg_replace('/^\s*(\/\*)*\//', '', $arUrl['fragment']), false).'[^<]*<\/a>/i', $res, $m))
		{
			$loc = $m[1];
			if(strpos($loc, '/')===0) $loc = $arUrl['scheme'].'://'.$arUrl['host'].$loc;
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$client->setHeader('User-Agent', $userAgent);
			$client->setCookies($arCookies);
			$res = $client->download($loc, $tmpPath);
			if($res && $client->getStatus()!=404) return true;
		}
		elseif(preg_match('/<a[^>]+href="([^"]*downloadFolderArchive[^"]*)"/Uis', $res, $m))
		{
			$loc = $m[1];
			if(strpos($loc, '/')===0) $loc = $arUrl['scheme'].'://'.$arUrl['host'].$loc;
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$client->setHeader('User-Agent', $userAgent);
			$client->setCookies($arCookies);
			$res = $client->download($loc, $tmpPath);
			if($res && $client->getStatus()!=404) return true;
		}
		elseif(preg_match('/<a[^>]+href="([^"]*\/download\/[^"]*)"/Uis', $res, $m))
		{
			$loc = html_entity_decode($m[1]);
			if(strpos($loc, '//'.$arUrl['host'])===0) $loc = $arUrl['scheme'].':'.$loc;
			elseif(strpos($loc, '/'.$arUrl['host'])===0) $loc = $arUrl['scheme'].':/'.$loc;
			elseif(strpos($loc, '/')===0) $loc = $arUrl['scheme'].'://'.$arUrl['host'].$loc;
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$client->setHeader('User-Agent', $userAgent);
			$client->setCookies($arCookies);
			$res = $client->download($loc, $tmpPath);
			if($res && $client->getStatus()!=404) return true;
		}
		return false;
	}
}