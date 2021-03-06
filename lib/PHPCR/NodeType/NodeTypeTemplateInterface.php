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
 * The NodeTypeTemplate interface represents a simple container structure used
 * to define node types which are then registered through the
 * NodeTypeManager.registerNodeType method.
 *
 * NodeTypeTemplate, like NodeType, is a subclass of NodeTypeDefinition so it
 * shares with NodeType those methods that are relevant to a static definition.
 * In addition, NodeTypeTemplate provides methods for setting the attributes of
 * the definition. Implementations of this interface need not contain any
 * validation logic.
 *
 * See the corresponding get methods for each attribute in NodeTypeDefinition
 * for the default values assumed when a new empty NodeTypeTemplate is created
 * (as opposed to one extracted from an existing NodeType).
 *
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @license http://opensource.org/licenses/bsd-license.php Simplified BSD License
 * @api
 */
interface PHPCR_NodeType_NodeTypeTemplateInterface extends PHPCR_NodeType_NodeTypeDefinitionInterface {

	/**
	 * Sets the name of the node type.
	 *
	 * @param string $name a String.
	 * @return void
	 * @api
	 */
	public function setName($name);

	/**
	 * Sets the names of the supertypes of the node type.
	 *
	 * @param array $names a String array.
	 * @return void
	 * @api
	 */
	public function setDeclaredSuperTypeNames(array $names);

	/**
	 * Sets the abstract flag of the node type.
	 *
	 * @param boolean $abstractStatus a boolean.
	 * @return void
	 * @api
	 */
	public function setAbstract($abstractStatus);

	/**
	 * Sets the mixin flag of the node type.
	 *
	 * @param boolean $mixin a boolean.
	 * @return void
	 * @api
	 */
	public function setMixin($mixin);

	/**
	 * Sets the orderable child nodes flag of the node type.
	 *
	 * @param boolean $orderable a boolean.
	 * @return void
	 * @api
	 */
	public function setOrderableChildNodes($orderable);

	/**
	 * Sets the name of the primary item.
	 *
	 * @param string $name a String.
	 * @return void
	 * @api
	 */
	public function setPrimaryItemName($name);

	/**
	 * Sets the queryable status of the node type.
	 *
	 * @param booolean $queryable a boolean.
	 * @return void
	 * @api
	 */
	public function setQueryable($queryable);

	/**
	 * Returns a mutable List of PropertyDefinitionTemplate objects. To define a
	 * new NodeTypeTemplate or change an existing one, PropertyDefinitionTemplate
	 * objects can be added to or removed from this List.
	 *
	 * @return array a mutable List of PropertyDefinitionTemplate objects.
	 * @api
	 */
	public function getPropertyDefinitionTemplates();

	/**
	 * Returns a mutable List of NodeDefinitionTemplate objects. To define a new
	 * NodeTypeTemplate or change an existing one, NodeDefinitionTemplate objects
	 * can be added to or removed from this List.
	 *
	 * @return array a mutable List of NodeDefinitionTemplate objects.
	 * @api
	 */
	public function getNodeDefinitionTemplates();
}

?>