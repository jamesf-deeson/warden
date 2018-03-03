# Warden

Warden is for busy people managing multiple websites.  It provides a central
dashboard for reviewing the status of every website, highlighting those
with immediate issues which need resolving.

Presently Warden monitors Drupal websites. Drupal websites need to install the
[Warden Drupal module][1] in order to connect to Warden.

On the roadmap is a pluggable system allowing Warden to be used flexibly
for any website which has a supporting connector module.

## Server Configuration

Warden is built using the Symfony2 web development framework.

Symfony2 uses [Composer][2] to manage its dependencies, if you don't have
Composer yet, download it following the instructions on http://getcomposer.org/
or just run the following command:

    curl -s http://getcomposer.org/installer | php

Warden also has a dependency on [Mongodb][3], so this will need to also be
installed and PHP configured to use it.

To configure your web server to run Warden, please refer to the Symfony [documentation][6]

### Mongodb Driver

Warden uses Doctrine's MongoDB ODM bundle to interface with MongoDB. Under the 
bonnet Doctrine's MongoDB ODM depends upon the MongoDB PHP driver. 

There was an [issue][4] raised due to Warden using the legacy MongoDB PHP driver.
This has been fixed now so that the latest Mongodb driver can be used when setting
up the server to run Warden.

The following page on the MongoDB docs site explains more about the different types 
of driver, the user land PHP library and compatibility with different MongoDB server 
versions and PHP language versions.

https://docs.mongodb.com/ecosystem/drivers/php/

(There can sometimes be version issues with the mongodb PHP driver when installing 
the Warden app. This is detailed further down under 'Known issues').

#### Using the legacy Mongodb driver

If you are using an older version of Mongodb which limits you to the legacy mongodb 
driver, Warden is shipped with a legacy composer file to help get started.

There is a file called `composer.json-legacy` which has the package details for
the using the legacy driver. 

Before installing the Warden server, rename the file composer.json-legacy to be 
composer.json. When installing Warden the legacy Mongodb will then be installed.

## Installation

Once the server is configured with a web server, PHP and mongodb, you will need to 
install the Warden application. To do this, download and install the latest version
from the Github repository.
Then run: `composer install` within the application directory to install the Symfony 
application fully.
  
Once all the application has been installed, you will ask for configuration details 
for it:

The basic installation parameters are:

  * `locale`            - the language code (e.g. en), currently only en is supported
  * `secret`            - a long random string used for security
  * `protocol`          - how warden should be accessed, either https (recommended) or http (not secure)
  * `public_key_file`   - the location of where the Warden app will create the public key
  * `private_key_file`  - the location of where the Warden app will create the private key
  
Installation parameters when using Mongodb with authentication are:

  * `db_host`      - the mongodb host (defaults to localhost)
  * `db_port`      - the mongodb port (defaults to 27017)
  * `db_name`      - the mongodb database name (defaults to warden)
  * `db_username`  - the mongodb authentication username (defaults to null)
  * `db_password`  - the mongodb authentication password (defaults to null)
  
If you are not using Mongodb with authentication enabled, then you can leave the 
username and password settings as 'null', otherwise these should be the username
and password needed to be able to connect the Mongodb database.
  
Installation parameters when using Swiftmailer for sending emails are:

  * `mailer_transport`               - the transport method to use to deliver emails (defaults to smtp)
  * `mailer_host`                    - The host to connect to when using smtp as the transport (defaults to 127.0.0.1)
  * `mailer_port`                    - The port when using smtp as the transport (defaults to 25)
  * `mailer_user`                    - The username when using smtp as the transport (defaults to null)
  * `mailer_password`                - The password when using smtp as the transport (defaults to null)
  * `email_sender_address`           - The email address that any emails will be sent from (defaults to blank)
  * `email_dashboard_alert_address`  - The email address to send the dashboard alerts to (defaults to blank)
  
If you do not want to send any email alerts, then leave these blank.

Installation parameters for using Slack notifications:

  * `warden.dashboard.slack.hook_url` - The hook URL within Slack to which Warden can send notifications to.
  
If you do not want to send any Slack notifications, then leave this blank.

After these, you will also be asked for to set up the login credentials to access the 
Warden application. Once set up you can log in using these credentials.
  
Further reading on mailer configuration can be found on the [Symfony documentation][5]

## How it Works

