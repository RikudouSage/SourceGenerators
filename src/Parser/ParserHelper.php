<?php

namespace Rikudou\SourceGenerators\Parser;

use LogicException;
use PhpParser\Node;
use PhpParser\Node\AttributeGroup;

final readonly class ParserHelper
{
    /**
     * @param class-string $attribute
     */
    public function hasAttribute(Node $node, string $attribute): bool
    {
        if (!property_exists($node, 'attrGroups')) {
            return false;
        }

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->name === $attribute) {
                    return true;
                }
            }
        }

        return false;
    }

    public function removeAttribute(Node $node, string $attribute): void
    {
        assert(property_exists($node, 'attrGroups'));
        foreach ($node->attrGroups as $groupIndex => $attrGroup) {
            assert($attrGroup instanceof AttributeGroup);
            foreach ($attrGroup->attrs as $attrIndex => $attr) {
                if ($attr->name->name !== $attribute) {
                    continue;
                }

                unset($attrGroup->attrs[$attrIndex]);
                if (!count($attrGroup->attrs)) {
                    unset($node->attrGroups[$groupIndex]);
                }
            }
        }
    }

    public function toExpression(Node\Expr|string|bool|int|float|array $value): Node\Expr
    {
        if ($value instanceof Node\Expr) {
            return $value;
        }

        if (is_string($value)) {
            return new Node\Scalar\String_($value);
        }
        if (is_bool($value)) {
            return new Node\Expr\ConstFetch(new Node\Name($value ? 'true' : 'false'));
        }
        if (is_int($value)) {
            return new Node\Scalar\Int_($value);
        }
        if (is_float($value)) {
            return new Node\Scalar\Float_($value);
        }
        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[] = new Node\ArrayItem($this->toExpression($item), $this->toExpression($key));
            }
            return new Node\Expr\Array_($items);
        }

        throw new LogicException('Could not conver type to expression: ' . gettype($value));
    }
}
