<?php

namespace LogScope\Auth;

use Illuminate\Http\Request;

interface Authorizer
{
    /**
     * Decide whether the given request may access LogScope.
     */
    public function authorize(Request $request): bool;
}
