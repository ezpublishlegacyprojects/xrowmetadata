<?php

$ini = eZINI::instance( 'xrowsitemap.ini' );

$Module = $Params['Module'];
$access = $GLOBALS['eZCurrentAccess']['name'];

if ( $ini->hasVariable( 'Settings', 'SiteAccessList' ) )
{
    $alist = $ini->hasVariable( 'Settings', 'SiteAccessList' );
    if ( ! in_array( $access, $alist ) )
    {
        return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
    }
}

$index = new xrowSitemapIndex();

$dirname = eZSys::storageDirectory() . '/sitemap/' . xrowSitemapTools::domain();
$dir = new DirectoryIterator( $dirname );

foreach ( $dir as $file )
{
    if ( $file->isDot() and $file->isDir() )
    {
        continue;
    }
    $date = new xrowSitemapItemModified();
    $date->date = new DateTime( "@" . $file->getMTime() );
    $loc = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $dirname . '/' . $file->getFilename();
    
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
header( 'X-Powered-By: eZ Publish' );

while ( @ob_end_clean() );

echo $content;

eZExecution::cleanExit();
?>