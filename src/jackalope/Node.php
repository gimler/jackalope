<?php

class jackalope_Node extends jackalope_Item implements PHPCR_NodeInterface {

    protected $index = 1;
    /** @var string */
    protected $primaryType;

    /**
     * mapping of property name to property objects. all properties are loaded in constructor
     */
    protected $properties = array();
    /**
     * list of children names
     */
    protected $nodes = array();

    protected $uuid = null;

    public function __construct($rawData, $path,  $session, $objectManager, $new = false) {
        parent::__construct($rawData, $path,  $session, $objectManager, $new);
        $this->isNode = true;

        //TODO: determine the index if != 1
        foreach ($rawData as $key => $value) {
            if (is_object($value)) {
                array_push($this->nodes, $key);
            } else {
                if ( 0 === strpos($key, ':')) continue; //It's a property type

                switch ($key) {
                    case 'jcr:index':
                        $this->index = $value;
                        break;
                    case 'jcr:primaryType':
                        $this->primaryType = $value;
                        $this->properties[$key] = jackalope_Factory::get(
                            'Property',
                            array(
                                array('type' => $rawData->{':jcr:primaryType'},  'value' => $value),
                                $this->getPath() . '/jcr:primaryType',
                                $this->session,
                                $this->objectManager,
                            )
                        );
                        break;
                    case 'jcr:uuid':
                        $this->uuid = $value;
                        break;

                    //TODO: more special information?
                    //TODO: optimization? not instantiate objects but just have value for the simple cases, then shortcut funktion to avoid getProperty()->getValue()
                    default:
                        $type = isset($rawData->{':' . $key}) ? $rawData->{':' . $key} : 'undefined';
                        $this->properties[$key] = jackalope_Factory::get(
                            'Property',
                            array(
                                array('type' => $type, 'value' => $value),
                                $this->getPath() . '/'. $key,
                                $this->session,
                                $this->objectManager,
                            )
                        );
                        break;
                }
            }
        }
    }

    /**
     * Creates a new node at $relPath.
     *
     * This is session-write method, meaning that the addition of the new node
     * is dispatched upon Session#save.
     *
     * The $relPath provided must not have an index on its final element,
     * otherwise a Repository
     *
     * If ordering is supported by the node type of the parent node of the new
     * node then the new node is appended to the end of the child node list.
     *
     * The new node's primary node type will be determined by the child node
     * definitions in the node types of its parent. This may occur either
     * immediately, on dispatch (save, whether within or without transactions)
     * or on persist (save without transactions, commit within a transaction),
     * depending on the implementation.
     *
     * If $primaryNodeTypeName is given:
     * The behavior of this method is identical to addNode($relPath) except that
     * the primary node type of the new node is explicitly specified.
     *
     * @param string $relPath The path of the new node to be created.
     * @param string $primaryNodeTypeName The name of the primary node type of the new node.
     * @param string $identifier The identifier to use for the new node, if not given an UUID will be created. Non-JCR-spec parameter!
     * @return PHPCR_NodeInterface The node that was added.
     * @throws PHPCR_ItemExistsException if the identifier is already used, if an item at the specified path already exists, same-name siblings are not allowed and this implementation performs this validation immediately.
     * @throws PHPCR_PathNotFoundException if the specified path implies intermediary Nodes that do not exist or the last element of relPath has an index, and this implementation performs this validation immediately.
     * @throws PHPCR_ConstraintViolationException if a node type or implementation-specific constraint is violated or if an attempt is made to add a node as the child of a property and this implementation performs this validation immediately.
     * @throws PHPCR_Version_VersionException if the node to which the new child is being added is read-only due to a checked-in node and this implementation performs this validation immediately.
     * @throws PHPCR_Lock_LockException if a lock prevents the addition of the node and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_RepositoryException If the last element of relPath has an index or if another error occurs.
     * @api
     */
    public function addNode($relPath, $primaryNodeTypeName = NULL, $identifier = NULL) {

        $ntm = $this->session->getWorkspace()->getNodeTypeManager();

        // are we not the immediate parent?
        if (strpos($relPath, '/') !== false) {
            // forward to real parent
            try {
                $parentNode = $this->objectManager->getNode(dirname($relPath), $this->path);
            } catch(PHPCR_ItemNotFoundException $e) {
                throw new PHPCR_PathNotFoundException($e->getMessage(), $e->getCode(), $e);
            }
            return $parentNode->addNode(basename($relPath), $primaryNodeTypeName, $identifier);
        }

        if (!is_null($primaryNodeTypeName)) {
            // sanitize
            $nt = $ntm->getNodeType($primaryNodeTypename);
            if ($nt->isMixin()) {
                throw new PHPCR_NodeType_ConstraintViolationException('Not allowed to add a node with a mixin type: '.$primaryNodeTypeName);
            } elseif ($nt->isAbstract()) {
                throw new PHPCR_NodeType_ConstraintViolationException('Not allowed to add a node with an abstract type: '.$primaryNodeTypeName);
            }
        } else {
            $nodeDefinitions = $ntm->getNodeType($this->primaryType)->getChildNodeDefinitions();
            foreach ($nodeDefinitions as $def) {
                if (!is_null($def->getDefaultPrimaryType())) {
                    $primaryNodeTypeName = $def->getDefaultPrimaryType();
                    break;
                }
            }
            if (is_null($primaryNodeTypeName)) {
                throw new PHPCR_NodeType_ConstraintViolationException("No matching child node definition found for `$relPath' in type `{$this->primaryType}'");
            }
        }

        // create child node
        $data = array('jcr:primaryType' => $primaryNodeTypeName);
        if (! is_null($identifier)) {
            $data['jcr:uuid'] = $identifier;
        }
        $path = $this->path.'/'.$relPath;
        $node = jackalope_Factory::get('Node', array($data, $path,
                $this->session, $this->objectManager, true));
        $this->objectManager->addItem($path, $node);
        $this->nodes[] = $relPath;
        $this->setModified();

        return $node;
    }

