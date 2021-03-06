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
 * A node definition. Used in node type definitions.
 *
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @license http://opensource.org/licenses/bsd-license.php Simplified BSD License
 * @api
 */
interface PHPCR_NodeType_NodeDefinitionInterface extends PHPCR_NodeType_ItemDefinitionInterface {

	/**
	 * Gets the minimum set of primary node types that the child node must have.
	 * Returns an array to support those implementations with multiple inheritance.
	 * This method never returns an empty array. If this node definition places
	 * no requirements on the primary node type, then this method will return an
	 * array containing only the NodeType object representing nt:base, which is
	 * the base of all primary node types and therefore constitutes the least
	 * restrictive node type requirement. Note that any particular node instance
	 * still has only one assigned primary node type, but in multiple-inheritance-
	 * supporting implementations the RequiredPrimaryTypes attribute can be used
	 * to restrict that assigned node type to be a subtype of all of a specified
	 * set of node types.
	 * In implementations that support node type registration an NodeDefinition
	 * object may be acquired (in the form of a NodeDefinitionTemplate) that is
	 * not attached to a live NodeType. In such cases this method returns NULL.
	 *
	 * @return PHPCR_NodeType_NodeTypeInterface an array of NodeType objects.
	 * @api
	 */
	public function getRequiredPrimaryTypes();

	/**
	 * Returns the names of the required primary node types.
	 * If this NodeDefinition is acquired from a live NodeType this list will
	 * reflect the node types returned by getRequiredPrimaryTypes, above.
	 *
	 * If this NodeDefinition is actually a NodeDefinitionTemplate that is not
	 * part of a registered node type, then this method will return the required
	 * primary types as set in that template. If that template is a newly-created
	 * empty one, then this method will return NULL.
	 *
	 * @return array a String array
	 * @api
	 */
	public function getRequiredPrimaryTypeNames();

	/**
	 * Gets the default primary node type that will be assigned to the child node
	 * if it is created without an explicitly specified primary node type. This
	 * node type must be a subtype of (or the same type as) the node types returned
	 * by getRequiredPrimaryTypes.
	 * If null is returned this indicates that no default primary type is
	 * specified and that therefore an attempt to create this node without
	 * specifying a node type will throw a ConstraintViolationException. In
	 * implementations that support node type registration an NodeDefinition
	 * object may be acquired (in the form of a NodeDefinitionTemplate) that is
	 * not attached to a live NodeType. In such cases this method returns null.
	 *
	 * @return PHPCR_NodeType_NodeTypeInterface a NodeType.
	 * @api
	 */
	public function getDefaultPrimaryType();

	/**
	 * Returns the name of the default primary node type.
	 * If this NodeDefinition is acquired from a live NodeType this list will
	 * reflect the NodeType returned by getDefaultPrimaryType, above.
	 *
	 * If this NodeDefinition is actually a NodeDefinitionTemplate that is not
	 * part of a registered node type, then this method will return the required
	 * primary types as set in that template. If that template is a newly-created
	 * empty one, then this method will return null.
	 *
	 * @return string a String
	 * @api
	 */
	public function getDefaultPrimaryTypeName();

	/**
	 * Reports whether this child node can have same-name siblings. In other
	 * words, whether the parent node can have more than one child node of this
	 * name. If this NodeDefinition is actually a NodeDefinitionTemplate that is
	 * not part of a registered node type, then this method will return the same
	 * name siblings status as set in that template. If that template is a
	 * newly-created empty one, then this method will return false.
	 *
	 * @return boolean a boolean.
	 * @api
	 */
	public function allowsSameNameSiblings();

}

?>