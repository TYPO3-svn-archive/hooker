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
 * Service 'fetchUrl' for the 'hooker' extension.
 *
 * @author	Pavlenko Zorik <zorik@zorik.net>
 */



require_once(PATH_t3lib.'class.t3lib_svbase.php');

class tx_hooker_sv1 extends t3lib_svbase {
	var $prefixId = 'tx_hooker_sv1';		// Same as class name
	var $scriptRelPath = 'sv1/class.tx_hooker_sv1.php';	// Path to this script relative to the extension dir.
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
	 * fetch the url with libcurl
	 * 
	 * @param	Array		Array of parameters that should overwrite default params
	 * @param	Bool		1 = remove scripts and return body only
	 * @param	String		original encoding to convert from into utf-8
	 *
	 * @return	String		URL content
	 */
	function process($params,$cleanup=0,$encoding='',$single=0,$nocache=0) {
		// setup default parameters for curl
		$curlParam = Array(
			// booleans
			'CURLOPT_BINARYTRANSFER' => 0,
			'CURLOPT_CRLF' => 0,
			'CURLOPT_DNS_USE_GLOBAL_CACHE' => 1,
			'CURLOPT_FAILONERROR' => 1,
			'CURLOPT_FILETIME' => 0,
			'CURLOPT_FOLLOWLOCATION' => 1,
			'CURLOPT_FORBID_REUSE' => 0,
			'CURLOPT_FRESH_CONNECT' => 0,
			'CURLOPT_HEADER' => 0,
			'CURLOPT_HTTPGET' => 1,
			//'CURLOPT_HTTPPROXYTUNNEL' => 0,
			'CURLOPT_MUTE' => 1,
			'CURLOPT_NETRC' => 0,
			'CURLOPT_NOBODY' => 0,
			//'CURLOPT_NOPROGRESS' => 1,
			//'CURLOPT_NOSIGNAL' => 1,
			'CURLOPT_POST' => 0,
			'CURLOPT_PUT' => 0,
			'CURLOPT_RETURNTRANSFER' => 1,
			'CURLOPT_SSL_VERIFYPEER' => 0,
			'CURLOPT_UNRESTRICTED_AUTH' => 0,
			'CURLOPT_VERBOSE' => 0,
			// value should be an integer
			'CURLOPT_CONNECTTIMEOUT' => 15,
			'CURLOPT_LOW_SPEED_LIMIT' => 5,
			'CURLOPT_LOW_SPEED_TIME' => 10,
			'CURLOPT_MAXCONNECTS' => 10,
			'CURLOPT_MAXREDIRS' => 3,
			'CURLOPT_TIMEOUT' => 25,
			//value should be a string
//TODO: define in settings, not here
			'CURLOPT_COOKIEFILE' => dirname(PATH_thisScript).'/cookies/cookies.txt',
			'CURLOPT_COOKIEJAR' => dirname(PATH_thisScript).'/cookies/cookies.txt',
			'CURLOPT_CUSTOMREQUEST' => 0,
			'CURLOPT_POSTFIELDS' => '',
			'CURLOPT_REFERER' => '',
			'CURLOPT_USERAGENT' => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0; .NET CLR 1.1.4322)',
			'CURLOPT_USERPWD' => '',
			// value should be a stream resource
			//'CURLOPT_FILE' => '',
			//'CURLOPT_INFILE' => '',
			//'CURLOPT_WRITEHEADER' => '',
			// value should be a string that is the name of a valid callback function
			//'CURLOPT_READFUNCTION' => '',
			//'CURLOPT_WRITEFUNCTION' => '',
		);


		// if no url provided - die
		if (!$params['CURLOPT_URL']) {
			die('No url was specified for fetching!');
		}
		
		// check if its get or post
		if ($params['CURLOPT_POST']) { //post
			$url = $params['CURLOPT_URL'].'?'.$params['CURLOPT_POSTFIELDS'];
		} elseif ($params['CURLOPT_POSTFIELDS']) { //new get
			$url = $params['CURLOPT_URL'].'?'.$params['CURLOPT_POSTFIELDS'];
			$params['CURLOPT_URL'] = $params['CURLOPT_URL'].'?'.$params['CURLOPT_POSTFIELDS'];
			$params['CURLOPT_POSTFIELDS'] = '';
		} else { // old get
			$url = $params['CURLOPT_URL'];
		}

// 		$filename = md5(str_pad(preg_replace('/[\W|\D]/si','',$url),100).str_pad(preg_replace('/[\W|\D]/si','',$url),100));
		$filename = md5($url);
		$filename = PATH_site.'typo3temp/zor_bot/cache/'.$filename;
		if (file_exists($filename)) {
			$filectime = filemtime($filename);
		}
		$lasttime = time()-(24*60*60);

