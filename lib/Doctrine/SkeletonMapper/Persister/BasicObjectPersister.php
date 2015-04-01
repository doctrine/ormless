<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\SkeletonMapper\Persister;

use Doctrine\SkeletonMapper\ObjectManagerInterface;

abstract class BasicObjectPersister extends ObjectPersister
{
    /**
     * @var \Doctrine\SkeletonMapper\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var \Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface
     */
    protected $class;

    /**
     * @param \Doctrine\SkeletonMapper\ObjectManagerInterface $objectManager $eventManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param string $className
     */
    public function setClassName($className)
    {
        $this->className = $className;
    }

    /**
     * @return \Doctrine\SkeletonMapper\Mapping\ClassMetadataInterface
     */
    public function getClassMetadata()
    {
        if ($this->class === null) {
            $this->class = $this->objectManager->getClassMetadata($this->getClassName());
        }

        return $this->class;
    }

    /**
     * Prepares an object changeset for persistence.
     *
     * @param \Doctrine\SkeletonMapper\Persister\PersistableInterface $object
     * @param array                                                   $changeSet
     *
     * @return array
     */
    public function prepareChangeSet($object, array $changeSet = array())
    {
        if ($object instanceof PersistableInterface) {
            return $object->prepareChangeSet($changeSet);
        }

        return $this->dynamicPrepareChangeSet($object, $changeSet);
    }

    /**
     * Assign identifier to object.
     *
     * @param object $object
     * @param array  $identifier
     */
    public function assignIdentifier($object, array $identifier)
    {
        if ($object instanceof IdentifiableInterface) {
            return $object->assignIdentifier($identifier);
        }

        return $this->dynamicAssignIdentifier($object, $identifier);
    }

    /**
     * Dynamically prepare a changeset using mapping information.
     *
     * @param \Doctrine\SkeletonMapper\Persister\PersistableInterface $object
     * @param array                                                   $changeSet
     *
     * @return array
     */
    private function dynamicPrepareChangeSet($object, array $changeSet = array())
    {
        if ($changeSet) {
            return array_map(function($change) {
                return $change[1];
            }, $changeSet);
        }

        $class = $this->getClassMetadata();

        $changeSet = array();
        foreach ($class->fieldMappings as $fieldMapping) {
            $value = $class->reflFields[$fieldMapping['fieldName']]->getValue($object);

            $changeSet[$fieldMapping['name']] = $value;
        }

        return $changeSet;
    }

    /**
     * Dynamically assign identifier to object using mapping information.
     *
     * @param object $object
     * @param array  $identifier
     */
    private function dynamicAssignIdentifier($object, array $identifier)
    {
        $class = $this->getClassMetadata();

        $class->reflFields[$class->identifierFieldNames[0]]
            ->setValue($object, $identifier[$class->identifier[0]]);
    }
}
