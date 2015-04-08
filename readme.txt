=== 2BC Form Security ===
Contributors: 2bc_jason, 2bc_aron
Donate link: http://2bcoding.com/donate-to-2bcoding/
Tags: 2bc, 2bcoding, google, recaptcha, google recaptcha, captcha, integration, honeypot, spam, security, statistics, form, forms, login, registration, comments
Author URI: http://2bcoding.com/
Plugin URI: http://2bcoding.com/plugins/2bc-form-security/
Requires at least: 3.6
Tested up to: 4.1.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Increase security and reduce spam by adding a honeypot and Google reCAPTCHA V2 to the log in form, registration form, and comment form

== Description ==

The [2BC Form Security](http://2bcoding.com/plugins/2bc-form-security/) WordPress plugin will increase security and reduce spam by using tools like the Google reCAPTCHA V2 widget and a honeypot field.  While simple, these can be very effective at keeping spam bots and scripts out of your site.

= Features =

2BC Form Security will automatically activate a honeypot field in any of the locations marked in the settings.  With a few minutes of setup, it can also display the [Google reCAPTCHA v2](https://www.google.com/recaptcha/) widget in the same locations.

The reason captcha and honeypots work is because spam bots and scripts have a hard time reading CSS and Javascript when they are trying to fill out a form.  The honeypot is hidden from a normal user, but a bot  will try to fill in the field with some information.  If anything is detected in a honeypot field, 2BC Form Security will return an error and prevent the action from happening.

Google reCAPTCHA has historically been difficult because Google used warped pictures that were hard for bots AND humans to fill out.  Now it has been condensed into a Javascript widget.  This is the first level of defense: bots have a hard time detecting or clicking Javascript elements in a web page.  However humans have a very easy time, they simply have to click.

Eventually spam bots will figure out a way around this.  Google has many additional layers of defense and have prepared extra questions.  Some of the new challenges will be incredibly hard for a bot to work out, but are still easy on humans and actually fun to complete!

In addition to adding the honeypot and Google reCAPTCHA tools, 2BC Form Security has the following features:

* Statistics page in the admin section - make sure to enable this on the options page
* Optional login error rewrite - prevents hackers from figuring out valid user names
* Mark failed comments as Spam, or put into the Moderation Queue
* Dashboard widget
* Style the Google reCAPTCHA widget in either of the current themes: Light or Dark

Future updates include integration with *BuddyPress*, *bbPress*, *Contact Form 7*, and a shortcode for custom forms.

= Documentation =

The [2BC Form Security documentation page](http://2bcoding.com/plugins/2bc-form-security/2bc-form-security-documentation/) contains an explanation of all the settings, as well as how to setup and run the plugin.

== Installation ==

The *2BC Image Gallery* can be installed via the WordPress plugin repository (automatic), or by uploading the files directly to the web server (manual).

= Automatic =

1. [Log in to the WordPress administration panel](https://codex.wordpress.org/First_Steps_With_WordPress#Log_In) with an administrator account
2. Click **Plugins** > **Add New**
3. Search for *2BC Image Gallery*
4. Find the plugin in the list of results and click the **Install Now** button
5. Click **OK** to confirm the plugin installation.  If there are any file permission issues, WordPress may ask for a valid FTP account to continue.  Either enter the FTP credentials, or proceed to the Manual installation instructions.
6. Click the **Activate Plugin** link after the installation is complete

= Manual =

1. [Download a copy of the plugin](https://wordpress.org/plugins/2bc-image-gallery/) and save it to the local computer.  Make sure that the folder has been unzipped.
2. [Using an FTP program or cPanel](https://codex.wordpress.org/FTP_Clients), connect to the server that is hosting the website
3. Find the root folder for the site and browse to the following directories: **wp-content** > **plugins**
4. Upload the un-compressed *2bc-form-security* folder in to the *plugins* folder on the server
5. [Log in to the WordPress administration panel](https://codex.wordpress.org/First_Steps_With_WordPress#Log_In) with an administrator account
6. Click **Plugins** > **Installed Plugins**
7. Find the *2BC Form Security* plugin in the list and click the **Activate** link

== Frequently Asked Questions ==

= How can I get Google reCAPTCHA V2 API keys =

See our blog post on [How To Get Google reCAPTCHA V2 API Keys](http://2bcoding.com/how-to-get-google-recaptcha-v2-api-keys/).  It's quick, simple, and a free service.

= How can I see if the fields are working =

Edit the options screen and click **Enable Reporting** to see a summary of what fields are working and where.

== Screenshots ==

1. Google reCAPTCHA V2 on the WordPress log in form
2. Google reCAPTCHA V2 on the twentyfifteen comment form
3. 2BC Form Security options page
4. 2BC Form Security Dashboard widget

== Other Notes ==

* Requires WordPress 3.6, and PHP 5.2+

== Changelog ==

= 1.0.0 =
* Launch of 2BC Form Security

== Upgrade Notice ==

= 1.0.0 =
Launch of 2BC Form Security