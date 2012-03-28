<?php

$ini = eZINI::instance( 'site.ini' );
$xrowsitemapINI = eZINI::instance( 'xrowsitemap.ini' );

//getting custom set site access or default access
if ( $xrowsitemapINI->hasVariable( 'SitemapSettings', 'AvailableSiteAccessList' ) )
{
    $siteAccessArray = $xrowsitemapINI->variable( 'SitemapSettings', 'AvailableSiteAccessList' );
}
else
{
    $siteAccessArray = array( 
        $ini->variable( 'SiteSettings', 'DefaultAccess' ) 
    );
}

$Module = $Params['Module'];
$access = $GLOBALS['eZCurrentAccess']['name'];

if( is_array( $siteAccessArray ) && count( $siteAccessArray ) > 0 )
{
    if ( ! in_array( $access, $siteAccessArray ) )
    {
        return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
    }
}

$index = new xrowSitemapIndex();
$dirname = eZSys::storageDirectory() . '/sitemap/' . xrowSitemapTools::domain();
$dir = new eZClusterDirectoryIterator( $dirname );

foreach ( $dir as $file )
{
    $date = new xrowSitemapItemModified();
    $date->date = new DateTime( "@" . $file->mtime() );
    $loc = 'http://' . $_SERVER['HTTP_HOST'] . '/'. $file->name();
    $index->add( $loc, array( 
        $date 
    ) );
}

unset( $dir );

// Append foreign Sitemaps
if ( $ini->hasVariable( 'Settings', 'AddSitemapIndex' ) )
{
    $urlList = $ini->variable( 'Settings', 'AddSitemapIndex' );
    foreach ( $urlList as $loc )
    {
        $index->add( $loc, array( 
            $date 
        ) );
    }
}
$content = $index->saveXML();

// Set header settings
header( 'Content-Type: text/xml; charset=UTF-8' );
header( 'Content-Length: ' . strlen( $content ) );
header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
header( 'Cache-Control: no-cache, must-revalidate' );
header( 'Pragma: no-cache' );

while ( @ob_end_clean() );

echo $content;

eZExecution::cleanExit();
?>