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
 * Service 'Read/Write Agent' for the 'hooker' extension.
 *
 * @author	Pavlenko Zorik <zorik@zorik.net>
 */

/*
Agents are written in a database table "tx_hooker_agent".
Agents have unique name - filename, which is md5 hash of the agent's content
	each content chunk is given unique filename and written here
	(so that duplicate content is eliminated)
Agents have state1
	which is a state from which transition starts
Agents have state2
	at which transition ends
Agents have their starttime set
	with starttime we can set timeout for failed attempts
"crdate" - shows a date when agent was created
	useful when deleting expired agents
Agents have "status" field which represent its status - 
	todo(2), done(0) or doing(1)
"importance" field should show its importance from 0-100 scale
	agents with status "todo" should be processed by importance descending
"attempts"
	amount of failed attempts to complete agent

*/


require_once(PATH_t3lib.'class.t3lib_svbase.php');

class tx_hooker_sv2 extends t3lib_svbase {
	var $prefixId = 'tx_hooker_sv2';		// Same as class name
	var $scriptRelPath = 'sv2/class.tx_hooker_sv2.php';	// Path to this script relative to the extension dir.
	var $extKey = 'hooker';	// The extension key.

	var $PagesTSconfig = array();
	
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
	 * This function would replace getBotFilename function
	 * if we are in debug mode
	 * On the command line a transition to be debugged will be provided
	 * It will be checked if this transition has agents already
	 * in case it does - it will return that agent
	 * in case it doesnt - it will search previuos transition agent
	 * and so on up to the root
	 * 
	 * @param	Array		
	 * 
	 * @return	Array		filename, state1, state2
	 */
	function getBotFilename_debugMode(&$confG) {
		global $TYPO3_CONF_VARS;

		// get family, state1 and state2 to be dubugged
		$d_family = $confG['debug']['family'];
		$d_state1 = $confG['debug']['state1'];
		$d_state2 = $confG['debug']['state2'];

		// verify the transition is described in $TYPO3_CONF_VARS
		if (!isset($TYPO3_CONF_VARS['EXTCONF']['hooker']['families'][$d_family]['states'][$d_state1][$d_state2])) {
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('DEBUG: transition '.$d_family.':'.$d_state1.':'.$d_state2.' is not defined in ext_localconf!', 'hooker', '2', array(''));
			return FALSE;
		}

		// pull from db (any status)
		$qwhere[] = 'status>0';//try to get only todo
		$qwhere[] = 'state1="'.$d_state1.'"';
		$qwhere[] = 'state2="'.$d_state2.'"';
		$qwhere = implode(' AND ',$qwhere);
		$ret = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows (
			'filename, state1, state2',
			'tx_hooker_agent',
			$qwhere,
			'',
			'attempts ASC,importance DESC',
			'0,1'
		);

		// did not work with todo? try without
		if (!strlen($ret[0]['filename'])) {
			$qwhere = array();
			$qwhere[] = 'state1="'.$d_state1.'"';
			$qwhere[] = 'state2="'.$d_state2.'"';
			$qwhere = implode(' AND ',$qwhere);
			$ret = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows (
				'filename, state1, state2',
				'tx_hooker_agent',
				$qwhere,
				'',
				'attempts ASC,importance DESC',
				'0,1'
			);
		}

		// target transition agent does not exist
		// we should not run into endless loop because botInit() is done and 1 root agent was created for sure
		while (!strlen($ret[0]['filename'])) {
			// find previous transition and search for it's agent
			reset($TYPO3_CONF_VARS['EXTCONF']['hooker']['families'][$d_family]['states']);
			while(list($state1,$state2)=each($TYPO3_CONF_VARS['EXTCONF']['hooker']['families'][$d_family]['states'])) {
				if (isset($state2[$d_state1])) {// found one state up..
					//redefine states
					$d_state2 = $d_state1;
					$d_state1 = $state1;
					
					$qwhere = array();
					$qwhere[] = 'state1="'.$d_state1.'"';
					$qwhere[] = 'state2="'.$d_state2.'"';
					$qwhere = implode(' AND ',$qwhere);
					$ret = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows (
						'filename, state1, state2',
						'tx_hooker_agent',
						$qwhere,
						'',
						'attempts ASC,importance DESC',
						'0,1'
					);
					break;
				}
			}
		}

		return $ret;
	}


