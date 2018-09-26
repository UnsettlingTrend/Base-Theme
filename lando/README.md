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
  
  
Getting PHPUnit Working
-----------------------
NOTE: As of 9/21/2018, For PHP 7.2, use PHPUnit 6.5
1. Do this: https://www.jetbrains.com/help/phpstorm/enabling-php-unit-support.html
2. Then this: https://www.youtube.com/watch?v=I7aGWO6K3Ho
  (it looks like the "...PHP -> PHPUnit" is not "...PHP -> Test Frameworks" in PHPStorm's settings) 
3. And this: https://www.drupal.org/docs/8/phpunit/running-phpunit-tests-within-phpstorm (If you followed the previous instructions, don't rename /core/phpunit.xml.dist)