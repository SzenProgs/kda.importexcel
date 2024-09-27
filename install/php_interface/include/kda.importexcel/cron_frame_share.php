<?
$arProfiles = array_map('trim', explode(',', $argv[1]));
foreach($arProfiles as $profileId)
{
	if(strtolower(substr($profileId, 0, 3))=='ixh')
	{
		$argv[1] = (int)substr($profileId, 3);
		$fn = dirname(__FILE__).'/../esol.importxml/cron_frame_highload.php';
		if(file_exists($fn)) include($fn);
	}
	elseif(strtolower(substr($profileId, 0, 2))=='ix')
	{
		$argv[1] = (int)substr($profileId, 2);
		$fn = dirname(__FILE__).'/../esol.importxml/cron_frame.php';
		if(file_exists($fn)) include($fn);
	}
	elseif(strtolower(substr($profileId, 0, 2))=='ih')
	{
		$argv[1] = (int)substr($profileId, 2);
		include(dirname(__FILE__).'/cron_frame_highload.php');
	}
	elseif(strtolower(substr($profileId, 0, 1))=='i')
	{
		$argv[1] = (int)substr($profileId, 1);
		include(dirname(__FILE__).'/cron_frame.php');
	}
	elseif(strtolower(substr($profileId, 0, 2))=='eh')
	{
		$argv[1] = (int)substr($profileId, 2);
		$fn = dirname(__FILE__).'/../kda.exportexcel/cron_frame_highload.php';
		if(file_exists($fn)) include($fn);
	}
	elseif(strtolower(substr($profileId, 0, 1))=='e')
	{
		$argv[1] = (int)substr($profileId, 1);
		$fn = dirname(__FILE__).'/../kda.exportexcel/cron_frame.php';
		if(file_exists($fn)) include($fn);
	}
	elseif(strtolower(substr($profileId, 0, 1))=='m')
	{
		$argv[1] = (int)substr($profileId, 1);
		$fn = dirname(__FILE__).'/../esol.massedit/cron_frame.php';
		if(file_exists($fn)) include($fn);
	}
}
?>