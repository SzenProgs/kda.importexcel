<?php
namespace Bitrix\KdaImportexcel;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class ProfileGroupTable extends Entity\DataManager
{
	const TYPE_IMPORT_IBLOCK = 1;
	const TYPE_IMPORT_HLBLOCK = 2;
	const TYPE_EXPORT_IBLOCK = 3;
	const TYPE_EXPORT_HLBLOCK = 4;
	
	public static function getFilePath()
	{
		return __FILE__;
	}

	public static function getTableName()
	{
		return 'b_kdaimportexcel_profile_group';
	}

	public static function getMap()
	{
		return array(
			'ID' => array(
				'data_type' => 'integer',
				'primary' => true,
				'autocomplete' => true,
			),
			'PROFILE_TYPE' => array(
				'data_type' => 'integer',
			),
			'ACTIVE' => array(
				'data_type' => 'boolean',
				'values' => array('N', 'Y'),
				'default_value' => 'Y'
			),
			'NAME' => array(
				'data_type' => 'string',
				'required' => true,
				'validation' => array(__CLASS__, 'validateName')
			),
			'SORT' => array(
				'data_type' => 'integer',
				'default_value' => 500
			)
		);
	}
	
	public static function validateName()
	{
		return array(
			new Entity\Validator\Length(null, 255),
		);
	}
}