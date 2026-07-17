<?php

namespace App\Contracts;

interface HandleInvoicePaidActionInterface
{
    public function execute(object $invoice): void;
}
