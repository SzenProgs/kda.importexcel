<?
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ExpressionField;

if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
$moduleId = 'kda.importexcel';
$moduleFilePrefix = 'kda_import_excel';
$moduleJsId = str_replace('.', '_', $moduleId);
$moduleJsId2 = $moduleJsId;
$moduleDemoExpiredFunc = $moduleJsId2.'_demo_expired';
$moduleShowDemoFunc = $moduleJsId2.'_show_demo';
CModule::IncludeModule($moduleId);
CJSCore::Init(array('fileinput', $moduleJsId));
require_once ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
IncludeModuleLangFile(__FILE__);

include_once(dirname(__FILE__).'/../install/demo.php');
if (call_user_func($moduleDemoExpiredFunc)) {
	require ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	call_user_func($moduleShowDemoFunc);
	require ($DOCUMENT_ROOT."/bitrix/modules/main/include/epilog_admin.php");
	die();
}

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT <= "R") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$profileType = \Bitrix\KdaImportexcel\ProfileGroupTable::TYPE_IMPORT_IBLOCK;

if($_POST['action']=='savenewgroup')
{
	$error = '';
	if(strlen(trim($_POST['new_group_name']))==0) $error = GetMessage("KDA_IE_NEW_GROUP_NAME_EMPTY");	
	if(strlen($error)==0)
	{
		$dbRes = \Bitrix\KdaImportexcel\ProfileGroupTable::getList(array('filter'=>array(
			'PROFILE_TYPE' => $profileType,
			'NAME' => trim($_POST['new_group_name'])
		)));
		if($dbRes->Fetch())
		{
			$error = GetMessage("KDA_IE_NEW_GROUP_NAME_DUPLICATE");
		}
		else
		{
			$dbRes2 = \Bitrix\KdaImportexcel\ProfileGroupTable::add(array(
				'PROFILE_TYPE' => $profileType,
				'ACTIVE' => 'Y',
				'NAME' => trim($_POST['new_group_name']),
				'SORT' => (int)$_POST['new_group_sort']
			));
			if(!$dbRes2->isSuccess()) $error = $dbRes2->GetErrorMessages();
		}
	}
	$APPLICATION->RestartBuffer();
	if(strlen($error) > 0) echo \CUtil::PhpToJSObject(array('TYPE'=>'ERROR', 'MESSAGE'=>$error));
	else echo \CUtil::PhpToJSObject(array('TYPE'=>'SUCCESS'));
	
	die();
}
elseif($_GET['action']=='shownewgroupform')
{
	$APPLICATION->RestartBuffer();
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");
	?>
	<form action="" method="post" enctype="multipart/form-data" id="new_profile_group">
		<input type="hidden" name="action" value="savenewgroup">
		<table width="100%">
			<col width="50%">
			<col width="50%">
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_NEW_GROUP_NAME")?>:</td>
				<td class="adm-detail-content-cell-r"><input type="text" name="new_group_name"></td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_NEW_GROUP_SORT")?>:</td>
				<td class="adm-detail-content-cell-r"><input type="text" name="new_group_sort" value="500"></td>
			</tr>
		</table>
	</form>
	<?
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");
}
elseif($_GET['action']=='showoldparams' || $_GET['action']=='saveoldparams')
{
	$APPLICATION->RestartBuffer();
	ob_end_clean();
	$pid = $_GET['pid'];
	$suffix = 'iblock';
	if(mb_strpos($pid, 'hl')===0)
	{
		$pid = mb_substr($pid, 2);
		$suffix = 'highload';
	}
	$oProfile = \CKDAImportProfile::getInstance($suffix);
	
	if($_GET['action']=='saveoldparams')
	{
		if((int)$_POST['restore_point'] > 0)
		{
			$oProfile->RestoreFromChanges($pid, (int)$_POST['restore_point']);
		}
		echo \CUtil::PhpToJSObject(array('TYPE'=>'SUCCESS', 'MESSAGE'=>GetMessage("KDA_IE_OLD_SETTINGS_RESTORE_SUCCESS")));
	}
	elseif($_GET['action']=='showoldparams')
	{
		require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");
		$arProfile = $oProfile->GetFieldsByID($pid - 1);
		$arChanges = $oProfile->GetChangesList($pid);
		?>
		<form action="" method="post" enctype="multipart/form-data" id="restore_profile_params">
			<?
			if(false /*empty($arChanges)*/)
			{
				echo GetMessage("KDA_IE_OLD_SETTINGS_NO_POINTS");
			}
			else
			{
				?>
			<input type="hidden" name="action" value="saveoldparams">
			<table width="100%">
				<col width="50%">
				<col width="50%">
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_OLD_SETTINGS_PROFILE_NAME")?>:</td>
					<td class="adm-detail-content-cell-r"><b><?echo $arProfile['NAME']?></b></td>
				</tr>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("KDA_IE_OLD_SETTINGS_RESTORE_POINT")?>:</td>
					<td class="adm-detail-content-cell-r">
						<select name="restore_point" id="restore_point">
							<option value=""><?echo GetMessage("KDA_IE_OLD_SETTINGS_RESTORE_POINT_CURRENT")?></option>
						<?
						foreach($arChanges as $arChangeItem)
						{
							echo '<option value="'.htmlspecialcharsbx($arChangeItem['ID']).'">'.htmlspecialcharsbx($arChangeItem['DATE']).'</option>';
						}
						?>
						</select>
					</td>
				</tr>
			</table>
			<?
			}
			?>
		</form>
		<?
		require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");
	}
	die();
}

