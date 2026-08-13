<?php

namespace App\Enums;

enum OptimisationType: string
{
    case Title = 'title';
    case MetaDescription = 'meta_description';
    case H1 = 'h1';
    case Text = 'text';
    case Html = 'html';
    case AppendHtml = 'append_html';
    case PrependHtml = 'prepend_html';
    case Attribute = 'attribute';
    case ImageAlt = 'image_alt';
    case InternalLink = 'internal_link';
    case JsonLd = 'json_ld';

    public function isPixelDeployable(): bool
    {
        return true;
    }
}
