<?php

namespace App\Contracts;

use App\Models\User;

interface RegisterUserActionInterface
{
    /**
     * @param  array<string, string>  $data
     */
    public function execute(array $data): User;
}