$oProfile = new CKDAImportProfile();
$sTableID = "tbl_kdaimportexcel_profile";
$instance = \Bitrix\Main\Application::getInstance();
$context = $instance->getContext();
$request = $context->getRequest();

if(isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'export')
{
	$oProfile->OutputBackup();
}

$oSort = new CAdminSorting($sTableID, "ID", "asc");
$lAdmin = new CAdminList($sTableID, $oSort);

$arFilterFields = array(
	"filter_name",
	"filter_group_id"
);

$lAdmin->InitFilter($arFilterFields);

$filter = array();

if(strlen($filter_name) > 0)
	$filter["%NAME"] = trim($filter_name);
if($filter_group_id=='ALL') {}
elseif(strlen($filter_group_id) > 0) $filter["GROUP_ID"] = (int)$filter_group_id;
else $filter["GROUP.ID"] = false;

if($lAdmin->EditAction())
{
	foreach ($_POST['FIELDS'] as $ID => $arFields)
	{
		if(preg_match('/^G(\d+)$/', $ID, $m))
		{
			if(!array_key_exists('NAME', $arFields) || strlen(trim($arFields['NAME'])) > 0)
			{
				\Bitrix\KdaImportexcel\ProfileGroupTable::update($m[1], $arFields);
			}
			continue;
		}
		
		$ID = (int)$ID;

		if ($ID <= 0 || !$lAdmin->IsUpdated($ID))
			continue;
		
		$dbRes = \Bitrix\KdaImportexcel\ProfileTable::update($ID, $arFields);
		if(!$dbRes->isSuccess())
		{
			$error = '';
			if($dbRes->getErrors())
			{
				foreach($dbRes->getErrors() as $errorObj)
				{
					$error .= $errorObj->getMessage().'. ';
				}
			}
			if($error)
				$lAdmin->AddUpdateError($error, $ID);
			else
				$lAdmin->AddUpdateError(GetMessage("KDA_IE_ERROR_UPDATING_REC")." (".$arFields["ID"].", ".$arFields["NAME"].", ".$arFields["SORT"].")", $ID);
		}
	}
}

