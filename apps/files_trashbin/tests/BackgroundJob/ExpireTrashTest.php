<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OCA\Files_Trashbin\Tests\BackgroundJob;
 
use \OCA\Files_Trashbin\BackgroundJob\ExpireTrash;

class ExpireTrashTest extends \Test\TestCase {
	public function testConstructAndRun() {
		$backgroundJob = new ExpireTrash(
			$this->getMock('OCP\IUserManager'),
			$this->getMockBuilder('OCA\Files_Trashbin\Expiration')->disableOriginalConstructor()->getMock()
		);

		$jobList = $this->getMock('\OCP\BackgroundJob\IJobList');

		/** @var \OC\BackgroundJob\JobList $jobList */
		$backgroundJob->execute($jobList);
		$this->assertTrue(true);
	}
}
