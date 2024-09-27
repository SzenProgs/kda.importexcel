<?
if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
$moduleId = 'kda.importexcel';
CModule::IncludeModule('iblock');
CModule::IncludeModule($moduleId);
IncludeModuleLangFile(__FILE__);

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT <= "R") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$arGet = $_GET;
$INPUT_ID = $arGet['INPUT_ID'];
$HLBL_ID = (int)$arGet['HLBL_ID'];

if($_POST['action']=='save')
{
	\CUtil::JSPostUnescape();
	define('PUBLIC_AJAX_MODE', 'Y');
	$APPLICATION->RestartBuffer();
	ob_end_clean();
	
	echo '<script>';
	echo '$("#'.$INPUT_ID.'").val("'.(is_array($_POST['DEFAULTS']) ? base64_encode(serialize($_POST['DEFAULTS'])) : '').'");';
	echo 'BX.WindowManager.Get().Close();';
	echo '</script>';
	die();
}

if($OLDDEFAULTS) $DEFAULTS = unserialize(base64_decode($OLDDEFAULTS));
if(!is_array($DEFAULTS)) $DEFAULTS= array();

$fl = new \CKDAFieldList();
$arDefaultFields = $fl->GetHigloadBlockFields($HLBL_ID);
if(isset($arDefaultFields['ID'])) unset($arDefaultFields['ID']);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");
?>
<form action="" method="post" enctype="multipart/form-data" name="field_settings">
	<input type="hidden" name="action" value="save">
	<table width="100%" class="kda-ie-list-settings">
		<col width="50%">
		<col width="50%">
		
		<tr class="heading">
			<td colspan="2">
				<?echo GetMessage("KDA_IE_LIST_SETTING_PROPERTIES_DEFAULT"); ?>
			</td>
		</tr>
		<?
		if(is_array($DEFAULTS))
		{
			foreach($DEFAULTS as $k=>$v)
			{
				$fieldName = $arDefaultFields[$k]['NAME_LANG'];
				?>
				<tr class="kda-ie-list-settings-defaults">
					<td class="adm-detail-content-cell-l"><?echo $fieldName;?>:</td>
					<td class="adm-detail-content-cell-r">
						<input type="text" name="DEFAULTS[<?echo $k;?>]" value="<?echo htmlspecialcharsex($v);?>">
						<a class="delete" href="javascript:void(0)" onclick="ESettings.RemoveDefaultProp(this);" title="<?echo GetMessage("KDA_IE_LIST_SETTING_DELETE"); ?>"></a>
					</td>
				</tr>
				<?
			}
		}
		?>		
		<tr class="kda-ie-list-settings-defaults" style="display: none;">
			<td class="adm-detail-content-cell-l"></td>
			<td class="adm-detail-content-cell-r">
				<input type="text" name="empty" value="">
				<a class="delete" href="javascript:void(0)" onclick="ESettings.RemoveDefaultProp(this);" title="<?echo GetMessage("KDA_IE_LIST_SETTING_DELETE"); ?>"></a>
			</td>
		</tr>
		<tr>
			<td colspan="2" class="kda-ie-chosen-td">
				<select name="prop_default" style="min-width: 200px; max-width: 500px;" class="kda-ie-chosen-multi" onchange="ESettings.AddDefaultProp(this, false, 'DEFAULTS')">
					<option value=""><?echo GetMessage('KDA_IE_PLACEHOLDER_CHOOSE');?></option>
					<?
					foreach($arDefaultFields as $elKey=>$elField)
					{
						echo '<option value="'.$elKey.'">'.$elField['NAME_LANG'].'</option>';
					}
					?>
				</select>
			</td>
		</tr>		
	</table>
</form>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");?>