    /**
     * If this node supports child node ordering, this method inserts the child
     * node at srcChildRelPath into the child node list at the position
     * immediately before destChildRelPath.
     *
     * To place the node srcChildRelPath at the end of the list, a destChildRelPath
     * of null is used.
     *
     * Note that (apart from the case where destChildRelPath is null) both of
     * these arguments must be relative paths of depth one, in other words they
     * are the names of the child nodes, possibly suffixed with an index.
     *
     * If srcChildRelPath and destChildRelPath are the same, then no change is
     * made.
     *
     * This is session-write method, meaning that a change made by this method
     * is dispatched on save.
     *
     * @param string $srcChildRelPath the relative path to the child node (that is, name plus possible index) to be moved in the ordering
     * @param string $destChildRelPath the the relative path to the child node (that is, name plus possible index) before which the node srcChildRelPath will be placed.
     * @return void
     * @throws PHPCR_UnsupportedRepositoryOperationException if ordering is not supported on this node.
     * @throws PHPCR_ConstraintViolationException if an implementation-specific ordering restriction is violated and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_ItemNotFoundException if either parameter is not the relative path of a child node of this node.
     * @throws PHPCR_Version_VersionException if this node is read-only due to a checked-in node and this implementation performs this validation immediately.
     * @throws PHPCR_Lock_LockException if a lock prevents the re-ordering and this implementation performs this validation immediately.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function orderBefore($srcChildRelPath, $destChildRelPath) {
        if ($srcChildRelPath == $destChildRelPath) return; //nothing to move
        $oldpos = array_search($srcChildRelPath, $this->nodes);
        if ($oldpos === false)
            throw new PHPCR_ItemNotFoundException("$srcChildRelPath is not a valid child of ".$this->path);

        if ($destChildRelPath == null) {
            //null means move to end
            unset($this->nodes[$oldpos]);
            $this->nodes[] = $srcChildRelPath;
        } else {
            //insert somewhere specified by dest path
            $newpos = array_search($destChildRelPath, $this->nodes);
            if ($newpos === false)
                throw new PHPCR_ItemNotFoundException("$destChildRelPath is not a valid child of ".$this->path);
            if ($oldpos < $newpos) $newpos--; //we first unset, so
            unset($this->nodes[$oldpos]);
            array_splice($this->nodes, $newpos, 0, $srcChildRelPath);
        }
        $this->modified = true;
        //TODO: do we have to record reorderings specifically for telling the backend?
    }

    /**
     * Sets the single-value property of this node called $name to the specified
     * value.
     *
     * If the property does not yet exist, it is created and its property type
     * determined by the node type of this node. If, based on the name and value
     * passed, there is more than one property definition that applies, the
     * repository chooses one definition according to some implementation-
     * specific criteria. Once property with name P has been created, the
     * behavior of a subsequent setProperty(P,V) may differ across implementations.
     * Some repositories may allow P to be dynamically re-bound to a different
     * property definition (based for example, on the new value being of a
     * different type than the original value) while other repositories may not
     * allow such dynamic re-binding.
     *
     * If the property type one or more supplied Value objects is different from
     * that required, then a best-effort conversion is attempted.
     *
     * If the node type of this node does not indicate a specific property type,
     * then the property type of the supplied Value object is used and if the
     * property already exists it assumes both the new value and new property type.
     *
     * Passing a null as the second parameter removes the property. It is equivalent
     * to calling remove on the Property object itself. For example,
     * N.setProperty("P", (Value)null) would remove property called "P" of the
     * node in N.
     *
     * This is a session-write method, meaning that changes made through this
     * method are dispatched on Session#save.
     *
     * If $type is given:
     * The behavior of this method is identical to that of setProperty($name,
     * $value) except that the intended property type is explicitly specified.
     *
     * Note:
     * Have a look at the JSR-283 spec and/or API documentation for more details
     * on what is supposed to happen for different types of values being passed
     * to this method.
     *
     * @param string $name The name of a property of this node
     * @param mixed $value The value to be assigned
     * @param integer $type The type to set for the property
     * @return PHPCR_PropertyInterface The updated Property object
     * @throws PHPCR_ValueFormatException if the specified property is a DATE but the value cannot be expressed in the ISO 8601-based format defined in the JCR 2.0 specification and the implementation does not support dates incompatible with that format or if value cannot be converted to the type of the specified property or if the property already exists and is multi-valued.
     * @throws PHPCR_Version_VersionException if this node is versionable and checked-in or is non-versionable but its nearest versionable ancestor is checked-in and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_Lock_LockException  if a lock prevents the setting of the property and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_ConstraintViolationException if the change would violate a node-type or other constraint and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function setProperty($name, $value, $type = NULL) {
        if ($value instanceof PHPCR_ValueInterface) {
            if (! is_null($type) && $type != $value->getType()) {
                throw new jackalope_NotImplementedException('converting value seems like pain. do we have to?'); //valuefactory has some logic to this end
            } else {
                $type = $value->getType();
            }
            $data = $value;
        } else {
            //use the valuefactory to solve type issues
            $data = $this->session->getValueFactory()->createValue($value, $type);
            $type = $data->getType();
        }
        if (! isset($this->properties[$name])) {
            $path = $this->path . "/$name";
            $property = jackalope_Factory::get(
                            'Property',
                            array($data, $path,
                                  $this->session, $this->objectManager,
                                  true));
            $this->objectManager->addItem($path, $property);
            $this->properties[$name] = $property;
            //validity check will be done by backend on commit, which is allowed by spec
        } else {
            if (! is_null($type) && $this->properties[$name]->getType() != $type)
                throw new jackalope_NotImplementedException('converting value seems like pain. do we have to?');//valuefactory has some logic to this end
            $this->properties[$name]->setValue($value);
        }
    }

    /**
     * Returns the node at relPath relative to this node.
     * If relPath contains a path element that refers to a node with same-name
     * sibling nodes without explicitly including an index using the array-style
     * notation ([x]), then the index [1] is assumed (indexing of same name
     * siblings begins at 1, not 0, in order to preserve compatibility with XPath).
     *
     * Within the scope of a single Session object, if a Node object has been
     * acquired, any subsequent call of getNode reacquiring the same node must
     * return a Node object reflecting the same state as the earlier Node object.
     * Whether this object is actually the same Node instance, or simply one
     * wrapping the same state, is up to the implementation.
     *
     * @param string $relPath The relative path of the node to retrieve.
     * @return PHPCR_NodeInterface The node at relPath.
     * @throws PHPCR_PathNotFoundException If no node exists at the specified path or the current Session does not read access to the node at the specified path.
     * @throws PHPCR_RepositoryException If another error occurs.
     * @api
     */
    public function getNode($relPath) {
        $node = null;
        try {
            $node = $this->objectManager->getNodeByPath($this->objectManager->absolutePath($this->path, $relPath));
        } catch (PHPCR_ItemNotFoundException $e) {
            throw new PHPCR_PathNotFoundException($e->getMessage(), $e->getCode(), $e);
        }
        return $node;
    }

