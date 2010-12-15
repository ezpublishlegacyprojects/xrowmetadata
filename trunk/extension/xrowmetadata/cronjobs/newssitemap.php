<?php
$ts = time();

require_once "access.php";

if ( ! $isQuiet )
{
    $cli->output( "Generating Sitemaps...\n" );
    eZDebug::writeDebug( "Generating Sitemaps..." );
}
$ini = eZINI::instance( 'site.ini' );
$googlesitemapsINI = eZINI::instance( 'xrowsitemap.ini' );

//getting custom set site access or default access
if ( $googlesitemapsINI->hasVariable( 'SitemapSettings', 'AvailableSiteAccessList' ) )
{
    $siteAccessArray = $googlesitemapsINI->variable( 'SitemapSettings', 'AvailableSiteAccessList' );
}
else
{
    $siteAccessArray = array( 
        $ini->variable( 'SiteSettings', 'DefaultAccess' ) 
    );
}

if ( $googlesitemapsINI->variable( 'Settings', 'NewsSitemap' ) == 'enabled' )
{
	xrowSitemapTools::siteaccessCallFunction( $siteAccessArray, 'xrowSitemapTools::createNewsSitemap' );
}
if ( $googlesitemapsINI->variable( 'Settings', 'Sitemap' ) == 'enabled' )
{
	xrowSitemapTools::siteaccessCallFunction( $siteAccessArray, 'xrowSitemapTools::createSitemap' );
}



$today = mktime( 0, 0, 0 );
if ( ! $isQuiet )
{
    $cli->output( "Generating Sitemaps took " . date( "H:i:s", ( time() - $ts ) + $today ) );
}
xrowSitemapTools::ping();

?>