<?php
/*
 *  Compatibility layer to DataStorage2
*/


class DataStorage extends DataStorage2
{
    public function __construct($args, $sid = null, $lockDB = null, $format = null, $lockTimeout = null, $secure = null)
    {
        if ($sid !== null) {
            if (is_string($args)) {
                $args = ['dataFile' => $args];
            }
            $args = array_merge($args, ['sid' => $sid, 'lockDB' => $lockDB, 'format' => $format,'secure' => $secure]);
        }
        parent::__construct($args);
    }



    public function read( $arg = null )
    {
        if (!$arg || ($arg === '*')) {
            return parent::read();
        } else {
            return parent::readElement($arg);
        }
    }


    public function write($data, $replace = true)
    {
        if (is_array($data)) {
            return parent::write($data, $replace);
        } else {
            // $value == $replace;
            return parent::writeElement(data, $replace);
        }
    }

} // DataStorage

