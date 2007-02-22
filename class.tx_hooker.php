<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2004 Pavlenko Zorik (zorik@zorik.net)
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




class tx_hooker extends Daemon {
	var $extKey = 'hooker';	// The extension key.
	var $children = array();// array to hold child pids of forked processes
	var $childrenCRtime = array();// array to hold child pid creation times
	
	var $envMac = array();//machine environment

	/**
	 * 
	 * Initialize all parameters needed to run this class
	 * 
	 * @param	void
	 * 
	 * @return	void
	 */
	function __construct() {
		global $TYPO3_CONF_VARS;

		// initialize devlog
		if (!is_object($GLOBALS['c_bot_env']['do']['devlog']=t3lib_div::makeInstanceService('hooker_base','devlog'))) {
			print_r('dont use logging if you want, but the service must exist!!!');
			exit;
		}

		// check that we have agent service. should be available during the family init process
		if (!is_object($GLOBALS['c_bot_env']['do']['rw_agent'] = t3lib_div::makeInstanceService('hooker_base','rw_agent'))) {
			print_r('dont use rw_agent if you want, but the service must exist!!!');
			exit;
		}

		// initialize cruise_control
		if (!is_object($GLOBALS['c_bot_env']['do']['cruise_control']=t3lib_div::makeInstanceService('hooker_base','cruise_control'))) {
			print_r('dont use cruise_control if you want, but the service must exist!!!');
			exit;
		}

		// we set id of this instance of hooker, so that we could cache it's vars, but not use cached in new instance
		$this->envMac['hooker_id'] = uniqid();
		//read startup variables passed through cli
		reset($GLOBALS['argv']);
		while(list(,$cli_paramsV)=each($GLOBALS['argv'])) {
			list($cli_paramsVK,$cli_paramsVV) = t3lib_div::trimExplode('=',$cli_paramsV);
			// we need only parameters passed as key=value pair
			// and they can override defaults
			if ($cli_paramsVV) {
				$this->envMac[strtolower($cli_paramsVK)] = $cli_paramsVV;
			}
		}
		$GLOBALS['c_bot_env']['do']['rw_agent']->init_Hooker($this->envMac);

		// check that families exist
		if (!is_array($TYPO3_CONF_VARS['EXTCONF']['hooker']['families'])) {
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('no families found! Exiting...', $this->extKey, '3', array(''));
			return 0;
		}

		// if everything went ok till this point... should we start?
		$GLOBALS['c_bot_env']['do']['devlog']->devLog('HOOKER START', $this->extKey, '-1', array(''));
	}


	/**
	* Starts daemon
	*
	* @access public
	* @since 1.0
	* @return bool
	*/
	function start() {
		// if 'nodaemon' is specified - no forking, no daemon - single process
		if ($this->envMac['nodaemon']) {
			$this->_doTask();
		} else {
			$this->_logMessage('Starting daemon');
			
			if (!$this->_daemonize())
			{
				$this->_logMessage('Could not start daemon', DLOG_ERROR);
			
				return false;
			}
			
			
			$this->_logMessage('Running...');
			
			$this->_isRunning = true;
			
			
			while ($this->_isRunning)
			{
				$this->_doTask();
		
				// in a stand-by mode we should run it once a minute..
				$GLOBALS['c_bot_env']['do']['devlog']->devLog('sleeping...', $this->extKey, '-1', array(''));
//TODO:
// how long should we wait?
				sleep(3);
			}
		}

		return true;
	}


	/**
	 * useless. should be removed
	 *
	 * 
	 * @return	void
	 */
	function TestDaemon() {
		parent::Daemon();
		
		$fp = fopen('/tmp/daemon.log', 'a');
		fclose($fp);
		
		chmod('/tmp/daemon.log', 0777);
	}


	/**
	 * write to daemon logfile
	 *
	 * @param	string		message
	 * @param	string		status
	 * 
	 * @return	void
	 */
	function _logMessage($msg, $status = DLOG_NOTICE) {
		if ($status & DLOG_TO_CONSOLE)
		{
			print $msg."\n";
		}
		
		$fp = fopen('/tmp/daemon.log', 'a');
		fwrite($fp, date("Y/m/d H:i:s ").$msg."\n");
		fclose($fp);
	}


