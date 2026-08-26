<?php

namespace Yntech\DomainForge;

class DomainForge
{
    /**
     * current version of the package.
     */
    const VERSION = '4.0.0';

    /**
     * Return the current version of the package
     *
     * @return string
     */
    public static function version()
    {
        return self::VERSION;
    }
}