    /**
     * If $filter is a string:
     * Gets all child nodes of this node accessible through the current Session
     * that match namePattern (if no pattern is given, all accessible child nodes
     * are returned). Does not include properties of this Node. The pattern may
     * be a full name or a partial name with one or more wildcard characters ("*"),
     * or a disjunction (using the "|" character to represent logical OR) of these.
     * For example,
     *  N->getNodes("jcr:* | myapp:report | my doc")
     * would return a NodeIterator holding all accessible child nodes of N that
     * are either called 'myapp:report', begin with the prefix 'jcr:' or are
     * called 'my doc'.
     *
     * The substrings within the pattern that are delimited by "|" characters
     * and which may contain wildcard characters ("*") are called "globs".
     *
     * Note that leading and trailing whitespace around a glob is ignored, but
     * whitespace within a disjunct forms part of the pattern to be matched.
     *
     *If $filter is an array:
     * Gets all child nodes of this node accessible through the current
     * Session that match one or more of the $filter strings in the passed
     * array.
     *
     * A glob may be a full name or a partial name with one or more wildcard
     * characters ("*"). For example,
     *  N->getNodes(array("jcr:*", "myapp:report", "my doc"))
     * would return a NodeIterator holding all accessible child nodes of N that
     * are either called 'myapp:report', begin with the prefix 'jcr:' or are
     * called 'my doc'.
     *
     * Note that unlike in the case of the getNodes(<string>) leading and
     * trailing whitespace around a glob is not ignored.
     *
     *
     * The pattern is matched against the names (not the paths) of the immediate
     * child nodes of this node.
     *
     * If this node has no accessible matching child nodes, then an empty
     * iterator is returned.
     *
     * The same reacquisition semantics apply as with getNode($relPath).
     *
     * @param string|array $filter a name pattern or an array of globbing strings.
     * @return PHPCR_NodeIteratorInterface a NodeIterator over all (matching) child Nodes
     * @throws PHPCR_RepositoryException If an unexpected error occurs.
     * @api
     */
    public function getNodes($filter = NULL) {
        $names = self::filterNames($filter, $this->nodes);
        foreach($names as $name) {
            //OPTIMIZE: batch get nodes
            $result[] = $this->getNode($name);
        }
        return new jackalope_NodeIterator($result);
    }