	/**
	 * 
	 * 
	 * 
	 * @param	Array		
	 * 
	 * @return	Array		filename, state1, state2
	 */
	function getBotFilename(&$confG) {
		$filename = FALSE;

		// if we are in debug mode
		if (isset($confG['debug'])) {
			$ret = $this->getBotFilename_debugMode($confG);
		} else {
			// pull from db
			$qwhere[] = 'status=2';//only todo
			$qwhere[] = '(starttime<'.time().' OR starttime=0)';
			$qwhere = implode(' AND ',$qwhere);
			$ret = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows (
				'filename, state1, state2, pid, botuid',
				'tx_hooker_agent',
				$qwhere,
				'',
				'attempts ASC,importance DESC',
				'0,1'
			);
		}

		if (strlen($ret[0]['filename'])) {
			$filename = $ret[0];
		} else {
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('no agents to process','hooker', '1');
		}

		return $filename;
	}

	
	/**
	* Write agent's content to file
	* a content is represented by "state"
	* a state has one content file regardless of how many next states it may have 
	* 
	* @param	String		name of the function to be executed on next level
	* @param	Array		content to work on on the next level
	* 
	* @return	Array		Numeric array of results
	*/

/*
we must distinguish between "content" and "transition"
content - is md5 of content
transision - is agent - is state1+state2+md5content
this is writing "agents" and not single "agent" 
*/
	function envWrite_agent(&$confG,$botcont) {
		global $TYPO3_CONF_VARS;

		// make unique name for content
		//what filename should we use?
		$writeFilename = md5(serialize($botcont));
		//serialize data
		$writeCont = serialize($botcont);
		

		// get all "state2" for current state 
		$current_state = $TYPO3_CONF_VARS['EXTCONF']['hooker']['families'][$confG['family']]['states'][$confG['state2']];
		// for each "state2" in current state we create new agent in db
		reset($current_state);
		while(list($next_state,$symbolArr)=each($current_state)) {
			$this->envWrite_agent_DB($confG,$next_state,$writeFilename);
		}

		// as content is the same for all next states - we write it only once
		if (!file_exists($confG['dir_tmp_sub_todo'].$writeFilename)) {
			if (!t3lib_div::writeFile($confG['dir_tmp_sub_todo'].$writeFilename,$writeCont)) {
				$GLOBALS['c_bot_env']['do']['devlog']->devLog('cannot create content file '.$writeFilename.' in filesystem!', 'hooker', '2', array(''));
				return;
			} else {
				$GLOBALS['c_bot_env']['do']['devlog']->devLog('content file '.$writeFilename.' successfully written!', 'hooker', '-1', array(''));
			}
		}
	}


	/**
	* Write agent to database
	* agent - is a transition between two states
	* so that a single state may transform to many states (therefore use many transitions - agents)
	* each transition though uses the same content from state1 ($cur_state) and transforms it to new content ($next_state)
	* 
	* @param	String		name of the function to be executed on next level
	* @param	Array		content to work on on the next level
	* 
	* @return	Bool		1 if we write a new agent; 0 if we do not write
	*/
	function envWrite_agent_DB(&$confG,$next_state,$writeFilename) {
		$result = 0;

		// make sure we do not create a duplicate entry
		// pid + current state + next state + content
		$qwhere = 'pid='.$confG['pid'];
		$qwhere .= ' AND botuid="'.$confG['botuid'].'"';
		$qwhere .= ' AND state1="'.$confG['state2'].'"';
		$qwhere .= ' AND state2="'.$next_state.'"';
		$qwhere .= ' AND filename="'.$writeFilename.'"';
		$qwhere .= ' AND (endtime>'.time().' OR status>0)';

		$ret = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows (
			'filename',
			'tx_hooker_agent',
			$qwhere,
			'',
			'',
			'0,1'
		);

		// if the agent does not exist in db
		if (!strlen($ret[0]['filename'])) {
			// try to create the agent in db
			$insertq['pid'] = $confG['pid'];//same pid as parent
			$insertq['botuid'] = $confG['botuid'];
			$insertq['filename'] = $writeFilename;
			$insertq['starttime'] = time();
			// agents which have their endtime passed and status 0 should be deleted
			// lifetime of an agent should be configured in localconf
			if (isset($confG['lifetime'])) {
				$insertq['endtime'] = time() + $confG['lifetime'];
			} else {
				$insertq['endtime'] = time()+(60*60*24);//default lifetime is 1 day
			}
			$insertq['crdate'] = time();// 1 days; 24 hoursfile:///var/www/html/zoriksite/typo3conf/ext/hooker/sv2/class.tx_hooker_sv2.php; 60 mins; 60secs
			$insertq['status'] = 2;//todo
			$insertq['importance'] = ($confG['importance'])?$confG['importance']:50;//50 - default
			$insertq['attempts'] = 0;//no attempts yet
			$insertq['state1'] = $confG['state2'];//todo
			$insertq['state2'] = $next_state;//todo

			// then insert
			$dbres = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_hooker_agent', $insertq);

			if (!$dbres) {
				$GLOBALS['c_bot_env']['do']['devlog']->devLog('cannot create transition '.$confG['state2'].' - '.$next_state.' for '.$writeFilename.'!', 'hooker', '2', array(''));
			} else {
				$GLOBALS['c_bot_env']['do']['devlog']->devLog('transition '.$confG['state2'].' - '.$next_state.' for '.$writeFilename.' successfully written!', 'hooker', '-1', array(''));
			}

			$result = 1;
		}

		return $result;
	}


