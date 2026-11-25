<?php

namespace App\Http\Controllers;

use App\Models\ProductWidget;
use App\Services\AccountAccess;

class ProductShareController extends Controller
{
    public function show(ProductWidget $product)
    {
        abort_unless(AccountAccess::manages($product), 404);
        $url = route('products.show', $product);
        $title = htmlspecialchars($product->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $embedUrl = route('products.embed', $product);
        $buyUrl = route('products.checkout', $product);
        $widgetCode = '<iframe src="'.$embedUrl.'" title="'.$title.'" width="420" height="740" style="border:0;max-width:100%;" loading="lazy"></iframe>';
        $buttonCode = '<a href="'.$buyUrl.'" target="_blank" rel="noopener" style="display:inline-block;padding:14px 24px;background:#ffb66e;color:#171b18;border:1px solid #f2a352;border-radius:8px;font:600 16px Arial,sans-serif;text-decoration:none;">Buy now on Ne_Woo</a>';
        $text = rawurlencode($product->name.' '.$url);
        $channels = [
            'WhatsApp' => 'https://wa.me/?text='.$text,
            'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url),
            'X' => 'https://twitter.com/intent/tweet?text='.rawurlencode($product->name).'&url='.rawurlencode($url),
            'Email' => 'mailto:?subject='.rawurlencode($product->name).'&body='.$text,
            'SMS' => 'sms:?body='.$text,
        ];
        return response()->view('merchant-share', compact('product', 'url', 'widgetCode', 'buttonCode', 'channels'))->header('Cache-Control', 'private, no-store');
    }
}
