#
# Table structure for table 'tx_hooker_bot'
#
CREATE TABLE tx_hooker_bot (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	starttime int(11) DEFAULT '0' NOT NULL,
	endtime int(11) DEFAULT '0' NOT NULL,
	last_run int(11) DEFAULT '0' NOT NULL,
	run_interval int(11) DEFAULT '0' NOT NULL,
	family tinytext NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);



#
# Table structure for table 'tx_hooker_agent'
#
CREATE TABLE tx_hooker_agent (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	starttime int(11) DEFAULT '0' NOT NULL,
	endtime int(11) DEFAULT '0' NOT NULL,
	filename tinytext NOT NULL,
	state1 tinytext NOT NULL,
	state2 tinytext NOT NULL,
	status int(11) DEFAULT '0' NOT NULL,
	importance int(11) DEFAULT '0' NOT NULL,
	attempts int(11) DEFAULT '0' NOT NULL,
	botuid int(11) DEFAULT '0' NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);