    /**
     * Returns the property at relPath relative to this node. The same
     * reacquisition semantics apply as with getNode(String).
     *
     * @param string $relPath The relative path of the property to retrieve.
     * @return PHPCR_PropertyInterface The property at relPath.
     * @throws PHPCR_PathNotFoundException if no property exists at the specified path or if the current Session does not have read access to the specified property.
     * @throws PHPCR_RepositoryException If another error occurs.
     * @api
     */
    public function getProperty($relPath) {
        if (false === strpos($relPath, '/')) {
            if (isset($this->properties[$relPath])) {
                return $this->properties[$relPath];
            } else {
                throw new PHPCR_PathNotFoundException($relPath);
            }
        } else {
            $this->session->getProperty($this->path . '/.' . $relPath);
        }
    }

    /**
     * If $filter is a string:
     * Gets all properties of this node accessible through the current Session
     * that match namePattern (if no pattern is given, all accessible properties
     * are returned). Does not include child nodes of this node. The pattern may
     * be a full name or a partial name with one or more wildcard characters ("*"),
     * or a disjunction (using the "|" character to represent logical OR) of
     * these. For example,
     * N.getProperties("jcr:* | myapp:name | my doc")
     * would return a PropertyIterator holding all accessible properties of N
     * that are either called 'myapp:name', begin with the prefix 'jcr:' or are
     * called 'my doc'.
     *
     * The substrings within the pattern that are delimited by "|" characters
     * and which may contain wildcard characters ("*") are called globs.
     *
     * Note that leading and trailing whitespace around a glob is ignored, but
     * whitespace within a disjunct forms part of the pattern to be matched.
     *
     * If $filter is an array:
     * Gets all properties of this node accessible through the current
     * Session that match one or more of the $filter strings in the passed array.
     *
     * A glob may be a full name or a partial name with one or more wildcard
     * characters ("*"). For example,
     *  N->getProperties(array("jcr:*", "myapp:report", "my doc"))
     * would return a PropertyIterator holding all accessible properties of N
     * that are either called 'myapp:report', begin with the prefix 'jcr:' or
     * are called 'my doc'.
     *
     * Note that unlike in the case of getProperties(<string>) leading and
     * trailing whitespace around a glob is not ignored.
     *
     *
     * The pattern is matched against the names (not the paths) of the immediate
     * child properties of this node.
     *
     * If this node has no accessible matching properties, then an empty iterator
     * is returned.
     *
     * The same reacquisition semantics apply as with getNode(String).
     *
     * @param string|array $filter a name pattern
     * @return PHPCR_PropertyIteratorInterface a PropertyIterator
     * @throws PHPCR_RepositoryException If an unexpected error occurs.
     * @api
     */
    public function getProperties($filter = NULL) {
        $names = self::filterNames($filter, array_keys($this->properties));
        foreach($names as $name) {
            $result[] = $this->properties[$name]; //we know for sure the properties exist, as they come from the array keys of the array we are accessing
        }
        return new jackalope_PropertyIterator($result);
    }

    /**
     * Returns the primary child item of this node. The primary node type of this
     * node may specify one child item (child node or property) of this node as
     * the primary child item. This method returns that item.
     *
     * In cases where the primary child item specifies the name of a set same-name
     * sibling child nodes, the node returned will be the one among the same-name
     * siblings with index [1].
     *
     * The same reacquisition semantics apply as with getNode(String).
     *
     * @return PHPCR_ItemInterface the primary child item.
     * @throws PHPCR_ItemNotFoundException if this node does not have a primary child item, either because none is declared in the node type or because a declared primary item is not present on this node instance, or because none accessible through the current Session
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function getPrimaryItem() {
        throw new jackalope_NotImplementedException();
    }

    /**
     * Returns the identifier of this node. Applies to both referenceable and
     * non-referenceable nodes.
     *
     * @return string the identifier of this node
     * @throws PHPCR_RepositoryException If an error occurs.
     * @api
     */
    public function getIdentifier() {
        return $this->uuid;
    }

