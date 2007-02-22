<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA["tx_hooker_bot"] = array (
	"ctrl" => $TCA["tx_hooker_bot"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "hidden,starttime,endtime,last_run,run_interval,family"
	),
	"feInterface" => $TCA["tx_hooker_bot"]["feInterface"],
	"columns" => array (
		'hidden' => array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config' => array (
				'type' => 'check',
				'default' => '0'
			)
		),
		'starttime' => array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.starttime',
			'config' => array (
				'type' => 'input',
				'size' => '8',
				'max' => '20',
				'eval' => 'date',
				'default' => '0',
				'checkbox' => '0'
			)
		),
		'endtime' => array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.endtime',
			'config' => array (
				'type' => 'input',
				'size' => '8',
				'max' => '20',
				'eval' => 'date',
				'checkbox' => '0',
				'default' => '0',
				'range' => array (
					'upper' => mktime(0,0,0,12,31,2020),
					'lower' => mktime(0,0,0,date('m')-1,date('d'),date('Y'))
				)
			)
		),
		"last_run" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_bot.last_run",		
			"config" => Array (
				"type" => "input",
				"size" => "12",
				"max" => "20",
				"eval" => "datetime",
				"checkbox" => "0",
				"default" => "0"
			)
		),
		"run_interval" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_bot.run_interval",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"eval" => "int,nospace",
			)
		),
		"family" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_bot.family",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "hidden;;1;;1-1-1, last_run, run_interval, family")
	),
	"palettes" => array (
		"1" => array("showitem" => "starttime, endtime")
	)
);



$TCA["tx_hooker_agent"] = array (
	"ctrl" => $TCA["tx_hooker_agent"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "starttime,endtime,filename,state1,state2,status,importance,attempts,botuid"
	),
	"feInterface" => $TCA["tx_hooker_agent"]["feInterface"],
	"columns" => array (
		'starttime' => array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.starttime',
			'config' => array (
				'type' => 'input',
				'size' => '8',
				'max' => '20',
				'eval' => 'date',
				'default' => '0',
				'checkbox' => '0'
			)
		),
		'endtime' => array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.endtime',
			'config' => array (
				'type' => 'input',
				'size' => '8',
				'max' => '20',
				'eval' => 'date',
				'checkbox' => '0',
				'default' => '0',
				'range' => array (
					'upper' => mktime(0,0,0,12,31,2020),
					'lower' => mktime(0,0,0,date('m')-1,date('d'),date('Y'))
				)
			)
		),
		"filename" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_agent.filename",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
		"state1" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_agent.state1",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
		"state2" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_agent.state2",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
		"status" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_agent.status",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"range" => Array ("lower"=>0,"upper"=>1000),	
				"eval" => "int,nospace",
			)
		),
		"importance" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_agent.importance",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"range" => Array ("lower"=>0,"upper"=>1000),	
				"eval" => "int,nospace",
			)
		),
		"attempts" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_agent.attempts",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"range" => Array ("lower"=>0,"upper"=>1000),	
				"eval" => "int,nospace",
			)
		),
		"botuid" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:hooker/locallang_db.xml:tx_hooker_agent.botuid",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"range" => Array ("lower"=>0,"upper"=>1000),	
				"eval" => "int,nospace",
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "starttime;;;;1-1-1, endtime, filename, state1, state2, status, importance, attempts, botuid")
	),
	"palettes" => array (
		"1" => array("showitem" => "")
	)
);
?>