Lando ReadMe
============
This /lando directory is for configuring your Lando-based development environment

Directories
-----------
### /lando/config
Configuration changes

  * php.ini	
    * Custom php configuration (one of the biggest being the xDebug settings)
  * my.cnf
    * Custom mysql settings

### /lando/scripts
Supporting scripts, usually run during lando build

  * Build scripts are run in the order in which they're numbered, when the lando project is initially setup.
  