if(($arID = $lAdmin->GroupAction()))
{
	if($_REQUEST['action_target']=='selected')
	{
		$arID = Array();
		$dbResultList = \Bitrix\KdaImportexcel\ProfileTable::getList(array('filter'=>$filter, 'select'=>array('ID')));
		while($arResult = $dbResultList->Fetch())
			$arID[] = $arResult['ID'];
	}

	foreach ($arID as $ID)
	{
		if(strlen($ID) <= 0)
			continue;

		switch ($_REQUEST['action'])
		{
			case "delete":
				if(preg_match('/^G(\d+)$/', $ID, $m))
				{
					$dbRes = \Bitrix\KdaImportexcel\ProfileGroupTable::delete($m[1]);
					if($dbRes->isSuccess())
					{
						$arProfiles = $oProfile->GetList(array('GROUP_ID'=>$m[1]));
						foreach($arProfiles as $profileId=>$profileName)
						{
							$oProfile->UpdateFields($profileId, array('GROUP_ID'=>false));
						}
					}
				}
				else
				{
					$oProfile->Delete($ID - 1);
					/*$dbRes = \Bitrix\KdaImportexcel\ProfileTable::delete($ID);
					if(!$dbRes->isSuccess())
					{
						$error = '';
						if($dbRes->getErrors())
						{
							foreach($dbRes->getErrors() as $errorObj)
							{
								$error .= $errorObj->getMessage().'. ';
							}
						}
						if($error)
							$lAdmin->AddGroupError($error, $ID);
						else
							$lAdmin->AddGroupError(GetMessage("KDA_IE_ERROR_DELETING_TYPE"), $ID);
					}*/
				}
				break;
			case "move_to_group":
				$oProfile->UpdateFields($ID - 1, array('GROUP_ID'=>(int)$_REQUEST['new_profile_group']));
				break;
		}
	}
}

$arProfileGroups = array();
$dbRes = \Bitrix\KdaImportexcel\ProfileGroupTable::getList(array('filter'=>array('PROFILE_TYPE' => $profileType), 'order'=>array('SORT'=>'ASC')));
while($arGroup = $dbRes->Fetch())
{
	$arProfileGroups[] = $arGroup;
}

$usePageNavigation = true;
$navyParams = CDBResult::GetNavParams(CAdminResult::GetNavSize(
	$sTableID,
	array('nPageSize' => 20, 'sNavID' => $APPLICATION->GetCurPage())
));
if ($navyParams['SHOW_ALL'])
{
	$usePageNavigation = false;
}
else
{
	$navyParams['PAGEN'] = (int)$navyParams['PAGEN'];
	$navyParams['SIZEN'] = (int)$navyParams['SIZEN'];
}

$getListParams = array(
	'select' => array(
		'ID', 
		'ACTIVE', 
		'NAME', 
		'DATE_START', 
		'DATE_FINISH', 
		'SORT',
		//'PROFILE_EXEC_ID'
	),
	/*'runtime' => array(
		'PROFILE_EXEC_ID' => array(
			"data_type" => "integer",
			"expression" => array("MAX(%s)", 'PROFILE_EXEC_STAT.PROFILE_EXEC.ID')
		)
	),*/ //slow select
	'filter' => $filter
);

$getListGroupParams = array(
	'select' => array(
		'ID', 
		'ACTIVE', 
		'NAME', 
		'SORT',
	),
	'filter' => array_merge(array_intersect_key($filter, array('NAME'=>'', 'ACTIVE'=>'')), array('PROFILE_TYPE'=>$profileType))
);
if(strlen($filter_group_id) > 0)
{
	$getListGroupParams['filter'] = array('ID'=>-1);
}

if ($usePageNavigation)
{
	$getListParams['limit'] = $navyParams['SIZEN'];
	$getListParams['offset'] = $navyParams['SIZEN']*($navyParams['PAGEN']-1);
}

if ($usePageNavigation)
{
	$countQuery = new Query(\Bitrix\KdaImportexcel\ProfileGroupTable::getEntity());
	$countQuery->addSelect(new ExpressionField('CNT', 'COUNT(1)'));
	$countQuery->setFilter($getListGroupParams['filter']);
	$totalCount = $countQuery->setLimit(null)->setOffset(null)->exec()->fetch();
	unset($countQuery);
	$totalCountGroup = (int)$totalCount['CNT'];		
	
	$countQuery = new Query(\Bitrix\KdaImportexcel\ProfileTable::getEntity());
	$countQuery->addSelect(new ExpressionField('CNT', 'COUNT(1)'));
	$countQuery->setFilter($getListParams['filter']);
	$totalCount = $countQuery->setLimit(null)->setOffset(null)->exec()->fetch();
	unset($countQuery);
	$totalCountProfile = $totalCount['CNT'];
	$totalCount = $totalCountProfile + $totalCountGroup;
	if ($totalCount > 0)
	{
		$totalPages = ceil($totalCount/$navyParams['SIZEN']);
		if ($navyParams['PAGEN'] > $totalPages)
			$navyParams['PAGEN'] = $totalPages;
		$getListParams['limit'] = $navyParams['SIZEN'];
		$getListParams['offset'] = $navyParams['SIZEN']*($navyParams['PAGEN']-1) - $totalCountGroup;
		if($getListParams['offset'] < 0)
		{
			$getListParams['limit'] = max(0, $getListParams['limit'] + $getListParams['offset']);
			$getListParams['offset'] = 0;
		}
		$getListGroupParams['limit'] = $navyParams['SIZEN'];
		$getListGroupParams['offset'] = $navyParams['SIZEN']*($navyParams['PAGEN']-1);
	}
	else
	{
		$navyParams['PAGEN'] = 1;
		$getListParams['limit'] = $navyParams['SIZEN'];
		$getListParams['offset'] = 0;
		$getListGroupParams['limit'] = $navyParams['SIZEN'];
		$getListGroupParams['offset'] = 0;
	}
}

