<?php
/**
 * Filemanager plugin for S3
 *
 * utilizes the Amazon Web Services S3 API for PHP
 *
 * @license MIT License
 * @author	Wil Moore III <wmoore@net-results.com>
 */

require_once realpath(__DIR__ . '/AWSSDKforPHP/sdk-1.5.0/sdk.class.php');

/**
 * Filemanager plugin for S3
 *
 * utilizes the Amazon Web Services S3 API for PHP
 *
 * @license MIT License
 * @author	Wil Moore III <wmoore@net-results.com>
 *
 * @todo    future improvements:
 *          1 - pub/sub events
 *          2 - compose in options/config instances (vs. $config array)
 *          3 - compose in request/parameters object(s) (vs. $_POST, $_GET)
 */
class FilemanagerS3 extends Filemanager {

/*******************************************************************************
 * Constants
 ******************************************************************************/

  const FILE_TYPE_DIR         = 'dir';
  const FILE_TYPE_IMG         = 'img';
  const DEFAULT_CACHE_EXPIRE  = '1 day ago';
  const DATE_FORMAT           = 'm/d/Y H:i:s';

  const MSG_DIR_NOTCREATED_EXISTS = 'directory (%s) not created as it already exists.';
  const MSG_DEBUG_UPLOAD_INI      = 'post_max_size: %s, upload_max_filesize: %s, max_input_time: %s';

  /**
   * s3 service object
   *
   * @var AmazonS3
   */
  protected $s3               = null;

  /**
   * amazon s3 bucket
   *
   * @var string
   */
  protected $bucket           = null;

  /**
   * public domain
   * 
   * @var string
   */
  protected $domain           = null;

  /**
   * enable/disable debug mode
   *
   * @var boolean
   */
  protected $debug            = false;

  /**
   * folder/directory icon
   *
   * @var string
   */
  protected $folderIcon       = null;

  /**
   * root directory
   *
   * @var string
   */
  protected $rootDirectory    = null;

  /**
   * should the root directory be created if it doesn't exist?
   *
   * @var boolean
   */
  protected $createRootDir    = false;

  /**
   * when the cache will expire
   *
   * Accepts a number of seconds as an integer, or a relative string (e.g. "1 minute"):
   * http://php.net/manual/en/datetime.formats.relative.php
   *
   * NOTE: defaults to having an expiration date in the past which disables caching
   */
  protected $cacheExpire      = self::DEFAULT_CACHE_EXPIRE;

  /**
   * maximum megabytes per file upload
   *
   * @var integer
   */
  protected $uploadMaxMb      = 1;

  /**
   * Where should cached objects be stored?
   *
   * valid caching schemes
   * apc, xcache, memcache, memcached, pdo, pdo_sqlite, sqlite, sqlite3
   *
   */
  protected $cacheScheme      = 'apc';

  /**
   * default file information template
   *
   * @var array
   */
  protected $defaultInfo      = array(
    'Path'       => '',
    'Filename'   => '',
    'File Type'  => '',
    'Preview'    => '',
    'Error'      => '',
    'Code'       => 0,
    'Properties' => array(
      'Date Created'  => '',
      'Date Modified' => '',
      'Height'        => 0,
      'Width'         => 0,
      'Size'          => 0
    )
  );

  /**
   * Prepare to handle FileManager API requests
   *
   * Construct an AmazonS3 Service instance then configure configure paths, etc.
   *
   * @param type  $config plugin specific configuration
   *
   * @todo  throw error if keys are not set
   */
	public function __construct($config) {
		parent::__construct($config);

    // Configure instance
    $this->configure($this->config);
	}

/*******************************************************************************
 * Configuration
 ******************************************************************************/

