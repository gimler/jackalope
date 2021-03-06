<?php
declare(ENCODING = 'utf-8');

/*                                                                        *
 * This script belongs to the FLOW3 package "PHPCR".                      *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Exception thrown when a lock-related error occurs.
 *
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @license http://opensource.org/licenses/bsd-license.php Simplified BSD License
 * @api
 */
class PHPCR_Lock_LockException extends PHPCR_RepositoryException {

	/**
	 * Absolute path of the node that caused the error, in normalized, standard
	 * form, that is, each path segment must be a JCR name in qualified form,
	 * the path must have no trailing slash, no self or parent segments and no
	 * [1] indexes.
	 */
	protected $failureNodePath;

	/**
	 * If a path is passed it must be an absolute path in normalized, standard form,
	 * that is, each path segment must be a JCR name in qualified form, the path
	 * must have no trailing slash, no self or parent segments and no [1]
	 * indexes.
	 *
	 * @param string $message The exception message
	 * @param integer $code The exception error code
	 * @param string $failureNodePath the absolute path of the node that caused the error or  NULL if the implementation chooses not to, or cannot, return a path.
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @api
	 */
	public function __construct($message, $code, $failureNodePath = NULL) {
		parent::construct($message, $code);
		$this->failureNodePath = $failureNodePath;
	}

	/**
	 * Returns the absolute path of the node that caused the error or NULL
	 * if the implementation chooses not to, or cannot, return a path.
	 *
	 * @return string path of the node that caused the error
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @api
	 */
	public function getFailureNodePath() {
		return $this->failureNodePath;
	}

}

?>