	/**
	* Signals handler
	*
	* @access public
	* @since 1.0
	* @return void
	*/
	function sigHandler($sigNo) {
		switch ($sigNo) {
			case SIGTERM:   // Shutdown
				$this->_logMessage('Shutdown signal');

				// kill all children
				while(list($pid,)=each($this->childrenCRtime)) {
					posix_kill($pid, SIGINT);
				}

				exit();
			break;
			case SIGCHLD:   // Halt
				$this->_logMessage('Halt signal');
			
				while (($pid = pcntl_wait( $status, WNOHANG)) > 0) {
					unset($this->childrenCRtime[$pid]);
			
					if ( !pcntl_wifexited($status) ) {
						$this->_logMessage("Exited terminated process $pid\n");
					} else {
						$this->_logMessage("Exited completed process $pid\n");
					}
				}
			break;
			case SIGALRM:   // Alarm
				// TODO: not sure if it's working...
				$this->alarmChildren();
			break;
		}
	}


	/**
	 * executed at pcntl_alarm
	 * check creation times of currently running processes.
	 * Kill processes which are executing too long
	 *
	 * 
	 * @return	void
	 */
	function alarmChildren() {
		foreach ($this->childrenCRtime as $childrenCRtimeK=>$childrenCRtimeV) {
			// if this child takes too long to execute - we have to kill it
			if ((time()-$childrenCRtimeV)>30) {
				posix_kill($childrenCRtimeK, SIGINT);
				unset($this->childrenCRtime[$childrenCRtimeK]);
				$this->_logMessage("Killed hung process $childrenCRtimeK\n");
			}
		}
	}


	/**
	 * This is where everything starts
	 * This method is executed by daemon every minute (at stand-by mode)
	 *
	 * @param	string		message
	 * @param	string		status
	 * 
	 * @return	[type]		nothing
	 */
	function _doTask() {
		$this->startHooker();
	}

	/**
	 * startHooker - the entrance script
	 *
	 * 
	 * @return	void
	 */
	function startHooker() {
		// at this stage, if there are any agents with status 1 - change statuses of agents from 1 to 2
		$GLOBALS['c_bot_env']['do']['rw_agent']->agent_initStatus();

		$this->startFamilies();

		// give it a second to release database (?)
		// i dont know.. if it continues too fast - it does not see agents created in previous step
// 		sleep(1);

		// we check for bots existence here and in fork process
		// because fork's "while" ends when no more bots are found, 
		// but while children run - new bots may be created. so we just make sure
		while ($filename = $GLOBALS['c_bot_env']['do']['rw_agent']->getBotFilename($this->envMac)) {
			$this->forkChildren();

			// give it a second to release database (?)
			// i dont know.. if it continues too fast - it does not see agents created in previous step
// 			sleep(1);
		}

		// now we can clean up a little
		$GLOBALS['c_bot_env']['do']['rw_agent']->cleanExpiredDB();

		//parent may not exit while children are still running - because we need to track signals from them
		while (count($this->childrenCRtime)) {
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('SLEEEPING AFTER FORKS'.$pid, $this->extKey, '2', array(''));
			sleep(2);
			$this->alarmChildren();
		}

		$GLOBALS['c_bot_env']['do']['devlog']->devLog('HOOKER END', $this->extKey, '-1', array(''));
	}


	/**
	 * 
	 * 
	 * @return	void
	 */
	function startFamilies() {
		global $TYPO3_CONF_VARS;

		$conf = $this->envMac;

		// this is a debug mode
		if ($this->envMac['debug']) {
			//init debug family
			$conf['family'] = $conf['debug']['family'];
			$this->startFamily($conf);
		} else {
			// loop through families
			// family must consist of first step method (called "start")
			reset($TYPO3_CONF_VARS['EXTCONF']['hooker']['families']);
			while(list($familyName,)=each($TYPO3_CONF_VARS['EXTCONF']['hooker']['families'])) {
				//which means - this family is enabled; bots may be unhidden while families disabled - we do not want to process these bots
				$conf['family'] = $familyName;
				$this->startFamily($conf);
			}
		}
	}


