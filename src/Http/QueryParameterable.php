<?php

namespace mindtwo\TwoTility\Http;

interface QueryParameterable
{
    /**
     * Convert the object to a query parameter string.
     *
     * @return string
     */
    public function toQueryParam(): string;

}