  /**
   * Recieves all configuration values and sets up this instance
   *
   * All configuration should be handled here
   *
   * @param array $config
   */
  protected function configure($config) {
    // Configurable instance variables (if not set, use the instance default)
    $this->debug          = isset($config['enable-debug-mode'])
                          ? $config['enable-debug-mode']
                          : $this->debug;

    $this->folderIcon     = $config['icons']['path'] . $config['icons']['directory'];

    $this->bucket         = isset($config['s3-bucket'])
                          ? $config['s3-bucket']
                          : $this->bucket;

    $this->rootDirectory  = isset($config['doc_root'])
                          ? trim($config['doc_root'], '/ ')
                          : $this->rootDirectory;

    $this->createRootDir  = isset($config['create-root-dir'])
                          ? $config['create-root-dir']
                          : $this->createRootDir;

    $this->domain         = isset($config['s3-public-domain'])
                          ? $config['s3-public-domain']
                          : $this->domain;

    $this->cacheScheme    = isset($config['aws-cache-scheme'])
                          ? $config['aws-cache-scheme']
                          : $this->cacheScheme;

    $this->cacheExpire    = isset($config['aws-cache-expirein'])
                          ? $config['aws-cache-expirein']
                          : $this->cacheExpire;

    $this->uploadMaxMb    = isset($config['upload']['size'])
                          ? (int) $config['upload']['size']
                          : $this->uploadMaxMb;

    // if we are in debug mode, auto-expire cache
    $this->cacheExpire    = $this->debug
                          ? self::DEFAULT_CACHE_EXPIRE
                          : $this->cacheExpire;

    // set global static credentials
    CFCredentials::set(array(
      '@default' => array(
        'key'                   => $config['aws-access-key'],
        'secret'                => $config['aws-secret-key'],
        'default_cache_config'  => $this->cacheScheme,
        'certificate_authority' => false
    )));

    // Instantiate the AmazonS3 class (we should probably be injecting this)
    $this->s3 = new AmazonS3();

    // if we are in debug mode put the http-client into debug mode
    $this->s3->enable_debug_mode($this->debug);
  }

/*******************************************************************************
 * API
 ******************************************************************************/

  /**
   * add/upload a file to the current path
   *
   * @return  void
   */
  public function add() {
    // helpful debug info for dealing with uploads (you'll thank me later :)
    $debugInfo[] = ini_get('post_max_size');
    $debugInfo[] = ini_get('upload_max_filesize');
    $debugInfo[] = ini_get('max_input_time');
    $debugInput  = vsprintf(self::MSG_DEBUG_UPLOAD_INI, $debugInfo);
    $this->debug($debugInput);
    $this->debug(sprintf('Max Upload Size: %dMB', $this->uploadMaxMb));

    // check for uploaded file
		if(empty($_FILES['newfile']['tmp_name']) || !is_uploaded_file($_FILES['newfile']['tmp_name'])) {
			$this->error(sprintf($this->lang('INVALID_FILE_UPLOAD')), true);
		}

		if(!$this->isUploadValidSize()) {
			$this->error(sprintf($this->lang('UPLOAD_FILES_SMALLER_THAN'), $this->uploadMaxMb . 'Mb'), true);
		}

		if (!$this->isUploadValidType($_FILES['newfile']['tmp_name'])) {
				$this->error(sprintf($this->lang('UPLOAD_IMAGES_TYPE_JPEG_GIF_PNG')), true);
		}

    // unless we are in overwrite mode, we need a unique file name
		if (empty($this->config['upload']['overwrite'])) {
      $this->uniqueFile($this->buildFullPath(), $_FILES['newfile']['name']);
    }

    // write new file to s3
    $newFile  = sprintf('%s/%s', $this->buildFullPath(), $_FILES['newfile']['name']);
    $response = $this->s3->create_object($this->bucket, $newFile, array(
        'fileUpload' => $_FILES['newfile']['tmp_name'],
        'acl'        => AmazonS3::ACL_PUBLIC,
        'contentType'=> $_FILES['newfile']['type']
    ));

    if (!$response->isOK()) {
			$this->error(sprintf($this->lang('INVALID_FILE_UPLOAD')), true);
    }

		$return = array(
      'Path'  => $this->buildFullPath(),
			'Name'  => $_FILES['newfile']['name'],
			'Error' => '',
			'Code'  => 0
    );

		exit(sprintf('<textarea>%s</textarea>', json_encode($return)));
  }

