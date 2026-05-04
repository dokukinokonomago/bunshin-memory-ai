<?php

namespace App\Auth;

use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request;

class BearerTokenGuard extends RequestGuard
{
    /**
     * Set the current request instance.
     *
     * @return $this
     */
    public function setRequest(Request $request)
    {
        parent::setRequest($request);

        return $this->forgetUser();
    }
}
