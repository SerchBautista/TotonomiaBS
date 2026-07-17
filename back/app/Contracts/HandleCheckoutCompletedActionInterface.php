<?php

namespace App\Contracts;

interface HandleCheckoutCompletedActionInterface
{
    public function execute(object $session): void;
}