  /**
   * create a new folder in the current directory
   *
   * @return  array
   */
  public function addfolder() {
    // set response defaults
    $Code  = 0;
    $Error = '';

    // $parent is the directory where the new folder is created
    $Parent    = $this->buildFullPath();

    // $Name is the name of the new folder/directory
    $Name      = $this->sanitizePath($this->get['name']);

    // build the full path via the $Parent and $Name
    $fullPath  = sprintf('%s/%s', $Parent, $Name);

    $acl  = AmazonS3::ACL_PUBLIC;
    $body = null;
    $contentType = 'binary/octet-stream';

    $response = $this->s3->create_object($this->bucket, sprintf('%s/', $fullPath), compact('acl', 'body', 'contentType'));

    if (!$response->isOK()) {
      $Code  = -1;
      $Error = 'Unable to add folder';
    }

    return compact('Parent', 'Name', 'Code', 'Error');
  }

  /**
   * remove a file/directory
   *
   * @return  array
   *
   * @todo    need to think through how to delete objects in a timely fashion
   *          while not confusing the user should they refresh before s3 becomes
   *          consistent (you know, since they replicate all over the world).
   *
   * @todo    thinking this would be cleaner if refactored into multiple methods
   */
  public function delete() {
    $isDir    = $this->isDir($this->metadata($this->buildFullPath() . '/'));

    $Path     = $isDir
              ? "{$this->buildFullPath()}/"
              : $this->buildFullPath();

    $contents = $isDir
              ? $this->s3->get_object_list($this->bucket, array('prefix' => $Path))
              : array();

    // NOTE: we do this instead of isDir as we only care if there are files under this prefix
    if (count($contents) > 1) {
      $this->error('Unfortunately, we are currently unable to delete a non-empty directory.', false);
    }

    $response = $this->s3->delete_object($this->bucket, $Path);
    $Error    = $response->isOK() ? '' : sprintf($this->lang('INVALID_DIRECTORY_OR_FILE'), $Path);
    $Code     = $response->isOK() ? 0  : -1;

    return compact('Error', 'Code', 'Path');
  }

  /**
   * download a file
   *
   * @return  void
   */
  public function download() {
    $fileInfo = $this->fileInfo($this->buildFullPath());
    $url      = 'http:'.$fileInfo['Preview'];
    $fileName = basename($fileInfo['Filename']);
    $fileSize = $fileInfo['Size'];

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename={$fileName}");
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header("Content-Length: {$fileSize}");
    ob_clean();
    flush();
    readfile($url);
    exit;
  }

  /**
   * Retrieve contents of the given directory (indicated by a “path” parameter).
   *
   * @return  array {FILE_PATH => [FILE_INFO_1..FILE_INFO_N]}
   *
   * @todo   need to respect files that are not public by scrubbing them from the list
   */
	public function getfolder() {
    // the path prefix is the full requested path (this should equate to a folder)
    $directory = $this->sanitizePath($this->buildFullPath()) . '/';
    $metadata  = $this->metadata($directory);

    // [optionally] create root directory
    if ($this->createRootDir) { $this->createDirectory($this->buildRootPath()); }

    // bail-out if the requested directory does not exist
    if (!$this->isDir($metadata)) {
      $error = sprintf($this->lang('DIRECTORY_NOT_EXIST'), $directory);
      $this->error($error);
    }

    // request a list of objects filtered by prefix
    $objects = $this->s3->get_object_list($this->bucket, array('prefix' => $directory));

    // filter out the root path object (root path is referred to as prefix here)
    $objects = array_filter($objects, function($filePath) use($directory){
      return trim($directory, '/ ') !== trim($filePath, '/ ');
    });

    $_this   = $this;
    $objects = array_filter($objects, function($filePath) use($_this, $directory){
      $filePath = preg_replace("@^{$directory}@", '', $filePath);
      $fileInfo = explode('/', $filePath);
      return !isset($fileInfo[1]) || $fileInfo[1] === '';
    });

    // build the list of files
    $fileList = array();
    foreach ($objects as $filePath) {
      // make each $filePath a key; value is a hash containing the file metadata
      $fileList[$filePath] = $this->fileInfo($filePath);
    }

    return $fileList;
	}

  /**
   * retrieve properties of requested file as a hash
   *
   * @return  array
   */
  public function getinfo() {
    $fileInfo = $this->fileInfo($this->buildFullPath());

    return $fileInfo;
  }

