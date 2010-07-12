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

/**
 * The Manager provides functions for creating and fetching a NestedSet tree.
 *
 * @author  Brandon Turner <bturner@bltweb.net>
 */
class Manager
{
    /** @var Config */
    protected $config;

    /** @var array */
    protected $wrappers;


    /**
     * Initializes a new NestedSet Manager.
     *
     * @param string|Doctrine\ORM\Mapping\ClassMetadata $clazz the fully qualified entity class name
     *   or a ClassMetadata object representing the class of nodes to be managed
     *   by this manager
     * @param Doctrine\ORM\EntityManager $em The EntityManager to use.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->wrappers = array();
    }


    /**
     * Fetches the complete tree, returning the root node of the tree
     *
     * @param mixed $rootId the root id of the tree (or null if model doesn't
     *   support multiple trees
     *
     * @return NodeWrapper $root
     */
    public function fetchTree($rootId=null)
    {
        $wrappers = $this->fetchTreeAsArray($rootId);

        if(is_array($wrappers))
        {
            return $wrappers[0];
        }

        return $wrappers;
    }


    /**
     * Fetches the complete tree, returning a flat array of node wrappers with
     * parent, children, ancestors and descendants pre-populated.
     *
     * @param mixed $rootId the root id of the tree (or null if model doesn't
     *   support multiple trees
     *
     * @return array
     */
    public function fetchTreeAsArray($rootId=null)
    {
        $config = $this->getConfiguration();
        $lftField = $config->getLeftFieldName();
        $rgtField = $config->getRightFieldName();
        $rootField = $config->getRootFieldName();

        if($rootId === null && $rootField !== null)
        {
            throw new \InvalidArgumentException('Must provide root id');
        }

        $qb = $config->getBaseQueryBuilder();
        $alias = $config->getQueryBuilderAlias();

        $qb->andWhere("$alias.$lftField >= :lowerbound")
            ->setParameter('lowerbound', 1)
            ->orderBy("$alias.$lftField", "ASC");

        // TODO: Add support for depth?

        if($rootField !== null)
        {
            $qb->andWhere("$alias.$rootField = :rootid")
                ->setParameter('rootid', $rootId);
        }

        $nodes = $qb->getQuery()->execute();
        if(empty($nodes))
        {
            return null;
        }

        $wrappers = array();
        foreach($nodes as $node)
        {
            $wrappers[] = $this->wrapNode($node);
        }

        $this->buildTree($wrappers);

        return $wrappers;
    }


    /**
     * Fetches a branch of a tree, returning the starting node of the branch.
     * All children and descendants are pre-populated.
     *
     * @param mixed $pk the primary key used to locate the node to traverse
     *   the tree from
     *
     * @return NodeWrapper $branch
     */
    public function fetchBranch($pk)
    {
        $wrappers = $this->fetchBranchAsArray($pk);

        if(is_array($wrappers))
        {
            return $wrappers[0];
        }

        return $wrappers;
    }


    /**
     * Fetches a branch of a tree, returning a flat array of node wrappers with
     * parent, children, ancestors and descendants pre-populated.
     *
     * @param mixed $pk the primary key used to locate the node to traverse
     *   the tree from
     *
     * @return array
     */
    public function fetchBranchAsArray($pk)
    {
        $config = $this->getConfiguration();
        $lftField = $config->getLeftFieldName();
        $rgtField = $config->getRightFieldName();
        $rootField = $config->getRootFieldName();

        $node = $this->getEntityManager()->find($this->getConfiguration()->getClassname(), $pk);

        if(!$node)
        {
            return null;
        }

        $qb = $config->getBaseQueryBuilder();
        $alias = $config->getQueryBuilderAlias();

        $qb->andWhere("$alias.$lftField >= :lowerbound")
            ->setParameter('lowerbound', $node->getLeftValue())
            ->andWhere("$alias.$rgtField <= :upperbound")
            ->setParameter('upperbound', $node->getRightValue())
            ->orderBy("$alias.$lftField", "ASC");

        // TODO: Add support for depth?

        if($this->getConfiguration()->isRootFieldSupported())
        {
            $qb->andWhere("$alias.$rootField = :rootid")
                ->setParameter('rootid', $node->getRootValue());
        }

        $nodes = $qb->getQuery()->execute();
        // @codeCoverageIgnoreStart
        if(empty($nodes))
        {
            return null;
        }
        // @codeCoverageIgnoreEnd

        $wrappers = array();
        foreach($nodes as $node)
        {
            $wrappers[] = $this->wrapNode($node);
        }

        $this->buildTree($wrappers);

        return $wrappers;
    }


    /**
     * Creates a new root node
     *
     * NOTE: This persists an entity via the EntityManager but does not call
     * flush.  To save the new node to the database you should call
     * EntityManager::flush.
     *
     * @param Node
     *
     * @return NodeWrapper
     */
    public function createRoot(Node $node, $rootId=null)
    {
        if($node instanceof NodeWrapper)
        {
            throw new \InvalidArgumentException('Can\'t create a root node from a NodeWrapper node');
        }

        $node->setLeftValue(1);
        $node->setRightValue(2);
        if($rootId !== null)
        {
            $node->setRootValue($rootId);
        }
        $this->getEntityManager()->persist($node);
        return $this->wrapNode($node);
    }


