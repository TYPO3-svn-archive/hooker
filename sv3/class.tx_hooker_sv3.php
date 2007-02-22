<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005 Pavlenko Zorik (zorik@zorik.net)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Service 'DevLog' for the 'hooker' extension.
 *
 * @author	Pavlenko Zorik <zorik@zorik.net>
 */


/*
* TODO: set verbose levels??
*/


require_once(PATH_t3lib.'class.t3lib_svbase.php');

class tx_hooker_sv3 extends t3lib_svbase {
	var $prefixId = 'tx_hooker_sv3';		// Same as class name
	var $scriptRelPath = 'sv3/class.tx_hooker_sv3.php';	// Path to this script relative to the extension dir.
	var $extKey = 'hooker';	// The extension key.
	
	/**
	 * [Put your description here]
	 */
	function init()	{
		$available = parent::init();
	
		// Here you can initialize your class.
	
		// The class have to do a strict check if the service is available.
		// The needed external programs are already checked in the parent class.
	
		// If there's no reason for initialization you can remove this function.
	
		return $available;
	}
	
	/**
	 * Developer log
	 *
	 * $logArr = array('msg'=>$msg, 'extKey'=>$extKey, 'severity'=>$severity, 'dataVar'=>$dataVar);
	 * 'msg'		string		Message (in english).
	 * 'extKey'		string		Extension key (from which extension you are calling the log)
	 * 'severity'	integer		Severity: 0 is info, 1 is notice, 2 is warning, 3 is fatal error, -1 is "OK" message
	 * 'dataVar'	array		Must consist of content.
	 * 
	 * @param	array		log data array
	 * @return void	 
	 */
	function devLog($message, $extKey, $severity=0, $dataVar=FALSE)	{
		global $TYPO3_CONF_VARS;// here we can put our own configuration - edit file "ext_localconf.php"

		$msg = '['.$severity.']-';
		$msg .= '['.$extKey.']'.chr(10);
		$msg .= $message.chr(10);
// 		$msg .= serialize($logArr['dataVar']).chr(10);

		if (is_array($dataVar)) {
			if ($dataVar['content']) {
				fwrite(STDERR,$logArr['dataVar']['content']);
			}
		}

		// human readable STDOUT
		print_r($msg);

		// very important!!
		// flush the stdout immediately! otherwise you will get messages repeated several times, 
		// which will be wrong and very confusing!
		ob_flush();
	}

// 		$time = 0;
// 		$time_start = $this->microtime_float();
// 		$morebots = 1;
//	do some stuff
// 		$time_end = $this->microtime_float();
// 		$time = $time_end - $time_start;
	/**
	* Simple function to replicate PHP5 behaviour
	*/
// 	function microtime_float() {
// 		list($usec, $sec) = explode(" ", microtime());
// 		return ((float)$usec + (float)$sec);
// 	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/hooker/sv3/class.tx_hooker_sv3.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/hooker/sv3/class.tx_hooker_sv3.php']);
}

?>