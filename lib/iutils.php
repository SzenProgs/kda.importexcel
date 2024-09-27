<?php
namespace Bitrix\KdaImportexcel;

class IUtils
{
	public static $moduleId = 'kda.importexcel';
	public static $moduleSubDir = '';
	protected static $cpSpecCharLetters = null;
	
	public static function GetCurUserID()
	{
		global $USER;
		if($USER && is_callable(array($USER, 'GetID'))) return $USER->GetID();
		else return 0;
	}
	
	public static function Trim($str)
	{
		if(is_array($str))
		{
			foreach($str as $k=>$v)
			{
				$str[$k] = self::Trim($v);
			}
			return $str;
		}
		$str = trim($str);
		$str = preg_replace('/(^(\xC2\xA0|\s)+|(\xC2\xA0|\s)+$)/s', '', $str);
		return $str;
	}
	
	public static function Translate($string, $langFrom, $langTo=false)
	{
		if(strlen(trim($string)) == 0) return $string;
		if($apiKey = \Bitrix\Main\Config\Option::get(static::$moduleId, 'TRANSLATE_GOOGLE_KEY', ''))
		{
			if($langTo===false) $langTo = LANGUAGE_ID;
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$res = $client->post('https://translation.googleapis.com/language/translate/v2', array('q'=>$string, 'source'=>$langFrom, 'target'=>$langTo, 'format'=>"text", 'key'=>$apiKey));
			$arRes = \KdaIE\Utils::JSObjectToPhp($res);
			if(isset($arRes['data']['translations'][0]['translatedText']))
			{
				$string = (is_array($arRes['data']['translations'][0]['translatedText']) ? implode('', $arRes['data']['translations'][0]['translatedText']) : $arRes['data']['translations'][0]['translatedText']);
			}
		}
		elseif(($apiKey = \Bitrix\Main\Config\Option::get('main', 'translate_key_yandex', '')) ||
			($apiKey = \Bitrix\Main\Config\Option::get(static::$moduleId, 'TRANSLATE_YANDEX_KEY', '')))
		{
			if($langTo===false) $langTo = LANGUAGE_ID;
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$client->setHeader('Content-Type', 'application/xml');
			$res = $client->get('https://translate.yandex.net/api/v1.5/tr.json/translate?key='.$apiKey.'&lang='.$langFrom.'-'.$langTo.'&text='.urlencode($string));
			$arRes = \KdaIE\Utils::JSObjectToPhp($res);
			if(array_key_exists('code', $arRes) && $arRes['code']==200 && array_key_exists('text', $arRes))
			{
				$string = (is_array($arRes['text']) ? implode('', $arRes['text']) : $arRes['text']);
			}
		}
		return $string;
	}
	
	public static function Str2Url($string, $arParams=array(), $allowEmpty=true)
	{
		if(!is_array($arParams)) $arParams = array();
		
		if(count($arParams)==0)
		{
			$arTransParams = \Bitrix\Main\Config\Option::get(static::$moduleId, 'TRANS_PARAMS', '');
			if(is_string($arTransParams) && !empty($arTransParams)) $arTransParams = unserialize($arTransParams);
			if(!is_array($arTransParams)) $arTransParams = array();
			if(!empty($arTransParams))
			{
				$arTransParams['TRANSLITERATION'] = 'Y';
				$arParams = $arTransParams;
			}
			else
			{
				$arParams['TRANSLITERATION'] = 'Y';
				$arParams['TRANS_LEN'] = 200;
				$arParams['TRANS_SPACE'] = $arParams['TRANS_OTHER'] = '-';
			}
		}
		
		if($arParams['TRANSLITERATION']=='Y')
		{
			if($arParams['USE_GOOGLE']=='Y') $string = self::Translate($string, LANGUAGE_ID, 'en');

			if(isset($arParams['TRANS_LEN'])) $arParams['max_len'] = $arParams['TRANS_LEN'];
			if(isset($arParams['TRANS_CASE'])) $arParams['change_case'] = $arParams['TRANS_CASE'];
			if(isset($arParams['TRANS_SPACE'])) $arParams['replace_space'] = $arParams['TRANS_SPACE'];
			if(isset($arParams['TRANS_OTHER'])) $arParams['replace_other'] = $arParams['TRANS_OTHER'];
			if(isset($arParams['TRANS_EAT']) && $arParams['TRANS_EAT']=='N') $arParams['delete_repeat_replace'] = false;
		}
		$res = \CUtil::translit($string, LANGUAGE_ID, $arParams);
		if(strlen($res)==0 && strlen($string)>0 && !$allowEmpty) $res = $string;
		return $res;
	}
	
	public static function DownloadTextTextByLink($val, $altVal='')
	{
		$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
		$path = (strlen(trim($altVal)) > 0 ? trim($altVal) : trim($val));
		if(strlen($path)==0) return '';
		$arUrl = parse_url($path);
		$res = trim($client->get($path));
		if($client->getStatus()==404) $res = '';
		$hct = ToLower($client->getHeaders()->get('content-type'));
		$siteEncoding = \CKDAImportUtils::getSiteEncoding();
		if(strlen($res) > 0 && class_exists('\DOMDocument') && $arUrl['fragment'])
		{
			$res = self::GetHtmlDomVal($res, $arUrl['fragment']);
		}
		elseif(preg_match('/charset=(.+)(;|$)/Uis', $hct, $m))
		{
			$fileEncoding = ToLower(trim($m[1]));
			if($siteEncoding!=$fileEncoding)
			{
				$res = \Bitrix\Main\Text\Encoding::convertEncoding($res, $fileEncoding, $siteEncoding);
			}
		}
		else
		{
			if(\CUtil::DetectUTF8($res))
			{
				if($siteEncoding!='utf-8') $res = \Bitrix\Main\Text\Encoding::convertEncoding($res, 'utf-8', $siteEncoding);
			}
			elseif($siteEncoding=='utf-8') $res = \Bitrix\Main\Text\Encoding::convertEncoding($res, 'cp1251', $siteEncoding);
		}
		return $res;
	}
	
	public static function GetHtmlDomVal($html, $selector, $img=false, $multi=false, $path=false)
	{
		$finalHtml = '';
		if(strlen($html) > 0 && class_exists('\DOMDocument') && $selector)
		{
			if($multi && !$img) $multi = false;
			/*Bom UTF-8*/
			if(\CUtil::DetectUTF8(substr($html, 0, 10000)) && (substr($html, 0, 3)!="\xEF\xBB\xBF"))
			{
				$html = "\xEF\xBB\xBF".$html;
			}
			/*/Bom UTF-8*/
			$doc = new \DOMDocument();
			$doc->preserveWhiteSpace = false;
			$doc->formatOutput = true;
			$doc->loadHTML($html);
			$node = $doc;
			$arNodes = array();
			$arParts = preg_split('/\s+/', $selector);
			$i = 0;
			while(isset($arParts[$i]) && ($node instanceOf \DOMDocument || $node instanceOf \DOMElement))
			{
				$part = $arParts[$i];
				$tagName = (preg_match('/^([^#\.\[]+)([#\.\[].*$|$)/', $part, $m) ? $m[1] : '');
				$tagId = (preg_match('/^[^#]*#([^#\.\[]+)([#\.\[].*$|$)/', $part, $m) ? $m[1] : '');
				$arClasses = array_diff(explode('.', (preg_match('/^[^\.]*\.([^#\[]+)([#\.\[].*$|$)/', $part, $m) ? $m[1] : '')), array(''));
				$arAttributes = array_map(array(__CLASS__, 'GetDomAttributes'), (preg_match_all('/\[([^\]]+(=[^\]])?)\]/', $part, $m) ? $m[1] : array()));
				if($tagName)
				{
					$nodes = $node->getElementsByTagName($tagName);
					if($tagId || !empty($arClasses) || !empty($arAttributes))
					{
						$find = false;
						$key = 0;
						while((!$find || $multi) && $key<$nodes->length)
						{
							$node1 = $nodes->item($key);
							$subfind = true;
							if($tagId && $node1->getAttribute('id')!=$tagId) $subfind = false;
							foreach($arClasses as $className)
							{
								if($className && !preg_match('/(^|\s)'.preg_quote($className, '/').'(\s|$)/is', $node1->getAttribute('class'))) $subfind = false;
							}
							foreach($arAttributes as $arAttr)
							{
								if(!$node1->hasAttribute($arAttr['k']) || (strlen($arAttr['v']) > 0 && $node1->getAttribute($arAttr['k'])!=$arAttr['v'])) $subfind = false;
							}
							$find = (bool)($find || $subfind);
							if($multi && $subfind) $arNodes[] = $nodes->item($key);
							if(!$find || $multi) $key++;
						}
						if($find && !$multi) $node = $nodes->item($key);
						elseif($find && count($arNodes)==1)
						{
							$node = current($arNodes);
							$arNodes = array();
						}
						else $node = null;
					}
					else
					{
						if($multi)
						{
							$key = 0;
							while($key<$nodes->length)
							{
								$arNodes[] = $nodes->item($key);
								$key++;
							}
						}
						$node = $nodes->item(0);
					}
				}
				$i++;
			}

			if($img && $multi && count($arNodes) > 0)
			{
				$arLinks = array();
				foreach($arNodes as $node)
				{
					if($node instanceOf \DOMElement)
					{
						$link = '';
						if($node->hasAttribute('data-src')) $link = $node->getAttribute('data-src');
						elseif($node->hasAttribute('src')) $link = $node->getAttribute('src');
						elseif($node->hasAttribute('href')) $link = $node->getAttribute('href');
						$link = trim($link);
						if(strlen($link) > 0 && !preg_match('#^https?://#', $link) && $path)
						{
							$arUrl = parse_url($path);
							$protocol = $arUrl['scheme'];
							$host = $protocol.'://'.$arUrl['host'];
							if(strpos($link, '/')===0) $link = $host.$link;
							else $link = $host.$arUrl['path'].$link;
						}
						if(strlen($link) > 0) $arLinks[] = $link;
					}
				}
				return $arLinks;
			}
			
			if($node instanceOf \DOMElement)
			{
				$innerHTML = '';
				if($img)
				{
					if($node->hasAttribute('data-src')) $innerHTML = $node->getAttribute('data-src');
					elseif($node->hasAttribute('src')) $innerHTML = $node->getAttribute('src');
					elseif($node->hasAttribute('href')) $innerHTML = $node->getAttribute('href');
					if(strlen($innerHTML) > 0 && !preg_match('#^https?://#', $innerHTML) && $path)
					{
						$arUrl = parse_url($path);
						$protocol = $arUrl['scheme'];
						$host = $protocol.'://'.$arUrl['host'];
						if(strpos($innerHTML, '/')===0) $innerHTML = $host.$innerHTML;
						else $innerHTML = $host.$arUrl['path'].$innerHTML;
					}
				}
				else
				{
					$children = $node->childNodes;
					foreach($children as $child)
					{
						$innerHTML .= $child->ownerDocument->saveHTML($child);
					}
					if(strlen($innerHTML)==0 && $node->nodeValue) $innerHTML = $node->nodeValue;
				}
				$finalHtml = trim($innerHTML);
			}
			else
			{
				$finalHtml = '';
			}
			$siteEncoding = \CKDAImportUtils::getSiteEncoding();
			if($finalHtml && $siteEncoding!='utf-8')
			{
				$finalHtml = \Bitrix\Main\Text\Encoding::convertEncoding($res, 'utf-8', $siteEncoding);
			}
		}
		return $finalHtml;
	}
	