    /**
     * This method returns the index of this node within the ordered set of its
     * same-name sibling nodes. This index is the one used to address same-name
     * siblings using the square-bracket notation, e.g., /a[3]/b[4]. Note that
     * the index always starts at 1 (not 0), for compatibility with XPath. As a
     * result, for nodes that do not have same-name-siblings, this method will
     * always return 1.
     *
     * @return integer The index of this node within the ordered set of its same-name sibling nodes.
     * @throws PHPCR_RepositoryException if an error occurs.
     * @api
     */
    public function getIndex() {
        return $this->index;
    }

    /**
     * This method returns all REFERENCE properties that refer to this node, have
     * the specified name and that are accessible through the current Session.
     * If the name parameter is null then all referring REFERENCES are returned
     * regardless of name.
     *
     * Some implementations may only return properties that have been persisted.
     * Some may return both properties that have been persisted and those that
     * have been dispatched but not persisted (for example, those saved within a
     * transaction but not yet committed) while others implementations may
     * return these two categories of property as well as properties that are
     * still pending and not yet dispatched.
     *
     * In implementations that support versioning, this method does not return
     * properties that are part of the frozen state of a version in version storage.
     *
     * If this node has no referring properties with the specified name, an empty
     * iterator is returned.
     *
     * @param string $name name of referring REFERENCE properties to be returned; if null then all referring REFERENCEs are returned
     * @return PHPCR_PropertyIteratorInterface A PropertyIterator.
     * @throws PHPCR_RepositoryException if an error occurs
     * @api
     */
    public function getReferences($name = NULL) {
        throw new jackalope_NotImplementedException();
    }

    /**
     * This method returns all WEAKREFERENCE properties that refer to this node,
     * have the specified name and that are accessible through the current Session.
     * If the name parameter is null then all referring WEAKREFERENCE are returned
     * regardless of name.
     *
     * Some write implementations may only return properties that have been
     * saved (in a transactional setting this includes both those properties that
     * have been saved but not yet committed, as well as properties that have
     * been committed). Other level 2 implementations may additionally return
     * properties that have been added within the current Session but are not yet
     * saved.
     *
     * In implementations that support versioning, this method does not return
     * properties that are part of the frozen state of a version in version storage.
     *
     * If this node has no referring properties with the specified name, an empty
     * iterator is returned.
     *
     * @param string $name name of referring WEAKREFERENCE properties to be returned; if null then all referring WEAKREFERENCEs are returned
     * @return PHPCR_PropertyIteratorInterface A PropertyIterator.
     * @throws PHPCR_RepositoryException if an error occurs
     * @api
     */
    public function getWeakReferences($name = NULL) {
        throw new jackalope_NotImplementedException();
    }

    /**
     * Indicates whether a node exists at relPath
     * Returns true if a node accessible
     * through the current Session exists at relPath and false otherwise.
     *
     * @param string $relPath The path of a (possible) node.
     * @return boolean true if a node exists at relPath; false otherwise.
     * @throws PHPCR_RepositoryException if an error occurs.
     * @api
     */
    public function hasNode($relPath) {
        if (false === strpos($relPath, '/')) {
            return isset($this->nodes[$relPath]);
        } else {
            $this->session->nodeExists($this->path . '/'. $relPath);
        }
    }

    /**
     * Indicates whether a property exists at relPath Returns true if a property
     * accessible through the current Session exists at relPath and false otherwise.
     *
     * @param string $relPath The path of a (possible) property.
     * @return boolean true if a property exists at relPath; false otherwise.
     * @throws PHPCR_RepositoryException if an error occurs.
     * @api
     */
    public function hasProperty($relPath) {
        if (false === strpos($relPath, '/')) {
            return isset($this->properties[$relPath]);
         } else {
            $this->session->propertyExists($this->path . '/'. $relPath);
         }
    }

    /**
     * Indicates whether this node has child nodes. Returns true if this node has
     * one or more child nodes accessible through the current Session; false otherwise.
     *
     * @return boolean true if this node has one or more child nodes; false otherwise.
     * @throws PHPCR_RepositoryException if an error occurs.
     * @api
     */
    public function hasNodes() {
        return (! empty($this->nodes));
    }

