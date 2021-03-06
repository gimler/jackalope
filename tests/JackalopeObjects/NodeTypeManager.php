<?php
require_once(dirname(__FILE__) . '/../inc/JackalopeObjectsCase.php');

/**
 * This tests NodeType, NodeTypeDefinition, and NodeTypeTemplate as well
 */
class jackalope_tests_NodeTypeManager extends jackalope_JackalopeObjectsCase {

    public function testGetNodeType() {
        $ntm = $this->getNodeTypeManager();
        $nt = $ntm->getNodeType('nt:file');
        $this->assertType('jackalope_NodeType_NodeType', $nt);
        $this->assertSame('nt:file', $nt->getName());
        $this->assertSame(array('nt:hierarchyNode'), $nt->getDeclaredSupertypeNames());
        $this->assertSame(false, $nt->isAbstract());
        $this->assertSame(false, $nt->isMixin());
        $this->assertSame(false, $nt->hasOrderableChildNodes());
        $this->assertSame(true, $nt->isQueryable());
        $this->assertSame('jcr:content', $nt->getPrimaryItemName());
        $this->assertSame(array(), $ntm->getDeclaredSubtypes('nt:file'));
        $this->assertSame(array(), $ntm->getSubtypes('nt:file'));
        $this->assertSame(array('nt:file', 'nt:folder', 'nt:linkedFile', 'rep:Authorizable', 'rep:AuthorizableFolder'), $ntm->getDeclaredSubtypes('nt:hierarchyNode'));
        $this->assertSame(array('nt:file', 'nt:folder', 'nt:linkedFile', 'rep:Authorizable', 'rep:Group', 'rep:User', 'rep:AuthorizableFolder'), $ntm->getSubtypes('nt:hierarchyNode'));
        $this->assertTrue($ntm->hasNodeType('nt:folder'));
        $this->assertFalse($ntm->hasNodeType('nonode'));
        $allNodes = $ntm->getAllNodeTypes();
        $this->assertType('jackalope_NodeType_NodeTypeIterator', $allNodes);
        $this->assertEquals(52, $allNodes->getSize());
        $this->assertType('jackalope_NodeType_NodeType', $allNodes->nextNodeType());
        $primaryNodes = $ntm->getPrimaryNodeTypes();
        $this->assertType('jackalope_NodeType_NodeTypeIterator', $primaryNodes);
        $this->assertEquals(36, $primaryNodes->getSize());
        $this->assertType('jackalope_NodeType_NodeType', $primaryNodes->nextNodeType());
        $mixinNodes = $ntm->getMixinNodeTypes();
        $this->assertType('jackalope_NodeType_NodeTypeIterator', $mixinNodes);
        $this->assertEquals(16, $mixinNodes->getSize());
        $this->assertType('jackalope_NodeType_NodeType', $mixinNodes->nextNodeType());
    }
    
