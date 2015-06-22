<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Eav\Model\Entity\VersionControl;

/**
 * Class AbstractEntity
 */
abstract class AbstractEntity extends \Magento\Eav\Model\Entity\AbstractEntity
{
    /**
     * @var \Magento\Framework\Model\Resource\Db\VersionControl\Snapshot
     */
    protected $entitySnapshot;

    /**
     * @var \Magento\Framework\Model\Resource\Db\VersionControl\RelationComposite
     */
    protected $entityRelationComposite;

    /**
     * @param \Magento\Framework\Model\Resource\Db\VersionControl\Snapshot $entitySnapshot
     * @param \Magento\Framework\Model\Resource\Db\VersionControl\RelationComposite $entityRelationComposite
     * @param \Magento\Eav\Model\Entity\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Eav\Model\Entity\Context $context,
        \Magento\Framework\Model\Resource\Db\VersionControl\Snapshot $entitySnapshot,
        \Magento\Framework\Model\Resource\Db\VersionControl\RelationComposite $entityRelationComposite,
        $data = []
    ) {
        $this->entitySnapshot = $entitySnapshot;
        $this->entityRelationComposite = $entityRelationComposite;

        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _afterLoad(\Magento\Framework\Object $object)
    {
        $this->entitySnapshot->registerSnapshot($object);
        return parent::_afterLoad($object);
    }

    /**
     * @inheritdoc
     */
    public function save(\Magento\Framework\Model\AbstractModel $object)
    {
        /**
         * Direct deleted items to delete method
         */
        if ($object->isDeleted()) {
            return $this->delete($object);
        }

        $this->beginTransaction();

        try {
            if (!$this->isModified($object)) {
                $this->entityRelationComposite->processRelations($object);
                $this->commit();
                return $this;
            }

            $object->validateBeforeSave();
            $object->beforeSave();

            if ($object->isSaveAllowed()) {
                if (!$this->isPartialSave()) {
                    $this->loadAllAttributes($object);
                }

                if ($this->getEntityTable() ==  \Magento\Eav\Model\Entity::DEFAULT_ENTITY_TABLE
                    && !$object->getEntityTypeId()
                ) {
                    $object->setEntityTypeId($this->getTypeId());
                }

                $object->setParentId((int)$object->getParentId());

                $this->objectRelationProcessor->validateDataIntegrity($this->getEntityTable(), $object->getData());

                $this->_beforeSave($object);
                $this->_processSaveData($this->_collectSaveData($object));
                $this->_afterSave($object);
                $this->entitySnapshot->registerSnapshot($object);
                $object->afterSave();
                $this->entityRelationComposite->processRelations($object);
            }

            $this->addCommitCallback([$object, 'afterCommitCallback'])->commit();
            $object->setHasDataChanges(false);
        } catch (\Exception $e) {
            $this->rollBack();
            $object->setHasDataChanges(true);
            throw $e;
        }

        return $this;
    }

    protected function isModified(\Magento\Framework\Model\AbstractModel $object)
    {
        return $this->entitySnapshot->isModified($object);
    }
}
