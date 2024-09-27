<?php
include_once(dirname(__FILE__).'/install/demo.php');

if(!class_exists('CKDAImportExcelRunner'))
{
	class CKDAImportExcelRunner
	{
		protected static $moduleId = 'kda.importexcel';
		
		static function GetModuleId()
		{
			return self::$moduleId;
		}
		
		private static function DemoExpired()
		{
			$DemoMode = CModule::IncludeModuleEx(self::$moduleId);
			$cnstPrefix = str_replace('.', '_', self::$moduleId);
			if ($DemoMode==MODULE_DEMO) {
				$now=time();
				if (defined($cnstPrefix."_OLDSITEEXPIREDATE")) {
					if ($now>=constant($cnstPrefix.'_OLDSITEEXPIREDATE') || constant($cnstPrefix.'_OLDSITEEXPIREDATE')>$now+2000000 || $now - filectime(__FILE__)>2000000) {
						return true;
					}
				} else{ 
					return true;
				}
			} elseif ($DemoMode==MODULE_DEMO_EXPIRED) {
				return true;
			}
			return false;
		}
		
		static function ImportIblock($filename, $params, $fparams, $stepparams, $pid = false)
		{
			if(self::DemoExpired()) return array();
			$ie = new CKDAImportExcel($filename, $params, $fparams, $stepparams, $pid);
			return $ie->Import();
		}
		
		static function ImportHighloadblock($filename, $params, $fparams, $stepparams, $pid = false)
		{
			if(self::DemoExpired()) return array();
			$ie = new CKDAImportExcelHighload($filename, $params, $fparams, $stepparams, $pid);
			return $ie->Import();
		}
	}
}

