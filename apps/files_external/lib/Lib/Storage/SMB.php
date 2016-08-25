<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Jesús Macias <jmacias@solidgear.es>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Philipp Kapfer <philipp.kapfer@gmx.at>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH.
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

namespace OCA\Files_External\Lib\Storage;

use Icewind\SMB\Exception\ConnectException;
use Icewind\SMB\Exception\Exception;
use Icewind\SMB\Exception\ForbiddenException;
use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\NativeServer;
use Icewind\SMB\Server;
use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Cache\CappedMemoryCache;
use OC\Files\Filesystem;
use OCP\Files\StorageNotAvailableException;
use OCP\Util;

class SMB extends \OC\Files\Storage\Common {
	/**
	 * @var \Icewind\SMB\Server
	 */
	protected $server;

	/**
	 * @var \Icewind\SMB\Share
	 */
	protected $share;

	/**
	 * @var string
	 */
	protected $root;

	/**
	 * @var \Icewind\SMB\FileInfo[]
	 */
	protected $statCache;

	public function __construct($params) {
		$loggedParams = $params;
		// remove password from log if it is set
		if (!empty($loggedParams['password'])) {
			$loggedParams['password'] = '***removed***';
		}
		$this->log('enter: '.__FUNCTION__.'('.json_encode($loggedParams).')');

		if (isset($params['host']) && isset($params['user']) && isset($params['password']) && isset($params['share'])) {
			if (Server::NativeAvailable()) {
				$this->server = new NativeServer($params['host'], $params['user'], $params['password']);
			} else {
				$this->server = new Server($params['host'], $params['user'], $params['password']);
			}
			$this->share = $this->server->getShare(trim($params['share'], '/'));

			$this->root = isset($params['root']) ? $params['root'] : '/';
			if (!$this->root || $this->root[0] != '/') {
				$this->root = '/' . $this->root;
			}
			if (substr($this->root, -1, 1) != '/') {
				$this->root .= '/';
			}
		} else {
			$ex = new \Exception('Invalid configuration');
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		}
		$this->statCache = new CappedMemoryCache();
		$this->log('leave: '.__FUNCTION__.', getId:'.$this->getId());
	}

	/**
	 * @return string
	 */
	public function getId() {
		// FIXME: double slash to keep compatible with the old storage ids,
		// failure to do so will lead to creation of a new storage id and
		// loss of shares from the storage
		return 'smb::' . $this->server->getUser() . '@' . $this->server->getHost() . '//' . $this->share->getName() . '/' . $this->root;
	}

	/**
	 * @param string $path
	 * @return string
	 */
	protected function buildPath($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = Filesystem::normalizePath($this->root . '/' . $path, true, false, true);
		return $this->leave(__FUNCTION__, $result);
	}

	/**
	 * @param string $path
	 * @return \Icewind\SMB\IFileInfo
	 * @throws StorageNotAvailableException
	 */
	protected function getFileInfo($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		try {
			$path = $this->buildPath($path);
			if (!isset($this->statCache[$path])) {
				$this->log("stat fetching '{$this->root}$path'");
				$this->statCache[$path] = $this->share->stat($path);
			} else {
				$this->log("stat cache hit for '$path'");
			}
			$result = $this->statCache[$path];
		} catch (ConnectException $e) {
			$ex = new StorageNotAvailableException(
				$e->getMessage(), $e->getCode(), $e);
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		}
		return $this->leave(__FUNCTION__, $result);
	}

	/**
	 * @param string $path
	 * @return \Icewind\SMB\IFileInfo[]
	 * @throws StorageNotAvailableException
	 */
	protected function getFolderContents($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		try {
			$path = $this->buildPath($path);
			$result = $this->share->dir($path);
			foreach ($result as $file) {
				$this->statCache[$path . '/' . $file->getName()] = $file;
			}
		} catch (ConnectException $e) {
			$ex = new StorageNotAvailableException(
				$e->getMessage(), $e->getCode(), $e);
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		}
		return $this->leave(__FUNCTION__, $result);
	}

	/**
	 * @param \Icewind\SMB\IFileInfo $info
	 * @return array
	 */
	protected function formatInfo($info) {
		return array(
			'size' => $info->getSize(),
			'mtime' => $info->getMTime()
		);
	}

	/**
	 * @param string $path
	 * @return array
	 */
	public function stat($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = $this->formatInfo($this->getFileInfo($path));
		return $this->leave(__FUNCTION__, $result);
	}