    /**
     * Indicates whether this node has properties. Returns true if this node has
     * one or more properties accessible through the current Session; false otherwise.
     *
     * @return boolean true if this node has one or more properties; false otherwise.
     * @throws PHPCR_RepositoryException if an error occurs.
     * @api
     */
    public function hasProperties() {
        return (! empty($this->properties));
    }

    /**
     * Returns the primary node type in effect for this node. Which NodeType is
     * returned when this method is called on the root node of a workspace is up
     * to the implementation.
     *
     * @return PHPCR_NodeType_NodeTypeInterface a NodeType object.
     * @throws PHPCR_RepositoryException if an error occurs
     * @api
     */
    public function getPrimaryNodeType() {
        $ntm = $this->session->getWorkspace()->getNodeTypeManager();
        return $ntm->getNodeType($this->primaryType);
    }

    /**
     * Returns an array of NodeType objects representing the mixin node types in
     * effect for this node. This includes only those mixin types explicitly
     * assigned to this node. It does not include mixin types inherited through
     * the addition of supertypes to the primary type hierarchy or through the
     * addition of supertypes to the type hierarchy of any of the declared mixin
     * types.
     *
     * @return array of PHPCR_NodeType_NodeTypeInterface objects.
     * @throws PHPCR_RepositoryException if an error occurs
     * @api
     */
    public function getMixinNodeTypes() {
        throw new jackalope_NotImplementedException();
    }

    /**
     * Returns true if this node is of the specified primary node type or mixin
     * type, or a subtype thereof. Returns false otherwise.
     * This method respects the effective node type of the node.
     *
     * @param string $nodeTypeName the name of a node type.
     * @return boolean true if this node is of the specified primary node type or mixin type, or a subtype thereof. Returns false otherwise.
     * @throws PHPCR_RepositoryException If an error occurs.
     * @api
     */
    public function isNodeType($nodeTypeName) {
        throw new jackalope_NotImplementedException();
    }

    /**
     * Changes the primary node type of this node to nodeTypeName. Also immediately
     * changes this node's jcr:primaryType property appropriately. Semantically,
     * the new node type may take effect immediately or on dispatch but must take
     * effect on persist.
     * Whichever behavior is adopted it must be the same as the behavior adopted
     * for addMixin() (see below) and the behavior that occurs when a node is
     * first created.
     *
     * @param string $nodeTypeName the name of the new node type.
     * @return void
     * @throws PHPCR_ConstraintViolationException If the specified primary node type creates a type conflict and this implementation performs this validation immediately.
     * @throws PHPCR_NodeType_NoSuchNodeTypeException If the specified nodeTypeName is not recognized and this implementation performs this validation immediately.
     * @throws PHPCR_Version_VersionException if this node is read-only due to a checked-in node and this implementation performs this validation immediately.
     * @throws PHPCR_Lock_LockException if a lock prevents the change of the primary node type and this implementation performs this validation immediately.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function setPrimaryType($nodeTypeName) {
        throw new jackalope_NotImplementedException('Write');
    }

    /**
     * Adds the mixin node type named $mixinName to this node. If this node is already
     * of type $mixinName (either due to a previously added mixin or due to its
     * primary type, through inheritance) then this method has no effect.
     * Otherwise $mixinName is added to this node's jcr:mixinTypes property.
     *
     * Semantically, the new node type may take effect immediately, on disptahc
     * or on persist. The behavior is adopted must be the same as the behavior
     * adopted for setPrimaryType(java.lang.String) and the behavior that occurs
     * when a node is first created.
     *
     * A ConstraintViolationException is thrown either immediately or on save if
     * a conflict with another assigned mixin or the primary node type or for an
     * implementation-specific reason. Implementations may differ on when this
     * validation is done.
     *
     * In some implementations it may only be possible to add mixin types before
     * a a node is persisted for the first time. I such cases any later calls to
     * $addMixin will throw a ConstraintViolationException either immediately,
     * on dispatch or on persist.
     *
     * @param string $mixinName the name of the mixin node type to be added
     * @return void
     * @throws PHPCR_NodeType_NoSuchNodeTypeException If the specified mixinName is not recognized and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_ConstraintViolationException If the specified mixin node type is prevented from being assigned.
     * @throws PHPCR_Version_VersionException if this node is versionable and checked-in or is non-versionable but its nearest versionable ancestor is checked-in and this implementation performs this validation immediately instead of waiting until save..
     * @throws PHPCR_Lock_LockException if a lock prevents the addition of the mixin and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function addMixin($mixinName) {
        throw new jackalope_NotImplementedException('Write');
    }

    /**
     * Removes the specified mixin node type from this node and removes mixinName
     * from this node's jcr:mixinTypes property. Both the semantic change in
     * effective node type and the persistence of the change to the jcr:mixinTypes
     * property occur on persist.
     *
     * @param string $mixinName the name of the mixin node type to be removed.
     * @return void
     * @throws PHPCR_NodeType_NoSuchNodeTypeException if the specified mixinName is not currently assigned to this node and this implementation performs this validation immediately.
     * @throws PHPCR_ConstraintViolationException if the specified mixin node type is prevented from being removed and this implementation performs this validation immediately.
     * @throws PHPCR_Version_VersionException if this node is read-only due to a checked-in node and this implementation performs this validation immediately.
     * @throws PHPCR_Lock_LockException if a lock prevents the removal of the mixin and this implementation performs this validation immediately.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function removeMixin($mixinName) {
        throw new jackalope_NotImplementedException('Write');
    }

    /**
     * Returns true if the specified mixin node type called $mixinName can be
     * added to this node. Returns false otherwise. A result of false must be
     * returned in each of the following cases:
     * * The mixin's definition conflicts with an existing primary or mixin node
     *   type of this node.
     * * This node is versionable and checked-in or is non-versionable and its
     *   nearest versionable ancestor is checked-in.
     * * This node is protected (as defined in this node's NodeDefinition, found
     *   in the node type of this node's parent).
     * * An access control restriction would prevent the addition of the mixin.
     * * A lock would prevent the addition of the mixin.
     * * An implementation-specific restriction would prevent the addition of the mixin.
     *
     * @param string $mixinName The name of the mixin to be tested.
     * @return boolean true if the specified mixin node type, mixinName, can be added to this node; false otherwise.
     * @throws PHPCR_NodeType_NoSuchNodeTypeException if the specified mixin node type name is not recognized.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function canAddMixin($mixinName) {
        throw new jackalope_NotImplementedException('Write');
    }

    /**
     * Returns the node definition that applies to this node. In some cases there
     * may appear to be more than one definition that could apply to this node.
     * However, it is assumed that upon creation of this node, a single particular
     * definition was used and it is that definition that this method returns.
     * How this governing definition is selected upon node creation from among
     * others which may have been applicable is an implementation issue and is
     * not covered by this specification. The NodeDefinition returned when this
     * method is called on the root node of a workspace is also up to the
     * implementation.
     *
     * @return PHPCR_NodeType_NodeDefinitionInterface a NodeDefinition object.
     * @throws PHPCR_RepositoryException if an error occurs.
     * @api
     */
    public function getDefinition() {
        throw new jackalope_NotImplementedException();
    }