$getListParams['order'] = array(ToUpper($by) => ToUpper($order));
if(in_array($by, array('NAME', 'ACTIVE', 'SORT', 'ID'))) $getListGroupParams['order'] = array(ToUpper($by) => ToUpper($order));
else $getListGroupParams['order'] = array('ID' => 'ASC');

$rsDataGroup = new CAdminResult(\Bitrix\KdaImportexcel\ProfileGroupTable::getList($getListGroupParams), $sTableID);

$rsData = new CAdminResult(\Bitrix\KdaImportexcel\ProfileTable::getList($getListParams), $sTableID);
if ($usePageNavigation)
{
	$rsData->NavStart($getListParams['limit'], $navyParams['SHOW_ALL'], $navyParams['PAGEN']);
	$rsData->NavRecordCount = $totalCount;
	$rsData->NavPageCount = $totalPages;
	$rsData->NavPageNomer = $navyParams['PAGEN'];
}
else
{
	$rsData->NavStart();
}

$lAdmin->NavText($rsData->GetNavPrint(GetMessage("KDA_IE_PROFILE_LIST")));

$lAdmin->AddHeaders(array(
	array("id"=>"ID", "content"=>"ID", 	"sort"=>"ID", "default"=>true),
	array("id"=>"ACTIVE", "content"=>GetMessage("KDA_IE_PL_ACTIVE"), "sort"=>"ACTIVE", "default"=>true),
	array("id"=>"NAME", "content"=>GetMessage("KDA_IE_PL_NAME"), "sort"=>"NAME", "default"=>true),
	array("id"=>"DATE_START", "content"=>GetMessage("KDA_IE_PL_DATE_START"), "sort"=>"DATE_START", "default"=>true),
	array("id"=>"DATE_FINISH", "content"=>GetMessage("KDA_IE_PL_DATE_FINISH"), "sort"=>"DATE_FINISH", "default"=>true),
	array("id"=>"SORT", "content"=>GetMessage("KDA_IE_PL_SORT"), "sort"=>"SORT", "default"=>true),
	array("id"=>"STATUS", "content"=>GetMessage("KDA_IE_PL_STATUS"), "default"=>true),
));

$arVisibleColumns = $lAdmin->GetVisibleHeaderColumns();

while($arGroup = $rsDataGroup->NavNext(true, "f_"))
{
	$row =& $lAdmin->AddRow('G'.$f_ID, $arGroup, $moduleFilePrefix."_profile_list.php?set_filter=Y&filter_group_id=".$f_ID."&lang=".LANG, GetMessage("KDA_IE_TO_GROUP"));

	$row->AddField("ID", "<a href=\"".$moduleFilePrefix."_profile_list.php?set_filter=Y&filter_group_id=".$f_ID."&lang=".LANG."\">".$f_ID."</a>");
	$row->AddCheckField("ACTIVE", $f_ACTIVE);
	$row->AddViewField("NAME", '<a href="'.$moduleFilePrefix.'_profile_list.php?set_filter=Y&filter_group_id='.$f_ID.'&lang='.LANG.'"><span class="adm-submenu-item-link-icon adm-list-table-icon iblock-section-icon"></span> '.$f_NAME.'</a>');
	$row->AddInputField("NAME", array('SIZE'=>40));
	$row->AddInputField("SORT", array('SIZE'=>10));
	
	$arActions = array();
	$arActions[] = array("ICON"=>"delete", "TEXT"=>GetMessage("KDA_IE_PROFILE_DELETE"), "ACTION"=>"if(confirm('".GetMessageJS('KDA_IE_PROFILE_DELETE_CONFIRM')."')) ".$lAdmin->ActionDoGroup('G'.$f_ID, "delete"));

	$row->AddActions($arActions);
}

