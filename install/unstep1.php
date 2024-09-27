<?
IncludeModuleLangFile(__FILE__);

$moduleId = 'kda.importexcel';
$pathJS = '/bitrix/js/'.$moduleId;
$pathCSS = '/bitrix/panel/'.$moduleId;
$pathLang = BX_ROOT.'/modules/'.$moduleId.'/lang/'.LANGUAGE_ID;
$arJSKdaUninstConfig = array(
	'kda_importexcel_uninst' => array(
		'js' => array($pathJS.'/chosen/chosen.jquery.min.js', $pathJS.'/script.js'),
		'css' => array($pathJS.'/chosen/chosen.min.css', $pathCSS.'/styles.css'),
		'rel' => array('jquery'),
		'lang' => $pathLang.'/js_admin.php',
	),
);

foreach ($arJSKdaUninstConfig as $ext => $arExt) {
	CJSCore::RegisterExt($ext, $arExt);
}
CJSCore::Init(array('kda_importexcel_uninst'));
?>
<form action="<?echo $APPLICATION->GetCurPage()?>" method="get" class="kda-ie-uninst" id="kda-ie-uninst">
	<?=bitrix_sessid_post()?>
	<input type="hidden" name="lang" value="<?echo LANG?>">
	<input type="hidden" name="id" value="kda.importexcel">
	<input type="hidden" name="uninstall" value="Y">
	<input type="hidden" name="step" value="1">
	<input type="hidden" name="prevstep" value="1">
	
	<?
	if($_REQUEST['prevstep']==1)
	{
		if(!isset($_REQUEST['reason']))
		{
			CAdminMessage::ShowMessage(array(
				'TYPE' => 'error',
				'MESSAGE' => GetMessage("KDA_EE_UNINST_ERROR"),
				'DETAILS' => GetMessage("KDA_EE_UNINST_ERROR_TEXT"),
				'HTML' => true
			));
		}
	}
	?>

	<p><b><?echo GetMessage("KDA_EE_UNINST_TOP_TEXT")?></b></p>
	
	<p class="subtitle"><b><?echo GetMessage("KDA_EE_UNINST_REASON")?></b></p>
	<input type="radio" name="reason" id="reason_1" value="1" <?if($_REQUEST['reason']==1){echo 'checked';}?>> <label for="reason_1"><?echo GetMessage("KDA_EE_UNINST_REASON_SETTINGS")?></label><br>
	<input type="radio" name="reason" id="reason_2" value="2" <?if($_REQUEST['reason']==2){echo 'checked';}?>> <label for="reason_2"><?echo GetMessage("KDA_EE_UNINST_REASON_FUNCTIONAL")?></label><br>
	<input type="radio" name="reason" id="reason_3" value="3" <?if($_REQUEST['reason']==3){echo 'checked';}?>> <label for="reason_3"><?echo GetMessage("KDA_EE_UNINST_REASON_DEMOEND")?></label><br>
	<input type="radio" name="reason" id="reason_4" value="4" <?if($_REQUEST['reason']==4){echo 'checked';}?>> <label for="reason_4"><?echo GetMessage("KDA_EE_UNINST_REASON_ANOTHER_MODULE")?></label><br>
	<input type="radio" name="reason" id="reason_5" value="5" <?if($_REQUEST['reason']==5){echo 'checked';}?>> <label for="reason_5"><?echo GetMessage("KDA_EE_UNINST_REASON_ERRORS")?></label><br>
	<input type="radio" name="reason" id="reason_6" value="6" <?if($_REQUEST['reason']==6){echo 'checked';}?>> <label for="reason_6"><?echo GetMessage("KDA_EE_UNINST_REASON_NOTWORK")?></label><br>
	<input type="radio" name="reason" id="reason_7" value="7" <?if($_REQUEST['reason']==7){echo 'checked';}?>> <label for="reason_7"><?echo GetMessage("KDA_EE_UNINST_REASON_ANOTHER")?></label><br>
	<textarea name="reason_other" id="reason_other" style="display: none;" placeholder="<?echo GetMessage("KDA_EE_UNINST_REASON_NOTE")?>"></textarea>
	
	<br><br>
	<p class="subtitle"><b><?echo GetMessage("KDA_EE_UNINST_SUPPORT")?></b></p>
	<input type="radio" name="support" id="support_1" value="1" <?if($_REQUEST['support']==1){echo 'checked';}?>> <label for="support_1"><?echo GetMessage("KDA_EE_UNINST_SUPPORT_YES")?></label><br>
	<input type="radio" name="support" id="support_0" value="0" <?if(isset($_REQUEST['support']) && $_REQUEST['support']==0){echo 'checked';}?>> <label for="support_0"><?echo GetMessage("KDA_EE_UNINST_SUPPORT_NO")?></label><br>
	
	<div>&nbsp;</div>
	<input type="submit" name="inst" value="<?echo GetMessage("KDA_EE_UNINST_DEL")?>">
</form>