    /**
     * If this node does have a corresponding node in the workspace srcWorkspace,
     * then this replaces this node and its subgraph with a clone of the
     * corresponding node and its subgraph.
     * If this node does not have a corresponding node in the workspace srcWorkspace,
     * then the update method has no effect.
     *
     * If the update succeeds the changes made are persisted immediately, there
     * is no need to call save.
     *
     * Note that update does not respect the checked-in status of nodes. An update
     * may change a node even if it is currently checked-in (This fact is only
     * relevant in an implementation that supports versioning).
     *
     * @param string $srcWorkspace the name of the source workspace.
     * @return void
     * @throws PHPCR_NoSuchWorkspaceException if srcWorkspace does not exist.
     * @throws PHPCR_InvalidItemStateException if this Session (not necessarily this Node) has pending unsaved changes.
     * @throws PHPCR_AccessDeniedException if the current session does not have sufficient access to perform the operation.
     * @throws PHPCR_Lock_LockException if a lock prevents the update.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function update($srcWorkspace) {
        if ($this->isNew()) return; //no node in workspace
        throw new jackalope_NotImplementedException('Write');
    }

    /**
     * Returns the absolute path of the node in the specified workspace that
     * corresponds to this node.
     *
     * @param string $workspaceName the name of the workspace.
     * @return string the absolute path to the corresponding node.
     * @throws PHPCR_ItemNotFoundException if no corresponding node is found.
     * @throws PHPCR_NoSuchWorkspaceException if the workspace is unknown.
     * @throws PHPCR_AccessDeniedException if the current session has insufficient access capabilities to perform this operation.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function getCorrespondingNodePath($workspaceName) {
        throw new jackalope_NotImplementedException();
    }

    /**
     * Returns an iterator over all nodes that are in the shared set of this node.
     * If this node is not shared then the returned iterator contains only this node.
     *
     * @return PHPCR_NodeIteratorInterface a NodeIterator
     * @throws PHPCR_RepositoryException if an error occurs.
     * @api
     */
    public function getSharedSet() {
        throw new jackalope_NotImplementedException();
    }

