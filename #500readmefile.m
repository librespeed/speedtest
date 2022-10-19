Install PHP by following the steps below. Note that there are several ways to configure Apache and PHP, but this is possibly the quickest method.

Step 1: Download the PHP files
You’ll need the PHP Windows installer. There are a number of versions of PHP available. 
Make sure you get the latest PHP 8 x64 Thread Safe ZIP package from https://www.php.net/downloads.php.

Step 2: Extract the files
Create a new php folder in the root of your C:\ drive and extract the contents of the ZIP into it.

PHP can be installed anywhere on your system, but you’ll need to change the paths referenced below if C:\php isn’t used.

Step 3: Configure php.ini
PHP’s configuration file is named php.ini. This doesn’t exist initially, so copy C:\php\php.ini-development to C:\php\php.ini. 
This default configuration provides a development setup which reports all PHP errors and warnings.

Step 4: Add C:\php to the path environment variable
To ensure Windows can find the PHP executable, you need to change the PATH environment variable. Click the Windows Start button and type “environment”, 
then click Edit the system environment variables. Select the Advanced tab, and click the Environment Variables button.

Step 5: Configure PHP as an Apache module
Ensure Apache isn’t running and open its C:\Apache24\conf\httpd.conf configuration file in a text editor. 
Add the following lines to the bottom of the file to set PHP as an Apache module (change the file locations if necessary):

Step 6: Test a PHP file
Create a new file named index.php in Apache’s web page root folder at C:\Apache24\htdocs and add the following PHP code:
