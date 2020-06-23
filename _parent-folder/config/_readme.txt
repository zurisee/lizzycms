Readme: config/
===============

Files:
------
config.yaml				: Lizzy configuration file
page_template.html		: Page tepmlate file (HTML)
sitemap.txt				: Definition of site structure
user_variables.yaml		: Definitions of user variables

Optional/sample files:
----------------------
#dev-mode-config.yaml	: Lizzy config file for development phase *)
#schedule.yaml			: example of a schedule file
#slack.webhook			: placeholder for a slack-webhook
#users.yaml				: template for user admin file



Dev Mode
--------
File: 		dev-mode-config.yaml

Purpose: 	for config options only used in development phase.
			-> option to turn off automatically by next morning

Usage:		Place following lines in main config.yaml to enable dev mode:
				debug_enableDevMode: true
				debug_enableDevModeAutoOff: true