$moduleId = CKDAImportExcelRunner::GetModuleId();
$moduleJsId = str_replace('.', '_', $moduleId);
$pathJS = '/bitrix/js/'.$moduleId;
$pathCSS = '/bitrix/panel/'.$moduleId;
$pathLang = BX_ROOT.'/modules/'.$moduleId.'/lang/'.LANGUAGE_ID;
CModule::AddAutoloadClasses(
	$moduleId,
	array(
		'CKDAFieldList' => 'classes/general/field_list.php',
		'CKDAImportProfile' => 'classes/general/profile.php',
		'CKDAImportProfileAll' => 'classes/general/profile.php',
		'CKDAImportProfileDB' => 'classes/general/profile_db.php',
		'CKDAImportProfileFS' => 'classes/general/profile_fs.php',
		'CKDAImportExcelBase' => 'classes/general/import_base.php',
		'CKDAImportExcelData' => 'classes/general/import_data.php',
		'CKDAImportExcel' => 'classes/general/import.php',
		'CKDAImportExcelRollback' => 'classes/general/import_rollback.php',
		'CKDAImportExcelHighload' => 'classes/general/import_highload.php',		
		'CKDAImportExtraSettings' => 'classes/general/extrasettings.php',
		'CKDAImportUtils' => 'classes/general/utils.php',
		'CKDAImportLogger' => 'classes/general/logger.php',
		'CKDAIEFilter' => 'classes/general/filter.php',
		//'CKDAImportMail' => 'classes/general/mail.php',
		'\Bitrix\KdaImportexcel\IUtils' => "lib/iutils.php",
		'\Bitrix\KdaImportexcel\ProfileTable' => "lib/profile.php",
		'\Bitrix\KdaImportexcel\ProfileHlTable' => "lib/profile_hl.php",
		'\Bitrix\KdaImportexcel\ProfileElementTable' => "lib/profile_element.php",
		'\Bitrix\KdaImportexcel\ProfileElementHlTable' => "lib/profile_element_hl.php",
		'\Bitrix\KdaImportexcel\ProfileExecTable' => "lib/profile_exec.php",
		'\Bitrix\KdaImportexcel\ProfileExecStatTable' => "lib/profile_exec_stat.php",
		'\Bitrix\KdaImportexcel\ProfileChangesTable' => "lib/profile_changes.php",
		'\Bitrix\KdaImportexcel\ProfileGroupTable' => "lib/profile_group.php",
		'\Bitrix\KdaImportexcel\Sftp' => "lib/sftp.php",
		'\Bitrix\KdaImportexcel\Conversion' => "lib/conversion.php",
		'\Bitrix\KdaImportexcel\Cloud' => "lib/cloud.php",
		'\Bitrix\KdaImportexcel\Cloud\MailRu' => "lib/cloud/mail_ru.php",
		'\Bitrix\KdaImportexcel\ZipArchive' => "lib/zip_archive.php",
		'\Bitrix\KdaImportexcel\CFileInput' => "lib/file_input.php",
		'\Bitrix\KdaImportexcel\Imap' => "lib/mail/imap.php",
		'\Bitrix\KdaImportexcel\SMail' => "lib/mail/mail.php",
		'\Bitrix\KdaImportexcel\MailHeader' => "lib/mail/mail_header.php",
		'\Bitrix\KdaImportexcel\MailMessage' => "lib/mail/mail_message.php",
		'\Bitrix\KdaImportexcel\MailUtil' => "lib/mail/mail_util.php",
		'\Bitrix\KdaImportexcel\DataManager\Discount' => "lib/datamanager/discount.php",
		'\Bitrix\KdaImportexcel\DataManager\DiscountProductTable' => "lib/datamanager/discount_product_table.php",
		'\Bitrix\KdaImportexcel\DataManager\Price' => "lib/datamanager/price.php",
		'\Bitrix\KdaImportexcel\DataManager\PriceD7' => "lib/datamanager/price_d7.php",
		'\Bitrix\KdaImportexcel\DataManager\Product' => "lib/datamanager/product.php",
		'\Bitrix\KdaImportexcel\DataManager\ProductD7' => "lib/datamanager/product_d7.php",
		'\Bitrix\KdaImportexcel\DataManager\IblockElementTable' => "lib/datamanager/iblockelement.php",
		'\Bitrix\KdaImportexcel\DataManager\IblockElementIdTable' => "lib/datamanager/iblockelementid_table.php",
		'\Bitrix\KdaImportexcel\DataManager\ElementPropertyTable' => "lib/datamanager/element_property_table.php",
		'\Bitrix\KdaImportexcel\DataManager\InterhitedpropertyValues' => "lib/datamanager/inheritedproperty_values.php",
		'\Bitrix\KdaImportexcel\ClassManager' => "lib/class_manager.php",
		'\Bitrix\KdaImportexcel\Api' => "lib/api.php",
		'\Bitrix\KdaImportexcel\ExcelViewer' => "lib/excel_viewer.php",
		'\Bitrix\KdaImportexcel\HttpClient' => "lib/httpclient.php"
	)
);

$initFile = $_SERVER["DOCUMENT_ROOT"].BX_ROOT.'/php_interface/include/'.$moduleId.'/init.php';
if(file_exists($initFile)) include_once($initFile);

$jqueryExt = (CJSCore::IsExtRegistered('jquery3') ? 'jquery3' : 'jquery2');
$arJSKdaIBlockConfig = array(
	$moduleJsId => array(
		'js' => $pathJS.'/script.js',
		'css' => $pathCSS.'/styles.css',
		'rel' => array($jqueryExt, $moduleJsId.'_chosen'),
		'lang' => $pathLang.'/js_admin.php',
	),
	$moduleJsId.'_highload' => array(
		'js' => $pathJS.'/script_highload.js',
		'css' => $pathCSS.'/styles.css',
		'rel' => array($jqueryExt, $moduleJsId.'_chosen'),
		'lang' => $pathLang.'/js_admin_hlbl.php',
	),
	$moduleJsId.'_chosen' => array(
		'js' => $pathJS.'/chosen/chosen.jquery.min.js',
		'css' => $pathJS.'/chosen/chosen.min.css',
		'rel' => array($jqueryExt)
	),
);

foreach ($arJSKdaIBlockConfig as $ext => $arExt) {
	CJSCore::RegisterExt($ext, $arExt);
}
if(class_exists('\CKDAImportUtils')) \CKDAImportUtils::PrepareJs();
?>