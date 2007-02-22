<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006 Pavlenko Zorik <zorik@zorik.net>
* based on:
* class_CPULoad.php - CPU Load Class
* Version 1.0.1
* Copyright 2001-2002, Steve Blinch
* http://code.blitzaffe.com
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

require_once(PATH_t3lib.'class.t3lib_svbase.php');


/**
 * Service "Cruise Control" for the "hooker" extension.
 *
 * @author	Pavlenko Zorik <zorik@zorik.net>
 * @package	TYPO3
 * @subpackage	tx_hooker
 */
define("TEMP_PATH","/tmp/"); 

class tx_hooker_sv4 extends t3lib_svbase {
	var $prefixId = 'tx_hooker_sv4';		// Same as class name
	var $scriptRelPath = 'sv4/class.tx_hooker_sv4.php';	// Path to this script relative to the extension dir.
	var $extKey = 'hooker';	// The extension key.
	
	
	function proc() {
		do {
			$this->get_load(1);// pass seconds of a sample
			$sysload = $this->load["cpu"];

			// calm down if overloaded
			if ($sysload>60) {
				$GLOBALS['c_bot_env']['do']['devlog']->devLog(time().' overload: '.$sysload, 'hooker', '-1');
				sleep(1);
			}
		} while ($sysload>60);

		$GLOBALS['c_bot_env']['do']['devlog']->devLog(time().' sysload: '.$sysload, 'hooker', '-1');
	}

	function check_load() {
		$fd = fopen("/proc/stat","r");
		if ($fd) {
		$statinfo = explode("\n",fgets($fd, 1024));
		fclose($fd);
		foreach($statinfo as $line) {
			$info = explode(" ",$line);
			//echo "<pre>"; var_dump($info); echo "</pre>";
			if($info[0]=="cpu") {
			array_shift($info);  // pop off "cpu"
			if(!$info[0]) array_shift($info); // pop off blank space (if any)
			$this->user = $info[0];
			$this->nice = $info[1];
			$this->system = $info[2];
			$this->idle = $info[3];
	//                    $this->print_current();
			return;
			}
		}
		}
	}
	
	function store_load() {
		$this->last_user = $this->user;
		$this->last_nice = $this->nice;
		$this->last_system = $this->system;
		$this->last_idle = $this->idle;
	}
	
	function save_load() {
		$this->store_load();

		$content = time()."\n";
		$content .= $this->last_user." ".$this->last_nice." ".$this->last_system." ".$this->last_idle."\n";
		$content .= $this->load["user"]." ".$this->load["nice"]." ".$this->load["system"]." ".$this->load["idle"]." ".$this->load["cpu"]."\n";

		t3lib_div::writeFile(PATH_site.'typo3temp/cpuinfo.tmp',$content);
	}
	
	function load_load() {
		$file = t3lib_div::getURL(PATH_site.'typo3temp/cpuinfo.tmp');
		if ($file) {
			$lines = explode("\n",$file);
			
			$this->lasttime = $lines[0];
			list($this->last_user,$this->last_nice,$this->last_system,$this->last_idle) = explode(" ",$lines[1]);
			list($this->load["user"],$this->load["nice"],$this->load["system"],$this->load["idle"],$this->load["cpu"]) = explode(" ",$lines[2]);
		} else {
			$this->lasttime = time() - 60;
			$this->last_user = $this->last_nice = $this->last_system = $this->last_idle = 0;
			$this->user = $this->nice = $this->system = $this->idle = 0;
		}
	}
	
	function calculate_load() {
		$d_user = $this->user - $this->last_user;
		$d_nice = $this->nice - $this->last_nice;
		$d_system = $this->system - $this->last_system;
		$d_idle = $this->idle - $this->last_idle;
		
		//printf("Delta - User: %f  Nice: %f  System: %f  Idle: %f<br>",$d_user,$d_nice,$d_system,$d_idle);
	
		$total=$d_user+$d_nice+$d_system+$d_idle;
		if ($total<1) $total=1;
		$scale = 100.0/$total;
		
		$cpu_load = ($d_user+$d_nice+$d_system)*$scale;
		$this->load["user"] = $d_user*$scale;
		$this->load["nice"] = $d_nice*$scale;
		$this->load["system"] = $d_system*$scale;
		$this->load["idle"] = $d_idle*$scale;
		$this->load["cpu"] = ($d_user+$d_nice+$d_system)*$scale;
	}
	
	function print_current() {
		printf("Current load tickers - User: %f  Nice: %f  System: %f  Idle: %f<br>",
		$this->user,
		$this->nice,
		$this->system,
		$this->idle
		);
	}
	
	function print_load() {
		printf("User: %.1f%%  Nice: %.1f%%  System: %.1f%%  Idle: %.1f%%  Load: %.1f%%<br>",
		$this->load["user"],
		$this->load["nice"],
		$this->load["system"],
		$this->load["idle"],
		$this->load["cpu"]
		);
	}
	
	function sample_load($interval=1) {
		$this->check_load();
		$this->store_load();
		sleep($interval);
		$this->check_load();
		$this->calculate_load();
	}
	
	function get_load($fastest_sample=4) {
		$this->load_load();
		$this->cached = (time()-$this->lasttime);
		if ($this->cached>=$fastest_sample) {
			$this->check_load();
			$this->calculate_load();
			$this->save_load();
		}
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/hooker/sv4/class.tx_hooker_sv4.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/hooker/sv4/class.tx_hooker_sv4.php']);
}

?>