	/**
	 * 
	 * 
	 * @return	void
	 */
	function startFamily($conf) {
		if (!$GLOBALS['c_bot_env']['do']['rw_agent']->init_Family($conf)) {
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('cannot init family '.$conf['family'].'!', 'hooker', '2');
			return;
		}

		// start building query
		$qwhere = 'family="'.$conf['family'].'"';
		$qwhere .= ' AND (starttime<'.time().' OR starttime=0) AND (endtime>'.time().' OR endtime=0)';
		$qwhere .= ' AND NOT hidden';
 
		//if pid was defined in command line - use it. and only it!
		// otherwise - check the db for bot pids to be processed
		if (isset($conf['pid'])) {
			$qwhere .= ' AND pid='.$conf['pid'];
		} else if (isset($conf['botuid'])) {
			$qwhere .= ' AND uid='.$conf['botuid'];
		} else {
			$qwhere .= ' AND ('.time().'-last_run)>(run_interval)';

		}

		$ret = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows (
			'uid,pid',
			'tx_hooker_bot',
			$qwhere,
			'',
			'',
			''
		);

		reset($ret);
		while(list(,$retV)=each($ret)) {
			$conf['botuid'] = $retV['uid'];
			$conf['pid'] = $retV['pid'];
			$this->botInit($conf);
		}
	}


	/**
	 * This function should find bots in the database
	 * and create agent for each of them with method "start" in their family
	 * 
	 * 
	 * @return	void
	 */
	function botInit($conf) {
		if (!$GLOBALS['c_bot_env']['do']['rw_agent']->init_Bot($conf)) {
			$GLOBALS['c_bot_env']['do']['devlog']->devLog('cannot init bot '.$conf['botuid'].'!', 'hooker', '2');
			return;
		}

		// update "last run" (has to be before execution to prevent concurrent run)
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_hooker_bot','uid='.$conf['botuid'],array('last_run'=>time()));

		// for each bot pid we find - we create a new agent
		$conf['state2'] = 'start';
		$GLOBALS['c_bot_env']['do']['rw_agent']->envWrite_agent($conf,'blah');
	}
	

	/**
	 * Forks children
	 * A child for each agent
	 * 
	 * 
	 * @return	void
	 */
	function forkChildren() {
		//forking
		//while we have a bot conf file in the pool
		//while we are parent
		$pid = 1;
		while (
			($filename = $GLOBALS['c_bot_env']['do']['rw_agent']->getBotFilename($this->envMac))
			&&$pid
		){
			//here we should check for system resources usage and wait till system is not overloaded
			$GLOBALS['c_bot_env']['do']['cruise_control']->proc();

			// no forking in nodaemon mode
			if ($this->envMac['nodaemon']||isset($this->envMac['debug'])) {
				$this->procChild($filename);
			} else {
				//avoid running too many processes concurrently - wait till someone ends
				while (count($this->childrenCRtime) > $this->envMac['max_current']) {
					sleep(1);
					$this->alarmChildren();
				}
	
				// we must make sure we fork processes for parent only - if pid present its parent
				if ($pid = pcntl_fork()) {
					// we are the parent
					$GLOBALS['c_bot_env']['do']['devlog']->devLog('Forked child '.$pid, $this->extKey, '-1', array(''));
	
					$this->childrenCRtime[$pid] = time();
				} else if ($pid == -1) {
					//could not fork. continue alone
					$GLOBALS['c_bot_env']['do']['devlog']->devLog('Cannot fork! '.$pid, $this->extKey, '2', array(''));
	
					$this->procChild($filename);
				} else {
					// we are the child
					$this->procChild($filename);
					exit(0);
				}
			}
		}
	}


