<?php
namespace KdaIE;

class Utils {
	public static function PhpToJSObject($arData)
	{
		$data = '';
		if(is_callable(array('\Bitrix\Main\Web\Json', 'encode')))
		{
			$data = \Bitrix\Main\Web\Json::encode($arData);
		}
		else
		{
			$data = \CUtil::PhpToJSObject($arData);
		}
		return $data;
	}
	
	public static function JsObjectToPhp($data)
	{
		if(strlen(trim($data))==0) return array();
		$arResult = null;
		if(is_callable(array('\Bitrix\Main\Web\Json', 'decode')))
		{
			try
			{
				$arResult = \Bitrix\Main\Web\Json::decode($data);
			}
			catch(\Throwable $exception)
			{
				//echo $exception->getMessage();
			}
		}
		if($arResult === null)
		{
			try
			{
				$arResult = \CUtil::JsObjectToPhp($data, true);
			}
			catch(\Throwable $exception)
			{
				//echo $exception->getMessage();
			}
		}
		if($arResult === null)
		{
			$arResult = array();
		}
		return $arResult;
	}
}
?>