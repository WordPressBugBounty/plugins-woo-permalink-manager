<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit18e76e752c766fabdc7fe224318a4da6
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Premmerce\\UrlManager\\' => 21,
            'Premmerce\\SDK\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Premmerce\\UrlManager\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Premmerce\\SDK\\' => 
        array (
            0 => __DIR__ . '/..' . '/premmerce/wordpress-sdk/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit18e76e752c766fabdc7fe224318a4da6::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit18e76e752c766fabdc7fe224318a4da6::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit18e76e752c766fabdc7fe224318a4da6::$classMap;

        }, null, ClassLoader::class);
    }
}