  /**
   * rename file/directory
   *
   * @return string
   */
  public function rename() {
    $oldPath  = $this->sanitizePath($this->get['old']);

    $isDir    = $this->isDir($this->metadata("{$oldPath}/"));

    $oldPath .= ($isDir) ? '/' : '';

    $contents = ($isDir)
              ? $this->s3->get_object_list($this->bucket, array('prefix' => $oldPath))
              : array();

    // NOTE: we do this instead of isDir as we only care if there are files under this prefix
    if (count($contents) > 1) {
      $this->error('Unfortunately, we are currently unable to rename a non-empty directory.', false);
    }

    $pathInfo = pathinfo($oldPath);
    $dirName  = $pathInfo['dirname'];
    $baseName = $pathInfo['basename'];
    $newFile  = $this->get['new'];
    $newPath  = join('/', array($dirName, $newFile));

    if ($isDir) {
      $response = $this->createDirectory($newPath);
    } else {
      $response = $this->s3->copy_object(
          array('bucket' => $this->bucket, 'filename' => $oldPath),
          array('bucket' => $this->bucket, 'filename' => $newPath),
          array('acl' => AmazonS3::ACL_PUBLIC)
      );
    }

    if ($response->isOK()) {
      $this->s3->delete_object($this->bucket, $oldPath);
    }

    return array(
			'Error'    => '',
			'Code'     => 0,
			'Old Path' => $oldPath,
			'Old Name' => $baseName,
			'New Path' => $newPath,
			'New Name' => $newFile
    );
  }

/*******************************************************************************
 * Utility
 ******************************************************************************/

  /**
   * Builds a path prefix based on configuration
   *
   * @return  string  path relative to root without leading/trailing slash
   */
  protected function buildRootPath() {
    // build the root directory
    $paths[] = $this->rootDirectory;

    // build the path prefix string
    $rootPath = join('/', array_filter($paths));

    return $rootPath;
  }

  /**
   * Builds a fully qualified path
   *
   * @return  string
   */
  protected function buildFullPath() {
    // uploading has a slightly different API
    if (isset($_POST['mode']) && in_array($_POST['mode'], array('add'))) {
      return $this->sanitizePath($this->post['currentpath']);
    }

    // essentially everything else
    return $this->sanitizePath($this->get['path']) ?: $this->buildRootPath();
  }

  /**
   * write a message to the configured error log
   *
   * see: ini_get('error_log')
   *
   * @param   string  $message
   *
   * @return  boolean
   */
  protected function debug($message) {
    // write to the configured error log only if we are in debug mode
    return $this->debug ? error_log($message, 0) : null;
  }

  /**
   * build a hash from the file's information
   *
   * @param   array $filePath
   *
   * @return  array
   */
  protected function fileInfo($filePath) {
    $metadata    = $this->metadata($filePath) ?: $this->metadata($filePath . '/');
    $isDirectory = $this->isDir($metadata);

    //if (!$metadata)
    $pathInfo = pathinfo($filePath);

    // obtain a copy of the defaults
    $fileInfo = $this->defaultInfo;

    // augment with metadata and other misc attributes
    $fileInfo['metadata']    = $metadata;
    $fileInfo['ContentType'] = $metadata['ContentType'];

    // attributes (general) -- NOTE: the ternarys are a bit of a smell (refactor?)
    $fileInfo['Path']        = $filePath;
    $fileInfo['Size']        = $metadata['Size'];
    $fileInfo['Filename']    = $pathInfo['basename'];
    $fileInfo['File Type']   = ($isDirectory) ? self::FILE_TYPE_DIR : $pathInfo['extension'];
    $fileInfo['Preview']     = ($isDirectory) ? $this->folderIcon   : sprintf('//%s/%s', $this->domain, $filePath);

    // attributes (properties)
    $fileInfo['Properties']['Date Created']  = $this->formatDate($metadata['Headers']['date']);
    $fileInfo['Properties']['Date Modified'] = $this->formatDate($metadata['Headers']['last-modified']);

    return $fileInfo;
  }

  /**
   * retrieve formated date string per the API spec
   *
   * @param   string $date
   * @return  string
   */
  protected function formatDate($date) {
    $timestamp = strtotime($date);
    return date(self::DATE_FORMAT, $timestamp);
  }

