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
$config['aws-access-key']     = '';

/**
 * Amazon Web Services Secret Key. Found in the AWS Security Credentials. [default = '']
 */
$config['aws-secret-key']     = '';

/*******************************************************************************
 * Domain, path, etc.
 ******************************************************************************/

/**
 * [optionally] override the base filemanager's 'doc_root' setting
 * You should omit leading and trailing slashes as they will be removed anyhow
 */
$config['doc_root']           = 'user1234/images';

/**
 * Should the root directory be created if it doesn't exist? [default = false]
 * use if you are dynamically building document root and appending a user specific directory
 */
$config['create-root-dir']    = true;

/**
 * S3 bucket name (REQUIRED) [default = '']
 */
$config['s3-bucket']          = 'filesmanager';

/**
 * Public DNS name (REQUIRED) [default = null]
 *
 * suggestions:
 *  1)  cloudfront distribution domain (i.e. {distribution-name}.cloudfront.net)
 *  2)  s3 origin domain (i.e. s3.amazonaws.com)
 */
$config['s3-public-domain']   = 'd40rlfik0wts0.cloudfront.net';

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