		// if not exist - fetch and cache
		if (!$filectime||$filectime<$lasttime||$nocache) {
			// say hi
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('--Fs-'.date('h').date('i').date('s').'-'.$url, 'hooker', '0', array(''));
			
			$t3lib_cs = t3lib_div::makeInstance('t3lib_cs');
			
			$params = array_merge($curlParam,$params);
			
			// set options based on parameters
			if (!$params['CURLOPT_REFERER']) $params['CURLOPT_REFERER'] = $params['CURLOPT_URL'];
			
			$content = 0;
			// variable to be incremented in loop
			$inc = 0;

			// loop to try to fetch url several times
// 			while ((!$content)&&($inc<5)) {
				$inc++;
				
				// create a new CURL resource
				$ch = curl_init($params['CURLOPT_URL']);
				unset($params['CURLOPT_URL']);
				
				if ($params['CURLOPT_BINARYTRANSFER']) curl_setopt($ch,CURLOPT_BINARYTRANSFER,$params['CURLOPT_BINARYTRANSFER']);
				if ($params['CURLOPT_CRLF']) curl_setopt($ch,CURLOPT_CRLF,$params['CURLOPT_CRLF']);
				if ($params['CURLOPT_DNS_USE_GLOBAL_CACHE']) curl_setopt($ch,CURLOPT_DNS_USE_GLOBAL_CACHE,$params['CURLOPT_DNS_USE_GLOBAL_CACHE']);
				if ($params['CURLOPT_FAILONERROR']) curl_setopt($ch,CURLOPT_FAILONERROR,$params['CURLOPT_FAILONERROR']);
				if ($params['CURLOPT_FILETIME']) curl_setopt($ch,CURLOPT_FILETIME,$params['CURLOPT_FILETIME']);
				if ($params['CURLOPT_FOLLOWLOCATION']) curl_setopt($ch,CURLOPT_FOLLOWLOCATION,$params['CURLOPT_FOLLOWLOCATION']);
				if ($params['CURLOPT_FORBID_REUSE']) curl_setopt($ch,CURLOPT_FORBID_REUSE,$params['CURLOPT_FORBID_REUSE']);
				if ($params['CURLOPT_FRESH_CONNECT']) curl_setopt($ch,CURLOPT_FRESH_CONNECT,$params['CURLOPT_FRESH_CONNECT']);
				if ($params['CURLOPT_HEADER']) curl_setopt($ch,CURLOPT_HEADER,$params['CURLOPT_HEADER']);
				if ($params['CURLOPT_HTTPGET']) curl_setopt($ch,CURLOPT_HTTPGET,$params['CURLOPT_HTTPGET']);
				if ($params['CURLOPT_MUTE']) curl_setopt($ch,CURLOPT_MUTE,$params['CURLOPT_MUTE']);
				if ($params['CURLOPT_NETRC']) curl_setopt($ch,CURLOPT_NETRC,$params['CURLOPT_NETRC']);
				if ($params['CURLOPT_NOBODY']) curl_setopt($ch,CURLOPT_NOBODY,$params['CURLOPT_NOBODY']);
				if ($params['CURLOPT_NOPROGRESS']) curl_setopt($ch,CURLOPT_NOPROGRESS,$params['CURLOPT_NOPROGRESS']);
				if ($params['CURLOPT_NOSIGNAL']) curl_setopt($ch,CURLOPT_NOSIGNAL,$params['CURLOPT_NOSIGNAL']);
				if ($params['CURLOPT_POST']) curl_setopt($ch,CURLOPT_POST,$params['CURLOPT_POST']);
				if ($params['CURLOPT_PUT']) curl_setopt($ch,CURLOPT_PUT,$params['CURLOPT_PUT']);
				if ($params['CURLOPT_RETURNTRANSFER']) curl_setopt($ch,CURLOPT_RETURNTRANSFER,$params['CURLOPT_RETURNTRANSFER']);
				if ($params['CURLOPT_SSL_VERIFYPEER']) curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,$params['CURLOPT_SSL_VERIFYPEER']);
				if ($params['CURLOPT_UNRESTRICTED_AUTH']) curl_setopt($ch,CURLOPT_UNRESTRICTED_AUTH,$params['CURLOPT_UNRESTRICTED_AUTH']);
				if ($params['CURLOPT_VERBOSE']) curl_setopt($ch,CURLOPT_VERBOSE,$params['CURLOPT_VERBOSE']);
				if ($params['CURLOPT_CONNECTTIMEOUT']) curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$params['CURLOPT_CONNECTTIMEOUT']);
				if ($params['CURLOPT_LOW_SPEED_LIMIT']) curl_setopt($ch,CURLOPT_LOW_SPEED_LIMIT,$params['CURLOPT_LOW_SPEED_LIMIT']);
				if ($params['CURLOPT_LOW_SPEED_TIME']) curl_setopt($ch,CURLOPT_LOW_SPEED_TIME,$params['CURLOPT_LOW_SPEED_TIME']);
				if ($params['CURLOPT_MAXCONNECTS']) curl_setopt($ch,CURLOPT_MAXCONNECTS,$params['CURLOPT_MAXCONNECTS']);
				if ($params['CURLOPT_MAXREDIRS']) curl_setopt($ch,CURLOPT_MAXREDIRS,$params['CURLOPT_MAXREDIRS']);
				if ($params['CURLOPT_TIMEOUT']) curl_setopt($ch,CURLOPT_TIMEOUT,$params['CURLOPT_TIMEOUT']);
				if ($params['CURLOPT_COOKIEFILE']) curl_setopt($ch,CURLOPT_COOKIEFILE,$params['CURLOPT_COOKIEFILE']);
				if ($params['CURLOPT_COOKIEJAR']) curl_setopt($ch,CURLOPT_COOKIEJAR,$params['CURLOPT_COOKIEJAR']);
				if ($params['CURLOPT_REFERER']) curl_setopt($ch,CURLOPT_REFERER,$params['CURLOPT_REFERER']);
				if ($params['CURLOPT_POSTFIELDS']) curl_setopt($ch,CURLOPT_POSTFIELDS,$params['CURLOPT_POSTFIELDS']);
				if ($params['CURLOPT_CUSTOMREQUEST']) curl_setopt($ch,CURLOPT_CUSTOMREQUEST,$params['CURLOPT_CUSTOMREQUEST']);
				if ($params['CURLOPT_USERAGENT']) curl_setopt($ch,CURLOPT_USERAGENT,$params['CURLOPT_USERAGENT']);
				if ($params['CURLOPT_USERPWD']) curl_setopt($ch,CURLOPT_USERPWD,$params['CURLOPT_USERPWD']);
				if ($params['CURLOPT_MAXREDIRS']) curl_setopt($ch,CURLOPT_MAXREDIRS,$params['CURLOPT_MAXREDIRS']);
				if ($params['CURLOPT_MAXREDIRS']) curl_setopt($ch,CURLOPT_MAXREDIRS,$params['CURLOPT_MAXREDIRS']);
				if ($params['CURLOPT_MAXREDIRS']) curl_setopt($ch,CURLOPT_MAXREDIRS,$params['CURLOPT_MAXREDIRS']);
				if ($params['CURLOPT_MAXREDIRS']) curl_setopt($ch,CURLOPT_MAXREDIRS,$params['CURLOPT_MAXREDIRS']);
				if ($params['CURLOPT_MAXREDIRS']) curl_setopt($ch,CURLOPT_MAXREDIRS,$params['CURLOPT_MAXREDIRS']);
				if ($params['CURLOPT_MAXREDIRS']) curl_setopt($ch,CURLOPT_MAXREDIRS,$params['CURLOPT_MAXREDIRS']);
				
				// grab URL and pass it to the browser
				$content = curl_exec($ch);
				
				// check for errors
				$errnum=curl_errno($ch);
				
				// close CURL resource, and free up system resources
				curl_close($ch);
			
