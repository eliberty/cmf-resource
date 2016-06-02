<?php

/*
 * This file is part of the Symfony CMF package.
 *
 * (c) 2011-2015 Symfony CMF
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Cmf\Component\Resource\Repository;

use DTL\Glob\Finder\PhpcrTraversalFinder;
use DTL\Glob\FinderInterface;
use PHPCR\SessionInterface;
use Puli\Repository\Api\ResourceNotFoundException;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Symfony\Cmf\Component\Resource\Repository\Resource\PhpcrResource;

/**
 * Resource repository for PHPCR.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class PhpcrRepository extends AbstractPhpcrRepository
{
    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @param SessionInterface $session
     * @param string           $basePath
     * @param FinderInterface  $finder
     */
    public function __construct(SessionInterface $session, $basePath = null, FinderInterface $finder = null)
    {
        $finder = $finder ?: new PhpcrTraversalFinder($session);
        parent::__construct($finder, $basePath);
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function get($path)
    {
        $resolvedPath = $this->resolvePath($path);

        try {
            $node = $this->session->getNode($resolvedPath);
        } catch (\PHPCR\PathNotFoundException $e) {
            throw new ResourceNotFoundException(sprintf(
                'No PHPCR node could be found at "%s"',
                $resolvedPath
            ), null, $e);
        }

        if (null === $node) {
            throw new \RuntimeException('Session did not return a node or throw an exception');
        }

        $resource = new PhpcrResource($path, $node);
        $resource->attachTo($this);

        return $resource;
    }

    public function listChildren($path)
    {
        $resource = $this->get($path);

        return $this->buildCollection((array) $resource->getPayload()->getNodes());
    }

    /**
     * {@inheritdoc}
     */
    public function contains($selector, $language = 'glob')
    {
        return count($this->find($selector, $language)) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function findByTag($tag)
    {
        throw new \Exception('Get by tag not currently supported');
    }

    /**
     * {@inheritdoc}
     */
    public function getTags()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    protected function buildCollection(array $nodes)
    {
        $collection = new ArrayResourceCollection();

        if (!$nodes) {
            return $collection;
        }

        foreach ($nodes as $node) {
            $path = $this->unresolvePath($node->getPath());
            $resource = new PhpcrResource($path, $node);
            $resource->attachTo($this);
            $collection->add($resource);
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    protected function removeNodes($nodes)
    {
        foreach ($nodes as $node) {
            $node->remove();
        }

        $this->session->save();
    }

    /**
     * {@inheritdoc}
     */
    protected function moveNodes($nodes, $sourceQuery, $targetPath)
    {
        $this->doMoveNodes($nodes, $sourceQuery, $targetPath);
        $this->session->save();
    }

    private function doMoveNodes($nodes, $sourceQuery, $targetPath)
    {
        if (count($nodes) === 1 && current($nodes)->getPath() === $sourceQuery) {
            return $this->session->move(current($nodes)->getPath(), $targetPath);
        }

        foreach ($nodes as $node) {
            $this->session->move($node->getPath(), $targetPath.'/'.$node->getName());
        }
    }
}
