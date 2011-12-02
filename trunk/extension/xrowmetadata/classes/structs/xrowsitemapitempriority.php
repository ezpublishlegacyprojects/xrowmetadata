<?php

class xrowSitemapItemPriority extends xrowSitemapItem
{
    
    public $priority; // text

    
    function __construct( $priority = false )
    {
        if ( $priority )
        {
            $this->priority = $priority;
        }
    
    }

    function DOMElement( xrowSitemapList $sitemap )
    {
        $priority = $sitemap->dom->createElement( 'priority' );
        $priority->appendChild( $sitemap->dom->createTextNode( $this->priority ) );
        return $priority;
    }

    /**
     * @return xrowSitemapItemModified
     */
    static public function __set_state( array $array )
    {
        return new xrowSitemapItemPriority( $array['priority'] );
    }
}
?>