    /**
     * wraps the node using the NodeWrapper class
     *
     * @param Node $node
     *
     * @return NodeWrapper
     */
    public function wrapNode(Node $node)
    {
        if($node instanceof NodeWrapper)
        {
            throw new \InvalidArgumentException('Can\'t wrap a NodeWrapper node');
        }

        if(!array_key_exists($node->getId(), $this->wrappers))
        {
            $this->wrappers[$node->getId()] = new NodeWrapper($node, $this);
        }

        return $this->wrappers[$node->getId()];
    }




    /**
     * Returns the Doctrine entity manager associated with this Manager
     *
     * @return Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->getConfiguration()->getEntityManager();
    }


    /**
     * gets configuration
     *
     * @return Config
     */
    public function getConfiguration()
    {
        return $this->config;
    }



    //
    // Methods marked internal should not be used outside of the
    // NestedSet namespace
    //


    /**
     * Internal
     * Updates the left values of managed nodes
     *
     * @param int $first first left value to shift
     * @param int $last last left value to shift, or 0
     * @param int $delta offset to shift by
     * @param mixed $rootVal the root value of entities to act upon
     *
     */
    public function updateLeftValues($first, $last, $delta, $rootVal)
    {
        $rootField = $this->getConfiguration()->getRootFieldName();

        foreach($this->wrappers as $wrapper)
        {
            if(($rootField === null) || ($wrapper->getRootValue() == $rootVal))
            {
                if($wrapper->getLeftValue() >= $first && ($last === 0 || $wrapper->getLeftValue() <= $last))
                {
                    $wrapper->setLeftValue($wrapper->getLeftValue() + $delta);
                    $wrapper->invalidate();
                }
            }
        }
    }


    /**
     * Internal
     * Updates the right values of managed nodes
     *
     * @param int $first first right value to shift
     * @param int $last last right value to shift, or 0
     * @param int $delta offset to shift by
     * @param mixed $rootVal the root value of entities to act upon
     *
     */
    public function updateRightValues($first, $last, $delta, $rootVal)
    {
        $rootField = $this->getConfiguration()->getRootFieldName();

        foreach($this->wrappers as $wrapper)
        {
            if(($rootField === null) || ($wrapper->getRootValue() == $rootVal))
            {
                if($wrapper->getRightValue() >= $first && ($last === 0 || $wrapper->getRightValue() <= $last))
                {
                    $wrapper->setRightValue($wrapper->getRightValue() + $delta);
                    $wrapper->invalidate();
                }
            }
        }
    }


    /**
     * Internal
     * Updates the left, right and root values of managed nodes
     *
     * @param int $first lowerbound (lft/rgt) of nodes to update
     * @param int $last upperbound (lft/rgt) of nodes to update, or 0
     * @param int $delta delta to add to lft/rgt values (can be negative)
     * @param mixed $oldRoot the old root value of entities to act upon
     * @param mixed $newRoot the new root value to set (or null to not change root)
     */
    public function updateValues($first, $last, $delta, $oldRoot=null, $newRoot=null)
    {
        if(!$this->wrappers)
        {
            return;
        }

        $rootField = $this->getConfiguration()->getRootFieldName();

        foreach($this->wrappers as $wrapper)
        {
            if($rootField === null || ($wrapper->getRootValue() == $oldRoot))
            {
                if($wrapper->getLeftValue() >= $first && ($last === 0 || $wrapper->getRightValue() <= $last))
                {
                    if($delta !== 0)
                    {
                        $wrapper->setLeftValue($wrapper->getLeftValue() + $delta);
                        $wrapper->setRightValue($wrapper->getRightValue() + $delta);
                    }
                    if($newRoot !== null)
                    {
                        $wrapper->setRootValue($newRoot);
                    }
                }
            }
        }
    }


    /**
     * Internal
     * Removes managed nodes
     *
     * @param int $left
     * @param int $right
     * @param mixed $root
     */
    public function removeNodes($left, $right, $root)
    {
        $rootField = $this->getConfiguration()->getRootFieldName();

        $removed = array();
        foreach($this->wrappers as $wrapper)
        {
            if($rootField === null || ($wrapper->getRootValue() == $root))
            {
                if($wrapper->getLeftValue() >= $left && $wrapper->getRightValue() <= $right)
                {
                    $removed[$wrapper->getId()] = $wrapper;
                }
            }
        }

        foreach($removed as $key => $wrapper)
        {
            unset($this->wrappers[$key]);
            $wrapper->setLeftValue(0);
            $wrapper->setRightValue(0);
            $wrapper->setRootValue(0);
            $this->getEntityManager()->detach($wrapper->getNode());
        }
    }



    protected function buildTree($wrappers)
    {
        // @codeCoverageIgnoreStart
        if(empty($wrappers))
        {
            return;
        }
        // @codeCoverageIgnoreEnd

        $rootNode = $wrappers[0];
        $stack = array();

        foreach($wrappers as $wrapper)
        {
            $parent = end($stack);
            while($parent && $wrapper->getLeftValue() > $parent->getRightValue())
            {
                array_pop($stack);
                $parent = end($stack);
            }

            if($parent && $wrapper !== $rootNode)
            {
                $wrapper->internalSetParent($parent);
                $parent->internalAddChild($wrapper);
                $wrapper->internalSetAncestors($stack);
                foreach($stack as $anc)
                {
                    $anc->internalAddDescendant($wrapper);
                }
            }

            if($wrapper->hasChildren())
            {
                array_push($stack, $wrapper);
            }
        }
    }


}
