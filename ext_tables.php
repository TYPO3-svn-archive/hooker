<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_extMgm::addService($_EXTKEY,  'hooker_base' /* sv type */,  'tx_hooker_sv1' /* sv key */,
		array(

			'title' => 'fetchUrl',
			'description' => 'fetchUrl',

			'subtype' => 'fetchUrl',

			'available' => TRUE,
			'priority' => 50,
			'quality' => 50,

			'os' => 'unix',
			'exec' => '',

			'classFile' => t3lib_extMgm::extPath($_EXTKEY).'sv1/class.tx_hooker_sv1.php',
			'className' => 'tx_hooker_sv1',
		)
	);


t3lib_extMgm::addService($_EXTKEY,  'hooker_base' /* sv type */,  'tx_hooker_sv2' /* sv key */,
		array(

			'title' => 'Read/Write Agent',
			'description' => 'Read/Write Agent Environment',

			'subtype' => 'rw_agent',

			'available' => TRUE,
			'priority' => 50,
			'quality' => 50,

			'os' => 'unix',
			'exec' => '',

			'classFile' => t3lib_extMgm::extPath($_EXTKEY).'sv2/class.tx_hooker_sv2.php',
			'className' => 'tx_hooker_sv2',
		)
	);


t3lib_extMgm::addService($_EXTKEY,  'hooker_base' /* sv type */,  'tx_hooker_sv3' /* sv key */,
		array(

			'title' => 'DevLog',
			'description' => 'logging facility',

			'subtype' => 'devlog',

			'available' => TRUE,
			'priority' => 50,
			'quality' => 50,

			'os' => 'unix',
			'exec' => '',

			'classFile' => t3lib_extMgm::extPath($_EXTKEY).'sv3/class.tx_hooker_sv3.php',
			'className' => 'tx_hooker_sv3',
		)
	);


t3lib_extMgm::addService($_EXTKEY,  'hooker_base' /* sv type */,  'tx_hooker_sv4' /* sv key */,
		array(

			'title' => 'Cruise Control',
			'description' => 'Cruise Control',

			'subtype' => 'cruise_control',

			'available' => TRUE,
			'priority' => 50,
			'quality' => 50,

			'os' => 'unix',
			'exec' => '',

			'classFile' => t3lib_extMgm::extPath($_EXTKEY).'sv4/class.tx_hooker_sv4.php',
			'className' => 'tx_hooker_sv4',
		)
	);


t3lib_extMgm::allowTableOnStandardPages('tx_hooker_bot');

$TCA["tx_hooker_bot"] = array (
	"ctrl" => array (
		'title' => 'LLL:EXT:hooker/locallang_db.xml:tx_hooker_bot',		
		'label' => 'uid',	
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => "ORDER BY crdate",	
		'enablecolumns' => array (		
			'disabled' => 'hidden',	
			'starttime' => 'starttime',	
			'endtime' => 'endtime',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_hooker_bot.gif',
	),
	"feInterface" => array (
		"fe_admin_fieldList" => "hidden, starttime, endtime, last_run, run_interval, family",
	)
);

$TCA["tx_hooker_agent"] = array (
	"ctrl" => array (
		'title' => 'LLL:EXT:hooker/locallang_db.xml:tx_hooker_agent',		
		'label' => 'filename',	
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => "ORDER BY filename",	
		'enablecolumns' => array (		
			'starttime' => 'starttime',	
			'endtime' => 'endtime',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_hooker_agent.gif',
	),
	"feInterface" => array (
		"fe_admin_fieldList" => "starttime, endtime, filename, state1, state2, status, importance, attempts, botuid",
	)
);
?>