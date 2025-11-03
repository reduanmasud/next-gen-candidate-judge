<?php

namespace App\Scripts;

class CreateUserScript extends Script
{

    public function __construct(
        protected string $username,
        protected string $password,
    ) {
        //
    }

    public function name(): string
    {
        return 'Create User Script for ' . $this->username;
    }

    public function template(): string
    {
        return 'scrips.create_user';
    }

    public function data(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}
