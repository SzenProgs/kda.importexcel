<?
if (!CModule::IncludeModule("iblock"))
	return false;

IncludeModuleLangFile(__FILE__);
$moduleId = 'kda.importexcel';
$moduleIdUl = 'kda_importexcel';
$moduleFilePrefix = 'kda_import_excel';

$aMenu = array();

global $USER;
$bUserIsAdmin = $USER->IsAdmin();

$bHasWRight = false;
$rsIBlocks = CIBlock::GetList(array("SORT"=>"asc", "NAME"=>"ASC"), array("MIN_PERMISSION" => "U"));
if($arIBlock = $rsIBlocks->Fetch())
{
	$bHasWRight = true;
}

if($APPLICATION->GetGroupRight($moduleId) <= "R")
{
	$bHasWRight = false;
}

if($bUserIsAdmin || $bHasWRight)
{
	$aSubMenu[] = array(
		"text" => GetMessage("KDA_MENU_IMPORT_TITLE"),
		"url" => $moduleFilePrefix.".php?lang=".LANGUAGE_ID,
		"more_url" => array(
			$moduleFilePrefix."_profile_list.php", 
			$moduleFilePrefix."_rollback.php"
		),
		"title" => GetMessage("KDA_MENU_IMPORT_TITLE"),
		"module_id" => $moduleId,
		"items_id" => "menu_".$moduleIdUl,
		"sort" => 100,
		"section" => $moduleIdUl,
	);
	
	if(CModule::IncludeModule('highloadblock'))
	{
		$aSubMenu[] = array(
			"text" => GetMessage("KDA_MENU_IMPORT_TITLE_HIGHLOAD"),
			"url" => $moduleFilePrefix."_highload.php?lang=".LANGUAGE_ID,
			"title" => GetMessage("KDA_MENU_IMPORT_TITLE_HIGHLOAD"),
			"module_id" => $moduleId,
			"items_id" => "menu_".$moduleIdUl."_highload",
			"sort" => 200,
			"section" => $moduleIdUl,
		);			
	}
	
	$aSubMenu[] = array(
		"text" => GetMessage("KDA_MENU_IMPORT_TITLE_STAT_WRAP"),
		"title" => GetMessage("KDA_MENU_IMPORT_TITLE_STAT_WRAP"),
		"module_id" => $moduleId,
		"items_id" => "menu_".$moduleIdUl,
		"sort" => 300,
		"section" => $moduleIdUl,
		'items' => array(
			array(
				"text" => GetMessage("KDA_MENU_IMPORT_TITLE_STAT"),
				"url" => $moduleFilePrefix."_event_stat.php?lang=".LANGUAGE_ID,
				"title" => GetMessage("KDA_MENU_IMPORT_TITLE_STAT"),
				"module_id" => $moduleId,
				"items_id" => "menu_".$moduleIdUl,
				"sort" => 100,
				"section" => $moduleIdUl,
			),
			array(
				"text" => GetMessage("KDA_MENU_IMPORT_TITLE_STAT_DETAIL"),
				"url" => $moduleFilePrefix."_event_log.php?lang=".LANGUAGE_ID,
				"title" => GetMessage("KDA_MENU_IMPORT_TITLE_STAT_DETAIL"),
				"module_id" => $moduleId,
				"items_id" => "menu_".$moduleIdUl,
				"sort" => 200,
				"section" => $moduleIdUl,
			)
		)
	);
	
	$aMenu[] = array(
		"parent_menu" => "global_menu_content",
		"section" => $moduleIdUl,
		"sort" => 1400,
		"text" => GetMessage("KDA_MENU_IMPORT_TITLE_PARENT"),
		"title" => GetMessage("KDA_MENU_IMPORT_TITLE_PARENT"),
		"icon" => $moduleIdUl."_menu_import_icon",
		"items_id" => "menu_".$moduleIdUl."_parent",
		"module_id" => $moduleId,
		"items" => $aSubMenu,
	);
}

return $aMenu;
?>