$oProfile = new CKDAImportProfile();
while($arProfile = $rsData->NavNext(true, "f_"))
{
	$arProfile['ID'] = $f_ID = $f_ID - 1;
	$row =& $lAdmin->AddRow(($f_ID+1), $arProfile, $moduleFilePrefix.".php?PROFILE_ID=".$f_ID."&lang=".LANG, GetMessage("KDA_IE_TO_PROFILE"));

	$row->AddField("ID", "<a href=\"".$moduleFilePrefix.".php?PROFILE_ID=".$f_ID."&lang=".LANG."\">".$f_ID."</a>");
	$row->AddCheckField("ACTIVE", $f_ACTIVE);
	$row->AddViewField("NAME", '<a href="'.$moduleFilePrefix.'.php?PROFILE_ID='.$f_ID.'&lang='.LANG.'">'.$f_NAME.'</a>');
	$row->AddInputField("NAME", array('SIZE'=>40));
	$row->AddInputField("SORT", array('SIZE'=>10));
	$row->AddField("DATE_START", $f_DATE_START);
	$row->AddField("DATE_FINISH", $f_DATE_FINISH);
	
	$arStatus = $oProfile->GetStatus($f_ID);
	$row->AddField("STATUS", (strlen($arStatus['MESSAGE']) > 0 ? $arStatus['MESSAGE'] : ''));
	
	$arActions = array();
	$arActions[] = array("ICON"=>"view", "TEXT"=>GetMessage("KDA_IE_TO_PROFILE_ACT"), "ACTION"=>$lAdmin->ActionRedirect($moduleFilePrefix.".php?PROFILE_ID=".$f_ID."&lang=".LANG), "DEFAULT"=>true);
	if(true /*$f_PROFILE_EXEC_ID > 0*/)
	{
		$arActions[] = array("ICON"=>"move", "TEXT"=>GetMessage("KDA_IE_RESTORE_ACT"), "ACTION"=>$lAdmin->ActionRedirect($moduleFilePrefix."_rollback.php?PROFILE_ID=".$f_ID."&lang=".LANG));
	}
	if(true)
	{
		$arActions[] = array("ICON"=>"move", "TEXT"=>GetMessage("KDA_IE_OLD_PARAMS_ACT"), "ACTION"=>"EProfileList.ShowOldParamsWindow(".($f_ID+1).");");
	}

	$arActions[] = array("SEPARATOR" => true);
	$arActions[] = array("ICON"=>"delete", "TEXT"=>GetMessage("KDA_IE_PROFILE_DELETE"), "ACTION"=>"if(confirm('".GetMessageJS('KDA_IE_PROFILE_DELETE_CONFIRM')."')) ".$lAdmin->ActionDoGroup(($f_ID+1), "delete"));

	$row->AddActions($arActions);
}

$lAdmin->AddFooter(
	array(
		array(
			"title" => GetMessage("MAIN_ADMIN_LIST_SELECTED"),
			"value" => $rsData->SelectedRowsCount()
		),
		array(
			"counter" => true,
			"title" => GetMessage("MAIN_ADMIN_LIST_CHECKED"),
			"value" => "0"
		),
	)
);

$selectProfileGroups = '<select name="new_profile_group" id="select_profile_groups" style="display: none;"><option value="">'.GetMessage("KDA_IE_MOVE_TO_GROUP_TOP").'</option>';
foreach($arProfileGroups as $arGroup)
{
	$selectProfileGroups .= '<option value="'.$arGroup['ID'].'">'.htmlspecialcharsBx($arGroup['NAME']).'</option>';
}
$selectProfileGroups .= '</select>';

