<?php


namespace Coffreo\CephOdm\Exception;

/**
 * Library specific exception
 */
class Exception extends \Exception
{
    const BUCKET_NOT_FOUND = 1;
    const MISSING_REQUIRED_PROPERTY = 2;
}