<?php

/*
 * $sitemap = new xrowSitemapList();
 * $image = new xrowSitemapItemImage();
 * $image->url = 'http://www.example.com/test.jpg';
 * $extensions[] = $image;
 * $sitemap->add( $url, $extensions ) );
 * $sitemap->save( $filename );
 */
class xrowSitemapList
{
    protected $dom;
    protected $root;
    
    const BASENAME = 'urlset';
    const SUFFIX = 'xml';
    const ITEMNAME = 'url';

    /**
     * 
     */
    function __construct()
    {
        // Create the DOMnode
        $this->dom = new DOMDocument( "1.0", "UTF-8" );
        $this->dom->formatOutput = true;
        // Create DOM-Root (urlset)
        $this->root = $this->dom->createElement( constant(get_class($this).'::BASENAME') );
        $this->root->setAttribute( "xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9" );
        $this->root->setAttribute( "xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance" );
        $this->root->setAttribute( "xsi:schemaLocation", "http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" );
        $this->dom->appendChild( $this->root );
    }

    /**
     * Add a new child to the sitemap
     *
     * @param string $url
     * @param array $extensions Extensions/Modules to the sitemap standard
     * @param int $modified
     * @param string $frequency
     * @param string $priority
     */
    function add( $url, $extensions = array() )
    {
        if ( trim( $url ) == "" )
        {
            return;
        }
        
        $node = $this->dom->createElement( constant(get_class($this).'::ITEMNAME') );
        $subNode = $this->dom->createElement( 'loc' );
        $subNode->appendChild( $this->dom->createTextNode( $url ) );
        $node->appendChild( $subNode );
        
        if ( is_array( $extensions ) )
        {
            foreach ( $extensions as $extension )
            {
                $subNode = $this->createDOMElement( $extension );
                $node->appendChild( $subNode );
            }
        }
        // append to root node
        $this->root->appendChild( $node );
    }
/*
 * @TODO move this function to the domain of xrowsitemapitem later on
 */
    function createDOMElement( $extension )
    {
        if ( $extension instanceof xrowSitemapItemImage )
        {
            $this->root->setAttribute( "xmlns:image", "http://www.google.com/schemas/sitemap-image/1.1" );
            $image = $this->dom->createElement( 'image:image' );
            
            $loc = $this->dom->createElement( 'image:loc' );
            $loc->appendChild( $this->dom->createTextNode( $extension->url ) );
            $image->appendChild( $loc );
            
            if ( isset( $extension->caption ) )
            {
                $caption = $this->dom->createElement( 'image:caption' );
                $caption->appendChild( $this->dom->createTextNode( $extension->caption ) );
                $image->appendChild( $caption );
            }
            
            return $image;
        }
        if ( $extension instanceof xrowSitemapItemModified )
        {
            return $this->dom->createElement( 'lastmod', $extension->date->format( DateTime::W3C ) );
        }
        if ( $extension instanceof xrowSitemapItemPriority )
        {
            $priority = $this->dom->createElement( 'priority' );
            $priority->appendChild( $this->dom->createTextNode( $extension->priority ) );
            return $priority;
        }
        if ( $extension instanceof xrowSitemapItemFrequency )
        {
            $changefreq = $this->dom->createElement( 'changefreq' );
            $changefreq->appendChild( $this->dom->createTextNode( $extension->frequency ) );
            return $changefreq;
        }
        if ( $extension instanceof xrowSitemapItemNews )
        {
            $this->root->setAttribute( "xmlns:news", "http://www.google.com/schemas/sitemap-news/0.9" );
            $news = $this->dom->createElement( 'news:news' );

            $publication = $this->dom->createElement( 'news:publication' );

            $pname = $this->dom->createElement( 'news:name' );
            $cdata_pname = $this->dom->createCDATASection( $extension->publication['name'] );
            $pname->appendChild( $cdata_pname );
            $publication->appendChild( $pname );

            $plang = $this->dom->createElement( 'news:language', $extension->publication['language'] );
            $publication->appendChild( $plang );

            $news->appendChild( $publication );
            $publication_date = $this->dom->createElement( 'news:publication_date', $extension->publication_date->format( DateTime::W3C ) );
            $news->appendChild( $publication_date );

            $title = $this->dom->createElement( 'news:title' );
            $cdata_title = $this->dom->createCDATASection( $extension->title );
            $title->appendChild( $cdata_title );

            $news->appendChild( $title );
            if ( $extension->access )
            {
                $access = $this->dom->createElement( 'news:access' );
                $access->appendChild( $this->dom->createTextNode( $extension->access ) );
                $news->appendChild( $access );
            }
            if ( $extension->genres )
            {
                $genres = $this->dom->createElement( 'news:genres' );
                $genres->appendChild( $this->dom->createTextNode( implode( ',', $extension->genres ) ) );
                $news->appendChild( $genres );
            }
            if ( count( $extension->keywords ) > 0 )
            {
                $keywords = $this->dom->createElement( 'news:keywords' );
                $keywords->appendChild( $this->dom->createTextNode( join( ",", $extension->keywords ) ) );
                $news->appendChild( $keywords );
            }
            return $news;
        }
        if ( $extension instanceof xrowSitemapItem )
        {
            return $extension->createDOMElement( $this, $extension );
        }
    }

    /**
     * Saves the xml content
     *
     * @param $filename Path to file
     */
    function save( $filename = 'sitemap.xml' )
    {
		global $cli, $isQuiet;
        $file = eZClusterFileHandler::instance( $filename );
        if ( $file->exists() )
        {
			eZDebug::writeDebug( "Time: ". date( 'd.m.Y H:i:s') . ". Action: ".$filename." exists. File will be remove." );
			if ( ! $isQuiet )
			{
				$cli->output( "\n" );
				$cli->output( "Time: ". date( 'd.m.Y H:i:s') . ". Action: ".$filename." exists. File will be remove." );
			}
            $file->delete();
        }
        $xml = $this->dom->saveXML();
		return $file->storeContents( $xml, 'sitemap', 'text/xml' );
    }

    /**
     * Gives the xml content
     *
     * @return string XML 
     */
    function saveXML()
    {
        return $this->dom->saveXML();
    }
}

?>