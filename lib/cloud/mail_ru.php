<?php
namespace Bitrix\KdaImportexcel\Cloud;

class MailRu
{
	protected static $instance = null;
	protected $lastLocation = '';
	
    function __construct($user, $pass)
    {
        $this->user = $user;
        $this->pass = $pass;
		$this->cookies = array();
        $this->dir = dirname(__FILE__);
        $this->token = '';
		$this->downloadToken = '';
        $this->x_page_id = '';
        $this->build = '';
        $this->upload_url = '';
        $this->ch = '';
		$this->weblink_get = '';
		$this->userAgent = \CKDAImportUtils::GetUserAgent();
		
		$this->isReady = $this->login();
    }
	
	public static function GetInstance()
	{
		if(!isset(static::$instance))
		{
			$user = \Bitrix\Main\Config\Option::get(\Bitrix\KdaImportexcel\IUtils::$moduleId, 'CLOUD_MAILRU_LOGIN', '');
			$pass = \Bitrix\Main\Config\Option::get(\Bitrix\KdaImportexcel\IUtils::$moduleId, 'CLOUD_MAILRU_PASSWORD', '');
			static::$instance = new static($user, $pass);
		}
		return static::$instance;
	}

    function login()
    {
		return true;
		//old version;
		
        /*$url = 'https://auth.mail.ru/cgi-bin/auth?lang=ru_RU&from=authpopup';

        $postData = array(
            "page" => "https://cloud.mail.ru/?from=promo",
            "FailPage" => "",
            "Domain" => "mail.ru",
            "Login" => $this->user,
            "Password" => $this->pass,
            "new_auth_form" => "1"
        );*/
		
		$this->Get('https://mail.ru/');
		$url = 'https://auth.mail.ru/cgi-bin/auth';
        $postData = array(
			'username'=>$this->user,
			'Login'=>$this->user,
			'password'=>$this->pass,
			'Password'=>$this->pass,
			'saveauth'=>'1',
			'new_auth_form'=>'1',
			'FromAccount'=>'opener=octavius',
			'allow_external'=>'1',
			'twoSteps'=>'1',
			'act_token'=>$this->cookies['act'],
        );

        if ($this->Post($url, $postData) !== 'error') {
            if ($this->getToken()/* && $this->getDownloadToken()*/) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function getToken()
    {
		//$url = 'https://cloud.mail.ru/?from=promo&from=authpopup';
        $url = 'https://cloud.mail.ru/?from-page=promo';
		
        $result = $this->Get($url);
        if ($result !== 'error') {
            $token = self::GetTokenFromText($result);
			$downloadToken = self::GetDownloadTokenFromText($result);
			/*New auth*/
			if (($token == '' || $downloadToken == '') && $this->lastLocation) {				
				while(strlen($this->lastLocation) > 0 && strpos($result, 'tokens')===false)
				{
					$result = $this->Get($this->lastLocation);
				}
				if ($result !== 'error') {
					$token = self::GetTokenFromText($result);
					$downloadToken = self::GetDownloadTokenFromText($result);
				}
			}
			/*/New auth*/
            if ($token == '' /*|| $downloadToken == ''*/) {				
				return false;
            } else {
                $this->token = $token;
				$this->downloadToken = $downloadToken;
                $this->x_page_id = self::GetXPageIdFromText($result);
                $this->build = self::GetBuildFromText($result);
                $this->upload_url = self::GetUploadUrlFromText($result);
				$this->weblink_get = self::GetWeblinkGetFromText($result);
                return true;
            }
        } else {
            return false;
        }
    }
	
    /*function getDownloadToken()
    {
        $url = 'https://cloud.mail.ru/api/v2/tokens/download';

        $postData = ''
                . 'api=2'
                . '&build=' . $this->build
                . '&email=' . $this->user //. '%40mail.ru'
                . '&token=' . $this->token
                . '&x-email=' . $this->user //. '%40mail.ru'
                . '&x-page-id=' . $this->x_page_id;

        $result = $this->Post($url, $postData);
        if ($result !== 'error') {
			$result = \CUtil::JsObjectToPhp($result);
			if($result['body']['token'])
			{
				$this->downloadToken = $result['body']['token'];
				return true;
			} else {
				return false;
			}
        } else {
            return false;
        }
    }*/
	
	function prepareService($path)
	{
		if(!$this->weblink_get)
		{
			$result = $this->Get($path);
			$this->x_page_id = self::GetXPageIdFromText($result);
			$this->build = self::GetBuildFromText($result);
			
			$result = $this->Get('https://cloud.mail.ru/api/v2/dispatcher?api=2&build='.$this->build.'&x-page-id='.$this->x_page_id.'&email=anonym&x-email=anonym&_='.round(microtime(true)*1000));
			$result = \CUtil::JsObjectToPhp($result);
			if(is_array($result))
			{
				$this->weblink_get = $result['body']['weblink_get'][0]['url'];
			}
		}
		return (bool)(strlen($this->weblink_get) > 0);
	}
	
    function getZipLink($path, $name)
    {
		$ob = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
		$ob->setHeader('Content-Type', 'application/json;charset=utf-8');
		$result = $ob->post('https://cloud.mail.ru/api/v3/zip/weblink', '{"weblink_list":["'.trim($path, '/').'"],"name":"'.$name.'"}');
		$result = \CUtil::JsObjectToPhp($result);
		if($result['key']) return $result['key'];
		else return "error";
		
		//old version
        /*$url = 'https://cloud.mail.ru/api/v2/zip';

        $postData = ''
                . 'api=2'
                . '&build=' . $this->build
                . '&email=' . $this->user //. '%40mail.ru'
                . '&token=' . $this->token
                . '&x-email=' . $this->user //. '%40mail.ru'
                . '&x-page-id=' . $this->x_page_id
				. '&weblink_list=' . urlencode('["'.$path.'"]')
				. '&name=' . urlencode($name)
				. '&cp866=' . 'true';

        $result = $this->Post($url, $postData);
        if ($result !== 'error') {
			$result = \CUtil::JsObjectToPhp($result);
            return $result['body'];
        } else {
            return "error";
        }*/
    }
	
    function getLinkByMask($path, $pattern)
    {
		$pattern = rawurldecode($pattern);
		$link = preg_replace('/^https?:\/\/cloud\.mail\.ru\/public\//i', '/', $path);
		$url = 'https://cloud.mail.ru/api/v2/folder?weblink='.urlencode(trim($link, '/')).'&offset=0&limit=9999&api=2&build='.$this->build.'&x-page-id='.$this->x_page_id.'&email=anonym&x-email=anonym&_='.round(microtime(true)*1000);
		$result = $this->Get($url);
		if ($result !== 'error') {
			$result = \CUtil::JsObjectToPhp($result);
			if(is_array($result['body']['list']))
			{
				foreach($result['body']['list'] as $arItem)
				{
					if($arItem['type']=='file' && fnmatch($pattern, $arItem['name'], GLOB_BRACE))
					{
						return $arItem['weblink'];
					}
				}
			}
		}
		return '';
		
		//old version
		/*$link = preg_replace('/^https?:\/\/cloud\.mail\.ru\/public\//i', '/', $path);
		$url = 'https://cloud.mail.ru/api/v2/folder';

		$postData = ''
				. 'api=2'
				. '&build=' . $this->build
				. '&email=' . urlencode($this->user) //. '%40mail.ru'
				. '&token=' . $this->token
				. '&x-email=' . urlencode($this->user) //. '%40mail.ru'
				. '&x-page-id=' . $this->x_page_id
				. '&weblink=' . urlencode(trim($link, '/'))
				. '&offset=' . 0
				. '&limit=' . '9999';

		//$result = $this->Post($url, $postData);
		$result = $this->Get($url.'?'.$postData);

		if ($result !== 'error') {
			$result = \CUtil::JsObjectToPhp($result);
			if(is_array($result['body']['list']))
			{
				foreach($result['body']['list'] as $arItem)
				{
					if($arItem['type']=='file' && fnmatch($pattern, $arItem['name'], GLOB_BRACE))
					{
						return $arItem['weblink'];
					}
				}
			}
		}
		return '';*/
    }
	
	function getFolderFiles($path, $pattern=false)
    {
		$arResult = array();
		$link = preg_replace('/^https?:\/\/cloud\.mail\.ru\/public\//i', '/', urldecode($path));
		$url = 'https://cloud.mail.ru/api/v2/folder?weblink='.urlencode(trim($link, '/')).'&offset=0&limit=9999&api=2&build='.$this->build.'&x-page-id='.$this->x_page_id.'&email=anonym&x-email=anonym&_='.round(microtime(true)*1000);
		$result = $this->Get($url);
		if ($result !== 'error') {
			$result = \CUtil::JsObjectToPhp($result);
			if(is_array($result['body']['list']))
			{
				foreach($result['body']['list'] as $arItem)
				{
					if($arItem['type']=='file' && ($pattern===false || fnmatch($pattern, $arItem['name'], GLOB_BRACE)))
					{
						$arResult[] = $arItem['weblink'];
					}
				}
			}
		}
		return $arResult;
		
		
		//old version
		/*$link = preg_replace('/^https?:\/\/cloud\.mail\.ru\/public\//i', '/', urldecode($path));
		$url = 'https://cloud.mail.ru/api/v2/folder';
		$arResult = array();

		$postData = ''
				. 'api=2'
				. '&build=' . $this->build
				. '&email=' . urlencode($this->user) //. '%40mail.ru'
				. '&token=' . $this->token
				. '&x-email=' . urlencode($this->user) //. '%40mail.ru'
				. '&x-page-id=' . $this->x_page_id
				. '&weblink=' . urlencode(trim($link, '/'))
				. '&offset=' . 0
				. '&limit=' . '9999';

		//$result = $this->Post($url, $postData);
		$result = $this->Get($url.'?'.$postData);

		if ($result !== 'error') {
			$result = \CUtil::JsObjectToPhp($result);
			if(is_array($result['body']['list']))
			{
				foreach($result['body']['list'] as $arItem)
				{
					if($arItem['type']=='file' && ($pattern===false || fnmatch($pattern, $arItem['name'], GLOB_BRACE)))
					{
						$arResult[] = $arItem['weblink'];
					}
				}
			}
		}
		return $arResult;*/
    }
	
	public function download(&$tmpPath, $path, $fragment='')
	{
		if(!$this->isReady || !$this->prepareService($path)) return false;
		
		$link = preg_replace('/^https?:\/\/cloud\.mail\.ru\/public\//i', '/', $path);
		//$fileLink = $this->weblink_get.$link.'?key='.$this->downloadToken;
		$fileLink = $this->weblink_get.$link;
		
		if($this->DownloadFile($tmpPath, $fileLink))
		{
			return true;
		}
		else
		{
			if(strlen($fragment) > 0 && ($link2 = $this->getLinkByMask($path, $fragment)))
			{
				//$fileLink = $this->weblink_get.'/'.$link2.'?key='.$this->downloadToken;
				$fileLink = $this->weblink_get.'/'.$link2;
				if($this->DownloadFile($tmpPath, $fileLink))
				{
					return true;
				}
				$link = $link2;
			}
			
			//$zipLink = 'https://cloud.mail.ru'.$this->getZipLink($link, 'folder').'?key='.$this->downloadToken;
			//$zipLink = $this->getZipLink($link, 'folder').'?key='.$this->downloadToken;
			$zipLink = $this->getZipLink($link, 'folder');
			$tmpPath = \Bitrix\KdaImportexcel\Cloud::GetTmpFilePath('folder.zip');
			if($this->DownloadFile($tmpPath, $zipLink))
			{
				return true;
			}
		}
		return false;
	}
	
	public function DownloadFile(&$tmpPath, $link)
	{
		$loc = $link;
		$client = $this->GetHttpClient(false);
		while(strlen($loc) > 0)
		{
			if(is_callable(array($client, 'head')))
			{
				$headers = $client->head($loc);
			}
			else
			{
				$client->get($loc);
				$headers = $client->getHeaders();
			}
			
			/*$client = $this->GetHttpClient(false);
			$res = $client->get($loc);*/
			
			if(in_array($client->getStatus(), array(301, 302))
				/*|| ($client->getStatus()==200 && $headers->get('location'))*/)
			{
				$loc = $link = $headers->get('location');
			}
			else $loc = '';
		}

		if($client && $client->getStatus()==200)
		{
			$fn = '';
			if($hcd = $headers->get('content-disposition'))
			{
				$arParts = preg_grep('/^filename=/i', array_map('trim', explode(';', $hcd)));
				if(count($arParts) > 0)
				{
					$fn = trim(substr(current($arParts), 9), ' "');
				}
				else
				{
					$fn = end(explode('/', current(explode('?', $link))));
				}
				
				if(strlen($fn) > 0)
				{
					$fn = urldecode($fn);
					if((!defined('BX_UTF') || !BX_UTF) && \CUtil::DetectUTF8($fn))
					{
						$fn = \Bitrix\Main\Text\Encoding::convertEncoding($fn, 'UTF-8', 'CP1251');
					}
					$tmpPath = \Bitrix\KdaImportexcel\Cloud::GetTmpFilePath($fn);
				}
			}
			if($client->download($link, $tmpPath))
			{
				return true;
			}
		}
		return false;
	}
	
	private function Post($url, $data)
	{
		$client = $this->GetHttpClient(false);
		$result = $client->post($url, $data);
		/*$i = 0;
		while($i < 5 && ($result = $client->post($url, $data)) && in_array($client->getStatus(), array(301, 302)) && ($this->GetResult($result, $client)))
		{
			$url = $client->getHeaders()->get("Location");
			$client = $this->GetHttpClient(false);
			$i++;
		}*/
		return $this->GetResult($result, $client);
	}
	
	private function Get($url)
	{
		$client = $this->GetHttpClient(false);
		$result = $client->get($url);
		/*$client = $this->GetHttpClient(false);
		//$result = $client->get($url);
		$i = 0;
		while($i < 5 && ($result = $client->get($url)) && in_array($client->getStatus(), array(301, 302)) && ($this->GetResult($result, $client)))
		{
			$url = $client->getHeaders()->get("Location");
			$client = $this->GetHttpClient(false);
			$i++;
		}*/
		return $this->GetResult($result, $client);
	}
	
	private function GetResult($result, $client)
	{
		if(in_array($client->getStatus(), array(200, 301, 302)) && !empty($result))
		{
			$arCookies = $client->getCookies()->toArray();
			$this->cookies = array_merge($this->cookies, $arCookies);
			$this->lastLocation = $client->getHeaders()->get("Location");
			return $result;
		}
		else return "error";
	}
	
	private function GetHttpClient($redirect = true)
	{
		$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true, 'redirect'=>$redirect));
		$client->setHeader('User-Agent', $this->userAgent);
		$client->setHeader('Cookie', implode('; ', array_map(array(__CLASS__, 'GetCookiesStr'),array_keys($this->cookies), $this->cookies)));
		//$client->setCookies($this->cookies);
		return $client;
	}

