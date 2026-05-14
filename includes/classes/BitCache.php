<?php
/**
 * @package kernel
 * @author spiderr <spiderr@bitweaver.org>
 * Copyright (c) 2020 bitweaver.org, All Rights Reserved
 * This source file is subject to the 2.0 GNU GENERAL PUBLIC LICENSE. 
 *
 * A basic library to handle caching of various data
 */

namespace Bitweaver;

class BitCache {
	/**
	 * Used to store the directory used to store the cache files.
	 * @private
	 */
	public $mFolder;
	public $mUrl;

	/**
	 * Will check the temp cache folder for existence and create it if necessary.
	 *
	 * @param string $pSubdir use a specifed subdirectory
	 * @param boolean $pUseStorage use the storage directory instead of the temp dir. only makes sense if you need direct webaccess to stored cachefiles
	 * @return void
	 */
	public function __construct( $pSubdir = 'cache', $pUseStorage = false ) {
		if( $pUseStorage and defined(STORAGE_PKG_PATH) ) {
			$this->mFolder = STORAGE_PKG_PATH.$pSubdir;
			$this->mUrl = STORAGE_PKG_URL.$pSubdir;
		} elseif( defined( "TEMP_PKG_PATH" )) {
			$this->mFolder = TEMP_PKG_PATH.$pSubdir;
		} elseif( getenv( "TMP" )) {
			$this->mFolder = getenv( "TMP" )."/".$pSubdir;
		} else {
			$this->mFolder = "/tmp/".$pSubdir;
		}

		if( !is_dir( $this->mFolder ) && !KernelTools::mkdir_p( $this->mFolder )) {
			error_log( 'Can not create the cache directory: '.$this->mFolder );
		}
	}

	/**
	 * getCacheFile
	 *
	 * @param string $pFile
	 * @return string filepath on success, false on failure
	 */
	public function getCacheFile( $pFile ) {
		if( !empty( $pFile )) {
			return $this->mFolder."/".$pFile;
		}
			return false;

	}

	/**
	 * getCacheUrl will get the URL to the cache file - only works when you're using BitCache with the UseStorage option
	 *
	 * @param string $pFile
	 * @return string fileurl on success, false on failure
	 */
	public function getCacheUrl( $pFile ) {
		if( !empty( $this->mUrl ) && !empty( $pFile )) {
			return $this->mUrl.'/'.$pFile;
		}
		return false;
	}

	/**
	 * Used to check if an object is cached.
	 *
	 * @param string $pFile name of the file we want to check for
	 * @param numeric $pModTime Pass in the modification time you wish to check against
	 * @return bool true if cached object exists
	 */
	function isCached( $pFile, $pModTime = false ) {
		if( !empty( $pFile ) && is_readable( $this->getCacheFile( $pFile ))) {
			// compare the cache filemtime to the desired file
			if( is_numeric( $pModTime )) {
				$isModified = filemtime( $this->getCacheFile( $pFile )) < $pModTime;
			}
			return empty( $isModified );
		}
			return false;

	}

	/**
	 * Used to retrieve an object if cached.
	 *
	 * @param string $pFile the unique identifier used to retrieve the cached item
	 * @return string if cached object exists
	 */
	function readCacheFile( $pFile ) {
		if( $this->isCached( $pFile )) {
			$cacheFile = $this->getCacheFile( $pFile );
			if( $h = fopen( $cacheFile, 'r' )) {
				$ret = fread( $h, filesize( $cacheFile ) );
				fclose( $h );
			}
		}

		return !empty( $ret ) ? $ret : null;
	}

	/**
	 * Used to remove a cached object.
	 *
	 * @param string $pFilepKey the unique identifier used to retrieve the cached item
	 */
	public function expungeCacheFile( $pFile ) {
		if( $this->isCached( $pFile )) {
			unlink( $this->getCacheFile( $pFile ));
		}
	}

	/**
	 * remove the entire cache in the cache folder
	 *
	 * @return bool true on success, false on failure
	 */
	public function expungeCache() {
		// the only places we can write to in bitweaver are temp and storage
		$subdir = str_replace( STORAGE_PKG_PATH, "", $this->mFolder );
		if(( strpos( $this->mFolder, STORAGE_PKG_PATH ) === 0 && $subdir != "users" && $subdir != "common" ) || strpos( $this->mFolder, TEMP_PKG_PATH ) === 0 ) {
			$ret = KernelTools::unlink_r( $this->mFolder );
			if( !is_dir( $this->mFolder )) {
				KernelTools::mkdir_p( $this->mFolder );
			}
		}
		return $ret;
	}

	/**
	 * writeCacheFile
	 *
	 * @param string $pFile file to write to
	 * @param string $pData string to write to file
	 * @return void
	 */
	public function writeCacheFile( $pFile, $pData ) {
		if( !empty( $pData ) && !empty( $pFile )) {
			if( $h = fopen( $this->getCacheFile( $pFile ), 'w' )) {
				fwrite( $h, $pData );
				fclose( $h );
			}
		}
	}
}