	/**
	* The last function in agent execution
	* update transition status to "done"
	* 
	* @param	String		name of the function to be executed on next level
	* @param	Array		content to work on on the next level
	* 
	* @return	Array		Numeric array of results
	*/
	function envKill_agent(&$filename) {
		// if lifetime=0 - delete the agent
		if (
			isset($filename['lifetime'])
			&&!$filename['lifetime']
		) {
			$this->envDelete_agent($filename);
		} else {
			// move parent bot to done directory
			$updateFields = array('status'=>0);
			$updWhere = 'filename="'.$filename['filename'].'" AND state1="'.$filename['state1'].'" AND state2="'.$filename['state2'].'"';
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_hooker_agent',$updWhere,$updateFields);
		}
	}


	/**
	* 
	* completely deletes the agent (done during debugging for handling of blank strings)
	* 
	* @param	String		name of the function to be executed on next level
	* @param	Array		content to work on on the next level
	* 
	* @return	Array		Numeric array of results
	*/
	function envDelete_agent(&$filename) {
		// delete parent bot
		$updWhere = 'filename="'.$filename['filename'].'" AND state1="'.$filename['state1'].'" AND state2="'.$filename['state2'].'"';
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_hooker_agent',$updWhere);
		if (file_exists($confG['dir_tmp_sub_todo'].$filename['filename'])) {
			unlink($filename['dir_tmp_sub_todo'].$filename['filename']);
		}
	}


	function envWrite_agent_timeout(&$filename) {
		// get current retries
		$updWhere = 'filename="'.$filename['filename'].'" AND state1="'.$filename['state1'].'" AND state2="'.$filename['state2'].'"';
		$ret = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows (
			'attempts',
			'tx_hooker_agent',
			$updWhere,
			'',
			'',
			'0,1'
		);

		// add retries and save
		$updateFields['status'] = 2;//change to todo
		$updateFields['starttime'] = time()+60*2;//postpone 2 minutes
		// update endtime too
		if (isset($filename['lifetime'])) {
			$updateFields['endtime'] = time() + $filename['lifetime'];
		} else {
			$updateFields['endtime'] = time()+(60*60*24);//default lifetime is 1 day
		}
		$updateFields['attempts'] = $ret[0]['attempts']+1;//log attempts

		if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_hooker_agent',$updWhere,$updateFields)) {
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('task file '.$filename['filename'].' queued for retry!', 'hooker', '0', array(''));
		} else {
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('task file '.$filename['filename'].' IS NOT queued for retry!', 'hooker', '0', array(''));
		}
	}


	/**
	* 
	* clean expired agents from database
	* 
	* 
	* @return	none
	*/
	function cleanExpiredDB() {
//TODO: files should be cleaned also, but not this way
// each file in filesystem should be checked for being referenced from db, and deleted if not

//TODO: a consideration - 
// if we delete agents in db - we could not track later what and how was executed
		// where endtime<now and status=0
		$updWhere = 'endtime<'.time().' AND status=0';
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_hooker_agent',$updWhere);
	}


	/**
	* Check for agents with statuses "1" and change them to "2"
	* This is done when hooker is initialized to catch up agents which did not finish their work and were interrupted
	* 
	* 
	* @return	
	*/
	function agent_initStatus() {
		$qwhere = 'status=1';

		$ret = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows (
			'uid',
			'tx_hooker_agent',
			$qwhere,
			'',
			'',
			'0,100'
		);

		$insertq['status'] = 2;

		if (isset($ret[0])) {
			reset($ret);
			while(list($retK,$retV)=each($ret)) {
				$cnt[] = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_hooker_agent','uid="'.$retV['uid'].'"',$insertq);
			}
		}
	}


