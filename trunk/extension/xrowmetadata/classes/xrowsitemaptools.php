<?php

/** 
 * @author bjoern
 * 
 * 
 */
class xrowSitemapTools
{

    public static function siteaccessCallFunction( $siteaccesses = array(), $fnc = null, $param = null )
    {
        $old_access = $GLOBALS['eZCurrentAccess'];
        foreach ( $siteaccesses as $siteaccess )
        {
            /* Change the siteaccess */
            changeAccess( array( 
                "name" => $siteaccess , 
                "type" => EZ_ACCESS_TYPE_URI 
            ) );
            unset( $GLOBALS['eZContentObjectDefaultLanguage'] );
            eZContentLanguage::expireCache();
            
            if ( $param === null )
            {
                call_user_func( $fnc );
                #call_user_func_array( $fnc );
            }
            else
            {
                call_user_func( $fnc );
                #call_user_func_array( $fnc, $param);
            }
        }
        changeAccess( $old_access );
    }

    public static function ping()
    {
        $ini = eZINI::instance( 'xrowsitemap.ini' );
        // send a ping to google?
        if ( $ini->variable( 'Settings', 'Ping' ) == 'true' )
        {
            $uri = '/sitemaps/index';
            eZURI::transformURI( $uri );
            $link = 'http://' . self::domain() . $uri;
            // google
            $url = "http://www.google.com/webmasters/tools/ping?sitemap=" . $link;
            file_get_contents( $url );
            // bing
            $url = "http://www.bing.com/webmaster/ping.aspx?siteMap=" . $link;
            file_get_contents( $url );
        }
    }

    public static function language()
    {
        $specificINI = eZINI::instance( 'site.ini' );
        $localestr = $specificINI->variable( 'RegionalSettings', 'ContentObjectLocale' );
        $local = new eZLocale( $localestr );
        return $local->LanguageCode;
    }

    public static function domain()
    {
        $ini = eZINI::instance( 'site.ini' );
        
        $domain = preg_split( '/[\/\:]/i', $ini->variable( 'SiteSettings', 'SiteURL' ), 2 );
        if ( is_array( $domain ) )
        {
            $domain = $domain[0];
            $domain2 = preg_split( '/[\/]/i', $domain, 2 );
            if ( is_array( $domain2 ) )
            {
                $domain = $domain2[0];
            }
        
        }
        else
        {
            $domain = preg_split( '/[\/]/i', $ini->variable( 'SiteSettings', 'SiteURL' ), 2 );
            if ( is_array( $domain ) )
            {
                $domain = $domain[0];
            }
            else
            {
                $domain = $siteURL;
            }
        }
        return $domain;
    }

