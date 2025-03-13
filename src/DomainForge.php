<?php

namespace Yntech\DomainForge;

class DomainForge
{
    /**
     * current version of the package.
     */
    const VERSION = '1.0.0-dev';
    
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