<?php

namespace Yntech\DomainForge;

class DomainForge
{
    /**
     * Versión actual del paquete.
     */
    const VERSION = '1.0.0-dev';
    
    /**
     * Obtiene la versión actual del paquete.
     *
     * @return string
     */
    public static function version()
    {
        return self::VERSION;
    }
}