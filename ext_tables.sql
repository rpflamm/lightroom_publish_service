CREATE TABLE tx_lightroompublishservice_domain_model_collection (
	uid int(11) unsigned NOT NULL auto_increment,
	pid int(11) DEFAULT 0 NOT NULL,
	tstamp int(11) unsigned DEFAULT 0 NOT NULL,
	crdate int(11) unsigned DEFAULT 0 NOT NULL,
	cruser_id int(11) unsigned DEFAULT 0 NOT NULL,
	deleted smallint(5) unsigned DEFAULT 0 NOT NULL,
	hidden smallint(5) unsigned DEFAULT 0 NOT NULL,
	sorting int(11) DEFAULT 0 NOT NULL,

	name varchar(255) DEFAULT '' NOT NULL,
	collection_type varchar(32) DEFAULT 'collection' NOT NULL,
	parent int(11) unsigned DEFAULT 0 NOT NULL,
	description text,

	PRIMARY KEY (uid),
	KEY parent (parent)
);

CREATE TABLE tx_lightroompublishservice_domain_model_photo (
	uid int(11) unsigned NOT NULL auto_increment,
	pid int(11) DEFAULT 0 NOT NULL,
	tstamp int(11) unsigned DEFAULT 0 NOT NULL,
	crdate int(11) unsigned DEFAULT 0 NOT NULL,
	cruser_id int(11) unsigned DEFAULT 0 NOT NULL,
	deleted smallint(5) unsigned DEFAULT 0 NOT NULL,
	hidden smallint(5) unsigned DEFAULT 0 NOT NULL,

	collection int(11) unsigned DEFAULT 0 NOT NULL,
	content_hash varchar(64) DEFAULT '' NOT NULL,

	PRIMARY KEY (uid),
	KEY collection (collection)
);

CREATE TABLE tx_lightroompublishservice_domain_model_apitoken (
	uid int(11) unsigned NOT NULL auto_increment,
	pid int(11) DEFAULT 0 NOT NULL,
	tstamp int(11) unsigned DEFAULT 0 NOT NULL,
	crdate int(11) unsigned DEFAULT 0 NOT NULL,
	cruser_id int(11) unsigned DEFAULT 0 NOT NULL,
	deleted smallint(5) unsigned DEFAULT 0 NOT NULL,

	name varchar(255) DEFAULT '' NOT NULL,
	selector varchar(32) DEFAULT '' NOT NULL,
	verifier_hash varchar(128) DEFAULT '' NOT NULL,
	last_used_at int(11) unsigned DEFAULT 0 NOT NULL,
	last_used_ip varchar(45) DEFAULT '' NOT NULL,
	revoked tinyint(1) unsigned DEFAULT 0 NOT NULL,

	PRIMARY KEY (uid),
	UNIQUE KEY selector (selector)
);
