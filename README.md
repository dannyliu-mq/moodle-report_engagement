#Engagement Analytics for Moodle
The Engagement Analytics set provides information about student progress against a range of indicators. As the name suggests the block provides feedback on the level of "engagement" of a student, in this plugin "engagement" refers to activities which have been identified by current research to have an impact on student success in an online course.

This was originally forked from https://github.com/netspotau/moodle-report_engagement

##Install
### Using a downloaded zip file
You can download a zip of this module from: https://github.com/dannyliu-mq/moodle-report_engagement/archive/master.zip
Unzip it to your report/ folder and rename the extracted folder to 'engagement'.
### Using Git
To install using git, run the following command from the root of your moodle installation:  
git clone git://github.com/dannyliu-mq/moodle-report_engagement.git report/engagement  

Then add report/engagement to your gitignore.

##Companion plugins

###Engagement analytics mod
This plugin provides the core subplugins needed (the indicators).

Repo: http://github.com/dannyliu-mq/moodle-mod_engagement

###Engagement analytics block
**The traffic light block was present as a companion plugin in the original plugin set, but is not recommended for use.**
Displays traffic lights showing at risk students.

Repo: http://github.com/netspotau/moodle-block_engagement

##Credits
Engagement Analytics was developed by NetSpoty Pty Ltd for Monash University (http://www.monash.edu.au) as part of the NetSpot Innovation Fund.

###Original plugin
Code: Adam Olley (adam.olley@netspot.com.au)  
Code: Ashley Holman  
Concept: Phillip Dawson (phillip.dawson@monash.edu)  
Indicator Algorithms: Phillip Dawson (phillip.dawson@monash.edu)  

###Later developments
Code and concept: Danny Liu (danny.liu@mq.edu.au)  
Code: Amara Atif (amara.atif@mq.edu.au)  
Concept: Chris Froissard (chris.froissard@mq.edu.au)  
Concept: Deborah Richards (deborah.richards@mq.edu.au)
