Core Five FileManager PHP connector S3 plugin
=============================================
["!http://stillmaintained.com/Net-Results/filemanager-php-s3-plugin.png!":http://stillmaintained.com/Net-Results/filemanager-php-s3-plugin]

**Homepage**:       [http://github.com/Net-Results/filemanager-php-s3-plugin](http://github.com/Net-Results/filemanager-php-s3-plugin)  
**Author**:         Wil Moore III   
**Contributors**:   See Contributors section below  
**License**:        MIT License     
**Latest Version**: 0.0.4       
**First Released**: December 20th 2011 


Summary
-------

An AWS s3 plugin for the Core Five FileManager. This plug-in works with the PHP connector providing the ability to
manage files in an Amazon s3 bucket. For use with: https://github.com/simogeo/Filemanager.


Features
-------------

**1. Caching**: Choose apc, xcache, memcache, memcached, database, or file caching.   
**2. CloudFront**: Server the managed files via a configurable CloudFront distribution name.   
**3. Configurable**: Configure most aspects of the plug-in including caching and custom domain.   
**4. Folder Support**: Supports s3 pseudo folders to simulate real directory browsing.   
**5. Debugging**: Set debug mode to (true) which disables caching and provides useful debug messages.   
**6. Dynamic Directories**: With a single line of configuration, you can provide users with a custom (semi-private) directory within a single bucket.   


Limitations
-----------

-   for a directory to be depicted, an empty file with a trailing "/" must be created as the name of the directory.
-   deleting/renaming a non-empty folder is not currently supported.
-   if you've traversed into a directory via the left-hand tree-navigation, then you close the
    the current node, going into a child directory of the currently selected node via the
    right-hand pane does _NOT_ trigger the left-hand tree-navigation to reflect the CWD. The
    correct behavior would be for the tree to locate the current working directory node and open
    it.


Requirements
-------------

*   [required] PHP 5.3+
*   [required] Core Five FileManager (http://labs.corefive.com/projects/filemanager/)


Installing
-------------

**(zip|tar)ball**

    # uncompress or sub-module into: connectors/php/plugins/s3
    $   cd {path/to/filemanager}/connectors/php/plugins
    $   mkdir s3
    $   curl -# -L https://github.com/Net-Results/filemanager-php-s3-plugin/tarball/master | tar -xz --strip 1 -C s3/

**Git Submodule (use this if your project's source is tracked via a Git repository)**

    # uncompress or sub-module into: connectors/php/plugins/s3
    $   git submodule add https://github.com/Net-Results/filemanager-php-s3-plugin.git {path/to/filemanager}/connectors/php/plugins/s3


Configuration
-------------

**Copy configuration template**

    $   cp filemanager.config.js.default filemanager.config.js

**filemanager.config.js**

This is the core filemanager's configuration; however, the s3 plug-in requires these items to be set correctly:

-   showFullPath
-   fileRoot
-   relPath

Sample Configuration (short)

    var culture              = 'en',
        defaultViewMode      = 'grid',
        autoload             = true,
        showFullPath         = false,
        displayPathDecorator = function(path) { return path.replace(/^\d+\/images/i, ''); },
        browseOnly           = false,
        lang                 = 'php',
        am                   = document.location.pathname.substring(1, document.location.pathname.lastIndexOf('/') + 1),
        fileRoot             = '/',
        relPath              = '//xxxxxxxxxxxxxx.cloudfront.net/',
        showThumbs           = true,
        imagesExt            = ['jpg', 'jpeg', 'gif', 'png'];

Sample Configuration (long)

    // Set culture to display localized messages
    var culture = 'en';

    // Set default view mode : 'grid' or 'list'
    var defaultViewMode = 'grid';

    // Autoload text in GUI
    // If set to false, set values manually into the HTML file
    var autoload = true;

    // Display full path - default : false
    var showFullPath         = false;

    // Browse only - default : false
    var browseOnly = false;

    // Set this to the server side language you wish to use.
    var lang = 'php';

    var am = document.location.pathname.substring(1, document.location.pathname.lastIndexOf('/') + 1);

    // Set this to the directory you wish to manage.
    var fileRoot = '/';

    // Path to the manage directory on the HTTP server
    var relPath = '//d40rlfik0wts0.cloudfront.net/';

    // Show image previews in grid views?
    var showThumbs = true;

    // Allowed image extensions when type is 'image'
    var imagesExt = ['jpg', 'jpeg', 'gif', 'png'];

**Copy configuration template**

    $   cp filemanager.s3.config.php.dist filemanager.s3.config.php

**filemanager.s3.config.php**

Minimal configuration items you should be concerned with:

-   aws-access-key
-   aws-secret-key
-   doc_root
-   s3-bucket
-   s3-public-domain
-   enable-debug-mode

Sample Configuration

    <?php
    /**
     * S3 plugin configuration
     *
     * You may over-ride any parameters set by the filemanager.config.php file here
     *
     * @license MIT License
     * @author	Wil Moore III <wmoore@net-results.com>
     */

    /*******************************************************************************
     * Credentials
     ******************************************************************************/

    /**
     * Amazon Web Services Key. Found in the AWS Security Credentials. [default = '']
     */
    $config['aws-access-key']     = 'XXXXXXXXXXXXXXXXXXXX';

    /**
     * Amazon Web Services Secret Key. Found in the AWS Security Credentials. [default = '']
     */
    $config['aws-secret-key']     = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

    /*******************************************************************************
     * Domain, path, etc.
     ******************************************************************************/

    /**
     * [optionally] override the base filemanager's 'doc_root' setting
     * You should leave of leading and trailing slashes as they will be removed anyhow
     */
    $config['doc_root']           = "{$_SESSION['userId']}/images";
    // NOTE: $_SESSION... is not the best way to handle this, but this is just an example

    /**
     * Should the root directory be created if it doesn't exist? [default = false]
     * use if you are dynamically building document root and appending a user specific directory
     */
    $config['create-root-dir']    = true;

    /**
     * S3 bucket name (REQUIRED) [default = '']
     */
    $config['s3-bucket']          = 'our-corporate-file-store';

    /**
     * Public DNS name (REQUIRED) [default = null]
     *
     * suggestions:
     *  1)  cloudfront distribution domain (i.e. {distribution-name}.cloudfront.net)
     *  2)  s3 origin domain (i.e. s3.amazonaws.com)
     */
    $config['s3-public-domain']   = 'xxxxxxxxxxxxx.cloudfront.net';

    /*******************************************************************************
     * Cache
     ******************************************************************************/

    /**
     * Where should cached objects be stored? [default = 'apc']
     * apc, xcache, memcache, memcached, pdo, pdo_sqlite, sqlite, sqlite3 (or path on disk)
     */
    $config['aws-cache-scheme']   = 'apc';

    /**
     * compress (gzip) cached objects (true|false) [default = true]
     */
    $config['aws-cache-compress'] = true;

    /**
     * expire cache after this date/time interval. [default = '1 day ago']
     * seconds as integer | relative: http://php.net/manual/en/datetime.formats.relative.php
     */
    $config['aws-cache-expirein'] = '1 minute';

    /*******************************************************************************
     * Debugging
     ******************************************************************************/

    /**
     * Enable debug mode (true|false) [default = false]
     * Enabling this will turn on debugging for the following components:
     *
     * 1 - curl (our favorite low-level http-client)
     * 2 - CFRuntime (the underlying component behind the AWS services API
     * 3 - FileManagerS3 (the actual filemanager plug-in you seek)
     *
     * Enabling this will also hard disable caching regardless of what you set in:
     * - $config['aws-cache-config']
     * - $config['aws-cache-compress']
     *
     * Where debug messages end up depends on 'log_errors' and 'error_log' ini settings
     */
    $config['enable-debug-mode']  = true;

    /*******************************************************************************
     * PHP error log settings
     ******************************************************************************/

    /**
     * as a bare-minimum, you will want to have 'log_errors' and 'error_log' set
     */
    ini_set('log_errors', true);
    ini_set('error_log',  'syslog');


Changelog
---------

-   **2011-12-22**: Released version 0.0.4 which removes the configuration file from the repo and ignores it (so we never clobber your custom configuration).

-   **2011-12-22**: Released version 0.0.3 which includes better support for renaming directories, clean-up of buildFullPath(), and clean-up of debug().

-   **2011-12-20**: Released initial version 0.0.1.


Contributors
------------

Special thanks to the following people for submitting patches:

* no patches submitted ATM