$lAdmin->AddGroupActionTable(
	array(
		"delete" => GetMessage("MAIN_ADMIN_LIST_DELETE"),
		"move_to_group" => array(
			'value' => 'move_to_group',
			'name' => GetMessage("KDA_IE_MOVE_TO_GROUP"),
		),
		"move_to_group_action" => array(
			'type' => 'html',
			'value' => $selectProfileGroups,
		)
	),
	array('select_onchange'=>'if(this.value=="move_to_group"){$("#select_profile_groups").show();}else{$("#select_profile_groups").hide();}')
);

$aContext = array();
if($filter_group_id!='ALL')
{
	if((int)$filter_group_id <= 0)
	{
		$aContext[] = array(
			"TEXT" => GetMessage("KDA_IE_CREATE_GROUP"),
			"ICON" => "btn_new",
			"ONCLICK" => "EProfileList.NewGroupWindow();"
		);
	}
	else
	{
		$aContext[] = array(
			"TEXT" => GetMessage("KDA_IE_BACK_UP"),
			"LINK" => $moduleFilePrefix."_profile_list.php?set_filter=Y&lang=".LANG
		);
	}
}

$lAdmin->AddAdminContextMenu($aContext, true, true, array());
$lAdmin->CheckListMode();

$APPLICATION->SetTitle(GetMessage("KDA_IE_PROFILE_LIST_TITLE"));
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if (!call_user_func($moduleDemoExpiredFunc)) {
	call_user_func($moduleShowDemoFunc);
}

$aMenu = array(
	array(
		"TEXT" => GetMessage("KDA_IE_BACK_TO_IMPORT"),
		"ICON" => "btn_list",
		"LINK" => "/bitrix/admin/".$moduleFilePrefix.".php?lang=".LANG
	)
);

if($oProfile instanceof CKDAImportProfileDB)
{	
	$aMenu[] = array(
		"TEXT"=>GetMessage("KDA_IE_MENU_EXPORT_IMPORT_PROFILES"),
		"TITLE"=>GetMessage("KDA_IE_MENU_EXPORT_IMPORT_PROFILES"),
		"MENU" => array(
			array(
				"TEXT" => GetMessage("KDA_IE_MENU_EXPORT_PROFILES"),
				"TITLE" => GetMessage("KDA_IE_MENU_EXPORT_PROFILES"),
				"LINK" => "/bitrix/admin/".$moduleFilePrefix."_profile_list.php?mode=export"
			),
			array(
				"TEXT" => GetMessage("KDA_IE_MENU_IMPORT_PROFILES"),
				"TITLE" => GetMessage("KDA_IE_MENU_IMPORT_PROFILES"),
				"ONCLICK" => "EProfileList.ShowRestoreWindow();"
			)
		),
		"ICON" => "btn_green",
	);
}

$context = new CAdminContextMenu($aMenu);
$context->Show();
?>

<form name="find_form" method="GET" action="<?echo $APPLICATION->GetCurPage()?>?">
<?
$oFilter = new CAdminFilter(
	$sTableID."_filter",
	array(
		GetMessage("SALE_F_PERSON_TYPE"),
	)
);

$oFilter->Begin();
?>
	<tr>
		<td><?echo GetMessage("KDA_IE_F_NAME")?>:</td>
		<td>
			<input type="text" name="filter_name" value="<?echo htmlspecialcharsex($filter_name)?>">
		</td>
	</tr>
	<tr>
		<td><?echo GetMessage("KDA_IE_F_GROUP")?>:</td>
		<td>
			<select name="filter_group_id" >
				<option value=""><?echo GetMessage("KDA_IE_GROUP_EMPTY"); ?></option>
				<option value="ALL"><?echo GetMessage("KDA_IE_ALL_GROUPS"); ?></option>
				<?
				foreach($arProfileGroups as $arGroup)
				{
					?><option value="<?echo $arGroup['ID'];?>" <?if($filter_group_id==$arGroup['ID']){echo 'selected';}?>><?echo htmlspecialcharsBx($arGroup['NAME']); ?></option><?
				}
				?>
			</select>
		</td>
	</tr>
<?
$oFilter->Buttons(
	array(
		"table_id" => $sTableID,
		"url" => $APPLICATION->GetCurPage(),
		"form" => "find_form"
	)
);
$oFilter->End();
?>
</form>

<?
$lAdmin->DisplayList();
require ($DOCUMENT_ROOT."/bitrix/modules/main/include/epilog_admin.php");
?>