Once a site has been 'registered' via the [Warden Drupal module][1], the site
is in a 'pending' state before all the data for that site has been requested 
by the Warden server.

To update the sites that are registered against the Warden server with the latest 
information for the sites and from Drupal.org, you will need to config the cron 
script to process the sites and the latest data from Drupal.org.

## Cron Scripts

Warden is shipped with a set of bash scripts which can be used to update the site
and Drupal module information.

In order to keep the site and module information up to date, you will need to setup
a cron entry to run the script: 

    ./scripts/cron.sh [ENV] --new-only

Where:
  * [ENV]  - the environment to run cron on (e.g. @dev, @test or @prod)
  * --new-only - set this to only import newly added sites, those that that are 
  in a 'pending' state

It is recommended to run this with the `new-only` flag as often as you can.
Depending upon the regularity of the number of sites that you will be adding, this
can be a fairly often (every 5 minutes) or longer (every few hours), as this should 
be a relatively short process to run as it is only importing new sites data.

It is also recommended to then run the full import (without the `new-only` flag) 
at least once a day, but this could be as often as you require. 
This will update all the sites and request updates from Drupal.org so this can 
be a longer running process depending upon the number of sites that you have.

## Security

It is recommended that this application should be run under SSL to maintain
the security of the data and the system.  For that reason this application has
the security set to 'force' to run under SSL by default.

During installation you will need to set the protocol parameter to 'https'
for secure SSL or 'http' for insecure if your server does not support SSL.

You can change this setting in app/config/parameters.yml file after installation

> *Using this application without SSL will be at your own risk.*

## Known issues

### Mongo PHP Driver
When installing the Warden application, (depending upon how the mongodb PHP driver 
has been installed), an error can sometimes be thrown due to an [invalid mongodb 
driver version][7]:

```
Loading composer repositories with package information
Installing dependencies (including require-dev) from lock file
Warning: The lock file is not up to date with the latest changes in composer.json. You may be getting outdated dependencies. Run update to update them.
Your requirements could not be resolved to an installable set of packages.

  Problem 1
    - mongodb/mongodb 1.1.1 requires ext-mongodb ^1.2.0 -> the requested PHP extension mongodb has the wrong version (1.1.5) installed.
    - mongodb/mongodb 1.1.1 requires ext-mongodb ^1.2.0 -> the requested PHP extension mongodb has the wrong version (1.1.5) installed.
    - Installation request for mongodb/mongodb 1.1.1 -> satisfiable by mongodb/mongodb[1.1.1].
```
 
This is due to the particular version of the Mongodb driver within the Symfony 
mongodb bundle (`"ext-mongodb": "^1.2.0"`). When installing the mongodb PHP driver
the standard package managers are often fixed to a particular version. In this case
it seems that it is often fixed to version 1.1.5.

To fix this, once you have installed the PHP mongodb driver, run the following commands
to build and install the latest mongodb PHP driver using PECL:

```
sudo apt-get install libcurl4-openssl-dev libsslcommon2-dev
sudo apt-get install autoconf g++ make openssl libssl-dev libcurl4-openssl-dev
sudo apt-get install libsasl2-dev pkg-config
sudo pecl install mongodb
```

## General Help

A couple of things for you to be aware of with this application:

  1. User credentials: If you need to regenerate the user credentials run:

      `php app/console deeson:warden:install --regenerate`

  2. There is a custom CSS file generated in the following directory:

      `src/Deeson/WardenBundle/Resources/public/css/warden-custom.css`

     If you want to override any of the styling of the application edit this
     file.
      
  3. If you override the default styling or update the application you will need to clear 
     the application cache. This can be done by running:

      `./scripts/clear-cache.sh [ENV] [RunAsWebServer]`

     Where:
      - `[ENV]` is the environment that you are running on - @dev/ @test/ @prod
      - `[RunAsWebServer]` is a boolean as to whether you want to run the command as the
      webserver used (normally `www-data`)



[1]: https://www.drupal.org/project/warden
[2]: http://getcomposer.org/
[3]: http://docs.mongodb.org/manual/
[4]: https://github.com/teamdeeson/warden/issues/60
[5]: https://symfony.com/doc/2.8/reference/configuration/swiftmailer.html
[6]: https://symfony.com/doc/2.8/setup/web_server_configuration.html
[7]: https://github.com/mongodb/mongo-php-driver/issues/704