/*
	ENVIRONMENT SETTINGS

	The below section would define all methods for managing the environments

Concept:
Environment is made of:
	1. Machine - related to this machine
	2. Strategic - centrally located - provides communication means to agents
	3. Agent - specific for single agent
Environment should be initialized in each stage, i.e.:
	1. Hooker initialization
	2. Family init
	3. Bot init
	4. State (a. content)
	5. Transition (a. db)
Environment sources:
	1. DB
	2. Extconf
	3. db bot record
	4. db agent record
	*. agent content file
	5. cli passed vars
	6. TS

$GLOBALS['agentenv'] cumulative array, which will be passed to each method

After reading configurations for single level - the config must be cached for each level separately.

Machine variables - overrides all other variables. Specified in a file(?) or command line
Strategic - cumulative array of all levels, overriding one another, mostly specified in TS, related to this specific agent
Agent - content and other stuff relevant only to this agent

The cached variables may be general(all identifiers) or local(for specific identifier)

IDEA:
new table should be created for gathering knowledge base of agents
each agent could insert variables there, so that other agents could use it
the table should consist: pid, level it is used on (bot, family, state, trans), variable name, content
This is done to replace passing variables with content (as it is done currently)

IDEA:
it should be possible to define actions for state entry and exit
During stage init it should be checked that there's all necessary variables exist
During stage end it should be checked that there's all necessary variables for output


TYPOSCRIPT:
tx_hooker.
	trans
	state
	bot
	family - tsconfig is taken only from page #1



*/
	/**
	* Hooker settings - are machine settings and override all others
	* 
	* 
	* @return	bool	whether to continue or not
	*/
	function init_Hooker(&$confG) {
		$hash = md5('hooker',$confG['hooker_id']);
		$cachedContent = t3lib_BEfunc::getHash($hash,0);

		//check if we have this bot' settings in cache
		if (isset($cachedContent))    {
			$conf = unserialize($cachedContent);
		} else {
			$conf = array();

			//defaults
			$conf['dir_tmp_root'] = PATH_site.'typo3temp/zor_bot/';
			$conf['dir_tmp_sub_todo'] = PATH_site.'typo3temp/zor_bot/todo/';
			$conf['dir_tmp_sub_cache'] = PATH_site.'typo3temp/zor_bot/cache/';
			$conf['max_current'] = 5;
	

			// get family, state1 and state2 to be dubugged
			if (isset($conf['debug'])) {
				list($d_family,$d_state1,$d_state2) = t3lib_div::trimExplode(':',$conf['debug']);
				$conf['debug'] = array();
				$conf['debug']['family'] = $d_family;
				$conf['debug']['state1'] = $d_state1;
				$conf['debug']['state2'] = $d_state2;
			}

			t3lib_BEfunc::storeHash($hash,serialize($conf),'hooker');
		}

		// merge with existing conf
		$confG = t3lib_div::array_merge_recursive_overrule($confG,$conf,0,true);

		return 1;
	}


	/**
	* Family name should have been defined during bot init
	* 
	* 
	* @return	bool	whether to continue or not
	*/
	function init_Family(&$confG) {
		$hash = md5('family'.$confG['family']);
		$cachedContent = t3lib_BEfunc::getHash($hash,0);

		//check if we have this bot' settings in cache
		if (isset($cachedContent))    {
			$conf = unserialize($cachedContent);
		} else {
			$tmp = 1;
			$conf = $this->getPagesTSconfig($tmp,'family',$confG['family']);

			t3lib_BEfunc::storeHash($hash,serialize($conf),'family-'.$confG['family']);
		}

		// merge with existing conf
		$confG = t3lib_div::array_merge_recursive_overrule($conf,$confG,0,true);

		return 1;
	}


	/**
	* Bot UID should have been taken from transition DB record
	* 
	* 
	* @return	bool	whether to continue or not
	*/
	function init_Bot(&$confG) {
		$hash = md5('bot'.$confG['botuid']);
		$cachedContent = t3lib_BEfunc::getHash($hash,0);

		//check if we have this bot' settings in cache
		if (isset($cachedContent))    {
			$conf = unserialize($cachedContent);
		} else {
			// init strategic environment
			$bot = t3lib_BEfunc::getRecordRaw('tx_hooker_bot','uid="'.$confG['botuid'].'"');
			if (!$bot||$bot['hidden']) {
				return 0;
			}

			$conf = $this->getPagesTSconfig($confG['pid'],'bot',$confG['botuid']);

			$conf['family'] = $bot['family'];
			$conf['pid'] = $bot['pid'];

			t3lib_BEfunc::storeHash($hash,serialize($conf),'bot-'.$confG['botuid']);
		}

		// merge with existing conf
		$confG = t3lib_div::array_merge_recursive_overrule($conf,$confG,0,true);

		// check that we have everything we need
		if (
			!$confG['family']
			||!$confG['pid']
		) {
			return 0;
		}

		return 1;
	}


	/**
	* 
	* 
	* 
	* @return	bool	whether to continue or not
	*/
	function init_State(&$confG) {
		// everything is unique in a given pid
		$hash = md5('state'.$confG['state1'].$confG['pid']);
		$cachedContent = t3lib_BEfunc::getHash($hash,0);

		//check if we have this bot' settings in cache
		if (isset($cachedContent))    {
			$conf = unserialize($cachedContent);
		} else {
			$conf = $this->getPagesTSconfig($confG['pid'],'state',$confG['state1']);

			t3lib_BEfunc::storeHash($hash,serialize($conf),'state-'.$confG['state1'].'-'.$confG['pid']);
		}

		// if we do have the filename
		// get its contents
		//we unserialize the file contents to become an array again
		$conf['botcont'] = unserialize(t3lib_div::getURL($confG['dir_tmp_sub_todo'].$confG['filename']));

		// merge with existing conf
		$confG = t3lib_div::array_merge_recursive_overrule($conf,$confG,0,true);

		// check that we have everything we need
		if (
			!$confG['botcont']
		) {
			return 0;
		}

		return 1;
	}


	/**
	* 
	* 
	* 
	* @return	bool	whether to continue or not
	*/
	function init_Transition(&$confG) {
		// everything is unique in a given pid
		$hash = md5('transition'.$confG['state1'].'-'.$confG['state2'].$confG['pid']);
		$cachedContent = t3lib_BEfunc::getHash($hash,0);

		//check if we have this bot' settings in cache
		if (isset($cachedContent))    {
			$conf = unserialize($cachedContent);
		} else {
			$conf = $this->getPagesTSconfig($confG['pid'],'trans',$confG['state1'].'-'.$confG['state2']);

			t3lib_BEfunc::storeHash($hash,serialize($conf),'state-'.$confG['state1'].'-'.$confG['state2'].'-'.$confG['pid']);
		}

		// merge with existing conf
		$confG = t3lib_div::array_merge_recursive_overrule($conf,$confG,0,true);

		// check that we have everything we need
		if (
			!$confG['filename']
			||!$confG['state1']
			||!$confG['state2']
			||!$confG['pid']
			||!$confG['botuid']
		) {
			return 0;
		}

		return 1;
	}


	/**
	* Fetch TSconfig for a page id
	* (cached)
	* 
	* @return	array	TS
	*/
	function getPagesTSconfig(&$PID,$stage='',$ident='') {
		$TS = array();

		//check if we have settings locally
		if (isset($this->PagesTSconfig[$PID]))    {
			$TS = $this->PagesTSconfig[$PID];
		} else {
			// try to get cached version
			$hash = md5('TS'.$PID);
			$cachedContent = t3lib_BEfunc::getHash($hash,0);//this cache should never expire

			//check if we have settings in cache
			if (isset($cachedContent))    {
				$TS = unserialize($cachedContent);
			} else {
				// get uid of unique branch root page
				$TSt = t3lib_BEfunc::getPagesTSconfig($PID);
				// we need only hooker settings
				if (is_array($TSt['tx_hooker.'])) {
					$TS = $this->hlp_RemoveDots($TSt['tx_hooker.']);
				}
				t3lib_BEfunc::storeHash($hash,serialize($TS),'TS'.$PID);
			}

			$this->PagesTSconfig[$PID] = $TS;
		}

		if (isset($stage)) {
			$TS = $TS[$stage];
		}
		if (isset($ident)) {
			if (!isset($TS['all'])) {
				$TS['all'] = array();
			}
			if (!isset($TS[$ident])) {
				$TS[$ident] = array();
			}
			$TS = t3lib_div::array_merge_recursive_overrule($TS['all'],$TS[$ident]);
		}

		return $TS;
	}


	/**
	* 
	* @param	array	array
	* 
	* @return	array	dots in keys removed
	*/
	function hlp_RemoveDots($arr) {
		foreach ($arr as $arrK=>$arrV) {
			$arrKm = ereg_replace('\.$','',$arrK);
			unset($arr[$arrK]);
			if (is_array($arrV)) {
				$arrV = $this->hlp_RemoveDots($arrV);
			}
			$arr[$arrKm] = $arrV;
		}
		return $arr;
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/hooker/sv2/class.tx_hooker_sv2.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/hooker/sv2/class.tx_hooker_sv2.php']);
}

?>