	public static function DownloadImagesFromText($val, $domain='')
	{
		$domain = trim($domain);
		$imgDir = '/upload/esol_images/';
		$arPatterns = array(
			'/<img[^>]*\ssrc=["\']([^"\'<>]+)["\'][^>]*>/Uis',
			'/<a[^>]*\shref=["\']([^"\'<>]+\.(jpg|jpeg|png|gif|svg|webp|bmp|pdf)(\?[^"\']*)?)["\'][^>]*>/Uis',
		);
		foreach($arPatterns as $pattern)
		{
			if(preg_match_all($pattern, $val, $m))
			{
				foreach($m[1] as $k=>$img)
				{
					if(strpos($img, '//')===0) $img = (($pos = strpos($domain, '//'))!==false ? substr($domain, 0, $pos) : 'http:').$img;
					elseif(strpos($img, '/')===0) $img = $domain.$img;
					$imgName = md5($img).'.'.preg_replace('/[#\?].*$/', '', bx_basename(rawurldecode($img)));
					$imgPathDir1 = $imgDir.substr($imgName, 0, 3).'/';
					$imgPathDir = $_SERVER['DOCUMENT_ROOT'].$imgPathDir1;
					$imgPath1 = $imgPathDir1.$imgName;
					$imgPath = $imgPathDir.$imgName;
					$realFile = \Bitrix\Main\IO\Path::convertLogicalToPhysical($imgPath);
					if(!file_exists($realFile) || filesize($realFile)==0)
					{
						CheckDirPath($imgPathDir);
						$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>15, 'streamTimeout'=>15));
						$ob->setHeader('User-Agent', \CKDAImportUtils::GetUserAgent());
						$ob->download($img, $imgPath);
					}
					$imgHtml = str_replace($m[1][$k], $imgPath1, $m[0][$k]);
					$val = str_replace($m[0][$k], $imgHtml, $val);
				}
			}
		}
		return $val;
	}
	
	public static function GetFloatRoundVal($val)
	{
		if(($ar = explode('.', $val)) && count($ar)>1){$val = round($val, strlen($ar[1]));}
		return $val;
	}
	
	public static function GetDomAttributes($n)
	{
		list($k,$v)=explode("=", $n, 2);
		return array("k"=>$k, "v"=>trim($v, " \"\'"));
	}
	
	public static function BinStrlen($val)
	{
		return mb_strlen($val, 'latin1');
	}
	
    public static function BinStrpos($haystack, $needle, $offset = 0)
    {
        return mb_strpos($haystack, $needle, $offset, 'latin1');
    }
	
    public static function BinSubstr($buf, $start, $length=null)
    {
		return mb_substr($buf, $start, ($length===null ? 2000000000 : $length), 'latin1');
    }
	
    public static function detectUtf8($string, $replaceHex = true)
    {
        //http://mail.nl.linux.org/linux-utf8/1999-09/msg00110.html

        if ($replaceHex)
        {
            $string = preg_replace_callback(
                "/(%)([\\dA-F]{2})/i",
                function ($match) {
                    return chr(hexdec($match[2]));
                },
                $string
            );
        }

        //valid UTF-8 octet sequences
        //0xxxxxxx
        //110xxxxx 10xxxxxx
        //1110xxxx 10xxxxxx 10xxxxxx
        //11110xxx 10xxxxxx 10xxxxxx 10xxxxxx

        $prevBits8and7 = 0;
        $isUtf = 0;
        foreach (unpack("C*", $string) as $byte)
        {
            $hiBits8and7 = $byte & 0xC0;
            if ($hiBits8and7 == 0x80)
            {
                if ($prevBits8and7 == 0xC0)
                {
                    $isUtf++;
                }
                elseif (($prevBits8and7 & 0x80) == 0x00)
                {
                    $isUtf--;
                }
            }
            elseif ($prevBits8and7 == 0xC0)
            {
                $isUtf--;
            }
            $prevBits8and7 = $hiBits8and7;
        }
        return ($isUtf > 0);
    }
	
	public static function ReplaceCpSpecChars($val)
	{
		$specChars = array('Ø'=>'&#216;', '™'=>'&#153;', '®'=>'&#174;', '©'=>'&#169;', '²'=>'&#178;', '✓'=>'&#10003;');
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

		/*$emoji_pattern = "/\\x{1F469}\\x{200D}\\x{2764}\\x{FE0F}\\x{200D}\\x{1F48B}\\x{200D}\\x{1F469}|\\x{1F469}\\x{200D}\\x{2764}\\x{FE0F}\\x{200D}\\x{1F48B}\\x{200D}\\x{1F468}|\\x{1F468}\\x{200D}\\x{2764}\\x{FE0F}\\x{200D}\\x{1F48B}\\x{200D}\\x{1F468}|\\x{1F469}\\x{200D}\\x{1F469}\\x{200D}\\x{1F466}\\x{200D}\\x{1F466}|\\x{1F468}\\x{200D}\\x{1F468}\\x{200D}\\x{1F467}\\x{200D}\\x{1F466}|\\x{1F469}\\x{200D}\\x{1F469}\\x{200D}\\x{1F467}\\x{200D}\\x{1F467}|\\x{1F469}\\x{200D}\\x{1F469}\\x{200D}\\x{1F467}\\x{200D}\\x{1F466}|\\x{1F468}\\x{200D}\\x{1F469}\\x{200D}\\x{1F467}\\x{200D}\\x{1F467}|\\x{1F468}\\x{200D}\\x{1F469}\\x{200D}\\x{1F467}\\x{200D}\\x{1F466}|\\x{1F468}\\x{200D}\\x{1F469}\\x{200D}\\x{1F466}\\x{200D}\\x{1F466}|\\x{1F468}\\x{200D}\\x{1F468}\\x{200D}\\x{1F467}\\x{200D}\\x{1F467}|\\x{1F468}\\x{200D}\\x{1F468}\\x{200D}\\x{1F466}\\x{200D}\\x{1F466}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{2764}\\x{FE0F}\\x{200D}\\x{1F469}|\\x{1F469}\\x{200D}\\x{2764}\\x{FE0F}\\x{200D}\\x{1F468}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{2764}\\x{FE0F}\\x{200D}\\x{1F468}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F469}\\x{200D}\\x{1F466}|\\x{1F468}\\x{200D}\\x{1F469}\\x{200D}\\x{1F467}|\\x{1F468}\\x{200D}\\x{1F467}\\x{200D}\\x{1F466}|\\x{1F468}\\x{200D}\\x{1F468}\\x{200D}\\x{1F466}|\\x{1F468}\\x{200D}\\x{1F468}\\x{200D}\\x{1F467}|\\x{1F469}\\x{200D}\\x{1F469}\\x{200D}\\x{1F467}|\\x{1F469}\\x{200D}\\x{1F469}\\x{200D}\\x{1F466}|\\x{1F469}\\x{200D}\\x{1F467}\\x{200D}\\x{1F467}|\\x{1F469}\\x{200D}\\x{1F467}\\x{200D}\\x{1F466}|\\x{1F469}\\x{200D}\\x{1F466}\\x{200D}\\x{1F466}|\\x{1F468}\\x{200D}\\x{1F467}\\x{200D}\\x{1F467}|\\x{1F468}\\x{200D}\\x{1F466}\\x{200D}\\x{1F466}|\\x{1F645}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F645}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F646}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F646}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FB}|\\x{1F646}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F646}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F646}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F645}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F646}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F646}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F646}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F646}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F647}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F646}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F645}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F645}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F93E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F487}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F487}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F487}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F487}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F487}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F487}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F487}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F487}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F487}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F93E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F93E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F645}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F93E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F93D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F93D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F93D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F93D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F93D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F645}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F645}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F647}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F645}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F645}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F647}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F3CA}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F647}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F64D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F3C4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F3C4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F64D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F3C4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F64D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F3C4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F64D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F64D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F64D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F3C4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F3C4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F64D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F3C4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F64D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F64D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F3C3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F64D}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F3C3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FE}|\\x{1F64E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F64E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F64E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F64E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F64E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F64B}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F64B}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F647}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F3CA}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F647}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F647}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F647}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F3CA}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F647}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F3CA}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F647}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F3CA}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F3CA}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F64B}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F64B}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F64B}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F3C4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F3CA}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F64B}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F3CA}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F64B}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F3CA}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F3CA}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F64B}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F3C4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F64B}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F3C4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F64B}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F487}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F486}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F486}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F46E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FD}|\\x{1F93E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FC}|\\x{1F46E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F46E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F46E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F46E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F46E}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F93D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F46E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FF}|\\x{1F46E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F46E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F46E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F93E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F471}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F471}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F471}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F471}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F471}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F471}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F93E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FE}|\\x{1F471}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FF}|\\x{1F93E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FC}|\\x{1F93E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{2696}\\x{FE0F}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{2695}\\x{FE0F}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FC}|\\x{1F471}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F471}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F486}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F482}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F481}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F481}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F481}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F481}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F482}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F482}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F482}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F482}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F482}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F441}\\x{FE0F}\\x{200D}\\x{1F5E8}\\x{FE0F}|\\x{1F482}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F481}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F482}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F482}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F482}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F486}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F486}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F486}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F486}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F3C3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F486}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F486}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F486}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F481}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F481}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F471}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F473}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F93E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F473}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F473}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F473}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F473}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F473}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F473}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F473}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F473}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F473}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F477}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F481}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F477}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F477}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F477}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F477}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F477}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F477}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F477}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F477}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F477}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F481}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F481}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F3C3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F3C3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{2708}\\x{FE0F}\\x{1F3FF}|\\x{1F6B5}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F938}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F6B6}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F938}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F6B5}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F938}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F6B5}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F6B5}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F6B5}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F6B5}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F938}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F6B5}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F6B5}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F6B6}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F6B5}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F6B5}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F938}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F6B4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F6B4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F6B4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F937}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F6B4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F6B4}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F6B4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F6B4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F6B4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F6B6}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F938}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F938}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F926}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F937}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F926}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F926}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F937}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F926}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F926}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F937}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F926}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F926}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F937}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F926}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F926}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F937}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F6B6}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F926}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F937}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F937}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F6B6}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F6B6}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F937}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F6B6}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F64E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F6B6}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F6B6}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F938}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F6B6}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F938}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F938}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F6B4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F6A3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F6A3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F939}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FB}|\\x{1F939}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F939}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F939}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F939}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F6A3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F93D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F6A3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F6A3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F93D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F93D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F939}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F6A3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F93D}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F3C3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F3C3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F64E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F3C3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F64E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F64E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F3C3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FE}|\\x{1F64E}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F3C3}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F939}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FF}|\\x{1F937}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F939}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FD}|\\x{1F6A3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FE}|\\x{1F6B4}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F6A3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FD}|\\x{1F6A3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FC}|\\x{1F939}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FB}|\\x{1F939}\\x{200D}\\x{2640}\\x{FE0F}\\x{1F3FC}|\\x{1F6A3}\\x{200D}\\x{2642}\\x{FE0F}\\x{1F3FF}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F3CB}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F3CC}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F575}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2640}\\x{FE0F}|\\x{26F9}\\x{FE0F}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F468}\\x{200D}\\x{1F4BC}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F33E}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F3EB}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F4BC}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F33E}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F3EB}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F527}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F3EB}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F4BC}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F527}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F3A8}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F3A8}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F3EB}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F527}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F3EB}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F3A8}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F527}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F3A8}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F3A8}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F527}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F3A4}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F3A4}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F33E}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F393}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F3ED}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F3A4}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F3ED}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F393}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F3ED}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F393}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F3ED}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F393}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F4BB}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F393}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F4BB}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F3ED}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F33E}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F4BB}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F373}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F3A4}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F373}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F4BB}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F373}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F4BB}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F4BC}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F373}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F4BC}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F3A4}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F33E}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F373}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F33E}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F52C}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F4BB}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F4BC}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F4BC}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F4BC}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F4BC}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F4BB}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F4BB}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F4BB}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F4BB}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F527}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F3ED}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F3ED}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F3ED}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F3ED}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F3ED}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F3EB}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F52C}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F4BC}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F527}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F3EB}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F680}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F692}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F692}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F692}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F692}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F692}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F680}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F680}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F680}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F527}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F680}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F52C}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F52C}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F52C}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F52C}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F52C}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F527}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F527}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F3EB}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F3EB}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F3EB}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F692}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F3A8}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F33E}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F33E}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F33E}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F692}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F692}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F692}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F692}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F373}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F680}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F680}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F680}\\x{1F3FD}|\\x{1F468}\\x{200D}\\x{1F680}\\x{1F3FC}|\\x{1F468}\\x{200D}\\x{1F680}\\x{1F3FB}|\\x{1F468}\\x{200D}\\x{1F52C}\\x{1F3FF}|\\x{1F468}\\x{200D}\\x{1F52C}\\x{1F3FE}|\\x{1F468}\\x{200D}\\x{1F52C}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F373}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F33E}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F373}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F393}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F3A8}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F3A4}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F3A4}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F3A4}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F3A4}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F3A8}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F3A4}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F393}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F3A8}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F393}\\x{1F3FD}|\\x{1F469}\\x{200D}\\x{1F393}\\x{1F3FC}|\\x{1F469}\\x{200D}\\x{1F393}\\x{1F3FB}|\\x{1F469}\\x{200D}\\x{1F373}\\x{1F3FF}|\\x{1F469}\\x{200D}\\x{1F373}\\x{1F3FE}|\\x{1F469}\\x{200D}\\x{1F3A8}\\x{1F3FC}|\\x{1F3F3}\\x{FE0F}\\x{200D}\\x{1F308}|\\x{1F3C4}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F468}\\x{200D}\\x{2695}\\x{FE0F}|\\x{1F3C3}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F3C4}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F468}\\x{200D}\\x{2708}\\x{FE0F}|\\x{1F3CA}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F3CA}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F468}\\x{200D}\\x{2696}\\x{FE0F}|\\x{1F3C3}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F93D}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F6B4}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F93C}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F487}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F937}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F6B5}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F93E}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F6B4}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F645}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F6B6}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F645}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F938}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F646}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F6A3}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F926}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F93D}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F93C}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F6B5}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F939}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F646}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F647}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F647}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F64B}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F939}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F64B}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F938}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F64D}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F6B6}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F64D}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F6A3}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F64E}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F487}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F64E}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F486}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F477}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F471}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F46F}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F471}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F473}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F926}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F46F}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F46E}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F46E}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F473}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F477}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F93E}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F469}\\x{200D}\\x{2708}\\x{FE0F}|\\x{1F481}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F486}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F482}\\x{200D}\\x{2642}\\x{FE0F}|\\x{1F469}\\x{200D}\\x{2696}\\x{FE0F}|\\x{1F937}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F481}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F482}\\x{200D}\\x{2640}\\x{FE0F}|\\x{1F469}\\x{200D}\\x{2695}\\x{FE0F}|\\x{1F468}\\x{200D}\\x{1F680}|\\x{1F469}\\x{200D}\\x{1F52C}|\\x{1F468}\\x{200D}\\x{1F3A8}|\\x{1F468}\\x{200D}\\x{1F373}|\\x{1F469}\\x{200D}\\x{1F692}|\\x{1F468}\\x{200D}\\x{1F466}|\\x{1F468}\\x{200D}\\x{1F4BB}|\\x{1F468}\\x{200D}\\x{1F393}|\\x{1F469}\\x{200D}\\x{1F3EB}|\\x{1F469}\\x{200D}\\x{1F373}|\\x{1F468}\\x{200D}\\x{1F3ED}|\\x{1F468}\\x{200D}\\x{1F4BC}|\\x{1F469}\\x{200D}\\x{1F680}|\\x{1F468}\\x{200D}\\x{1F3A4}|\\x{1F468}\\x{200D}\\x{1F467}|\\x{1F468}\\x{200D}\\x{1F33E}|\\x{1F469}\\x{200D}\\x{1F527}|\\x{1F468}\\x{200D}\\x{1F692}|\\x{1F469}\\x{200D}\\x{1F393}|\\x{1F468}\\x{200D}\\x{1F52C}|\\x{1F469}\\x{200D}\\x{1F3A4}|\\x{1F468}\\x{200D}\\x{1F3EB}|\\x{1F469}\\x{200D}\\x{1F4BB}|\\x{1F469}\\x{200D}\\x{1F467}|\\x{1F469}\\x{200D}\\x{1F4BC}|\\x{1F469}\\x{200D}\\x{1F466}|\\x{1F469}\\x{200D}\\x{1F3A8}|\\x{1F468}\\x{200D}\\x{1F527}|\\x{1F469}\\x{200D}\\x{1F3ED}|\\x{1F469}\\x{200D}\\x{1F33E}|\\x{0039}\\x{FE0F}\\x{20E3}|\\x{0030}\\x{FE0F}\\x{20E3}|\\x{0037}\\x{FE0F}\\x{20E3}|\\x{0036}\\x{FE0F}\\x{20E3}|\\x{0023}\\x{FE0F}\\x{20E3}|\\x{002A}\\x{FE0F}\\x{20E3}|\\x{0038}\\x{FE0F}\\x{20E3}|\\x{0034}\\x{FE0F}\\x{20E3}|\\x{0031}\\x{FE0F}\\x{20E3}|\\x{0033}\\x{FE0F}\\x{20E3}|\\x{0035}\\x{FE0F}\\x{20E3}|\\x{0032}\\x{FE0F}\\x{20E3}|\\x{1F1F0}\\x{1F1FF}|\\x{1F1EE}\\x{1F1F9}|\\x{1F1F0}\\x{1F1F3}|\\x{1F1F1}\\x{1F1F0}|\\x{1F1F0}\\x{1F1F7}|\\x{1F1F0}\\x{1F1FC}|\\x{1F1EF}\\x{1F1EA}|\\x{1F930}\\x{1F3FE}|\\x{1F1F1}\\x{1F1EE}|\\x{1F1EF}\\x{1F1F2}|\\x{1F1F1}\\x{1F1F8}|\\x{1F1EF}\\x{1F1F4}|\\x{1F1F1}\\x{1F1F7}|\\x{1F933}\\x{1F3FB}|\\x{1F1EF}\\x{1F1F5}|\\x{1F1F1}\\x{1F1E6}|\\x{1F1F0}\\x{1F1F2}|\\x{1F1F0}\\x{1F1EA}|\\x{1F1F0}\\x{1F1EC}|\\x{1F933}\\x{1F3FC}|\\x{1F1F0}\\x{1F1FE}|\\x{1F1F1}\\x{1F1E8}|\\x{1F930}\\x{1F3FF}|\\x{1F1F0}\\x{1F1ED}|\\x{1F1F0}\\x{1F1EE}|\\x{1F1F0}\\x{1F1F5}|\\x{1F1F1}\\x{1F1E7}|\\x{1F918}\\x{1F3FF}|\\x{1F1EE}\\x{1F1F8}|\\x{1F1EB}\\x{1F1F7}|\\x{1F1EC}\\x{1F1F1}|\\x{1F1EC}\\x{1F1EE}|\\x{1F1EC}\\x{1F1ED}|\\x{1F1EC}\\x{1F1EC}|\\x{1F6C0}\\x{1F3FC}|\\x{1F1EC}\\x{1F1EB}|\\x{1F1EC}\\x{1F1EA}|\\x{1F1EC}\\x{1F1E9}|\\x{1F1EC}\\x{1F1E7}|\\x{1F1EC}\\x{1F1E6}|\\x{1F6C0}\\x{1F3FB}|\\x{1F1EB}\\x{1F1F4}|\\x{1F1EC}\\x{1F1F3}|\\x{1F1EB}\\x{1F1F2}|\\x{1F1EB}\\x{1F1F0}|\\x{1F1EB}\\x{1F1EF}|\\x{1F933}\\x{1F3FF}|\\x{1F934}\\x{1F3FB}|\\x{1F934}\\x{1F3FC}|\\x{1F934}\\x{1F3FD}|\\x{1F934}\\x{1F3FE}|\\x{1F934}\\x{1F3FF}|\\x{1F935}\\x{1F3FB}|\\x{1F935}\\x{1F3FC}|\\x{1F1EC}\\x{1F1F2}|\\x{1F1EC}\\x{1F1F5}|\\x{1F1EE}\\x{1F1F7}|\\x{1F1ED}\\x{1F1F3}|\\x{1F1EE}\\x{1F1F6}|\\x{1F1EE}\\x{1F1F4}|\\x{1F1EE}\\x{1F1F3}|\\x{1F1EE}\\x{1F1F2}|\\x{1F1EE}\\x{1F1F1}|\\x{1F1EE}\\x{1F1EA}|\\x{1F1EE}\\x{1F1E9}|\\x{1F1EE}\\x{1F1E8}|\\x{1F1ED}\\x{1F1FA}|\\x{1F1ED}\\x{1F1F9}|\\x{1F1ED}\\x{1F1F7}|\\x{1F1ED}\\x{1F1F2}|\\x{1F1EC}\\x{1F1F6}|\\x{1F933}\\x{1F3FD}|\\x{1F1ED}\\x{1F1F0}|\\x{1F1EC}\\x{1F1FE}|\\x{1F1EC}\\x{1F1FA}|\\x{1F1EC}\\x{1F1F9}|\\x{1F933}\\x{1F3FE}|\\x{1F6C0}\\x{1F3FF}|\\x{1F6C0}\\x{1F3FE}|\\x{1F6C0}\\x{1F3FD}|\\x{1F1EC}\\x{1F1F8}|\\x{1F1EC}\\x{1F1F7}|\\x{1F1EC}\\x{1F1FC}|\\x{1F1F2}\\x{1F1F5}|\\x{1F1F1}\\x{1F1F9}|\\x{1F1F5}\\x{1F1EC}|\\x{1F1F5}\\x{1F1FE}|\\x{1F1F5}\\x{1F1FC}|\\x{1F1F5}\\x{1F1F9}|\\x{1F1F5}\\x{1F1F8}|\\x{1F1F5}\\x{1F1F7}|\\x{1F1F5}\\x{1F1F3}|\\x{1F1F5}\\x{1F1F2}|\\x{1F1F5}\\x{1F1F1}|\\x{1F1F5}\\x{1F1F0}|\\x{1F1F5}\\x{1F1ED}|\\x{1F1F5}\\x{1F1EB}|\\x{1F1F7}\\x{1F1EA}|\\x{1F918}\\x{1F3FC}|\\x{1F918}\\x{1F3FB}|\\x{1F91C}\\x{1F3FB}|\\x{1F91C}\\x{1F3FC}|\\x{1F91C}\\x{1F3FD}|\\x{1F91C}\\x{1F3FE}|\\x{1F91C}\\x{1F3FF}|\\x{1F91E}\\x{1F3FB}|\\x{1F91E}\\x{1F3FC}|\\x{1F91E}\\x{1F3FD}|\\x{1F1F6}\\x{1F1E6}|\\x{1F1F7}\\x{1F1F4}|\\x{1F91E}\\x{1F3FF}|\\x{1F1F8}\\x{1F1F1}|\\x{1F1F8}\\x{1F1FF}|\\x{1F1F8}\\x{1F1FE}|\\x{1F1F8}\\x{1F1FD}|\\x{1F1F8}\\x{1F1FB}|\\x{1F1F8}\\x{1F1F9}|\\x{1F1F8}\\x{1F1F8}|\\x{1F1F8}\\x{1F1F7}|\\x{1F1F8}\\x{1F1F4}|\\x{1F1F8}\\x{1F1F3}|\\x{1F1F8}\\x{1F1F2}|\\x{1F1F8}\\x{1F1F0}|\\x{1F1F7}\\x{1F1F8}|\\x{1F1F8}\\x{1F1EF}|\\x{1F1F8}\\x{1F1EE}|\\x{1F1F8}\\x{1F1ED}|\\x{1F1F8}\\x{1F1EC}|\\x{1F1F8}\\x{1F1EA}|\\x{1F1F8}\\x{1F1E9}|\\x{1F1F8}\\x{1F1E8}|\\x{1F1F8}\\x{1F1E7}|\\x{1F1F8}\\x{1F1E6}|\\x{1F1F7}\\x{1F1FC}|\\x{1F1F7}\\x{1F1FA}|\\x{1F91E}\\x{1F3FE}|\\x{1F926}\\x{1F3FB}|\\x{1F1F1}\\x{1F1FA}|\\x{1F1F2}\\x{1F1ED}|\\x{1F1F2}\\x{1F1F6}|\\x{1F1F2}\\x{1F1F4}|\\x{1F1F2}\\x{1F1F3}|\\x{1F1F2}\\x{1F1F2}|\\x{1F1F2}\\x{1F1F1}|\\x{1F930}\\x{1F3FB}|\\x{1F930}\\x{1F3FC}|\\x{1F930}\\x{1F3FD}|\\x{1F6CC}\\x{1F3FF}|\\x{1F1F2}\\x{1F1F0}|\\x{1F1F2}\\x{1F1EC}|\\x{1F1F2}\\x{1F1F8}|\\x{1F1F2}\\x{1F1EB}|\\x{1F1F2}\\x{1F1EA}|\\x{1F6CC}\\x{1F3FE}|\\x{1F6CC}\\x{1F3FD}|\\x{1F6CC}\\x{1F3FC}|\\x{1F6CC}\\x{1F3FB}|\\x{1F1F2}\\x{1F1E9}|\\x{1F1F2}\\x{1F1E8}|\\x{1F1F2}\\x{1F1E6}|\\x{1F1F1}\\x{1F1FE}|\\x{1F1F1}\\x{1F1FB}|\\x{1F1F2}\\x{1F1F7}|\\x{1F1F2}\\x{1F1F9}|\\x{1F1F5}\\x{1F1EA}|\\x{1F926}\\x{1F3FD}|\\x{1F1F5}\\x{1F1E6}|\\x{1F1F4}\\x{1F1F2}|\\x{1F1F3}\\x{1F1FF}|\\x{1F1F3}\\x{1F1FA}|\\x{1F926}\\x{1F3FC}|\\x{1F1F3}\\x{1F1F7}|\\x{1F1F3}\\x{1F1F5}|\\x{1F1F3}\\x{1F1F4}|\\x{1F1F3}\\x{1F1F1}|\\x{1F1F3}\\x{1F1EE}|\\x{1F1F3}\\x{1F1EC}|\\x{1F1F2}\\x{1F1FA}|\\x{1F1F3}\\x{1F1EB}|\\x{1F1F3}\\x{1F1EA}|\\x{1F1F3}\\x{1F1E8}|\\x{1F1F3}\\x{1F1E6}|\\x{1F926}\\x{1F3FE}|\\x{1F1F2}\\x{1F1FF}|\\x{1F1F2}\\x{1F1FE}|\\x{1F1F2}\\x{1F1FD}|\\x{1F1F2}\\x{1F1FC}|\\x{1F1F2}\\x{1F1FB}|\\x{1F926}\\x{1F3FF}|\\x{1F6B6}\\x{1F3FF}|\\x{1F1E9}\\x{1F1EF}|\\x{1F6B6}\\x{1F3FE}|\\x{1F647}\\x{1F3FD}|\\x{1F64C}\\x{1F3FB}|\\x{1F64B}\\x{1F3FF}|\\x{1F1E8}\\x{1F1E8}|\\x{1F64B}\\x{1F3FE}|\\x{1F64B}\\x{1F3FD}|\\x{1F1E8}\\x{1F1E6}|\\x{1F64B}\\x{1F3FC}|\\x{1F64B}\\x{1F3FB}|\\x{1F647}\\x{1F3FF}|\\x{1F647}\\x{1F3FE}|\\x{1F647}\\x{1F3FC}|\\x{1F64C}\\x{1F3FD}|\\x{1F647}\\x{1F3FB}|\\x{1F646}\\x{1F3FF}|\\x{1F646}\\x{1F3FE}|\\x{1F646}\\x{1F3FD}|\\x{1F646}\\x{1F3FC}|\\x{1F646}\\x{1F3FB}|\\x{1F645}\\x{1F3FF}|\\x{1F645}\\x{1F3FE}|\\x{1F645}\\x{1F3FD}|\\x{1F645}\\x{1F3FC}|\\x{1F64C}\\x{1F3FC}|\\x{1F64C}\\x{1F3FE}|\\x{1F91B}\\x{1F3FB}|\\x{1F64F}\\x{1F3FD}|\\x{1F93D}\\x{1F3FD}|\\x{1F93D}\\x{1F3FE}|\\x{1F93D}\\x{1F3FF}|\\x{1F93E}\\x{1F3FB}|\\x{1F93E}\\x{1F3FC}|\\x{1F93E}\\x{1F3FD}|\\x{1F93E}\\x{1F3FE}|\\x{1F93E}\\x{1F3FF}|\\x{1F64F}\\x{1F3FF}|\\x{1F64F}\\x{1F3FE}|\\x{1F64F}\\x{1F3FC}|\\x{1F64C}\\x{1F3FF}|\\x{1F64F}\\x{1F3FB}|\\x{1F64E}\\x{1F3FF}|\\x{1F64E}\\x{1F3FE}|\\x{1F64E}\\x{1F3FD}|\\x{1F64E}\\x{1F3FC}|\\x{1F64E}\\x{1F3FB}|\\x{1F64D}\\x{1F3FF}|\\x{1F64D}\\x{1F3FE}|\\x{1F64D}\\x{1F3FD}|\\x{1F64D}\\x{1F3FC}|\\x{1F64D}\\x{1F3FB}|\\x{1F645}\\x{1F3FB}|\\x{1F91B}\\x{1F3FE}|\\x{1F93D}\\x{1F3FB}|\\x{1F1E6}\\x{1F1EC}|\\x{1F1E6}\\x{1F1FA}|\\x{1F1E6}\\x{1F1F9}|\\x{1F1E6}\\x{1F1F8}|\\x{1F91A}\\x{1F3FB}|\\x{1F1E6}\\x{1F1F7}|\\x{1F1E6}\\x{1F1F6}|\\x{1F1E6}\\x{1F1F4}|\\x{1F1E6}\\x{1F1F2}|\\x{1F1E6}\\x{1F1F1}|\\x{1F1E6}\\x{1F1EE}|\\x{1F1E6}\\x{1F1EB}|\\x{1F1E6}\\x{1F1FD}|\\x{1F1E6}\\x{1F1EA}|\\x{1F1E6}\\x{1F1E9}|\\x{1F91B}\\x{1F3FF}|\\x{1F919}\\x{1F3FF}|\\x{1F919}\\x{1F3FE}|\\x{1F919}\\x{1F3FD}|\\x{1F919}\\x{1F3FC}|\\x{1F919}\\x{1F3FB}|\\x{1F918}\\x{1F3FD}|\\x{1F1E6}\\x{1F1E8}|\\x{1F918}\\x{1F3FE}|\\x{1F1E6}\\x{1F1FC}|\\x{1F91A}\\x{1F3FC}|\\x{1F91A}\\x{1F3FF}|\\x{1F1E7}\\x{1F1F2}|\\x{1F1E7}\\x{1F1FF}|\\x{1F1E7}\\x{1F1FE}|\\x{1F1E7}\\x{1F1FC}|\\x{1F1E7}\\x{1F1FB}|\\x{1F1E7}\\x{1F1F9}|\\x{1F1E7}\\x{1F1F8}|\\x{1F1E7}\\x{1F1F7}|\\x{1F1E7}\\x{1F1F6}|\\x{1F1E7}\\x{1F1F4}|\\x{1F1E7}\\x{1F1F3}|\\x{1F1E7}\\x{1F1F1}|\\x{1F1E6}\\x{1F1FF}|\\x{1F91A}\\x{1F3FE}|\\x{1F1E7}\\x{1F1EF}|\\x{1F1E7}\\x{1F1EE}|\\x{1F1E7}\\x{1F1ED}|\\x{1F1E7}\\x{1F1EC}|\\x{1F1E7}\\x{1F1EB}|\\x{1F91A}\\x{1F3FD}|\\x{1F1E7}\\x{1F1EA}|\\x{1F1E7}\\x{1F1E9}|\\x{1F1E7}\\x{1F1E7}|\\x{1F1E7}\\x{1F1E6}|\\x{1F93D}\\x{1F3FC}|\\x{1F939}\\x{1F3FF}|\\x{1F6B6}\\x{1F3FD}|\\x{1F57A}\\x{1F3FD}|\\x{1F6B5}\\x{1F3FB}|\\x{1F575}\\x{1F3FE}|\\x{1F575}\\x{1F3FF}|\\x{1F1E9}\\x{1F1F2}|\\x{1F1E9}\\x{1F1F0}|\\x{1F1F9}\\x{1F1E8}|\\x{1F1E9}\\x{1F1EC}|\\x{1F1E9}\\x{1F1EA}|\\x{1F57A}\\x{1F3FB}|\\x{1F57A}\\x{1F3FC}|\\x{1F57A}\\x{1F3FE}|\\x{1F575}\\x{1F3FC}|\\x{1F57A}\\x{1F3FF}|\\x{1F1E8}\\x{1F1FF}|\\x{1F1E8}\\x{1F1FE}|\\x{1F1E8}\\x{1F1FD}|\\x{1F935}\\x{1F3FE}|\\x{1F1E8}\\x{1F1FC}|\\x{1F1E8}\\x{1F1FB}|\\x{1F1E8}\\x{1F1FA}|\\x{1F590}\\x{1F3FB}|\\x{1F590}\\x{1F3FC}|\\x{1F575}\\x{1F3FD}|\\x{1F575}\\x{1F3FB}|\\x{1F590}\\x{1F3FD}|\\x{1F1EA}\\x{1F1ED}|\\x{1F6B6}\\x{1F3FC}|\\x{1F6B6}\\x{1F3FB}|\\x{1F935}\\x{1F3FD}|\\x{1F6B5}\\x{1F3FF}|\\x{1F1EB}\\x{1F1EE}|\\x{1F1EA}\\x{1F1FA}|\\x{1F1EA}\\x{1F1F9}|\\x{1F1EA}\\x{1F1F8}|\\x{1F1EA}\\x{1F1F7}|\\x{1F6B5}\\x{1F3FE}|\\x{1F1EA}\\x{1F1EC}|\\x{1F1E9}\\x{1F1F4}|\\x{1F1EA}\\x{1F1EA}|\\x{1F1EA}\\x{1F1E8}|\\x{1F1EA}\\x{1F1E6}|\\x{1F6B5}\\x{1F3FD}|\\x{1F1E9}\\x{1F1FF}|\\x{1F574}\\x{1F3FB}|\\x{1F574}\\x{1F3FC}|\\x{1F574}\\x{1F3FD}|\\x{1F574}\\x{1F3FE}|\\x{1F6B5}\\x{1F3FC}|\\x{1F574}\\x{1F3FF}|\\x{1F6B4}\\x{1F3FF}|\\x{1F590}\\x{1F3FE}|\\x{1F939}\\x{1F3FE}|\\x{1F938}\\x{1F3FB}|\\x{1F936}\\x{1F3FB}|\\x{1F936}\\x{1F3FC}|\\x{1F936}\\x{1F3FD}|\\x{1F936}\\x{1F3FE}|\\x{1F936}\\x{1F3FF}|\\x{1F937}\\x{1F3FB}|\\x{1F937}\\x{1F3FC}|\\x{1F937}\\x{1F3FD}|\\x{1F937}\\x{1F3FE}|\\x{1F937}\\x{1F3FF}|\\x{1F938}\\x{1F3FC}|\\x{1F1E8}\\x{1F1E9}|\\x{1F938}\\x{1F3FD}|\\x{1F6A3}\\x{1F3FF}|\\x{1F6A3}\\x{1F3FE}|\\x{1F6A3}\\x{1F3FD}|\\x{1F6A3}\\x{1F3FC}|\\x{1F6A3}\\x{1F3FB}|\\x{1F938}\\x{1F3FE}|\\x{1F938}\\x{1F3FF}|\\x{1F939}\\x{1F3FB}|\\x{1F939}\\x{1F3FC}|\\x{1F939}\\x{1F3FD}|\\x{1F935}\\x{1F3FF}|\\x{1F1E8}\\x{1F1EB}|\\x{1F590}\\x{1F3FF}|\\x{1F596}\\x{1F3FC}|\\x{1F1E8}\\x{1F1F7}|\\x{1F595}\\x{1F3FB}|\\x{1F6B4}\\x{1F3FE}|\\x{1F595}\\x{1F3FC}|\\x{1F595}\\x{1F3FD}|\\x{1F595}\\x{1F3FE}|\\x{1F595}\\x{1F3FF}|\\x{1F1E8}\\x{1F1F5}|\\x{1F6B4}\\x{1F3FD}|\\x{1F596}\\x{1F3FB}|\\x{1F596}\\x{1F3FD}|\\x{1F1E8}\\x{1F1EC}|\\x{1F596}\\x{1F3FE}|\\x{1F596}\\x{1F3FF}|\\x{1F6B4}\\x{1F3FC}|\\x{1F1E8}\\x{1F1F4}|\\x{1F1E8}\\x{1F1F3}|\\x{1F1E8}\\x{1F1F2}|\\x{1F1E8}\\x{1F1F1}|\\x{1F1E8}\\x{1F1F0}|\\x{1F6B4}\\x{1F3FB}|\\x{1F1E8}\\x{1F1EE}|\\x{1F1E8}\\x{1F1ED}|\\x{1F1F9}\\x{1F1E6}|\\x{1F44A}\\x{1F3FF}|\\x{1F1F9}\\x{1F1E9}|\\x{1F44B}\\x{1F3FC}|\\x{1F44D}\\x{1F3FF}|\\x{1F44D}\\x{1F3FE}|\\x{1F44D}\\x{1F3FD}|\\x{1F44D}\\x{1F3FC}|\\x{1F44D}\\x{1F3FB}|\\x{1F44C}\\x{1F3FF}|\\x{1F44C}\\x{1F3FE}|\\x{1F44C}\\x{1F3FD}|\\x{1F44C}\\x{1F3FC}|\\x{1F44C}\\x{1F3FB}|\\x{1F44B}\\x{1F3FF}|\\x{1F44B}\\x{1F3FE}|\\x{1F44B}\\x{1F3FD}|\\x{1F44B}\\x{1F3FB}|\\x{1F44E}\\x{1F3FC}|\\x{1F91B}\\x{1F3FD}|\\x{1F44A}\\x{1F3FE}|\\x{1F44A}\\x{1F3FD}|\\x{1F44A}\\x{1F3FC}|\\x{1F44A}\\x{1F3FB}|\\x{1F449}\\x{1F3FF}|\\x{1F449}\\x{1F3FE}|\\x{1F449}\\x{1F3FD}|\\x{1F449}\\x{1F3FC}|\\x{1F449}\\x{1F3FB}|\\x{1F448}\\x{1F3FF}|\\x{1F448}\\x{1F3FE}|\\x{1F448}\\x{1F3FD}|\\x{1F44E}\\x{1F3FB}|\\x{1F44E}\\x{1F3FD}|\\x{1F448}\\x{1F3FB}|\\x{1F466}\\x{1F3FE}|\\x{1F469}\\x{1F3FC}|\\x{1F469}\\x{1F3FB}|\\x{1F468}\\x{1F3FF}|\\x{1F468}\\x{1F3FE}|\\x{1F468}\\x{1F3FD}|\\x{1F468}\\x{1F3FC}|\\x{1F468}\\x{1F3FB}|\\x{1F467}\\x{1F3FF}|\\x{1F467}\\x{1F3FE}|\\x{1F467}\\x{1F3FD}|\\x{1F467}\\x{1F3FC}|\\x{1F467}\\x{1F3FB}|\\x{1F466}\\x{1F3FF}|\\x{1F466}\\x{1F3FD}|\\x{1F44E}\\x{1F3FE}|\\x{1F466}\\x{1F3FC}|\\x{1F466}\\x{1F3FB}|\\x{1F450}\\x{1F3FF}|\\x{1F450}\\x{1F3FE}|\\x{1F450}\\x{1F3FD}|\\x{1F450}\\x{1F3FC}|\\x{1F450}\\x{1F3FB}|\\x{1F44F}\\x{1F3FF}|\\x{1F44F}\\x{1F3FE}|\\x{1F44F}\\x{1F3FD}|\\x{1F44F}\\x{1F3FC}|\\x{1F44F}\\x{1F3FB}|\\x{1F44E}\\x{1F3FF}|\\x{1F448}\\x{1F3FC}|\\x{1F447}\\x{1F3FF}|\\x{1F469}\\x{1F3FE}|\\x{1F3C3}\\x{1F3FE}|\\x{1F3CA}\\x{1F3FC}|\\x{1F3CA}\\x{1F3FB}|\\x{1F3C7}\\x{1F3FF}|\\x{1F3C7}\\x{1F3FE}|\\x{1F3C7}\\x{1F3FD}|\\x{1F3C7}\\x{1F3FC}|\\x{1F3C7}\\x{1F3FB}|\\x{1F3C4}\\x{1F3FF}|\\x{1F3C4}\\x{1F3FE}|\\x{1F3C4}\\x{1F3FD}|\\x{1F3C4}\\x{1F3FC}|\\x{1F3C4}\\x{1F3FB}|\\x{1F3C3}\\x{1F3FF}|\\x{1F3C3}\\x{1F3FD}|\\x{1F3CA}\\x{1F3FE}|\\x{1F3C3}\\x{1F3FC}|\\x{1F3C3}\\x{1F3FB}|\\x{1F3C2}\\x{1F3FF}|\\x{1F3C2}\\x{1F3FE}|\\x{1F3C2}\\x{1F3FD}|\\x{1F1F9}\\x{1F1EB}|\\x{1F3C2}\\x{1F3FC}|\\x{1F3C2}\\x{1F3FB}|\\x{1F385}\\x{1F3FF}|\\x{1F385}\\x{1F3FE}|\\x{1F385}\\x{1F3FD}|\\x{1F385}\\x{1F3FC}|\\x{1F385}\\x{1F3FB}|\\x{1F3CA}\\x{1F3FD}|\\x{1F3CA}\\x{1F3FF}|\\x{1F447}\\x{1F3FE}|\\x{1F442}\\x{1F3FF}|\\x{1F447}\\x{1F3FD}|\\x{1F447}\\x{1F3FC}|\\x{1F447}\\x{1F3FB}|\\x{1F446}\\x{1F3FF}|\\x{1F446}\\x{1F3FE}|\\x{1F446}\\x{1F3FD}|\\x{1F446}\\x{1F3FC}|\\x{1F446}\\x{1F3FB}|\\x{1F443}\\x{1F3FF}|\\x{1F443}\\x{1F3FE}|\\x{1F443}\\x{1F3FD}|\\x{1F443}\\x{1F3FC}|\\x{1F443}\\x{1F3FB}|\\x{1F442}\\x{1F3FE}|\\x{1F3CB}\\x{1F3FB}|\\x{1F442}\\x{1F3FD}|\\x{1F442}\\x{1F3FC}|\\x{1F442}\\x{1F3FB}|\\x{1F1F9}\\x{1F1ED}|\\x{1F3CC}\\x{1F3FF}|\\x{1F3CC}\\x{1F3FE}|\\x{1F3CC}\\x{1F3FD}|\\x{1F3CC}\\x{1F3FC}|\\x{1F3CC}\\x{1F3FB}|\\x{1F3CB}\\x{1F3FF}|\\x{1F3CB}\\x{1F3FE}|\\x{1F3CB}\\x{1F3FD}|\\x{1F3CB}\\x{1F3FC}|\\x{1F469}\\x{1F3FD}|\\x{1F91B}\\x{1F3FC}|\\x{1F469}\\x{1F3FF}|\\x{1F486}\\x{1F3FE}|\\x{1F1FC}\\x{1F1F8}|\\x{1F1FD}\\x{1F1F0}|\\x{1F1FE}\\x{1F1EA}|\\x{1F1FE}\\x{1F1F9}|\\x{1F1FF}\\x{1F1E6}|\\x{1F1FF}\\x{1F1F2}|\\x{1F1FF}\\x{1F1FC}|\\x{1F487}\\x{1F3FF}|\\x{1F487}\\x{1F3FE}|\\x{1F487}\\x{1F3FD}|\\x{1F487}\\x{1F3FC}|\\x{1F487}\\x{1F3FB}|\\x{1F486}\\x{1F3FF}|\\x{1F486}\\x{1F3FD}|\\x{1F1FB}\\x{1F1FA}|\\x{1F486}\\x{1F3FC}|\\x{1F486}\\x{1F3FB}|\\x{1F485}\\x{1F3FF}|\\x{1F485}\\x{1F3FE}|\\x{1F485}\\x{1F3FD}|\\x{1F485}\\x{1F3FC}|\\x{1F485}\\x{1F3FB}|\\x{1F483}\\x{1F3FF}|\\x{1F483}\\x{1F3FE}|\\x{1F483}\\x{1F3FD}|\\x{1F483}\\x{1F3FC}|\\x{1F483}\\x{1F3FB}|\\x{1F482}\\x{1F3FF}|\\x{1F1FC}\\x{1F1EB}|\\x{1F1FB}\\x{1F1F3}|\\x{1F482}\\x{1F3FD}|\\x{1F1FA}\\x{1F1EC}|\\x{1F46E}\\x{1F3FB}|\\x{1F1F9}\\x{1F1EC}|\\x{1F1F9}\\x{1F1F0}|\\x{1F1F9}\\x{1F1F1}|\\x{1F1F9}\\x{1F1F2}|\\x{1F1F9}\\x{1F1F3}|\\x{1F1F9}\\x{1F1F4}|\\x{1F1F9}\\x{1F1F7}|\\x{1F1F9}\\x{1F1F9}|\\x{1F1F9}\\x{1F1FB}|\\x{1F1F9}\\x{1F1FC}|\\x{1F1F9}\\x{1F1FF}|\\x{1F1FA}\\x{1F1E6}|\\x{1F1FA}\\x{1F1F2}|\\x{1F1FB}\\x{1F1EE}|\\x{1F1FA}\\x{1F1F3}|\\x{1F1FA}\\x{1F1F8}|\\x{1F1FA}\\x{1F1FE}|\\x{1F1FA}\\x{1F1FF}|\\x{1F1FB}\\x{1F1E6}|\\x{1F4AA}\\x{1F3FF}|\\x{1F4AA}\\x{1F3FE}|\\x{1F4AA}\\x{1F3FD}|\\x{1F4AA}\\x{1F3FC}|\\x{1F4AA}\\x{1F3FB}|\\x{1F1FB}\\x{1F1E8}|\\x{1F1FB}\\x{1F1EA}|\\x{1F1FB}\\x{1F1EC}|\\x{1F482}\\x{1F3FE}|\\x{1F1F9}\\x{1F1EF}|\\x{1F482}\\x{1F3FC}|\\x{1F472}\\x{1F3FC}|\\x{1F474}\\x{1F3FF}|\\x{1F474}\\x{1F3FE}|\\x{1F474}\\x{1F3FD}|\\x{1F474}\\x{1F3FC}|\\x{1F474}\\x{1F3FB}|\\x{1F473}\\x{1F3FF}|\\x{1F473}\\x{1F3FE}|\\x{1F473}\\x{1F3FD}|\\x{1F473}\\x{1F3FC}|\\x{1F482}\\x{1F3FB}|\\x{1F472}\\x{1F3FF}|\\x{1F472}\\x{1F3FE}|\\x{1F472}\\x{1F3FD}|\\x{1F472}\\x{1F3FB}|\\x{1F475}\\x{1F3FC}|\\x{1F471}\\x{1F3FF}|\\x{1F471}\\x{1F3FE}|\\x{1F471}\\x{1F3FD}|\\x{1F471}\\x{1F3FC}|\\x{1F471}\\x{1F3FB}|\\x{1F470}\\x{1F3FF}|\\x{1F470}\\x{1F3FE}|\\x{1F470}\\x{1F3FD}|\\x{1F470}\\x{1F3FC}|\\x{1F470}\\x{1F3FB}|\\x{1F46E}\\x{1F3FF}|\\x{1F46E}\\x{1F3FE}|\\x{1F46E}\\x{1F3FD}|\\x{1F46E}\\x{1F3FC}|\\x{1F475}\\x{1F3FB}|\\x{1F473}\\x{1F3FB}|\\x{1F475}\\x{1F3FD}|\\x{1F478}\\x{1F3FB}|\\x{1F481}\\x{1F3FF}|\\x{1F481}\\x{1F3FE}|\\x{1F481}\\x{1F3FD}|\\x{1F481}\\x{1F3FB}|\\x{1F47C}\\x{1F3FF}|\\x{1F47C}\\x{1F3FE}|\\x{1F47C}\\x{1F3FD}|\\x{1F47C}\\x{1F3FC}|\\x{1F47C}\\x{1F3FB}|\\x{1F478}\\x{1F3FF}|\\x{1F478}\\x{1F3FE}|\\x{1F478}\\x{1F3FD}|\\x{1F478}\\x{1F3FC}|\\x{1F481}\\x{1F3FC}|\\x{1F477}\\x{1F3FF}|\\x{1F477}\\x{1F3FD}|\\x{1F477}\\x{1F3FC}|\\x{1F477}\\x{1F3FB}|\\x{1F476}\\x{1F3FF}|\\x{1F476}\\x{1F3FE}|\\x{1F476}\\x{1F3FD}|\\x{1F476}\\x{1F3FC}|\\x{1F476}\\x{1F3FB}|\\x{1F477}\\x{1F3FE}|\\x{1F475}\\x{1F3FF}|\\x{1F475}\\x{1F3FE}|\\x{270D}\\x{1F3FD}|\\x{270C}\\x{1F3FF}|\\x{270D}\\x{1F3FB}|\\x{270D}\\x{1F3FC}|\\x{261D}\\x{1F3FD}|\\x{270D}\\x{1F3FE}|\\x{270D}\\x{1F3FF}|\\x{261D}\\x{1F3FF}|\\x{261D}\\x{1F3FE}|\\x{270C}\\x{1F3FD}|\\x{261D}\\x{1F3FC}|\\x{261D}\\x{1F3FB}|\\x{270C}\\x{1F3FE}|\\x{270B}\\x{1F3FC}|\\x{270C}\\x{1F3FC}|\\x{270C}\\x{1F3FB}|\\x{270B}\\x{1F3FF}|\\x{270B}\\x{1F3FE}|\\x{270B}\\x{1F3FD}|\\x{270B}\\x{1F3FB}|\\x{270A}\\x{1F3FF}|\\x{270A}\\x{1F3FE}|\\x{270A}\\x{1F3FD}|\\x{270A}\\x{1F3FC}|\\x{26F9}\\x{1F3FB}|\\x{270A}\\x{1F3FB}|\\x{26F9}\\x{1F3FC}|\\x{26F9}\\x{1F3FD}|\\x{1F004}\\x{FE0F}|\\x{26F9}\\x{1F3FF}|\\x{1F202}\\x{FE0F}|\\x{1F237}\\x{FE0F}|\\x{1F21A}\\x{FE0F}|\\x{1F22F}\\x{FE0F}|\\x{26F9}\\x{1F3FE}|\\x{1F170}\\x{FE0F}|\\x{1F3CB}\\x{FE0F}|\\x{1F171}\\x{FE0F}|\\x{1F17F}\\x{FE0F}|\\x{1F17E}\\x{FE0F}|\\x{1F575}\\x{FE0F}|\\x{1F3CC}\\x{FE0F}|\\x{1F3F3}\\x{FE0F}|\\x{269B}\\x{FE0F}|\\x{2699}\\x{FE0F}|\\x{269C}\\x{FE0F}|\\x{2697}\\x{FE0F}|\\x{2696}\\x{FE0F}|\\x{25AB}\\x{FE0F}|\\x{2694}\\x{FE0F}|\\x{2195}\\x{FE0F}|\\x{2196}\\x{FE0F}|\\x{26A1}\\x{FE0F}|\\x{2693}\\x{FE0F}|\\x{2197}\\x{FE0F}|\\x{267F}\\x{FE0F}|\\x{2198}\\x{FE0F}|\\x{267B}\\x{FE0F}|\\x{26A0}\\x{FE0F}|\\x{26BD}\\x{FE0F}|\\x{26AA}\\x{FE0F}|\\x{203C}\\x{FE0F}|\\x{26F9}\\x{FE0F}|\\x{26F5}\\x{FE0F}|\\x{26F3}\\x{FE0F}|\\x{26F2}\\x{FE0F}|\\x{26EA}\\x{FE0F}|\\x{26D4}\\x{FE0F}|\\x{00AE}\\x{FE0F}|\\x{2049}\\x{FE0F}|\\x{26AB}\\x{FE0F}|\\x{26C5}\\x{FE0F}|\\x{2122}\\x{FE0F}|\\x{2139}\\x{FE0F}|\\x{2194}\\x{FE0F}|\\x{26C4}\\x{FE0F}|\\x{26BE}\\x{FE0F}|\\x{26B1}\\x{FE0F}|\\x{26B0}\\x{FE0F}|\\x{2199}\\x{FE0F}|\\x{2666}\\x{FE0F}|\\x{2668}\\x{FE0F}|\\x{2611}\\x{FE0F}|\\x{21AA}\\x{FE0F}|\\x{231A}\\x{FE0F}|\\x{231B}\\x{FE0F}|\\x{2328}\\x{FE0F}|\\x{261D}\\x{FE0F}|\\x{2618}\\x{FE0F}|\\x{24C2}\\x{FE0F}|\\x{2615}\\x{FE0F}|\\x{2614}\\x{FE0F}|\\x{260E}\\x{FE0F}|\\x{2622}\\x{FE0F}|\\x{2604}\\x{FE0F}|\\x{2603}\\x{FE0F}|\\x{2602}\\x{FE0F}|\\x{2601}\\x{FE0F}|\\x{2600}\\x{FE0F}|\\x{25FE}\\x{FE0F}|\\x{25AA}\\x{FE0F}|\\x{25FC}\\x{FE0F}|\\x{25FB}\\x{FE0F}|\\x{25C0}\\x{FE0F}|\\x{2620}\\x{FE0F}|\\x{2623}\\x{FE0F}|\\x{25B6}\\x{FE0F}|\\x{264C}\\x{FE0F}|\\x{2665}\\x{FE0F}|\\x{2663}\\x{FE0F}|\\x{2660}\\x{FE0F}|\\x{2653}\\x{FE0F}|\\x{2652}\\x{FE0F}|\\x{2651}\\x{FE0F}|\\x{2650}\\x{FE0F}|\\x{264F}\\x{FE0F}|\\x{264E}\\x{FE0F}|\\x{264D}\\x{FE0F}|\\x{264B}\\x{FE0F}|\\x{2626}\\x{FE0F}|\\x{264A}\\x{FE0F}|\\x{2649}\\x{FE0F}|\\x{2648}\\x{FE0F}|\\x{263A}\\x{FE0F}|\\x{2639}\\x{FE0F}|\\x{2638}\\x{FE0F}|\\x{21A9}\\x{FE0F}|\\x{262F}\\x{FE0F}|\\x{262E}\\x{FE0F}|\\x{262A}\\x{FE0F}|\\x{25FD}\\x{FE0F}|\\x{2934}\\x{FE0F}|\\x{00A9}\\x{FE0F}|\\x{27A1}\\x{FE0F}|\\x{2B1C}\\x{FE0F}|\\x{2B1B}\\x{FE0F}|\\x{26FA}\\x{FE0F}|\\x{2B06}\\x{FE0F}|\\x{2B05}\\x{FE0F}|\\x{2935}\\x{FE0F}|\\x{2764}\\x{FE0F}|\\x{2B55}\\x{FE0F}|\\x{2763}\\x{FE0F}|\\x{2757}\\x{FE0F}|\\x{2747}\\x{FE0F}|\\x{2744}\\x{FE0F}|\\x{2734}\\x{FE0F}|\\x{2733}\\x{FE0F}|\\x{2B50}\\x{FE0F}|\\x{3030}\\x{FE0F}|\\x{271D}\\x{FE0F}|\\x{0033}\\x{20E3}|\\x{0039}\\x{20E3}|\\x{0038}\\x{20E3}|\\x{0037}\\x{20E3}|\\x{0036}\\x{20E3}|\\x{0035}\\x{20E3}|\\x{0034}\\x{20E3}|\\x{0032}\\x{20E3}|\\x{303D}\\x{FE0F}|\\x{0031}\\x{20E3}|\\x{0030}\\x{20E3}|\\x{002A}\\x{20E3}|\\x{0023}\\x{20E3}|\\x{3299}\\x{FE0F}|\\x{3297}\\x{FE0F}|\\x{2721}\\x{FE0F}|\\x{2B07}\\x{FE0F}|\\x{2716}\\x{FE0F}|\\x{2714}\\x{FE0F}|\\x{2712}\\x{FE0F}|\\x{26FD}\\x{FE0F}|\\x{2702}\\x{FE0F}|\\x{270F}\\x{FE0F}|\\x{270D}\\x{FE0F}|\\x{2708}\\x{FE0F}|\\x{270C}\\x{FE0F}|\\x{2709}\\x{FE0F}|\\x{1F988}|\\x{1F98B}|\\x{1F98A}|\\x{1F989}|\\x{1F91D}|\\x{1F91E}|\\x{1F920}|\\x{1F987}|\\x{1F986}|\\x{1F985}|\\x{1F984}|\\x{1F98D}|\\x{1F921}|\\x{1F98C}|\\x{1F91C}|\\x{1F98E}|\\x{1F98F}|\\x{1F990}|\\x{1F991}|\\x{1F9C0}|\\x{1F923}|\\x{1F942}|\\x{1F941}|\\x{1F940}|\\x{1F93E}|\\x{1F93D}|\\x{1F938}|\\x{1F93C}|\\x{1F93A}|\\x{1F3EE}|\\x{1F922}|\\x{1F983}|\\x{1F924}|\\x{1F95A}|\\x{1F94A}|\\x{1F95B}|\\x{1F94B}|\\x{1F950}|\\x{1F951}|\\x{1F952}|\\x{1F959}|\\x{1F949}|\\x{1F958}|\\x{1F957}|\\x{1F934}|\\x{1F953}|\\x{1F954}|\\x{1F935}|\\x{1F956}|\\x{1F933}|\\x{1F936}|\\x{1F925}|\\x{1F927}|\\x{1F926}|\\x{1F955}|\\x{1F982}|\\x{1F981}|\\x{1F980}|\\x{1F95E}|\\x{1F930}|\\x{1F948}|\\x{1F95D}|\\x{1F937}|\\x{1F943}|\\x{1F944}|\\x{1F945}|\\x{1F95C}|\\x{1F947}|\\x{1F939}|\\x{1F615}|\\x{1F91B}|\\x{1F400}|\\x{1F40A}|\\x{1F409}|\\x{1F408}|\\x{1F407}|\\x{1F406}|\\x{1F405}|\\x{1F404}|\\x{1F403}|\\x{1F402}|\\x{1F401}|\\x{1F3FF}|\\x{1F40C}|\\x{1F3FE}|\\x{1F3FD}|\\x{1F3FC}|\\x{1F3FB}|\\x{1F3FA}|\\x{1F3F9}|\\x{1F3F8}|\\x{1F3F7}|\\x{1F3F5}|\\x{1F3F4}|\\x{1F40B}|\\x{1F40D}|\\x{1F3F0}|\\x{1F41B}|\\x{1F425}|\\x{1F424}|\\x{1F423}|\\x{1F422}|\\x{1F421}|\\x{1F420}|\\x{1F41F}|\\x{1F41E}|\\x{1F41D}|\\x{1F41C}|\\x{1F41A}|\\x{1F40E}|\\x{1F419}|\\x{1F418}|\\x{1F417}|\\x{1F416}|\\x{1F415}|\\x{1F414}|\\x{1F413}|\\x{1F412}|\\x{1F411}|\\x{1F410}|\\x{1F40F}|\\x{1F3F3}|\\x{1F3EF}|\\x{1F427}|\\x{1F3C7}|\\x{1F3D1}|\\x{1F3D0}|\\x{1F3CF}|\\x{1F3CE}|\\x{1F3CD}|\\x{1F3CC}|\\x{1F3CB}|\\x{1F3CA}|\\x{1F3C9}|\\x{1F3C8}|\\x{1F3C6}|\\x{1F3D3}|\\x{1F3C5}|\\x{1F3C4}|\\x{1F3C3}|\\x{1F3C2}|\\x{1F3C1}|\\x{1F3C0}|\\x{1F3BF}|\\x{1F3BE}|\\x{1F3BD}|\\x{1F3BC}|\\x{1F3D2}|\\x{1F3D4}|\\x{1F3ED}|\\x{1F3E2}|\\x{1F3EC}|\\x{1F3EB}|\\x{1F3EA}|\\x{1F3E9}|\\x{1F3E8}|\\x{1F3E7}|\\x{1F3E6}|\\x{1F3E5}|\\x{1F3E4}|\\x{1F3E3}|\\x{1F3E1}|\\x{1F3D5}|\\x{1F3E0}|\\x{1F3DF}|\\x{1F3DE}|\\x{1F3DD}|\\x{1F3DC}|\\x{1F3DB}|\\x{1F3DA}|\\x{1F3D9}|\\x{1F3D8}|\\x{1F3D7}|\\x{1F3D6}|\\x{1F426}|\\x{1F428}|\\x{1F3BA}|\\x{1F46B}|\\x{1F475}|\\x{1F474}|\\x{1F473}|\\x{1F472}|\\x{1F471}|\\x{1F470}|\\x{1F46F}|\\x{1F46E}|\\x{1F46D}|\\x{1F46C}|\\x{1F46A}|\\x{1F477}|\\x{1F469}|\\x{1F468}|\\x{1F467}|\\x{1F466}|\\x{1F465}|\\x{1F464}|\\x{1F463}|\\x{1F462}|\\x{1F461}|\\x{1F460}|\\x{1F476}|\\x{1F478}|\\x{1F45E}|\\x{1F486}|\\x{1F490}|\\x{1F48F}|\\x{1F48E}|\\x{1F48D}|\\x{1F48C}|\\x{1F48B}|\\x{1F48A}|\\x{1F489}|\\x{1F488}|\\x{1F487}|\\x{1F485}|\\x{1F479}|\\x{1F484}|\\x{1F483}|\\x{1F482}|\\x{1F481}|\\x{1F480}|\\x{1F47F}|\\x{1F47E}|\\x{1F47D}|\\x{1F47C}|\\x{1F47B}|\\x{1F47A}|\\x{1F45F}|\\x{1F45D}|\\x{1F429}|\\x{1F436}|\\x{1F91A}|\\x{1F43F}|\\x{1F43E}|\\x{1F43D}|\\x{1F43C}|\\x{1F43B}|\\x{1F43A}|\\x{1F439}|\\x{1F438}|\\x{1F437}|\\x{1F435}|\\x{1F442}|\\x{1F434}|\\x{1F433}|\\x{1F432}|\\x{1F431}|\\x{1F430}|\\x{1F42F}|\\x{1F42E}|\\x{1F42D}|\\x{1F42C}|\\x{1F42B}|\\x{1F42A}|\\x{1F441}|\\x{1F443}|\\x{1F45C}|\\x{1F451}|\\x{1F45B}|\\x{1F45A}|\\x{1F459}|\\x{1F458}|\\x{1F457}|\\x{1F456}|\\x{1F455}|\\x{1F454}|\\x{1F453}|\\x{1F452}|\\x{1F450}|\\x{1F444}|\\x{1F44F}|\\x{1F44E}|\\x{1F44D}|\\x{1F44C}|\\x{1F44B}|\\x{1F44A}|\\x{1F449}|\\x{1F448}|\\x{1F447}|\\x{1F446}|\\x{1F445}|\\x{1F3BB}|\\x{1F3B9}|\\x{1F492}|\\x{1F320}|\\x{1F32C}|\\x{1F32B}|\\x{1F32A}|\\x{1F329}|\\x{1F328}|\\x{1F327}|\\x{1F326}|\\x{1F325}|\\x{1F324}|\\x{1F321}|\\x{1F31F}|\\x{1F32E}|\\x{1F31E}|\\x{1F31D}|\\x{1F31C}|\\x{1F31B}|\\x{1F31A}|\\x{1F319}|\\x{1F318}|\\x{1F317}|\\x{1F316}|\\x{1F315}|\\x{1F32D}|\\x{1F32F}|\\x{1F313}|\\x{1F33D}|\\x{1F347}|\\x{1F346}|\\x{1F345}|\\x{1F344}|\\x{1F343}|\\x{1F342}|\\x{1F341}|\\x{1F340}|\\x{1F33F}|\\x{1F33E}|\\x{1F33C}|\\x{1F330}|\\x{1F33B}|\\x{1F33A}|\\x{1F339}|\\x{1F338}|\\x{1F337}|\\x{1F336}|\\x{1F335}|\\x{1F334}|\\x{1F333}|\\x{1F332}|\\x{1F331}|\\x{1F314}|\\x{1F312}|\\x{1F349}|\\x{1F195}|\\x{1F232}|\\x{1F22F}|\\x{1F21A}|\\x{1F202}|\\x{1F201}|\\x{1F19A}|\\x{1F199}|\\x{1F198}|\\x{1F197}|\\x{1F196}|\\x{1F194}|\\x{1F234}|\\x{1F193}|\\x{1F192}|\\x{1F191}|\\x{1F18E}|\\x{1F17F}|\\x{1F17E}|\\x{1F171}|\\x{1F170}|\\x{1F0CF}|\\x{1F004}|\\x{1F233}|\\x{1F235}|\\x{1F311}|\\x{1F306}|\\x{1F310}|\\x{1F30F}|\\x{1F30E}|\\x{1F30D}|\\x{1F30C}|\\x{1F30B}|\\x{1F30A}|\\x{1F309}|\\x{1F308}|\\x{1F307}|\\x{1F305}|\\x{1F236}|\\x{1F304}|\\x{1F303}|\\x{1F302}|\\x{1F301}|\\x{1F300}|\\x{1F251}|\\x{1F250}|\\x{1F23A}|\\x{1F239}|\\x{1F238}|\\x{1F237}|\\x{1F348}|\\x{1F34A}|\\x{1F3B8}|\\x{1F38D}|\\x{1F39A}|\\x{1F399}|\\x{1F397}|\\x{1F396}|\\x{1F393}|\\x{1F392}|\\x{1F391}|\\x{1F390}|\\x{1F38F}|\\x{1F38E}|\\x{1F38C}|\\x{1F39E}|\\x{1F38B}|\\x{1F38A}|\\x{1F389}|\\x{1F388}|\\x{1F387}|\\x{1F386}|\\x{1F385}|\\x{1F384}|\\x{1F383}|\\x{1F382}|\\x{1F39B}|\\x{1F39F}|\\x{1F380}|\\x{1F3AD}|\\x{1F3B7}|\\x{1F3B6}|\\x{1F3B5}|\\x{1F3B4}|\\x{1F3B3}|\\x{1F3B2}|\\x{1F3B1}|\\x{1F3B0}|\\x{1F3AF}|\\x{1F3AE}|\\x{1F3AC}|\\x{1F3A0}|\\x{1F3AB}|\\x{1F3AA}|\\x{1F3A9}|\\x{1F3A8}|\\x{1F3A7}|\\x{1F3A6}|\\x{1F3A5}|\\x{1F3A4}|\\x{1F3A3}|\\x{1F3A2}|\\x{1F3A1}|\\x{1F381}|\\x{1F37F}|\\x{1F34B}|\\x{1F358}|\\x{1F362}|\\x{1F361}|\\x{1F360}|\\x{1F35F}|\\x{1F35E}|\\x{1F35D}|\\x{1F35C}|\\x{1F35B}|\\x{1F35A}|\\x{1F359}|\\x{1F357}|\\x{1F364}|\\x{1F356}|\\x{1F355}|\\x{1F354}|\\x{1F353}|\\x{1F352}|\\x{1F351}|\\x{1F350}|\\x{1F34F}|\\x{1F34E}|\\x{1F34D}|\\x{1F34C}|\\x{1F363}|\\x{1F365}|\\x{1F37E}|\\x{1F373}|\\x{1F37D}|\\x{1F37C}|\\x{1F37B}|\\x{1F37A}|\\x{1F379}|\\x{1F378}|\\x{1F377}|\\x{1F376}|\\x{1F375}|\\x{1F374}|\\x{1F372}|\\x{1F366}|\\x{1F371}|\\x{1F370}|\\x{1F36F}|\\x{1F36E}|\\x{1F36D}|\\x{1F36C}|\\x{1F36B}|\\x{1F36A}|\\x{1F369}|\\x{1F368}|\\x{1F367}|\\x{1F491}|\\x{1F440}|\\x{1F493}|\\x{1F625}|\\x{1F62F}|\\x{1F62E}|\\x{1F62D}|\\x{1F62C}|\\x{1F62B}|\\x{1F62A}|\\x{1F629}|\\x{1F628}|\\x{1F627}|\\x{1F626}|\\x{1F624}|\\x{1F631}|\\x{1F623}|\\x{1F622}|\\x{1F621}|\\x{1F620}|\\x{1F61F}|\\x{1F61E}|\\x{1F61D}|\\x{1F61C}|\\x{1F61B}|\\x{1F61A}|\\x{1F630}|\\x{1F632}|\\x{1F618}|\\x{1F640}|\\x{1F64A}|\\x{1F649}|\\x{1F648}|\\x{1F647}|\\x{1F646}|\\x{1F645}|\\x{1F644}|\\x{1F643}|\\x{1F642}|\\x{1F641}|\\x{1F63F}|\\x{1F633}|\\x{1F63E}|\\x{1F63D}|\\x{1F63C}|\\x{1F63B}|\\x{1F63A}|\\x{1F639}|\\x{1F638}|\\x{1F637}|\\x{1F636}|\\x{1F494}|\\x{1F634}|\\x{1F619}|\\x{1F617}|\\x{1F64C}|\\x{1F5D1}|\\x{1F5F3}|\\x{1F5EF}|\\x{1F5E8}|\\x{1F5E3}|\\x{1F5E1}|\\x{1F5DE}|\\x{1F5DD}|\\x{1F5DC}|\\x{1F5D3}|\\x{1F5D2}|\\x{1F5C4}|\\x{1F5FB}|\\x{1F5C3}|\\x{1F5C2}|\\x{1F5BC}|\\x{1F5B2}|\\x{1F5B1}|\\x{1F5A8}|\\x{1F5A5}|\\x{1F5A4}|\\x{1F596}|\\x{1F595}|\\x{1F5FA}|\\x{1F5FC}|\\x{1F616}|\\x{1F60A}|\\x{1F614}|\\x{1F613}|\\x{1F612}|\\x{1F611}|\\x{1F610}|\\x{1F60F}|\\x{1F60E}|\\x{1F60D}|\\x{1F60C}|\\x{1F60B}|\\x{1F609}|\\x{1F5FD}|\\x{1F608}|\\x{1F607}|\\x{1F606}|\\x{1F605}|\\x{1F604}|\\x{1F603}|\\x{1F602}|\\x{1F601}|\\x{1F600}|\\x{1F5FF}|\\x{1F5FE}|\\x{1F64B}|\\x{1F64D}|\\x{1F58D}|\\x{1F6C0}|\\x{1F6CF}|\\x{1F6CE}|\\x{1F6CD}|\\x{1F6CC}|\\x{1F6CB}|\\x{1F6C5}|\\x{1F6C4}|\\x{1F6C3}|\\x{1F6C2}|\\x{1F6C1}|\\x{1F6BF}|\\x{1F6D1}|\\x{1F6BE}|\\x{1F6BD}|\\x{1F6BC}|\\x{1F6BB}|\\x{1F6BA}|\\x{1F6B9}|\\x{1F6B8}|\\x{1F6B7}|\\x{1F6B6}|\\x{1F6B5}|\\x{1F6D0}|\\x{1F6D2}|\\x{1F6B3}|\\x{1F6F6}|\\x{1F919}|\\x{1F918}|\\x{1F917}|\\x{1F916}|\\x{1F915}|\\x{1F914}|\\x{1F913}|\\x{1F912}|\\x{1F911}|\\x{1F910}|\\x{1F6F5}|\\x{1F6E0}|\\x{1F6F4}|\\x{1F6F3}|\\x{1F6F0}|\\x{1F6EC}|\\x{1F6EB}|\\x{1F6E9}|\\x{1F6E5}|\\x{1F6E4}|\\x{1F6E3}|\\x{1F6E2}|\\x{1F6E1}|\\x{1F6B4}|\\x{1F6B2}|\\x{1F64E}|\\x{1F68B}|\\x{1F695}|\\x{1F694}|\\x{1F693}|\\x{1F692}|\\x{1F691}|\\x{1F690}|\\x{1F68F}|\\x{1F68E}|\\x{1F68D}|\\x{1F68C}|\\x{1F68A}|\\x{1F697}|\\x{1F689}|\\x{1F688}|\\x{1F687}|\\x{1F686}|\\x{1F685}|\\x{1F684}|\\x{1F683}|\\x{1F682}|\\x{1F681}|\\x{1F680}|\\x{1F64F}|\\x{1F696}|\\x{1F698}|\\x{1F6B1}|\\x{1F6A6}|\\x{1F6B0}|\\x{1F6AF}|\\x{1F6AE}|\\x{1F6AD}|\\x{1F6AC}|\\x{1F6AB}|\\x{1F6AA}|\\x{1F6A9}|\\x{1F6A8}|\\x{1F6A7}|\\x{1F6A5}|\\x{1F699}|\\x{1F6A4}|\\x{1F6A3}|\\x{1F6A2}|\\x{1F6A1}|\\x{1F6A0}|\\x{1F69F}|\\x{1F69E}|\\x{1F69D}|\\x{1F69C}|\\x{1F69B}|\\x{1F69A}|\\x{1F590}|\\x{1F635}|\\x{1F58C}|\\x{1F4D6}|\\x{1F4E0}|\\x{1F4DF}|\\x{1F4DE}|\\x{1F4DD}|\\x{1F4DC}|\\x{1F4DB}|\\x{1F4DA}|\\x{1F4D9}|\\x{1F4D8}|\\x{1F4D7}|\\x{1F4D5}|\\x{1F4E2}|\\x{1F4D4}|\\x{1F4D3}|\\x{1F4D2}|\\x{1F4D1}|\\x{1F4D0}|\\x{1F4CF}|\\x{1F4CE}|\\x{1F4CD}|\\x{1F4CC}|\\x{1F4CB}|\\x{1F4E1}|\\x{1F4E3}|\\x{1F4C9}|\\x{1F4F1}|\\x{1F4FB}|\\x{1F4FA}|\\x{1F4F9}|\\x{1F4F8}|\\x{1F4F7}|\\x{1F4F6}|\\x{1F4F5}|\\x{1F4F4}|\\x{1F4F3}|\\x{1F4F2}|\\x{1F4F0}|\\x{1F4E4}|\\x{1F4EF}|\\x{1F4EE}|\\x{1F4ED}|\\x{1F4EC}|\\x{1F4EB}|\\x{1F4EA}|\\x{1F4E9}|\\x{1F4E8}|\\x{1F4E7}|\\x{1F4E6}|\\x{1F4E5}|\\x{1F4CA}|\\x{1F4C8}|\\x{1F4FD}|\\x{1F4A1}|\\x{1F58B}|\\x{1F4AA}|\\x{1F4A9}|\\x{1F4A8}|\\x{1F4A7}|\\x{1F4A6}|\\x{1F4A5}|\\x{1F4A4}|\\x{1F4A3}|\\x{1F4A2}|\\x{1F4A0}|\\x{1F4AD}|\\x{1F49F}|\\x{1F49E}|\\x{1F49D}|\\x{1F49C}|\\x{1F49B}|\\x{1F49A}|\\x{1F499}|\\x{1F498}|\\x{1F497}|\\x{1F496}|\\x{1F495}|\\x{1F4AC}|\\x{1F4AE}|\\x{1F4C7}|\\x{1F4BC}|\\x{1F4C6}|\\x{1F4C5}|\\x{1F4C4}|\\x{1F4C3}|\\x{1F4C2}|\\x{1F4C1}|\\x{1F4C0}|\\x{1F4BF}|\\x{1F4BE}|\\x{1F4BD}|\\x{1F4BB}|\\x{1F4AF}|\\x{1F4BA}|\\x{1F4B9}|\\x{1F4B8}|\\x{1F4B7}|\\x{1F4B6}|\\x{1F4B5}|\\x{1F4B4}|\\x{1F4B3}|\\x{1F4B2}|\\x{1F4B1}|\\x{1F4B0}|\\x{1F4FC}|\\x{1F4AB}|\\x{1F4FF}|\\x{1F54D}|\\x{1F558}|\\x{1F557}|\\x{1F556}|\\x{1F555}|\\x{1F554}|\\x{1F553}|\\x{1F552}|\\x{1F551}|\\x{1F550}|\\x{1F54E}|\\x{1F54C}|\\x{1F55A}|\\x{1F54B}|\\x{1F54A}|\\x{1F549}|\\x{1F53D}|\\x{1F53C}|\\x{1F53B}|\\x{1F53A}|\\x{1F539}|\\x{1F538}|\\x{1F537}|\\x{1F559}|\\x{1F55B}|\\x{1F535}|\\x{1F573}|\\x{1F500}|\\x{1F58A}|\\x{1F587}|\\x{1F57A}|\\x{1F579}|\\x{1F578}|\\x{1F577}|\\x{1F576}|\\x{1F575}|\\x{1F574}|\\x{1F570}|\\x{1F55C}|\\x{1F567}|\\x{1F566}|\\x{1F565}|\\x{1F564}|\\x{1F563}|\\x{1F562}|\\x{1F561}|\\x{1F560}|\\x{1F55F}|\\x{1F55E}|\\x{1F55D}|\\x{1F536}|\\x{1F56F}|\\x{1F534}|\\x{1F50D}|\\x{1F517}|\\x{1F516}|\\x{1F515}|\\x{1F514}|\\x{1F513}|\\x{1F512}|\\x{1F511}|\\x{1F510}|\\x{1F50F}|\\x{1F50E}|\\x{1F50C}|\\x{1F519}|\\x{1F50B}|\\x{1F50A}|\\x{1F509}|\\x{1F508}|\\x{1F506}|\\x{1F505}|\\x{1F504}|\\x{1F503}|\\x{1F502}|\\x{1F533}|\\x{1F501}|\\x{1F518}|\\x{1F507}|\\x{1F51A}|\\x{1F527}|\\x{1F531}|\\x{1F51B}|\\x{1F532}|\\x{1F530}|\\x{1F52F}|\\x{1F52E}|\\x{1F52C}|\\x{1F52B}|\\x{1F52A}|\\x{1F529}|\\x{1F528}|\\x{1F52D}|\\x{1F51D}|\\x{1F51C}|\\x{1F51E}|\\x{1F526}|\\x{1F51F}|\\x{1F521}|\\x{1F520}|\\x{1F522}|\\x{1F523}|\\x{1F524}|\\x{1F525}|\\x{262F}|\\x{2620}|\\x{262E}|\\x{262A}|\\x{2626}|\\x{2623}|\\x{2622}|\\x{2602}|\\x{2614}|\\x{261D}|\\x{2618}|\\x{2615}|\\x{2611}|\\x{260E}|\\x{2604}|\\x{2639}|\\x{2603}|\\x{2638}|\\x{2650}|\\x{263A}|\\x{2651}|\\x{2668}|\\x{2600}|\\x{2666}|\\x{2665}|\\x{2663}|\\x{2660}|\\x{2653}|\\x{2652}|\\x{264F}|\\x{2640}|\\x{264E}|\\x{264D}|\\x{264C}|\\x{264B}|\\x{264A}|\\x{2649}|\\x{2648}|\\x{2642}|\\x{2601}|\\x{2328}|\\x{25FE}|\\x{2197}|\\x{23CF}|\\x{231B}|\\x{231A}|\\x{21AA}|\\x{21A9}|\\x{2199}|\\x{2198}|\\x{2196}|\\x{23EA}|\\x{2195}|\\x{2194}|\\x{2139}|\\x{2122}|\\x{2049}|\\x{203C}|\\x{00AE}|\\x{267F}|\\x{23E9}|\\x{23EB}|\\x{25FD}|\\x{23FA}|\\x{25FC}|\\x{25FB}|\\x{25C0}|\\x{25B6}|\\x{25AB}|\\x{25AA}|\\x{24C2}|\\x{23F9}|\\x{23EC}|\\x{23F8}|\\x{23F3}|\\x{23F2}|\\x{23F1}|\\x{23F0}|\\x{23EF}|\\x{23EE}|\\x{23ED}|\\x{267B}|\\x{2728}|\\x{2692}|\\x{2744}|\\x{2757}|\\x{2755}|\\x{2754}|\\x{2753}|\\x{274E}|\\x{274C}|\\x{2747}|\\x{2734}|\\x{2764}|\\x{2733}|\\x{2721}|\\x{271D}|\\x{2716}|\\x{2714}|\\x{2712}|\\x{270F}|\\x{270D}|\\x{2763}|\\x{2795}|\\x{270B}|\\x{2B1B}|\\x{3299}|\\x{3297}|\\x{303D}|\\x{3030}|\\x{2B55}|\\x{2B50}|\\x{2B1C}|\\x{2B07}|\\x{2796}|\\x{2B06}|\\x{2B05}|\\x{2935}|\\x{2934}|\\x{27BF}|\\x{27B0}|\\x{27A1}|\\x{2797}|\\x{270C}|\\x{270A}|\\x{2693}|\\x{26AA}|\\x{26C5}|\\x{26C4}|\\x{26BE}|\\x{26BD}|\\x{26B1}|\\x{26B0}|\\x{26AB}|\\x{26A1}|\\x{26CE}|\\x{26A0}|\\x{269C}|\\x{269B}|\\x{2699}|\\x{2697}|\\x{2696}|\\x{2695}|\\x{2694}|\\x{26C8}|\\x{26CF}|\\x{2709}|\\x{26F5}|\\x{2708}|\\x{2705}|\\x{2702}|\\x{26FD}|\\x{26FA}|\\x{26F9}|\\x{26F8}|\\x{26F7}|\\x{26F4}|\\x{26D1}|\\x{26F3}|\\x{26F2}|\\x{26F1}|\\x{26F0}|\\x{26EA}|\\x{26E9}|\\x{26D4}|\\x{26D3}|\\x{00A9}/u";*/
		$emoji_pattern = "/(\xE2\x9C\x94|\xE2\x9C\x98|\xE2\x82\xBD)/";
		$val = preg_replace_callback($emoji_pattern, array(__CLASS__, 'Replace2Entity'), $val);
		return $val;
	}
	
	public static function Replace2Entity($matches)
	{
		return '&#' . hexdec(bin2hex(mb_convert_encoding($matches[0], 'UTF-32', 'UTF-8'))) . ';';
	}
}