    private static function GetTokenFromText($str)
    {
		if(preg_match('/"tokens":[^\}]*"csrf":\s*"([^"]*)"/Uis', $str, $m)) {
            return $m[1];
        } else {
            return '';
        }
    }
	
    private static function GetDownloadTokenFromText($str)
    {
		if(preg_match('/"tokens":[^\}]*"download":\s*"([^"]*)"/Uis', $str, $m)) {
            return $m[1];
        } else {
            return '';
        }
    }

    private static function GetXPageIdFromText($str)
    {
        $start = strpos($str, '"x-page-id": "');
        if ($start > 0) {
            $start = $start + 14;
            $str_out = substr($str, $start, strpos(substr($str, $start), '"'));
            return $str_out;
        } else {
            return '';
        }
    }

    private static function GetBuildFromText($str)
    {
        $start = strpos($str, '"BUILD": "');
        if ($start > 0) {
            $start = $start + 10;

            $str_temp = substr($str, $start, 100);

            $end = strpos($str_temp, '"');

            $str_out = substr($str_temp, 0, $end - 1);
            return $str_out;
        } else {
            return '';
        }
    }

    private static function GetUploadUrlFromText($str)
    {
        if(preg_match('/"public_upload":[^\]]*"url":[^\]]*"([^"]*)"/Uis', $str, $m)) {
            return $m[1];
        } else {
            return '';
        }
        /*$start = strpos($str, 'mail.ru/upload/"');
        if ($start > 0) {
            $start1 = $start - 50;
            $end1 = $start + 15;
            $lehgth = $end1 - $start1;
            $str_temp = substr($str, $start1, $lehgth);

            $start2 = strpos($str_temp, 'https://');
            $str_out = substr($str_temp, $start2, strlen($str_temp) - $start2);
            return $str_out;
        } else {
            return '';
        }*/
    }
	
    private static function GetWeblinkGetFromText($str)
    {
        if(preg_match('/"weblink_get":[^\]]*"url":[^\]]*"([^"]*)"/Uis', $str, $m)) {
            return $m[1];
        } else {
            return '';
        }
    }
	
	public static function GetCookiesStr($k, $v)
	{
		return $k."=".$v;
	}
}