    /**
     * Removes this node and every other node in the shared set of this node.
     *
     * This removal must be done atomically, i.e., if one of the nodes cannot be
     * removed, the method throws the exception Node#remove() would have thrown
     * in that case, and none of the nodes are removed.
     *
     * If this node is not shared this method removes only this node.
     *
     * @return void
     * @throws PHPCR_Version_VersionException if the parent node of this item is versionable and checked-in or is non-versionable but its nearest versionable ancestor is checked-in and this implementation performs this validation immediately.
     * @throws PHPCR_Lock_LockException if a lock prevents the removal of this item and this implementation performs this validation immediately.
     * @throws PHPCR_NodeType_ConstraintViolationException if removing the specified item would violate a node type or implementation-specific constraint and this implementation performs this validation immediately.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @see removeShare()
     * @see Item::remove()
     * @see SessionInterface::removeItem
     * @api
     */
    public function removeSharedSet() {
        throw new jackalope_NotImplementedException('Write');
    }

    /**
     * Removes this node, but does not remove any other node in the shared set
     * of this node.
     *
     * @return void
     * @throws PHPCR_Version_VersionException if the parent node of this item is versionable and checked-in or is non-versionable but its nearest versionable ancestor is checked-in and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_Lock_LockException if a lock prevents the removal of this item and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_NodeType_ConstraintViolationException if removing the specified item would violate a node type or implementation-specific constraint and this implementation performs this validation immediately instead of waiting until save.
     * @throws PHPCR_RepositoryException if this node cannot be removed without removing another node in the shared set of this node or another error occurs.
     * @see removeSharedSet()
     * @see Item::remove()
     * @see SessionInterface::removeItem
     * @api
     */
    public function removeShare() {
        throw new jackalope_NotImplementedException('Write');
    }

    /**
     * Returns FALSE if this node is currently in the checked-in state (either
     * due to its own status as a versionable node or due to the effect of
     * a versionable node being checked in above it). Otherwise this method
     * returns TRUE. This includes the case where the repository does not
     * support versioning (and therefore all nodes are always "checked-out",
     * by default).
     *
     * @return boolean
     * @throws PHPCR_RepositoryException if an error occurs.
     * @api
     */
    public function isCheckedOut() {
        throw new jackalope_NotImplementedException();
    }

    /**
     * Returns TRUE if this node is locked either as a result of a lock held
     * by this node or by a deep lock on a node above this node;
     * otherwise returns FALSE. This includes the case where a repository does
     * not support locking (in which case all nodes are "unlocked" by default).
     *
     * @return boolean.
     * @throws PHPCR_RepositoryException if an error occurs.
     * @api
     */
    public function isLocked() {
        throw new jackalope_NotImplementedException();
    }

    /**
     * Causes the lifecycle state of this node to undergo the specified transition.
     * This method may change the value of the jcr:currentLifecycleState property,
     * in most cases it is expected that the implementation will change the value
     * to that of the passed transition parameter, though this is an
     * implementation-specific issue. If the jcr:currentLifecycleState property
     * is changed the change is persisted immediately, there is no need to call
     * save.
     *
     * @param string $transition a state transition
     * @return void
     * @throws PHPCR_UnsupportedRepositoryOperationException  if this implementation does not support lifecycle actions or if this node does not have the mix:lifecycle mixin.
     * @throws PHPCR_InvalidLifecycleTransitionException if the lifecycle transition is not successful.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function followLifecycleTransition($transition) {
        throw new jackalope_NotImplementedException('Write');
    }

    /**
     * Returns the list of valid state transitions for this node.
     *
     * @return array a string array.
     * @throws PHPCR_UnsupportedRepositoryOperationException  if this implementation does not support lifecycle actions or if this node does not have the mix:lifecycle mixin.
     * @throws PHPCR_RepositoryException if another error occurs.
     * @api
     */
    public function getAllowedLifecycleTransitions() {
        throw new jackalope_NotImplementedException('Write');
    }

    /** filter the list of names according to the filter expression / array
     * @param string|array $filter according to getNodes|getProperties
     * @param array $names list of names to filter
     * @return the names in $names that match a filter
     */
    protected static function filterNames($filter, $names) {
        if (is_string($filter)) {
            $filter = explode('|', $filter);
        }
        $filtered = array();
        if ($filter !== null) {
            foreach($filter as $k => $f) {
               $f = trim($f);
               $filter[$k] = strtr($f, array('*'=>'.*', //wildcard
                                             '.'  => '\\.', //escape regexp
                                             '\\' => '\\\\',
                                             '{'  => '\\{',
                                             '}'  => '\\}',
                                             '('  => '\\(',
                                             ')'  => '\\)',
                                             '+'  => '\\+',
                                             '^'  => '\\^',
                                             '$'  => '\\$'));
            }
            foreach($names as $name) {
                foreach($filter as $f) {
                    if (preg_match('/^'.$f.'$/', $name)) {
                        $filtered[] = $name;
                    }
                }
            }
        } else {
            $filtered = $names;
        }
        return $filtered;
    }
}
