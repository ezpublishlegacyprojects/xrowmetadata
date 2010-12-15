<?php

class xrowSitemapItem extends ezcBaseStruct
{
    function hasattribute($name)
    {
        $classname = get_class($this);
        $vars = get_class_vars($classname);
        if ( array_key_exists($name,$vars) )
            return true;
        else
            return false;
    }
    function attribute($name)
    {
        return $this->$name;
    }
}
?>