  /**
   * does the metadata array reflect a pseudo directory/folder?
   *
   * @param   array|boolean $metadata
   *
   * @return  boolean
   *
   * @todo    doing it this way saves a metadata call, but after thinking about
   *          it, it is sort of silly and doesn't result in that much savings.
   *
   * @todo    refactor to:
   *            1) check if object/path exists (add slash)
   *            2) check filesize and content-type
   */
  protected function isDir($metadata) {
    // short-circuit if file doesn't exist
    if (!$this->fileExists($metadata)) { return false; }

    $contentType = $metadata['ContentType'];
    $fileSize    = (int) $metadata['Size'];

    return ($fileSize === 0) && ($contentType === 'binary/octet-stream');
  }

  /**
   * create a directory
   *
   * @param   string  $filePath
   *
   * @return  CFResponse
   */
  protected function createDirectory($filePath) {
    // bail-out of the directory already exists
    if ($this->isDir($this->metadata($filePath))) {
      $this->debug(sprintf(self::MSG_DIR_NOTCREATED_EXISTS, $filePath));
      return;
    }

    // properties that make up a directory
    $acl         = AmazonS3::ACL_PUBLIC;
    $body        = null;
    $contentType = 'binary/octet-stream';

    // create directory
    $response    = $this->s3->create_object(
      $this->bucket,
      sprintf('%s/', $filePath),
      compact('acl', 'body', 'contentType')
    );

    return $response;
  }

  /**
   * infer that the file/directory does not exist based on provided metadata
   *
   * @param   array|boolean $metadata
   *
   * @return  boolean
   */
  protected function fileExists($metadata) {
    return !($metadata === false);
  }

  /**
   * is the uploaded file's size valid?
   *
   * @return  boolean
   */
  protected function isUploadValidSize() {
    $sizeInBytes = $_FILES['newfile']['size'];
    $sizeInMb    = ($sizeInBytes / 1024) / 1024;
    $this->debug(sprintf('Uploaded File Size: %dMB', $sizeInMb));

    return $sizeInBytes < $this->uploadMaxBytes();
  }

  /**
   * is the uploaded file a valid type?
   *
   * @param   string  $filePath
   *
   * @return  boolean
   */
  protected function isUploadValidType($filePath) {
    // if we aren't restricted to images only, return true
    $imagesOnly = !empty($this->config['upload']['imagesonly']);
    if (!$imagesOnly) { return true; }

    // bail out if this is not an image
    $imageInfo  = getimagesize($filePath);
    if (false === $imageInfo) { return false; }

    // support only jpeg, gif, png (see: http://php.net/manual/en/image.constants.php)
    return in_array($imageInfo[2], array(1, 2, 3));
  }

  /**
   * retrieve a file's metadata
   *
   * @param   string  $filePath
   *
   * @param   array
   */
  protected function metadata($filePath) {
    return $this->s3->get_object_metadata($this->bucket, $filePath);
  }

  /**
   * apply common filters, etc. to a file path
   *
   * @param   string  $filePath
   *
   * @return  string
   */
  protected function sanitizePath($filePath) {
    return trim(rawurldecode($filePath), '/ ');
  }

  /**
   * Ensure that an uploaded file is unique
   *
   * @param string  $prefix
   * @param string  $fileName
   */
  protected function uniqueFile($prefix, $fileName) {
      // request a list of objects filtered by prefix
			$list = $this->s3->get_object_list($this->bucket, compact('prefix'));
      $path = join('/', array($prefix, $fileName));

			$i = 0;
			while (in_array($path, $list)) {
				$i++;
				$parts   = explode('.', $fileName);
				$ext     = array_pop($parts);
				$parts   = array_diff($parts, array("copy{$i}", "copy".($i-1)));
				$parts[] = "copy{$i}";
				$parts[] = $ext;
        $path    = join('/', array($prefix, implode('.', $parts)));
      }

      if (isset($parts)) {
        $_FILES['newfile']['name'] = implode('.', $parts);
      }
  }

  /**
   * the maximum file upload size in bytes
   *
   * @return  integer
   */
  protected function uploadMaxBytes() {
    return (int) ($this->uploadMaxMb * 1024 * 1024);
  }

}