    public static function createSitemap()
    {
        eZDebug::writeDebug( "Generating Standard Sitemap ...", __METHOD__ );
        $cli = $GLOBALS['cli'];
        global $cli, $isQuiet;
        if ( ! $isQuiet )
        {
            
            $cli->output( "Generating Sitemap for Siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " \n" );
        }
        $ini = eZINI::instance( 'site.ini' );
        $googlesitemapsINI = eZINI::instance( 'xrowsitemap.ini' );
        // Get the Sitemap's root node
        $contentINI = eZINI::instance( 'content.ini' );
        $rootNode = eZContentObjectTreeNode::fetch( $contentINI->variable( 'NodeSettings', 'RootNode' ) );
        
        if ( ! $rootNode instanceof eZContentObjectTreeNode )
        {
            $cli->output( "Invalid RootNode for Siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " \n" );
            continue;
        }
        
        // Settings variables
        if ( $googlesitemapsINI->hasVariable( 'SitemapSettings', 'ClassFilterType' ) and $googlesitemapsINI->hasVariable( 'SitemapSettings', 'ClassFilterArray' ) )
        {
            $params2 = array( 
                'ClassFilterType' => $googlesitemapsINI->variable( 'SitemapSettings', 'ClassFilterType' ) , 
                'ClassFilterArray' => $googlesitemapsINI->variable( 'SitemapSettings', 'ClassFilterArray' ) 
            );
        }
        
        // Fetch the content tree
        $params = array( 
            'Limit' => 49999 ,  // max. amount of links in 1 sitemap
            'Offset' => 0 , 
            'SortBy' => array( 
                array( 
                    'depth' , 
                    true 
                ) , 
                array( 
                    'published' , 
                    true 
                ) 
            ) 
        );
        if ( isset( $params2 ) )
        {
            array_merge( $params, $params2 );
        }
        
        $nodeArray = $rootNode->subTree( $params );
        
        $nodeArrayCount = count( $nodeArray ) + 1;
        if ( $nodeArrayCount == 1 )
        {
            $cli->output( "No Items found under node #" . $contentINI->variable( 'NodeSettings', 'RootNode' ) . "." );
        }
        if ( ! $isQuiet )
        {
            $cli->output( "Adding $nodeArrayCount nodes to the sitemap." );
            $output = new ezcConsoleOutput();
            $bar = new ezcConsoleProgressbar( $output, $nodeArrayCount );
        }
        
        $addPrio = false;
        if ( $googlesitemapsINI->hasVariable( 'SitemapSettings', 'AddPriorityToSubtree' ) and $googlesitemapsINI->variable( 'SitemapSettings', 'AddPriorityToSubtree' ) == 'true' )
        {
            $addPrio = true;
        }
        
        $sitemap = new xrowSitemap();
        // Generate Sitemap
        /** START Adding the root node **/
        $object = $rootNode->object();
        
        $meta = xrowMetaDataFunctions::fetchByObject( $object );
        $extensions = array();
        $extensions[] = new xrowSitemapItemModified( $rootNode->attribute( 'modified_subnode' ) );
        
        $url = $rootNode->attribute( 'url_alias' );
        eZURI::transformURI( $url, true );
        $url = 'http://' . xrowSitemapTools::domain() . '/' . $GLOBALS['eZCurrentAccess']['name'] . $url;
        
        if ( $meta and $meta->googlemap != '0' )
        {
            $extensions[] = new xrowSitemapItemFrequency( $meta->change );
            $extensions[] = new xrowSitemapItemPriority( $meta->priority );
            $sitemap->add( $url, $extensions );
        }
        elseif ( $meta === false )
        {
            if ( $addPrio )
            {
                $extensions[] = new xrowSitemapItemPriority( '1' );
            }
            
            $sitemap->add( $url, $extensions );
        }
        
        if ( isset( $bar ) )
        {
            $bar->advance();
        }
        /** END Adding the root node **/
        
        foreach ( $nodeArray as $subTreeNode )
        
        {
            eZContentLanguage::expireCache();
            $object = $subTreeNode->object();
            $meta = xrowMetaDataFunctions::fetchByObject( $object );
            $extensions = array();
            $extensions[] = new xrowSitemapItemModified( $subTreeNode->attribute( 'modified_subnode' ) );
            
            $url = $subTreeNode->attribute( 'url_alias' );
            eZURI::transformURI( $url, true );
            $url = 'http://' . xrowSitemapTools::domain() . '/' . $GLOBALS['eZCurrentAccess']['name'] . $url;
            if ( $meta and $meta->googlemap != '0' )
            {
                $extensions[] = new xrowSitemapItemFrequency( $meta->change );
                $extensions[] = new xrowSitemapItemPriority( $meta->priority );
                $sitemap->add( $url, $extensions );
            }
            elseif ( $meta === false )
            {
                
                if ( $addPrio )
                {
                    $rootDepth = $rootNode->attribute( 'depth' );
                    $prio = 1 - ( ( $subTreeNode->attribute( 'depth' ) - $rootDepth ) / 10 );
                    if ( $prio > 0 )
                    {
                        $extensions[] = new xrowSitemapItemPriority( $prio );
                    }
                }
                
                $sitemap->add( $url, $extensions );
            
            }
            
            if ( isset( $bar ) )
            {
                $bar->advance();
            }
        }
        // write XML Sitemap to file
        $dir = eZSys::storageDirectory() . '/sitemap/' . xrowSitemapTools::domain();
        if ( ! is_dir( $dir ) )
        {
            mkdir( $dir, 0777, true );
        }
        if ( count( $languages ) != 1 )
        {
            $filename = $dir . '/' . xrowSitemap::BASENAME . '_' . $GLOBALS['eZCurrentAccess']['name'] . '.' . xrowSitemap::SUFFIX;
        }
        else
        {
            $filename = $dir . '/' . xrowSitemap::BASENAME . '.' . xrowSitemap::SUFFIX;
        }
        $sitemap->save( $filename );
        
        /**
         * @TODO How will this work with cluster?
    if ( function_exists( 'gzencode' ) and $googlesitemapsINI->variable( 'SitemapSettings', 'Gzip' ) == 'enabled' )
    {
        $content = file_get_contents( $filename );
        $content = gzencode( $content );
        file_put_contents( $filename . '.gz', $content );
        unlink( $filename );
        $filename .= '.gz';
    }
         **/
        
        if ( ! $isQuiet )
        {
            $cli->output( "Sitemap $filename for siteaccess " . $language['siteaccess'] . " (language code " . $language['locale'] . ") has been generated!\n\n" );
        }
    }

    public static function createNewsSitemap()
    {
        eZDebug::writeDebug( "Generating News Sitemap ...", __METHOD__ );
        $rootNode = (int) eZINI::instance( 'content.ini' )->variable( 'NodeSettings', 'RootNode' );
        
        $ini = eZINI::instance( 'xrowsitemap.ini' );
        if ( $ini->hasVariable( 'NewsSitemapSettings', 'ClassFilterArray' ) )
        {
            $params2 = array();
            $params2['ClassFilterArray'] = $ini->variable( 'NewsSitemapSettings', 'ClassFilterArray' );
            $params2['ClassFilterType'] = 'include';
        }
        
        // Your News Sitemap should contain only URLs for your articles published in the last two days.
        $from = time() - 172800; // minus 2 days
        $from = 0;
        $till = time();
        // A News Sitemap can contain no more than 1,000 URLs.
        $max = 1000;
        $limit = 50;
        
        // first check if it's necerssary to recreate an exisiting one
        $filename = eZSys::storageDirectory() . '/sitemap/' . self::domain() . '/' . xrowSitemap::BASENAME . '_news.' . xrowSitemapList::SUFFIX;
        
        $file = eZClusterFileHandler::instance( $filename );
        $file->delete();
        if ( $file->exists() )
        {
            $mtime = $file->mtime();
            if ( $mtime > 0 )
            {
                $params = array( 
                    'IgnoreVisibility' => false , 
                    'MainNodeOnly' => false , 
                    
                    'SortBy' => array( 
                        array( 
                            'published' , 
                            false 
                        ) 
                    ) , 
                    'AttributeFilter' => array( 
                        'and' , 
                        array( 
                            'published' , 
                            '>' , 
                            $mtime 
                        ) , 
                        array( 
                            'published' , 
                            '<=' , 
                            $till 
                        ) 
                    ) 
                );
                if ( isset( $params2 ) )
                {
                    $params = array_merge( $params, $params2 );
                }
                $subtreeCount = eZContentObjectTreeNode::subTreeCountByNodeID( $params, $rootNode );
                if ( $subtreeCount == 0 )
                {
                    eZDebug::writeDebug( "No new published News", __METHOD__ );
                    return;
                }
            
            }
        }
        
        $params = array( 
            'IgnoreVisibility' => false , 
            'MainNodeOnly' => false , 
            
            'SortBy' => array( 
                array( 
                    'published' , 
                    false 
                ) 
            ) , 
            'AttributeFilter' => array( 
                'and' , 
                array( 
                    'published' , 
                    '>' , 
                    $from 
                ) , 
                array( 
                    'published' , 
                    '<=' , 
                    $till 
                ) 
            ) 
        );
        if ( isset( $params2 ) )
        {
            $params = array_merge( $params, $params2 );
        }
        
        $ts = microtime( true );
        $subtreeCount = eZContentObjectTreeNode::subTreeCountByNodeID( $params, $rootNode );
        
        eZDebug::writeDebug( "$subtreeCount items in export tree", __METHOD__ );
        
        $max = min( $max, $subtreeCount );
        $params['Limit'] = min( $max, $limit );
        $params['Offset'] = 0;
        
        // Generate Sitemap
        $sitemap = new xrowSitemap();
        
        while ( $params['Offset'] < $max )
        {
            $nodeArray = eZContentObjectTreeNode::subTreeByNodeID( $params, $rootNode );
            foreach ( $nodeArray as $node )
            {
                $news = new xrowSitemapItemNews();
                $images = array();
                // Adding the root node
                $object = $node->object();
                
                $news->publication_date = new DateTime( '@' . $object->attribute( 'published' ) );
                $news->title = $object->attribute( 'name' );
                
                $url = $node->attribute( 'url_alias' );
                eZURI::transformURI( $url, true, 'http://' . self::domain() );
                $url = 'http://' . self::domain() . $url;
                $dm = $node->dataMap();
                if ( $ini->hasVariable( 'NewsSitemapSettings', 'AdditionalKeywordList' ) )
                {
                    $news->keywords = $ini->variable( 'NewsSitemapSettings', 'AdditionalKeywordList' );
                }
                foreach ( $dm as $attribute )
                {
                    switch ( $attribute->DataTypeString )
                    {
                        case 'ezimage':
                            if ( $attribute->hasContent() )
                            {
                                
                                $imagedata = $attribute->content();
                                $aliasdata = $imagedata->attribute( 'rss' );
                                $image = new xrowSitemapItemImage();
                                $image->url = 'http://' . self::domain() . '/' . $aliasdata['url'];
                                if ( $imagedata->attribute( 'alternative_text' ) )
                                {
                                    $image->caption = $imagedata->attribute( 'alternative_text' );
                                }
                                $images[] = $image;
                            }
                            break;
                        case 'ezkeyword':
                            if ( $attribute->hasContent() )
                            {
                                $keywordattribute = $attribute->content();
                                $news->keywords = array_merge( $news->keywords, $keywordattribute->KeywordArray );
                            }
                            break;
                    }
                }
                
                $sitemap->add( $url, array_merge( array( 
                    $news 
                ), $images ) );
            }
            eZContentObject::clearCache();
            $params['Offset'] += $params['Limit'];
        }
        
        $sitemap->save( $filename );
        
        $ets = round( microtime( true ) - $ts, 2 );
        eZDebug::writeDebug( "News sitemap $filename has been generated in " . $ets . " seconds.", __METHOD__ );
    
    }
}

?>