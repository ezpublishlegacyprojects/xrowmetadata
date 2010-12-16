<?php

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

if ( $googlesitemapsINI->variable( 'Settings', 'Sitemap' ) == 'enabled' )
{
    if ( ! $isQuiet )
    {
        $cli->output( "Generating Regular Sitemaps...\n" );
    }
    xrowSitemapTools::siteaccessCallFunction( $siteAccessArray, 'xrowSitemapTools::createSitemap' );
}

xrowSitemapTools::ping();

?>