    public function testNodeTypeMethods() {
        $ntm = $this->getNodeTypeManager();
        $nt = $ntm->getNodeType('nt:configuration');
        $this->assertSame(array($ntm->getNodeType('mix:versionable'),$ntm->getNodeType('mix:referenceable'),$ntm->getNodeType('mix:simpleVersionable'), $ntm->getNodeType('nt:base')),$nt->getSupertypes());
        $this->assertSame(array($ntm->getNodeType('mix:versionable'), $ntm->getNodeType('nt:base')),$nt->getDeclaredSupertypes());
        $declaredSubTypes = $nt->getDeclaredSubtypes();
        $this->assertType('jackalope_NodeType_NodeTypeIterator', $declaredSubTypes);
        $this->assertSame(0, $declaredSubTypes->getSize());
        $subTypes = $nt->getSubtypes();
        $this->assertType('jackalope_NodeType_NodeTypeIterator', $subTypes);
        $this->assertSame(0, $subTypes->getSize());
        $this->assertSame(true,$nt->isNodeType('nt:configuration'));
        $this->assertSame(true,$nt->isNodeType('nt:base'));
        $this->assertSame(true,$nt->isNodeType('mix:simpleVersionable'));
        $this->assertSame(false,$nt->isNodeType('notanodetype'));
        $expectedProperties = array('jcr:root', 'jcr:predecessors', 'jcr:configuration', 'jcr:activity', 'jcr:mergeFailed', 'jcr:versionHistory', 'jcr:baseVersion', 'jcr:uuid', 'jcr:isCheckedOut', 'jcr:mixinTypes', 'jcr:primaryType');
        $this->assertSame(count($expectedProperties), count($nt->getPropertyDefinitions()));
        $i = 0;
        foreach ($nt->getPropertyDefinitions() as $propDef) {
            $this->assertType('jackalope_NodeType_PropertyDefinition', $propDef);
            $this->assertSame($expectedProperties[$i], $propDef->getName());
            $i++;
        }
        $this->assertSame(array(),$nt->getChildNodeDefinitions());
        
        $nt = $ntm->getNodeType('nt:hierarchyNode');
        $declaredSubTypes = $nt->getDeclaredSubtypes();
        $this->assertType('jackalope_NodeType_NodeTypeIterator', $declaredSubTypes);
        $this->assertSame(5, $declaredSubTypes->getSize());
        $subnode = $declaredSubTypes->nextNodeType();
        $this->assertType('jackalope_NodeType_NodeType', $subnode);
        $this->assertSame('nt:file', $subnode->getName());
        $subTypes = $nt->getSubtypes();
        $this->assertType('jackalope_NodeType_NodeTypeIterator', $subTypes);
        $this->assertSame(7, $subTypes->getSize());
        $subTypes->skip(4);
        $subnode = $subTypes->nextNodeType();
        $this->assertType('jackalope_NodeType_NodeType', $subnode);
        $this->assertSame('rep:Group', $subnode->getName());
        
        $nt = $ntm->getNodeType('rep:PrincipalAccessControl');
        $expectedChildNodes = array('rep:policy', '*', '*');
        $this->assertSame(count($expectedChildNodes), count($nt->getChildNodeDefinitions()));
        $i = 0;
        foreach ($nt->getChildNodeDefinitions() as $childNode) {
            $this->assertType('jackalope_NodeType_NodeDefinition', $childNode);
            $this->assertSame($expectedChildNodes[$i], $childNode->getName());
            $i++;
        }
    }
    
    public function testGetDefinedChildNodesAndNodeDefinitions() {
        $ntm = $this->getNodeTypeManager();
        $nt = $ntm->getNodeType('nt:folder');
        $nodes = $nt->getDeclaredChildNodeDefinitions();
        $this->assertType(PHPUnit_Framework_Constraint_IsType::TYPE_ARRAY, $nodes);
        $this->assertEquals(1, count($nodes));
        $node = $nodes[0];
        $this->assertType('jackalope_NodeType_NodeDefinition', $node);
        $this->assertSame('*', $node->getName());
        $this->assertSame(array($ntm->getNodeType('nt:hierarchyNode')), $node->getRequiredPrimaryTypes());
        $this->assertSame(array('nt:hierarchyNode'), $node->getRequiredPrimaryTypeNames());
        $this->assertSame(null, $node->getDefaultPrimaryTypeName());
        $this->assertSame(null, $node->getDefaultPrimaryType());
        $this->assertSame(false, $node->allowsSameNameSiblings());
        
        $ntm = $this->getNodeTypeManager();
        $nt = $ntm->getNodeType('nt:file');
        $nodes = $nt->getDeclaredChildNodeDefinitions();
        $this->assertType(PHPUnit_Framework_Constraint_IsType::TYPE_ARRAY, $nodes);
        $this->assertEquals(1, count($nodes));
        $node = $nodes[0];
        $this->assertType('jackalope_NodeType_NodeDefinition', $node);
        $this->assertSame('jcr:content', $node->getName());
        $this->assertSame(array($ntm->getNodeType('nt:base'), $ntm->getNodeType('nt:folder')), $node->getRequiredPrimaryTypes());
        $this->assertSame(array('nt:base', 'nt:folder'), $node->getRequiredPrimaryTypeNames());
        $this->assertSame(null, $node->getDefaultPrimaryTypeName());
        $this->assertSame(null, $node->getDefaultPrimaryType());
        
        //Test defaultPrimaryType
        $ntm = $this->getNodeTypeManager();
        $nt = $ntm->getNodeType('nt:nodeType');
        $nodes = $nt->getDeclaredChildNodeDefinitions();
        $this->assertEquals(2, count($nodes));
        $node = $nodes[0];
        $this->assertSame('nt:childNodeDefinition', $node->getDefaultPrimaryTypeName());
        $this->assertSame($ntm->getNodeType('nt:childNodeDefinition'), $node->getDefaultPrimaryType());
        $this->assertSame(true, $node->allowsSameNameSiblings());
    }

