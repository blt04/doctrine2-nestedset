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
 * and is licensed under the LGPL.
 */

namespace DoctrineExtensions\NestedSet;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * The Config class holds configuration for each NestedSet Manager instance.
 *
 * @author  Brandon Turner <bturner@bltweb.net>
 */
class Config
{
    private
        $em,
        $classname,
        $classMetadata,
        $leftFieldName,
        $rightFieldName,
        $rootFieldName,
        $baseQueryBuilder;

    /**
     * Constructor.
     *
     * @param EntityManager $em
     * @param mixed $clazz a class name or ClassMetadata object representing
     *   the entity class associated with this configuration
     */
    public function __construct(EntityManager $em, $clazz=null)
    {
        $this->em = $em;
        if($clazz)
        {
            $this->setClass($clazz);
        }

        // Set defaults
        $this->setLeftFieldName('lft');
        $this->setRightFieldName('rgt');
        $this->setRootFieldName('root');
    }


    /**
     * Sets the class associated with this configuration
     *
     * @param mixed $clazz a class name or ClassMetadata object representing
     *   the entity class associated with this configuration
     *
     * @return Config $this for fluent API
     */
    public function setClass($clazz)
    {
        if($clazz instanceof ClassMetadata)
        {
            $classMetadata = $clazz;
            $classname = $clazz->getReflectionClass()->getName();
        }
        else
        {
            if(!class_exists($clazz))
            {
                throw new \InvalidArgumentException("Can't find class: $clazz");
            }

            $classname = $clazz;
            $classMetadata = $this->getEntityManager()->getClassMetadata($clazz);
        }

        $reflectionClass = $classMetadata->getReflectionClass();
        if(!$reflectionClass->implementsInterface('DoctrineExtensions\NestedSet\Node'))
        {
            throw new \InvalidArgumentException('Class must implement Node interface: ' . $classname);
        }

        $this->classMetadata = $classMetadata;
        $this->classname = $classname;

        return $this;
    }


    /**
     * gets the entity class name associated with this configuration
     *
     * @return string
     */
    public function getClassname()
    {
        return $this->classname;
    }


    /**
     * gets the class metadata associated with this configuration
     *
     * @return ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->classMetadata;
    }


    /**
     * Returns the Doctrine entity manager associated with this Manager
     *
     * @return Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }


    /**
     * gets the left field name
     *
     * @return string
     */
    public function getLeftFieldName()
    {
        return $this->leftFieldName;
    }

    /**
     * sets the left field name
     *
     * @param string $fieldName
     *
     * @return Config $this for fluent API
     */
    public function setLeftFieldName($fieldName)
    {
        $this->leftFieldName = $fieldName;
        return $this;
    }


    /**
     * gets the right field name
     *
     * @return string
     */
    public function getRightFieldName()
    {
        return $this->rightFieldName;
    }

    /**
     * sets the right field name
     *
     * @param string $fieldName
     *
     * @return Config $this for fluent API
     */
    public function setRightFieldName($fieldName)
    {
        $this->rightFieldName = $fieldName;
        return $this;
    }


    /**
     * gets the root field name
     *
     * @return string
     */
    public function getRootFieldName()
    {
        return $this->rootFieldName;
    }

    /**
     * sets the root field name
     *
     * @param string $fieldName
     *
     * @return Config $this for fluent API
     */
    public function setRootFieldName($fieldName)
    {
        $this->rootFieldName = $fieldName;
        return $this;
    }


    /**
     * returns true if the root field is supported
     *
     * @return bool
     */
    public function isRootFieldSupported()
    {
        return $this->getRootFieldName() !== null;
    }

    /**
     * gets the base query builder
     *
     * @return QueryBuilder
     */
    public function getBaseQueryBuilder()
    {
        if(!$this->baseQueryBuilder)
        {
            $this->baseQueryBuilder = $this->getDefaultQueryBuilder();
        }

        return clone $this->baseQueryBuilder;
    }


    /**
     * sets the base query builder
     *
     * @param Query $baseQueryBuilder or null to reset the base query builder
     */
    public function setBaseQueryBuilder(QueryBuilder $baseQueryBuilder=null)
    {
        if($baseQueryBuilder === null)
        {
            $this->baseQueryBuilder = $this->getDefaultQueryBuilder();
        }
        else
        {
            $this->baseQueryBuilder = $baseQueryBuilder;
        }
    }


    /**
     * rests the base query builder back to the default
     */
    public function resetBaseQueryBuilder()
    {
        $this->setBaseQueryBuilder(null);
    }


    /**
     * gets the default query builder
     *
     * @return QueryBuilder
     */
    public function getDefaultQueryBuilder()
    {
        $em = $this->getEntityManager();
        return $em->createQueryBuilder()
            ->select('n')
            ->from($this->getClassname(), 'n');
    }


    public function getQueryBuilderAlias()
    {
        return $this->getBaseQueryBuilder()->getRootAlias();
    }
}
