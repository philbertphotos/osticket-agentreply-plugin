#Agent Email Reply v1.1 for osTicket 
=====================================
Basically the plugin converts a agent NOTE sent via email to a normal RESPONSE and then sends a email to the ticket creator, it will also auto-assign the ticket to the first agent that responds
Works and tested with version 1.10 to v1.17+ and PHP 8+

Features
========
 removes agents as collaborators when they respond by email and adds they as ticket owner if its unassigned.
 
Installing
==========

### Prebuilt

simply create a folder in the "includes\plugins\agentReply" on your osticket install and copy files in there.

Configuration 
=============
Choose what Departments it should activate on.

Bug fixes
===========


Roadmap
==========
Auto assign first Agent that responds via email. (done)

NOTES
===========
This is being used live in my environment, while I made several changes and updates to clean up the code before release its been tested and works as intended.