	/**
	 * @param string $path
	 * @return bool
	 * @throws StorageNotAvailableException
	 */
	public function unlink($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = false;
		try {
			if ($this->is_dir($path)) {
				$result = $this->rmdir($path);
			} else {
				$path = $this->buildPath($path);
				unset($this->statCache[$path]);
				$this->share->del($path);
				$result = true;
			}
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ConnectException $e) {
			$ex = new StorageNotAvailableException(
				$e->getMessage(), $e->getCode(), $e);
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		}
		return $this->leave(__FUNCTION__, $result);
	}

	/**
	 * @param string $path1 the old name
	 * @param string $path2 the new name
	 * @return bool
	 * @throws StorageNotAvailableException
	 */
	public function rename($path1, $path2) {
		$this->log('enter: '.__FUNCTION__."($path1, $path2)");
		$result = false;
		try {
			$this->remove($path2);
			$path1 = $this->buildPath($path1);
			$path2 = $this->buildPath($path2);
			$result = $this->share->rename($path1, $path2);
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ConnectException $e) {
			$ex = new StorageNotAvailableException(
				$e->getMessage(), $e->getCode(), $e);
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		}
		return $this->leave(__FUNCTION__, $result);
	}

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path, $time) {
		$this->log('enter: '.__FUNCTION__."($path, $time)");
		if (!$path and $this->root == '/') {
			// mtime doesn't work for shares, but giving the nature of the backend,
			// doing a full update is still just fast enough
			$result = true;
		} else {
			$actualTime = $this->filemtime($path);
			$result = $actualTime > $time;
		}
		return $this->leave(__FUNCTION__, $result);
	}

	/**
	 * @param string $path
	 * @param string $mode
	 * @return resource
	 * @throws StorageNotAvailableException
	 */
	public function fopen($path, $mode) {
		$this->log('enter: '.__FUNCTION__."($path, $mode)");
		$fullPath = $this->buildPath($path);
		$result = false;
		try {
			switch ($mode) {
				case 'r':
				case 'rb':
					if ($this->file_exists($path)) {
						$result = $this->share->read($fullPath);
					}
					break;
				case 'w':
				case 'wb':
					$source = $this->share->write($fullPath);
					$result = CallBackWrapper::wrap($source, null, null, function () use ($fullPath) {
						unset($this->statCache[$fullPath]);
					});
					break;
				case 'a':
				case 'ab':
				case 'r+':
				case 'w+':
				case 'wb+':
				case 'a+':
				case 'x':
				case 'x+':
				case 'c':
				case 'c+':
					//emulate these
					if (strrpos($path, '.') !== false) {
						$ext = substr($path, strrpos($path, '.'));
					} else {
						$ext = '';
					}
					if ($this->file_exists($path)) {
						if (!$this->isUpdatable($path)) {
							break;
						}
						$tmpFile = $this->getCachedFile($path);
					} else {
						if (!$this->isCreatable(dirname($path))) {
							break;
						}
						$tmpFile = \OCP\Files::tmpFile($ext);
					}
					$source = fopen($tmpFile, $mode);
					$share = $this->share;
					$result = CallbackWrapper::wrap($source, null, null, function () use ($tmpFile, $fullPath, $share) {
						unset($this->statCache[$fullPath]);
						$share->put($tmpFile, $fullPath);
						unlink($tmpFile);
					});
			}
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ConnectException $e) {
			$ex = new StorageNotAvailableException(
				$e->getMessage(), $e->getCode(), $e);
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		}
		return $this->leave(__FUNCTION__, $result);
	}

	public function rmdir($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = false;
		try {
			$this->statCache = array();
			$content = $this->share->dir($this->buildPath($path));
			foreach ($content as $file) {
				if ($file->isDirectory()) {
					$this->rmdir($path . '/' . $file->getName());
				} else {
					$this->share->del($file->getPath());
				}
			}
			$this->share->rmdir($this->buildPath($path));
			$result = true;
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ConnectException $e) {
			$ex = new StorageNotAvailableException(
				$e->getMessage(), $e->getCode(), $e);
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		}
		return $this->leave(__FUNCTION__, $result);
	}

	public function touch($path, $time = null) {
		$this->log('enter: '.__FUNCTION__."($path, $time)");
		try {
			if (!$this->file_exists($path)) {
				$fh = $this->share->write($this->buildPath($path));
				fclose($fh);
				$result = true;
			} else {
				$result = false;
			}
		} catch (ConnectException $e) {
			$ex = new StorageNotAvailableException(
				$e->getMessage(), $e->getCode(), $e);
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		}
		return $this->leave(__FUNCTION__, $result);
	}

	public function opendir($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = false;
		try {
			$files = $this->getFolderContents($path);
			$names = array_map(function ($info) {
				/** @var \Icewind\SMB\IFileInfo $info */
				return $info->getName();
			}, $files);
			$result = IteratorDirectory::wrap($names);
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		}
		return $this->leave(__FUNCTION__, $result);
	}

	public function filetype($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = false;
		try {
			$result = $this->getFileInfo($path)->isDirectory() ? 'dir' : 'file';
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		}
		return $this->leave(__FUNCTION__, $result);
	}

	public function mkdir($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = false;
		$path = $this->buildPath($path);
		try {
			$this->share->mkdir($path);
			$result = true;
		} catch (ConnectException $e) {
			$ex = new StorageNotAvailableException(
				$e->getMessage(), $e->getCode(), $e);
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		} catch (Exception $e) {
			$this->swallow(__FUNCTION__, $e);
		}
		return $this->leave(__FUNCTION__, $result);
	}

	public function file_exists($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = false;
		try {
			$this->getFileInfo($path);
			$result = true;
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ConnectException $e) {
			$ex = new StorageNotAvailableException(
				$e->getMessage(), $e->getCode(), $e);
			$this->leave(__FUNCTION__, $ex);
			throw $ex;
		}
		return $this->leave(__FUNCTION__, $result);
	}

	public function isReadable($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = false;
		try {
			$info = $this->getFileInfo($path);
			$result = !$info->isHidden();
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		}
		return $this->leave(__FUNCTION__, $result);
	}

	public function isUpdatable($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = false;
		try {
			$info = $this->getFileInfo($path);
			// following windows behaviour for read-only folders: they can be written into
			// (https://support.microsoft.com/en-us/kb/326549 - "cause" section)
			$result = !$info->isHidden() && (!$info->isReadOnly() || $this->is_dir($path));
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		}
		return $this->leave(__FUNCTION__, $result);
	}

	public function isDeletable($path) {
		$this->log('enter: '.__FUNCTION__."($path)");
		$result = false;
		try {
			$info = $this->getFileInfo($path);
			$result = !$info->isHidden() && !$info->isReadOnly();
		} catch (NotFoundException $e) {
			$this->swallow(__FUNCTION__, $e);
		} catch (ForbiddenException $e) {
			$this->swallow(__FUNCTION__, $e);
		}
		return $this->leave(__FUNCTION__, $result);
	}

	/**
	 * check if smbclient is installed
	 */
	public static function checkDependencies() {
		return (
			(bool)\OC_Helper::findBinaryPath('smbclient')
			|| Server::NativeAvailable()
		) ? true : ['smbclient'];
	}

	/**
	 * Test a storage for availability
	 *
	 * @return bool
	 */
	public function test() {
		$this->log('enter: '.__FUNCTION__."()");
		$result = false;
		try {
			$result = parent::test();
		} catch (Exception $e) {
			$this->swallow(__FUNCTION__, $e);
		}
		return $this->leave(__FUNCTION__, $result);
	}


	/**
	 * @param string $message
	 * @param int $level
	 * @param string $from
	 */
	private function log($message, $level = Util::DEBUG, $from = 'wnd') {
		if (\OC::$server->getConfig()->getSystemValue('wnd.logging.enable', false) === true) {
			Util::writeLog($from, $message, $level);
		}
	}

	/**
	 * if wnd.logging.enable is set to true in the config will log a leave line
	 * with the given function, the return value or the exception
	 *
	 * @param $function
	 * @param mixed $result an exception will be logged and then returned
	 * @return mixed
	 */
	private function leave($function, $result) {
		if (\OC::$server->getConfig()->getSystemValue('wnd.logging.enable', false) === false) {
			//don't bother building log strings
			return $result;
		} else if ($result === true) {
			Util::writeLog('wnd', "leave: $function, return true", Util::DEBUG);
		} else if ($result === false) {
			Util::writeLog('wnd', "leave: $function, return false", Util::DEBUG);
		} else if (is_string($result)) {
			Util::writeLog('wnd', "leave: $function, return '$result'", Util::DEBUG);
		} else if (is_resource($result)) {
			Util::writeLog('wnd', "leave: $function, return resource", Util::DEBUG);
		} else if ($result instanceof \Exception) {
			Util::writeLog('wnd', "leave: $function, throw ".get_class($result)
				.' - code: '.$result->getCode()
				.' message: '.$result->getMessage()
				.' trace: '.$result->getTraceAsString(), Util::DEBUG);
		} else {
			Util::writeLog('wnd', "leave: $function, return ".json_encode($result), Util::DEBUG);
		}
		return $result;
	}

	private function swallow($function, \Exception $exception) {
		if (\OC::$server->getConfig()->getSystemValue('wnd.logging.enable', false) === true) {
			Util::writeLog('wnd', "$function swallowing ".get_class($exception)
				.' - code: '.$exception->getCode()
				.' message: '.$exception->getMessage()
				.' trace: '.$exception->getTraceAsString(), Util::DEBUG);
		}
	}
}