	/**
	 * process child
	 * Read its environment and execute relevant family method
	 * 
	 * Get results, and
	 * 1. if result is a FALSE - reschedule agent execution (bot experienced trouble)
	 * 2. if result is a blank string - bot executed successfully, but returned negative result
	 * 3. if result is numeric array - for each element create new agent (change state)
	 * 
	 * @param	array		array('filename, state1, state2')
	 * 
	 * @return	array/false	array on success, FALSE on failure
	 */
	function procChild($filename) {
		global $TYPO3_CONF_VARS;

		$reward = FALSE;//initially reward must be false, so that in case of defected agent we perform correct action

		// init agent environment
		$updateFields['status'] = 1;
		$updWhere = 'filename="'.$filename['filename'].'" AND state1="'.$filename['state1'].'" AND state2="'.$filename['state2'].'"';
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_hooker_agent',$updWhere,$updateFields);

		$filename = t3lib_div::array_merge_recursive_overrule($filename,$this->envMac,0,false);
		if (!$GLOBALS['c_bot_env']['do']['rw_agent']->init_Transition($filename)) {
			$GLOBALS['c_bot_env']['do']['rw_agent']->envDelete_agent($filename);
			return FALSE;
		}
		if (!$GLOBALS['c_bot_env']['do']['rw_agent']->init_State($filename)) {
			$GLOBALS['c_bot_env']['do']['rw_agent']->envDelete_agent($filename);
			return FALSE;
		}
		if (!$GLOBALS['c_bot_env']['do']['rw_agent']->init_Bot($filename)) {
			$GLOBALS['c_bot_env']['do']['rw_agent']->envDelete_agent($filename);
			return FALSE;
		}
		if (!$GLOBALS['c_bot_env']['do']['rw_agent']->init_Family($filename)) {
			$GLOBALS['c_bot_env']['do']['rw_agent']->envDelete_agent($filename);
			return FALSE;
		}

		$symbolArr = $TYPO3_CONF_VARS['EXTCONF']['hooker']['families'][$filename['family']]['states'][$filename['state1']][$filename['state2']];

		// for each symbol (method)
		$refPath = 'EXT:'.$filename['family'].'/tx_'.$filename['family'].'.php';
		$refPath = $refPath.':&tx_'.$filename['family'];
		$ref = &t3lib_div::getUserObj($refPath);//make instance of family class
		//set environment in ref
		$ref->env = $filename;

		// for each method in transition
		reset($symbolArr);
		while(list($symbolName,$symbolMethod)=each($symbolArr)) {
			// agent processing
			// there's next state to pass reward (content) to
			$reward = t3lib_div::callUserFunction($symbolMethod,$filename,$ref,$checkPrefix='tx_',$silent=0);
		}

		// if this is the step we want to debug (and there is example content) - die
		if (
			isset($filename['debug'])
			&&($reward||!strcmp($filename['state2'],'end'))
			&&(!strcmp($filename['state1'],$filename['debug']['state1']))
			&&(!strcmp($filename['state2'],$filename['debug']['state2']))
		) {
			if ($reward) {
				print_r($reward);
			} else {
				print_r('end state. no reward returned');
			}
			die(chr(10).'debug "'.$filename['state1'].'/'.$filename['state2'].'" end');
		}

		if (!strcmp($filename['state2'],strtolower('end'))) {
			$reward = '';// so that no further agents will be created
		}

		// here we must check the returned value
		// possible values:
		// numeric array - provided positive reward; create new agent for each nonempty row; done current agent
		// blank string - provided negative reward; done current agent
		// FALSE - failed; reschedule agent execution
		if (is_array($reward)) {
			// numeric array

			$rewardExists = 0;// if we had something returned - this will become 1
			// create new agents
			reset($reward);
			while (list($rewardK,$content)=each($reward)) {
				// if we have some content
				if ($content) {
					$GLOBALS['c_bot_env']['do']['rw_agent']->envWrite_agent($filename,$content);
					// flag that we had something returned
					$rewardExists = 1;
				}
			}
			// getting a blank string in debug mode may create endless loop
			if (
				!$rewardExists
				&&isset($filename['debug'])
			) {
				$GLOBALS['c_bot_env']['do']['devlog']->devLog('debug "'.$filename['state1'].'/'.$filename['state2'].'" returned empty string',2,2);
				// kill agent completely...
				$GLOBALS['c_bot_env']['do']['rw_agent']->envDelete_agent($filename);
			} else {
				// kill agent - move to done directory...
				$GLOBALS['c_bot_env']['do']['rw_agent']->envKill_agent($filename);
			}
		} else if ($reward===FALSE) {
			// FALSE
			// update retries
			$GLOBALS['c_bot_env']['do']['rw_agent']->envWrite_agent_timeout($filename);
		} else if (strlen($reward)) {
			// its is not an array, nor false - content in one string?
			$GLOBALS['c_bot_env']['do']['rw_agent']->envWrite_agent($filename,$reward);
		} else {
			// blank string
			// getting a blank string in debug mode may create endless loop
			if (isset($filename['debug'])) {
				$GLOBALS['c_bot_env']['do']['devlog']->devLog('debug "'.$filename['state1'].'/'.$filename['state2'].'" returned empty string',2,2);
				// kill agent completely...
				$GLOBALS['c_bot_env']['do']['rw_agent']->envDelete_agent($filename);
			} else {
				// kill agent - move to done directory...
				$GLOBALS['c_bot_env']['do']['rw_agent']->envKill_agent($filename);
			}
		}

//TODO:
// a debug fnction needs a "reconstruct" command
// which, if specified, would reconstruct (delete and refetch agents) the cache of all stages
// 		return $return;
	}
}





 
?>