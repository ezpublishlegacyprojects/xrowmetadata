<?php

class xrowSitemapItemImage extends xrowSitemapItem
{
    public $url = array(); // array
    public $caption;
    public $geo_location = array(); // Array
    public $title; // text
    public $license; // text

    /**
     * @return xrowMetaData
     */
    static public function __set_state( array $array )
    {
        return new xrowSitemapItemImage( $array['url'], $array['caption'], $array['geo_location'], $array['title'], $array['license'] );
    }
}
?>