    public function testGetDefinedPropertysAndPropertyDefinition() {
        $ntm = $this->getNodeTypeManager();
        $nt = $ntm->getNodeType('nt:file');
        $properties = $nt->getDeclaredPropertyDefinitions();
        $this->assertType(PHPUnit_Framework_Constraint_IsType::TYPE_ARRAY, $properties);
        $this->assertEquals(0, count($properties));
        
        $nt = $ntm->getNodeType('mix:created');
        $this->assertType('jackalope_NodeType_NodeType', $nt);
        $this->assertSame('mix:created', $nt->getName());
        $this->assertSame(array(), $nt->getDeclaredSupertypeNames());
        $this->assertSame(false, $nt->isAbstract());
        $this->assertSame(true, $nt->isMixin());
        $this->assertSame(false, $nt->hasOrderableChildNodes());
        $this->assertSame(true, $nt->isQueryable());
        $this->assertSame(null, $nt->getPrimaryItemName());
        
        //ItemDefinition
        $properties = $nt->getDeclaredPropertyDefinitions();
        $this->assertType(PHPUnit_Framework_Constraint_IsType::TYPE_ARRAY, $properties);
        $this->assertEquals(2, count($properties));
        $property = $properties[0];
        $this->assertSame($nt, $property->getDeclaringNodeType());
        $this->assertSame('jcr:createdBy',$property->getName());
        $this->assertSame(true,$property->isAutoCreated());
        $this->assertSame(false,$property->isMandatory());
        $this->assertSame(PHPCR_Version_OnParentVersionAction::COPY,$property->getOnParentVersion());
        $this->assertSame(true,$property->isProtected());
        $this->assertSame(array(),$property->getDefaultValues());
        
        //PropertyDefinition
        $this->assertSame(PHPCR_PropertyType::STRING, $property->getRequiredType());
        $this->assertSame(array(), $property->getValueConstraints());
        $this->assertSame(false, $property->isMultiple());
        $this->assertSame(array('jcr.operator.equal.to', 'jcr.operator.not.equal.to', 'jcr.operator.greater.than', 'jcr.operator.greater.than.or.equal.to', 'jcr.operator.less.than', 'jcr.operator.less.than.or.equal.to', 'jcr.operator.like'), $property->getAvailableQueryOperators());
        $this->assertSame(true, $property->isFullTextSearchable());
        $this->assertSame(true, $property->isQueryOrderable());
        
        $nt = $ntm->getNodeType('mix:versionable');
        $properties = $nt->getDeclaredPropertyDefinitions();
        $property = $properties[0];
        $this->assertSame(array('nt:version'), $property->getValueConstraints());
        
        $nt = $ntm->getNodeType('mix:simpleVersionable');
        $properties = $nt->getDeclaredPropertyDefinitions();
        $property = $properties[0];
        $defaultValues = $property->getDefaultValues();
        $this->assertEquals(1, count($defaultValues));
        $this->assertType('jackalope_Value', $defaultValues[0]);
        $this->assertSame('true', $defaultValues[0]->getString());
        $this->assertSame(true, $defaultValues[0]->getBoolean());
    }

