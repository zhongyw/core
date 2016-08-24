<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Files\Storage\Wrapper;

use OC\Files\Cache\Wrapper\CacheJail;
use OCP\Lock\ILockingProvider;

/**
 * Jail to a subdirectory of the wrapped storage
 *
 * This restricts access to a subfolder of the wrapped storage with the subfolder becoming the root folder new storage
 */
class Jail extends Wrapper {
	/**
	 * @var string
	 */
	protected $rootPath;

	/**
	 * @param array $arguments ['storage' => $storage, 'mask' => $root]
	 *
	 * $storage: The storage that will be wrapper
	 * $root: The folder in the wrapped storage that will become the root folder of the wrapped storage
	 */
	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->rootPath = $arguments['root'];
	}

	public function getSourcePath($path) {
		if ($path === '') {
			return $this->rootPath;
		} else {
			return $this->rootPath . '/' . $path;
		}
	}

	public function getId() {
		return 'link:' . parent::getId() . ':' . $this->rootPath;
	}

	/**
	 * see http://php.net/manual/en/function.mkdir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function mkdir($path) {
		return $this->storage->mkdir($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.rmdir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path) {
		return $this->storage->rmdir($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.opendir.php
	 *
	 * @param string $path
	 * @return resource
	 */
	public function opendir($path) {
		return $this->storage->opendir($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.is_dir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function is_dir($path) {
		return $this->storage->is_dir($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.is_file.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function is_file($path) {
		return $this->storage->is_file($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.stat.php
	 * only the following keys are required in the result: size and mtime
	 *
	 * @param string $path
	 * @return array
	 */
	public function stat($path) {
		return $this->storage->stat($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.filetype.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function filetype($path) {
		return $this->storage->filetype($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.filesize.php
	 * The result for filesize when called on a folder is required to be 0
	 *
	 * @param string $path
	 * @return int
	 */
	public function filesize($path) {
		return $this->storage->filesize($this->getSourcePath($path));
	}

	/**
	 * check if a file can be created in $path
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isCreatable($path) {
		return $this->storage->isCreatable($this->getSourcePath($path));
	}

	/**
	 * check if a file can be read
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isReadable($path) {
		return $this->storage->isReadable($this->getSourcePath($path));
	}

	/**
	 * check if a file can be written to
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isUpdatable($path) {
		return $this->storage->isUpdatable($this->getSourcePath($path));
	}

	/**
	 * check if a file can be deleted
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isDeletable($path) {
		return $this->storage->isDeletable($this->getSourcePath($path));
	}

	/**
	 * check if a file can be shared
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isSharable($path) {
		return $this->storage->isSharable($this->getSourcePath($path));
	}

	/**
	 * get the full permissions of a path.
	 * Should return a combination of the PERMISSION_ constants defined in lib/public/constants.php
	 *
	 * @param string $path
	 * @return int
	 */
	public function getPermissions($path) {
		return $this->storage->getPermissions($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.file_exists.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function file_exists($path) {
		return $this->storage->file_exists($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.filemtime.php
	 *
	 * @param string $path
	 * @return int
	 */
	public function filemtime($path) {
		return $this->storage->filemtime($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.file_get_contents.php
	 *
	 * @param string $path
	 * @return string
	 */
	public function file_get_contents($path) {
		return $this->storage->file_get_contents($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.file_put_contents.php
	 *
	 * @param string $path
	 * @param string $data
	 * @return bool
	 */
	public function file_put_contents($path, $data) {
		return $this->storage->file_put_contents($this->getSourcePath($path), $data);
	}

	/**
	 * see http://php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function unlink($path) {
		return $this->storage->unlink($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.rename.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 */
	public function rename($path1, $path2) {
		return $this->storage->rename($this->getSourcePath($path1), $this->getSourcePath($path2));
	}

	/**
	 * see http://php.net/manual/en/function.copy.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 */
	public function copy($path1, $path2) {
		return $this->storage->copy($this->getSourcePath($path1), $this->getSourcePath($path2));
	}

	/**
	 * see http://php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource
	 */
	public function fopen($path, $mode) {
		return $this->storage->fopen($this->getSourcePath($path), $mode);
	}

	/**
	 * get the mimetype for a file or folder
	 * The mimetype for a folder is required to be "httpd/unix-directory"
	 *
	 * @param string $path
	 * @return string
	 */
	public function getMimeType($path) {
		return $this->storage->getMimeType($this->getSourcePath($path));
	}

	/**
	 * see http://php.net/manual/en/function.hash.php
	 *
	 * @param string $type
	 * @param string $path
	 * @param bool $raw
	 * @return string
	 */
	public function hash($type, $path, $raw = false) {
		return $this->storage->hash($type, $this->getSourcePath($path), $raw);
	}

	/**
	 * see http://php.net/manual/en/function.free_space.php
	 *
	 * @param string $path
	 * @return int
	 */
	public function free_space($path) {
		return $this->storage->free_space($this->getSourcePath($path));
	}

	/**
	 * search for occurrences of $query in file names
	 *
	 * @param string $query
	 * @return array
	 */
	public function search($query) {
		return $this->storage->search($query);
	}

	/**
	 * see http://php.net/manual/en/function.touch.php
	 * If the backend does not support the operation, false should be returned
	 *
	 * @param string $path
	 * @param int $mtime
	 * @return bool
	 */
	public function touch($path, $mtime = null) {
		return $this->storage->touch($this->getSourcePath($path), $mtime);
	}

	/**
	 * get the path to a local version of the file.
	 * The local version of the file can be temporary and doesn't have to be persistent across requests
	 *
	 * @param string $path
	 * @return string
	 */
	public function getLocalFile($path) {
		return $this->storage->getLocalFile($this->getSourcePath($path));
	}

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 *
	 * hasUpdated for folders should return at least true if a file inside the folder is add, removed or renamed.
	 * returning true for other changes in the folder is optional
	 */
	public function hasUpdated($path, $time) {
		return $this->storage->hasUpdated($this->getSourcePath($path), $time);
	}

	/**
	 * get a cache instance for the storage
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the cache
	 * @return \OC\Files\Cache\Cache
	 */
	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		$sourceCache = $this->storage->getCache($this->getSourcePath($path), $storage);
		return new CacheJail($sourceCache, $this->rootPath);
	}

	/**
	 * get the user id of the owner of a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getOwner($path) {
		return $this->storage->getOwner($this->getSourcePath($path));
	}

	/**
	 * get a watcher instance for the cache
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the watcher
	 * @return \OC\Files\Cache\Watcher
	 */
	public function getWatcher($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		return $this->storage->getWatcher($this->getSourcePath($path), $storage);
	}

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getETag($path) {
		return $this->storage->getETag($this->getSourcePath($path));
	}

	/**
	 * @param string $path
	 * @return array
	 */
	public function getMetaData($path) {
		return $this->storage->getMetaData($this->getSourcePath($path));
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function acquireLock($path, $type, ILockingProvider $provider) {
		$this->storage->acquireLock($this->getSourcePath($path), $type, $provider);
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function releaseLock($path, $type, ILockingProvider $provider) {
		$this->storage->releaseLock($this->getSourcePath($path), $type, $provider);
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function changeLock($path, $type, ILockingProvider $provider) {
		$this->storage->changeLock($this->getSourcePath($path), $type, $provider);
	}

	/**
	 * Resolve the path for the source of the share
	 *
	 * @param string $path
	 * @return array
	 */
	public function resolvePath($path) {
		return [$this->storage, $this->getSourcePath($path)];
	}

	/**
	 * @param \OCP\Files\Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function copyFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		if ($sourceStorage === $this) {
			return $this->copy($sourceInternalPath, $targetInternalPath);
		}
		return $this->storage->copyFromStorage($sourceStorage, $sourceInternalPath, $this->getSourcePath($targetInternalPath));
	}

	/**
	 * @param \OCP\Files\Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function moveFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		if ($sourceStorage === $this) {
			return $this->rename($sourceInternalPath, $targetInternalPath);
		}
		return $this->storage->moveFromStorage($sourceStorage, $sourceInternalPath, $this->getSourcePath($targetInternalPath));
	}
}
