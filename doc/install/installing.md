# Installing the plugin


There are two methods to install the plugin.


If possible, use the first and easiest method. If for some reason the first method does not work, then the second method may be your only choice.
## Method 1: Installing the plugin from the Wordpress admin panel


If your wordpress installation support it, the easiest way to install a plugin is via the admin panel.


1. First go to your WordPress Admin Panel. ( http:// your wordpress site /wp-admin)
2. Click on Plugins > Add New
3. Click on upload
4. Click on Choose File and select the obf-wp.zip you have downloaded.
5. Click on Install Now
6. If wordpress asks for FTP username and password check Method1: Fixing directory permissions
7. Proceed to step [Setting up](setting_up.md) the plugin

### Method 1: Fixing directory permissions

If your wordpress asks for FTP username or password when installing the plugin, it's recommended to fix the issue. Below are instructions to fix the issue. If installing the plugin went fine, then skip to Setting up the plugin.

If you are familiar with SSH and the linux console, these instructions should help you fix the permissions.

First thing you should do, is locate your wordpress directory. In many cases wordpress is located in /var/www/html. In your wordpress directory you need to change the owner of wp-content/plugins and wp-content/uploads directories to your web-server user (usually www-data or httpd).

When assuming your wordpress is in /var/www/html and your user is www-data, you would run a command `chown -R www-data /var/www/html/wp-content/plugins`.



On systems with sudo, and wordpress in /var/www/html:

    HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`
    echo Httpd user is $HTTPDUSER . Lets set plugin directory permissions
    sudo chown -R $HTTPDUSER /var/www/html/wp-content/uploads
    sudo chown -R $HTTPDUSER /var/www/html/wp-content/plugins


## Method 2: Manually installing the plugin using FTP and SSH

This six-step process shows an example of how to upload and unpack the Open Badge Factory wordpress plugin to the '/wp-content/plugins/' directory of your WordPress installation

1. Upload obf-wp.zip using your favourite FTP-client (FileZilla is a good choice) to your Wordpress server. Preferably store it in your user's home directory.
2. Connect to your server using an SSH-client. (PuTTy on windows or ”ssh” on OS X or Linux -terminal)
3. Go to your wordpress directory (`cd /var/www/html/` if your wordpress directory is in /var/www/html/.)
4. `cd wp-content/plugins/`
5. `unzip ~/obf-wp.zip`
6. Make sure obf/pki directory is writeable by the web-server user. (`chown www-data:www.data obf/pki`)
7. Proceed to step [Setting up](setting_up.md) the plugin