// 			}

			// convert to utf-8 (must come before any text manipulations)
			if ($encoding) {
				$content = $t3lib_cs->utf8_encode($content,$encoding);
			} else {
				//try to guess encoding from page
				// <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=windows-1255">
				preg_match('/charset\=(.+?)\"/i',$content,$matches2);
				
				// if we can identify encoding and it is not utf-8
				if ($encoding=$matches2[1]&&!(!strcmp('utf-8',strtolower($matches2[1])))) {
					//convert it to utf-8
					$content = $t3lib_cs->utf8_encode($content,$encoding);
				}
			}

			if ($cleanup) {
				// cut out body, wipe out comments and scripts
				if (preg_match("/\<body.+?\>(.+?)\<\/body/si",$content,$matches['try'])) {
					$content=$matches['try'][1];
					
					$patterns = array('/\<script.+?\<\/script/si','/\<\!\-\-.+?\-\-\>/si');
					$replacements = array('','');
					$content = preg_replace($patterns,$replacements,$content);
				}
			}

			if ($errnum) {
				// say hi
				$GLOBALS['c_bot_env']['do']['devlog']->devLog('--Fe-'.date('h').date('i').date('s').'-FAIL-err'.$errnum, 'hooker', '0', array(''));
				$content = '';
			} else {
				//write to temp file
				if (!t3lib_div::writeFile($filename,$content)) {
					$GLOBALS['c_bot_env']['do']['devlog']->devLog('--Fe-'.date('h').date('i').date('s').'-OK, no cache', 'hooker', '0', array(''));
				}
				
				// say hi
				$GLOBALS['c_bot_env']['do']['devlog']->devLog('--Fe-'.date('h').date('i').date('s').'-OK', 'hooker', '0', array(''));
			}
		} elseif (!$single) { //if exists and is not single record
			// say hi
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('--Cs-'.date('h').date('i').date('s').'-'.$url, 'hooker', '0', array(''));
			
			//$content = $rows[0]['content'];
			$content = t3lib_div::getURL($filename);
			
			// say hi
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('--Ce-'.date('h').date('i').date('s').'-OK', 'hooker', '0', array(''));
		} else { //if exists and is a single record
			$content = NULL;
			// say hi
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('--Se-'.date('h').date('i').date('s').'- single exist-exit '.$url, 'hooker', '0', array(''));
		}
		
		return $content;
	}
}




if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/hooker/sv1/class.tx_hooker_sv1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/hooker/sv1/class.tx_hooker_sv1.php']);
}

?>