    /**
     * @covers jackalope_NodeType_NodeTypeManager::createNodeTypeTemplate
     */
    public function testCreateNodeTypeTemplate() {
        $ntm = $this->getNodeTypeManager();

        $nt = $ntm->getNodeType('nt:file');
        $ntt = $ntm->createNodeTypeTemplate($nt);

        $this->assertThat($ntt, $this->isInstanceOf('jackalope_NodeType_NodeTypeDefinition'));
        $this->assertType('jackalope_NodeType_NodeTypeTemplate', $ntt);
        $this->assertSame('nt:file', $ntt->getName());

        $ntt->setName('nt:file-ext');
        $this->assertSame('nt:file-ext', $ntt->getName());
    }

    /**
     * @covers jackalope_NodeType_NodeTypeTemplate::__construct
     */
    public function testCreateNodeTypeTemplateEmpty() {
        $ntm = $this->getNodeTypeManager();

        $ntt = $ntm->createNodeTypeTemplate();

        // is empty as defined by doc
        $this->assertNull($ntt->getName());
        $this->assertEquals(array('nt:base'), $ntt->getDeclaredSupertypeNames());
        $this->assertFalse($ntt->isAbstract());
        $this->assertFalse($ntt->isMixin());
        $this->assertFalse($ntt->hasOrderableChildNodes());
        $this->assertFalse($ntt->isQueryable());
        $this->assertNull($ntt->getPrimaryItemName());
        $this->assertNull($ntt->getDeclaredPropertyDefinitions());
        $this->assertNull($ntt->getDeclaredChildNodeDefinitions());
    }

    /**
     * @covers jackalope_NodeType_NodeDefinitionTemplate::__construct
     */
    public function testCreateNodeDefinitionTemplateEmpty() {
        $ntm = $this->getNodeTypeManager();

        $ndt = $ntm->createNodeDefinitionTemplate();

        // is empty as defined by doc
        $this->assertNull($ndt->getName());
        $this->assertFalse($ndt->isAutoCreated());
        $this->assertFalse($ndt->isMandatory());
        $this->assertSame(PHPCR_Version_OnParentVersionAction::COPY, $ndt->getOnParentVersion());
        $this->assertFalse($ndt->isProtected());
        $this->assertNull($ndt->getRequiredPrimaryTypeNames());
        $this->assertNull($ndt->getDefaultPrimaryTypeName());
        $this->assertFalse($ndt->allowsSameNameSiblings());
    }

    /**
     * @covers jackalope_NodeType_PropertyDefinitionTemplate::__construct
     */
    public function testCreatePropertyDefinitionTemplateEmpty() {
        $ntm = $this->getNodeTypeManager();

        $ndt = $ntm->createPropertyDefinitionTemplate();

        // is empty as defined by doc
        $this->assertNull($ndt->getName());
        $this->assertFalse($ndt->isAutoCreated());
        $this->assertFalse($ndt->isMandatory());
        $this->assertSame(PHPCR_Version_OnParentVersionAction::COPY, $ndt->getOnParentVersion());
        $this->assertFalse($ndt->isProtected());
        $this->assertSame(PHPCR_PropertyType::STRING, $ndt->getRequiredType());
        $this->assertNull($ndt->getValueConstraints());
        $this->assertNull($ndt->getDefaultValues());
        $this->assertFalse($ndt->isMultiple());
        $this->assertFalse($ndt->isFullTextSearchable());
        $this->assertFalse($ndt->isQueryOrderable());
    }

}

