<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\ValueObjects\CheckoutSession */
class CheckoutSessionResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'url' => $this->url,
            'is_dummy' => $this->isDummy,
        ];
    }
}
