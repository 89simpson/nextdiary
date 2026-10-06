<?php

namespace OCA\NextDiary\Service\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Renders Markdown images as their (escaped) alt text instead of an <img> tag.
 *
 * Diary exports never embed images: an <img> would make the PDF renderer resolve the
 * image source, and a crafted `data:image/...` URI with a huge bitmap can exhaust server
 * resources (dompdf CVE-2026-59942). Local paths and remote URLs are not wanted either.
 * The alt text is rendered through the regular inline renderers, so it stays escaped.
 */
class ImageAltTextRenderer implements NodeRendererInterface
{
    /**
     * @return string
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        Image::assertInstanceOf($node);

        return $childRenderer->renderNodes($node->children());
    }
}
