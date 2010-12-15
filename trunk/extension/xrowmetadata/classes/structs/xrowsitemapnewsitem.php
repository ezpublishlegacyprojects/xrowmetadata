<?php

class xrowSitemapItemNews extends xrowSitemapItem
{
    public $publication = array(); // array
    public $access;
    public $genres = array(); // Array
    public $publication_date; // YYYY-MM-DDThh:mm:ssTZD (e.g., 1997-07-16T19:20:30+01:00)
    public $title; // text
    public $keywords = array(); // array
    public $stock_tickers = array(); // Array
    function __construct()
    {
    	if ( !eZINI::instance( 'xrowsitemap.ini' )->hasVariable( 'NewsSitemapSettings', 'Name' ) )
    	{
    		throw new Exception( "Publication Name is required for News Sitemap" );	
    	}
    	$this->publication = array( 'name' => eZINI::instance( 'xrowsitemap.ini' )->variable( 'NewsSitemapSettings', 'Name' ), 'language' => xrowSitemapTools::language() );
    	$this->genres = array( 'PressRelease' );
    }
    /**
     * @return xrowMetaData
     */
    static public function __set_state( array $array )
    {
        return new xrowSitemapItemNews( $array['publication'], $array['access'], $array['genres'], $array['publication_date'], $array['title'], $array['stock_tickers'] );
    }
}
?>
