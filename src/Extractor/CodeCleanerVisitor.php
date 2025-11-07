<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Extractor;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Removes comments and attributes from AST nodes
 */
final class CodeCleanerVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): Node
    {
        // Remove all comments
        $node->setAttribute('comments', []);

        // Remove attributes from nodes that support them
        if (property_exists($node, 'attrGroups')) {
            $node->attrGroups = [];
        }

        return $node;
    }
}
