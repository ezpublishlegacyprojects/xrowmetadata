<?php

/* Legacy 4.2 */
require_once "access.php";

class xrowSitemapTools
{
    /* max. amount of links in 1 sitemap */
    const MAX_PER_FILE = 300;
    const DEFAULT_LIMIT = 200;
    public static $excludes = null;

    public static function changeAccess( array $access )
    {
        eZSiteAccess::change( $access );
        unset( $GLOBALS['eZContentObjectDefaultLanguage'] );
        eZContentLanguage::expireCache();
        eZContentObject::clearCache();
    }

    public static function siteaccessCallFunction( $siteaccesses = array(), $fnc = null, $param = null )
    {
        
        $old_access = $GLOBALS['eZCurrentAccess'];
        foreach ( $siteaccesses as $siteaccess )
        {
            /* Change the siteaccess */
            self::changeAccess( array( 
                "name" => $siteaccess , 
                "type" => EZ_ACCESS_TYPE_URI 
            ) );
            
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
        self::changeAccess( $old_access );
    }

    public static function ping()
    {
        $ini = eZINI::instance( 'xrowsitemap.ini' );
        // send a ping to google?
        if ( ( $ini->hasVariable( 'Settings', 'Ping' ) and $ini->variable( 'Settings', 'Ping' ) == 'true' ) or ! $ini->hasVariable( 'Settings', 'Ping' ) )
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

    public static function getNewsConverter()
    {
        $converter = eZExtension::getHandlerClass( new ezpExtensionOptions( array( 
            'iniFile' => 'xrowsitemap.ini' , 
            'iniSection' => 'Settings' , 
            'iniVariable' => 'NewsConverter' 
        ) ) );
        if ( ! $converter )
        {
            $converter = new xrowSitemapConverter();
        }
        return $converter;
    }

    public static function getVideoConverter()
    {
        $converter = eZExtension::getHandlerClass( new ezpExtensionOptions( array( 
            'iniFile' => 'xrowsitemap.ini' , 
            'iniSection' => 'Settings' , 
            'iniVariable' => 'VideoConverter' 
        ) ) );
        if ( ! $converter )
        {
            $converter = new xrowSitemapConverter();
        }
        return $converter;
    }

    public static function getImageConverter()
    {
        $converter = eZExtension::getHandlerClass( new ezpExtensionOptions( array( 
            'iniFile' => 'xrowsitemap.ini' , 
            'iniSection' => 'Settings' , 
            'iniVariable' => 'ImageConverter' 
        ) ) );
        if ( ! $converter )
        {
            $converter = new xrowSitemapConverter();
        }
        return $converter;
    }

    public static function fetchImages( eZContentObjectTreeNode $node )
    {
        
        $images = array();
        $ini = eZINI::instance( 'xrowsitemap.ini' );
        $params = array( 
            'Limit' => 999 ,  #Google doesn`t allow more as 1000
            'ClassFilterType' => 'include' , 
            'ClassFilterArray' => $ini->variable( 'SitemapSettings', 'ImageClasses' ) 
        );
        
        $nodeArray = $node->subTree( $params );
        foreach ( $nodeArray as $imageNode )
        {
            $imageadd = xrowSitemapTools::getImageConverter()->addImage( $imageNode );
            if ( ! empty( $imageadd ) )
            {
                $images = array_merge( $images, $imageadd );
            }
        }
        
        return $images;
    }

    public static function excludeNode( $node )
    {
        if ( self::$excludes === null )
        {
            $ini = eZINI::instance( 'xrowsitemap.ini' );
            if ( $ini->hasVariable( 'SitemapSettings', 'ExcludeNodes' ) and $ini->hasVariable( 'SitemapSettings', 'ExcludeNodes' ) )
            {
                self::$excludes = $ini->variable( 'SitemapSettings', 'ExcludeNodes' );
            }
            else
            {
                self::$excludes = array();
            }
        }
        
        if ( ! empty( self::$excludes ) )
        {
            $result = array();
            $result = array_intersect( self::$excludes, $node->pathArray() );
            if ( count( $result ) > 0 )
            {
                return true;
            }
        }
        return false;
    }

    public static function addNode( xrowSitemap $sitemap, eZContentObjectTreeNode $node )
    {
        $ini = eZINI::instance( 'xrowsitemap.ini' );
        if ( self::excludeNode( $node ) )
        {
            return false;
        }
        $extensions = array();
        
        $meta = xrowMetaDataFunctions::fetchByNode( $node );
        if ( $meta and $meta->sitemap_use != '0' )
        {
            return false;
        }
        elseif ( $meta === false and $ini->variable( 'Settings', 'AlwaysAdd' ) == 'disabled' )
        {
            return false;
        }
        if ( $ini->hasVariable( 'SitemapSettings', 'GalleryClasses' ) and in_array( $node->attribute( 'parent' )->attribute( 'class_identifier' ), $ini->variable( 'SitemapSettings', 'GalleryClasses' ) ) and in_array( $node->attribute( 'class_identifier' ), $ini->variable( 'SitemapSettings', 'ImageClasses' ) ) )
        {
            return false;
        }
        $extensions[] = new xrowSitemapItemModified( $node->attribute( 'modified_subnode' ) );
        
        $url = $node->attribute( 'url_alias' );
        eZURI::transformURI( $url, true );
        if ( $ini->variable( 'SiteAccessSettings', 'RemoveSiteAccessIfDefaultAccess' ) == 'enabled' or $ini->variable( 'Settings', 'HideSiteaccessAlways' ) == 'true' )
        {
            $url = 'http://' . xrowSitemapTools::domain() . $url;
        }
        else
        {
            $url = 'http://' . xrowSitemapTools::domain() . '/' . $GLOBALS['eZCurrentAccess']['name'] . $url;
        }
        if ( $ini->hasVariable( 'SitemapSettings', 'GalleryClasses' ) and in_array( $node->attribute( 'class_identifier' ), $ini->variable( 'SitemapSettings', 'GalleryClasses' ) ) )
        {
            $imageextensions = self::fetchImages( $node );
            if ( ! empty( $imageextensions ) )
            {
                $extensions = array_merge( $extensions, $imageextensions );
            }
        }
        if ( $ini->hasVariable( 'SitemapSettings', 'VideoClasses' ) and in_array( $node->attribute( 'class_identifier' ), $ini->variable( 'SitemapSettings', 'VideoClasses' ) ) )
        {
            $extensions[] = self::getVideoConverter()->addVideo( $node );
        }
        if ( $meta and $meta->change )
        {
            $extensions[] = new xrowSitemapItemFrequency( $meta->change );
        }
        
        if ( $meta and $meta->priority !== null )
        {
            $extensions[] = new xrowSitemapItemPriority( $meta->priority );
        }
        elseif ( self::addPriority() )
        {
            $rootDepth = self::rootNode()->attribute( 'depth' );
            $prio = 1 - ( ( $node->attribute( 'depth' ) - $rootDepth ) / 10 );
            if ( $prio > 0 )
            {
                $extensions[] = new xrowSitemapItemPriority( $prio );
            }
        }
        $sitemap->add( $url, $extensions );
    }
    public static $addPriority = null;

    public static function addPriority()
    {
        if ( self::$addPriority === null )
        {
            $ini = eZINI::instance( 'xrowsitemap.ini' );
            if ( $ini->hasVariable( 'Settings', 'AddPriorityToSubtree' ) and $ini->variable( 'Settings', 'AddPriorityToSubtree' ) == 'true' )
            {
                self::$addPriority = true;
            }
            elseif ( ! $ini->hasVariable( 'Settings', 'AddPriorityToSubtree' ) )
            {
                self::$addPriority = true;
            }
            else
            {
                self::$addPriority = false;
            }
        }
        return self::$addPriority;
    }

    public static function createSitemap()
    {
        eZDebug::writeDebug( "Generating Standard Sitemap ...", __METHOD__ );
        eZContentLanguage::expireCache();
        $cli = $GLOBALS['cli'];
        global $cli, $isQuiet;
        if ( ! $isQuiet )
        {
            $cli->output( "Generating Sitemap for Siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " \n" );
        }
        $ini = eZINI::instance( 'site.ini' );
        $xrowsitemapINI = eZINI::instance( 'xrowsitemap.ini' );
        
        if ( $xrowsitemapINI->hasVariable( 'SitemapSettings', 'ClassFilterType' ) and $xrowsitemapINI->hasVariable( 'SitemapSettings', 'ClassFilterArray' ) )
        {
            $params2 = array( 
                'ClassFilterType' => $xrowsitemapINI->variable( 'SitemapSettings', 'ClassFilterType' ) , 
                'ClassFilterArray' => $xrowsitemapINI->variable( 'SitemapSettings', 'ClassFilterArray' ) 
            );
        }
        $max = self::MAX_PER_FILE;
        if ( $xrowsitemapINI->hasVariable( 'SitemapSettings', 'LimitPerLoop' ) )
        {
            $limit = (int) $xrowsitemapINI->variable( 'SitemapSettings', 'LimitPerLoop' );
        }
        else
        {
            $limit = self::DEFAULT_LIMIT;
        }
        // Fetch the content tree
        $params = array( 
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
            $params = array_merge( $params, $params2 );
        }
        $rootNode = self::rootNode();
        $subtreeCount = eZContentObjectTreeNode::subTreeCountByNodeID( $params, $rootNode->NodeID );
        
        if ( $subtreeCount <= 1 )
        {
            throw new Exception( "No Items found under root node #" . $rootNode->NodeID . "." );
        }
        
        $sitemap = new xrowSitemap();
        // Generate Sitemap
        self::addNode( $sitemap, $rootNode );
        
        $max = min( $max, $subtreeCount );
        $max_all = $max;
        $params['Limit'] = $limit;
        $params['Offset'] = 0;
        $counter = 1;
        $runs = ceil( $subtreeCount / $max_all );
        if ( ! $isQuiet )
        {
            $amount = $subtreeCount + 1; // for root node
            $cli->output( "Adding $amount nodes to the sitemap." );
            $output = new ezcConsoleOutput();
        }
        $sitemapfiles = array();
        $tmpsitemapfiles = array();
        while ( $counter <= $runs )
        {
            eZDebug::writeDebug( 'Run ' . $counter . ' of ' . $runs . ' runs' );
            if ( ! $isQuiet )
            {
                $cli->output( 'Run ' . $counter . ' of ' . $runs . ' runs' );
                if ( $counter == 1 )
                {
                    $bar = new ezcConsoleProgressbar( $output, $max + 1 ); // for root node
                }
                else
                {
                    $bar = new ezcConsoleProgressbar( $output, $max );
                }
            }
            while ( $params['Offset'] < $max_all )
            {
                
                $nodeArray = eZContentObjectTreeNode::subTreeByNodeID( $params, $rootNode->NodeID );
                foreach ( $nodeArray as $subTreeNode )
                {
                    self::addNode( $sitemap, $subTreeNode );
                    if ( isset( $bar ) )
                    {
                        $bar->advance();
                    }
                }
                eZContentObject::clearCache();
                $params['Offset'] += $params['Limit'];
            }
            // write XML Sitemap to file
            $dir = eZSys::storageDirectory() . '/sitemap/' . xrowSitemapTools::domain();
            $cachedir = eZSys::cacheDirectory() . '/sitemap/' . xrowSitemapTools::domain();
            
            if ( ! is_dir( $dir ) )
            {
                mkdir( $dir, 0777, true );
            }
            if ( ! is_dir( $cachedir ) )
            {
                mkdir( $cachedir, 0777, true );
            }
            
            $filename = xrowSitemap::BASENAME . '_standard_' . $GLOBALS['eZCurrentAccess']['name'] . '.' . xrowSitemap::SUFFIX;
            if ( $counter > 1 )
            {
                $filename = xrowSitemap::BASENAME . '_standard_' . $GLOBALS['eZCurrentAccess']['name'] . '_' . $counter . '.' . xrowSitemap::SUFFIX;
            }
            $sitemapfiles[] = $dir . "/" . $filename;
            $tmpsitemapfiles[] = $cachedir . "/" . $filename;
            
            $sitemap->saveLocal( $cachedir . "/" . $filename );
            if ( ! $isQuiet )
            {
                $cli->output( "\n" );
                $cli->output( "Time: " . date( 'd.m.Y H:i:s' ) . ". Action: Sitemap $filename for siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " has been generated.\n" );
            }
            
            /**
             * @TODO How will this work with cluster?
    	if ( function_exists( 'gzencode' ) and $xrowsitemapINI->variable( 'SitemapSettings', 'Gzip' ) == 'enabled' )
                        {
                                $content = file_get_contents( $filename );
                                $content = gzencode( $content );
                                file_put_contents( $filename . '.gz', $content );
                                unlink( $filename );
                                $filename .= '.gz';
                        }
             **/
            
            $counter ++;
            $max_all += $max;
            $sitemap = new xrowSitemap();
        }
        //delete all
        $dirname = eZSys::storageDirectory() . '/sitemap/' . xrowSitemapTools::domain();
        $dir = new eZClusterDirectoryIterator( $dirname );
        foreach ( $dir as $file )
        {
            if ( $file->exists() )
            {
                $file->delete();
            }
        }
        unset( $dir );
        //move all from cache to cluster filesystem
        for ( $i = 0; $i < count( $sitemapfiles ); $i ++ )
        {
            $file = eZClusterFileHandler::instance( $sitemapfiles[$i] );
            $file->storeContents( file_get_contents( $tmpsitemapfiles[$i] ), 'sitemap', 'text/xml' );
            unlink( $tmpsitemapfiles[$i] );
        }
    }

    /**
     * Get the Sitemap's root node
     */
    public static function rootNode()
    {
        $contentINI = eZINI::instance( 'content.ini' );
        $rootNode = eZContentObjectTreeNode::fetch( $contentINI->variable( 'NodeSettings', 'RootNode' ) );
        
        if ( ! $rootNode instanceof eZContentObjectTreeNode )
        {
            throw new Exception( "Invalid RootNode for Siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " \n" );
        }
        return $rootNode;
    }

    public static function createMobileSitemap()
    {
        eZDebug::writeDebug( "Generating mobile sitemap ...", __METHOD__ );
        $cli = $GLOBALS['cli'];
        global $cli, $isQuiet;
        if ( ! $isQuiet )
        {
            $cli->output( "Generating mobile sitemap for siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " \n" );
        }
        $ini = eZINI::instance( 'site.ini' );
        $xrowsitemapINI = eZINI::instance( 'xrowsitemap.ini' );
        // Get the Sitemap's root node
        $contentINI = eZINI::instance( 'content.ini' );
        $rootNode = eZContentObjectTreeNode::fetch( $contentINI->variable( 'NodeSettings', 'RootNode' ) );
        
        if ( ! $rootNode instanceof eZContentObjectTreeNode )
        {
            $cli->output( "Invalid RootNode for Siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " \n" );
            continue;
        }
        
        // Settings variables
        if ( $xrowsitemapINI->hasVariable( 'MobileSitemapSettings', 'ClassFilterType' ) and $xrowsitemapINI->hasVariable( 'MobileSitemapSettings', 'ClassFilterArray' ) )
        {
            $params2 = array( 
                'ClassFilterType' => $xrowsitemapINI->variable( 'MobileSitemapSettings', 'ClassFilterType' ) , 
                'ClassFilterArray' => $xrowsitemapINI->variable( 'MobileSitemapSettings', 'ClassFilterArray' ) 
            );
        }
        $max = self::MAX_PER_FILE;
        $limit = 50;
        
        // Fetch the content tree
        $params = array( 
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
            $params = array_merge( $params, $params2 );
        }
        
        $subtreeCount = eZContentObjectTreeNode::subTreeCountByNodeID( $params, $rootNode->NodeID );
        
        if ( $subtreeCount == 1 )
        {
            $cli->output( "No Items found under node #" . $contentINI->variable( 'NodeSettings', 'RootNode' ) . "." );
        }
        
        if ( ! $isQuiet )
        {
            $amount = $subtreeCount + 1; // +1 is root node
            $cli->output( "Adding $amount nodes to the sitemap." );
            $output = new ezcConsoleOutput();
            $bar = new ezcConsoleProgressbar( $output, $amount );
        }
        
        $addPrio = false;
        if ( $xrowsitemapINI->hasVariable( 'Settings', 'AddPriorityToSubtree' ) and $xrowsitemapINI->variable( 'Settings', 'AddPriorityToSubtree' ) == 'true' )
        {
            $addPrio = true;
        }
        
        $sitemap = new xrowMobileSitemap();
        // Generate Sitemap
        /** START Adding the root node **/
        $object = $rootNode->object();
        
        $meta = xrowMetaDataFunctions::fetchByObject( $object );
        $extensions = array();
        $extensions[] = new xrowSitemapItemModified( $rootNode->attribute( 'modified_subnode' ) );
        
        $url = $rootNode->attribute( 'url_alias' );
        eZURI::transformURI( $url );
        $url = 'http://' . xrowSitemapTools::domain() . $url;
        
        if ( $meta and $meta->sitemap_use != '0' )
        {
            $extensions[] = new xrowSitemapItemFrequency( $meta->change );
            $extensions[] = new xrowSitemapItemPriority( $meta->priority );
            $sitemap->add( $url, $extensions );
        }
        elseif ( $meta === false and $xrowsitemapINI->variable( 'Settings', 'AlwaysAdd' ) == 'enabled' )
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
        $max = min( $max, $subtreeCount );
        $params['Limit'] = min( $max, $limit );
        $params['Offset'] = 0;
        while ( $params['Offset'] < $max )
        {
            $nodeArray = eZContentObjectTreeNode::subTreeByNodeID( $params, $rootNode->NodeID );
            foreach ( $nodeArray as $subTreeNode )
            
            {
                eZContentLanguage::expireCache();
                $meta = xrowMetaDataFunctions::fetchByNode( $subTreeNode );
                $extensions = array();
                $extensions[] = new xrowSitemapItemModified( $subTreeNode->attribute( 'modified_subnode' ) );
                
                $url = $subTreeNode->attribute( 'url_alias' );
                eZURI::transformURI( $url );
                $url = 'http://' . xrowSitemapTools::domain() . $url;
                
                if ( $meta and $meta->sitemap_use != '0' )
                {
                    $extensions[] = new xrowSitemapItemFrequency( $meta->change );
                    $extensions[] = new xrowSitemapItemPriority( $meta->priority );
                    $sitemap->add( $url, $extensions );
                }
                elseif ( $meta === false and $xrowsitemapINI->variable( 'Settings', 'AlwaysAdd' ) == 'enabled' )
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
            eZContentObject::clearCache();
            $params['Offset'] += $params['Limit'];
        }
        // write XML Sitemap to file
        $dir = eZSys::storageDirectory() . '/sitemap/' . xrowSitemapTools::domain();
        if ( ! is_dir( $dir ) )
        {
            mkdir( $dir, 0777, true );
        }
        
        $filename = $dir . '/' . xrowSitemap::BASENAME . '_mobile_' . $GLOBALS['eZCurrentAccess']['name'] . '.' . xrowSitemap::SUFFIX;
        
        $sitemap->save( $filename );
        
        /**
         * @TODO How will this work with cluster?
    if ( function_exists( 'gzencode' ) and $xrowsitemapINI->variable( 'MobileSitemapSettings', 'Gzip' ) == 'enabled' )
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
            $cli->output( "\n" );
            $cli->output( "Mobile sitemap $filename for siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " has been generated.\n" );
        }
    }

    public static function createNewsSitemap()
    {
        eZDebug::writeDebug( "Generating News Sitemap ...", __METHOD__ );
        $cli = $GLOBALS['cli'];
        global $cli, $isQuiet;
        
        $rootNode = (int) eZINI::instance( 'content.ini' )->variable( 'NodeSettings', 'RootNode' );
        
        $ini = eZINI::instance( 'xrowsitemap.ini' );
        if ( $ini->hasVariable( 'NewsSitemapSettings', 'ClassFilterArray' ) )
        {
            $params2 = array();
            $params2['ClassFilterArray'] = $ini->variable( 'NewsSitemapSettings', 'ClassFilterArray' );
            $params2['ClassFilterType'] = 'include';
        }
        
        if ( $ini->hasVariable( 'NewsSitemapSettings', 'RootNode' ) )
        {
            $rootNode = (int) $ini->variable( 'NewsSitemapSettings', 'RootNode' );
        }
        
        if ( $ini->hasVariable( 'NewsSitemapSettings', 'Limitation' ) )
        {
            $limitation = $ini->variable( 'NewsSitemapSettings', 'Limitation' );
        }
        
        if ( $ini->hasVariable( 'NewsSitemapSettings', 'ExtraAttributeFilter' ) )
        {
            $extra_attribute_filter = array();
            $extra_attribute_filter = $ini->variable( 'NewsSitemapSettings', 'ExtraAttributeFilter' );
        }
        
        // Your News Sitemap should contain only URLs for your articles published in the last two days.
        $from = time() - 172800; // minus 2 days
        $till = time();
        // A News Sitemap can contain no more than 1,000 URLs.
        $max = 1000;
        $limit = 50;
        
        // first check if it's necerssary to recreate an exisiting one
        $filename = eZSys::storageDirectory() . '/sitemap/' . self::domain() . '/' . xrowSitemap::BASENAME . '_news_' . $GLOBALS['eZCurrentAccess']['name'] . '.' . xrowSitemapList::SUFFIX;
        
        $file = eZClusterFileHandler::instance( $filename );
        if ( $file->exists() )
        {
            #reduce 5 min because article might be published during the runtime of the cron
            $mtime = $file->mtime() - 300;
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
                if ( isset( $limitation ) && $limitation == 'disable' )
                {
                    $params['Limitation'] = array();
                }
                if ( isset( $extra_attribute_filter ) )
                {
                    foreach ( $extra_attribute_filter as $key => $extra_attribute_filter_item )
                    {
                        if ( $ini->hasGroup( $extra_attribute_filter_item ) )
                        {
                            $value = $ini->variable( $extra_attribute_filter_item, 'Value' );
                            array_push( $params['AttributeFilter'], $value );
                        }
                    }
                }
                $subtreeCount = eZContentObjectTreeNode::subTreeCountByNodeID( $params, $rootNode );
                if ( $subtreeCount == 0 )
                {
                    eZDebug::writeDebug( "No new published news", __METHOD__ );
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
        
        if ( isset( $limitation ) && $limitation == 'disable' )
        {
            $params['Limitation'] = array();
        }
        if ( isset( $extra_attribute_filter ) )
        {
            foreach ( $extra_attribute_filter as $key => $extra_attribute_filter_item )
            {
                if ( $ini->hasGroup( $extra_attribute_filter_item ) )
                {
                    $value = $ini->variable( $extra_attribute_filter_item, 'Value' );
                    array_push( $params['AttributeFilter'], $value );
                }
            }
        }
        
        $subtreeCount = eZContentObjectTreeNode::subTreeCountByNodeID( $params, $rootNode );
        
        $max = min( $max, $subtreeCount );
        $max_all = $max;
        $params['Limit'] = $limit;
        $params['Offset'] = 0;
        $counter = 1;
        $runs = ceil( $subtreeCount / $max_all );
        if ( ! $isQuiet )
        {
            $amount = $subtreeCount;
            $cli->output( "Adding $amount nodes to the news sitemap." );
            $output = new ezcConsoleOutput();
        }
        
        while ( $counter <= $runs )
        {
            eZDebug::writeDebug( 'Run ' . $counter . ' of ' . $runs . ' runs' );
            if ( ! $isQuiet )
            {
                $cli->output( 'Run ' . $counter . ' of ' . $runs . ' runs' );
                $bar = new ezcConsoleProgressbar( $output, (int) $max );
            }
            
            // Generate Sitemap
            $sitemap = new xrowSitemap();
            while ( $params['Offset'] < $max_all )
            {
                $nodeArray = eZContentObjectTreeNode::subTreeByNodeID( $params, $rootNode );
                foreach ( $nodeArray as $node )
                {
                    $extensions = array();
                    $extensions[] = xrowSitemapTools::getNewsConverter()->addNews( $node );
                    $imageadd = xrowSitemapTools::getImageConverter()->addImage( $node );
                    if ( ! empty( $imageadd ) )
                    {
                        $extensions = array_merge( $extensions, $imageadd );
                    }
                    
                    $url = $node->attribute( 'url_alias' );
                    eZURI::transformURI( $url, true );
                    $url = 'http://' . self::domain() . $url;
                    $sitemap->add( $url, $extensions );
                    
                    if ( isset( $bar ) )
                    {
                        $bar->advance();
                    }
                }
                eZContentObject::clearCache();
                $params['Offset'] += $params['Limit'];
            }
            
            // write XML Sitemap to file
            $dir = eZSys::storageDirectory() . '/sitemap/' . xrowSitemapTools::domain();
            if ( ! is_dir( $dir ) )
            {
                mkdir( $dir, 0777, true );
            }
            
            $filename = $dir . '/' . xrowSitemap::BASENAME . '_news_' . $GLOBALS['eZCurrentAccess']['name'] . '.' . xrowSitemap::SUFFIX;
            
            if ( $counter > 1 )
            {
                $filename = $dir . '/' . xrowSitemap::BASENAME . '_news_' . $GLOBALS['eZCurrentAccess']['name'] . '_' . $counter . '.' . xrowSitemap::SUFFIX;
            }
            
            $sitemap->save( $filename );
            if ( ! $isQuiet )
            {
                $cli->output( "\n" );
                $cli->output( "Time: " . date( 'd.m.Y H:i:s' ) . ". Action: Sitemap $filename for siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " has been generated.\n" );
            }
            
            $counter ++;
            $max_all += $max;
            $sitemap = new xrowSitemap();
        }
    }

    /*!
     Check access for the current object

     \param function name ( edit, read, remove, etc. )
     \param original class ID ( used to check access for object creation ), default false
     \param parent class id ( used to check access for object creation ), default false
     \param return access list instead of access result (optional, default false )

     \return 1 if has access, 0 if not.
             If returnAccessList is set to true, access list is returned
    */
    public static function checkAccess( eZContentObject $contentobject, eZUser $user, $functionName, $originalClassID = false, $parentClassID = false, $returnAccessList = false, $language = false )
    {
        $classID = $originalClassID;
        
        $userID = $user->attribute( 'contentobject_id' );
        $origFunctionName = $functionName;
        
        // Fetch the ID of the language if we get a string with a language code
        // e.g. 'eng-GB'
        $originalLanguage = $language;
        if ( is_string( $language ) && strlen( $language ) > 0 )
        {
            $language = eZContentLanguage::idByLocale( $language );
        }
        else
        {
            $language = false;
        }
        
        // This will be filled in with the available languages of the object
        // if a Language check is performed.
        $languageList = false;
        
        // The 'move' function simply reuses 'edit' for generic access
        // but adds another top-level check below
        // The original function is still available in $origFunctionName
        if ( $functionName == 'move' )
            $functionName = 'edit';
        
        $accessResult = $user->hasAccessTo( 'content', $functionName );
        $accessWord = $accessResult['accessWord'];
        
        /*
        // Uncomment this part if 'create' permissions should become implied 'edit'.
        // Merges in 'create' policies with 'edit'
        if ( $functionName == 'edit' &&
             !in_array( $accessWord, array( 'yes', 'no' ) ) )
        {
            // Add in create policies.
            $accessExtraResult = $user->hasAccessTo( 'content', 'create' );
            if ( $accessExtraResult['accessWord'] != 'no' )
            {
                $accessWord = $accessExtraResult['accessWord'];
                if ( isset( $accessExtraResult['policies'] ) )
                {
                    $accessResult['policies'] = array_merge( $accessResult['policies'],
                                                             $accessExtraResult['policies'] );
                }
                if ( isset( $accessExtraResult['accessList'] ) )
                {
                    $accessResult['accessList'] = array_merge( $accessResult['accessList'],
                                                               $accessExtraResult['accessList'] );
                }
            }
        }
        */
        
        if ( $origFunctionName == 'remove' or $origFunctionName == 'move' )
        {
            $mainNode = $contentobject->attribute( 'main_node' );
            // We do not allow these actions on objects placed at top-level
            // - remove
            // - move
            if ( $mainNode and $mainNode->attribute( 'parent_node_id' ) <= 1 )
            {
                return 0;
            }
        }
        
        if ( $classID === false )
        {
            $classID = $contentobject->attribute( 'contentclass_id' );
        }
        if ( $accessWord == 'yes' )
        {
            return 1;
        }
        else
        {
            if ( $accessWord == 'no' )
            {
                if ( $functionName == 'edit' )
                {
                    // Check if we have 'create' access under the main parent
                    if ( $contentobject->attribute( 'current_version' ) == 1 && ! $contentobject->attribute( 'status' ) )
                    {
                        $mainNode = eZNodeAssignment::fetchForObject( $contentobject->attribute( 'id' ), $contentobject->attribute( 'current_version' ) );
                        $parentObj = $mainNode[0]->attribute( 'parent_contentobject' );
                        $result = $parentObj->checkAccess( 'create', $contentobject->attribute( 'contentclass_id' ), $parentObj->attribute( 'contentclass_id' ), false, $originalLanguage );
                        return $result;
                    }
                    else
                    {
                        return 0;
                    }
                }
                
                if ( $returnAccessList === false )
                {
                    return 0;
                }
                else
                {
                    return $accessResult['accessList'];
                }
            }
            else
            {
                $policies = & $accessResult['policies'];
                $access = 'denied';
                foreach ( array_keys( $policies ) as $pkey )
                {
                    $limitationArray = & $policies[$pkey];
                    if ( $access == 'allowed' )
                    {
                        break;
                    }
                    
                    $limitationList = array();
                    if ( isset( $limitationArray['Subtree'] ) )
                    {
                        $checkedSubtree = false;
                    }
                    else
                    {
                        $checkedSubtree = true;
                        $accessSubtree = false;
                    }
                    if ( isset( $limitationArray['Node'] ) )
                    {
                        $checkedNode = false;
                    }
                    else
                    {
                        $checkedNode = true;
                        $accessNode = false;
                    }
                    foreach ( array_keys( $limitationArray ) as $key )
                    {
                        $access = 'denied';
                        switch ( $key )
                        {
                            case 'Class':
                                {
                                    if ( $functionName == 'create' and ! $originalClassID )
                                    {
                                        $access = 'allowed';
                                    }
                                    else 
                                        if ( $functionName == 'create' and in_array( $classID, $limitationArray[$key] ) )
                                        {
                                            $access = 'allowed';
                                        }
                                        else 
                                            if ( $functionName != 'create' and in_array( $contentobject->attribute( 'contentclass_id' ), $limitationArray[$key] ) )
                                            {
                                                $access = 'allowed';
                                            }
                                            else
                                            {
                                                $access = 'denied';
                                                $limitationList = array( 
                                                    'Limitation' => $key , 
                                                    'Required' => $limitationArray[$key] 
                                                );
                                            }
                                }
                                break;
                            
                            case 'ParentClass':
                                {
                                    
                                    if ( in_array( $contentobject->attribute( 'contentclass_id' ), $limitationArray[$key] ) )
                                    {
                                        $access = 'allowed';
                                    }
                                    else
                                    {
                                        $access = 'denied';
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                }
                                break;
                            
                            case 'ParentDepth':
                                {
                                    $assignedNodes = $contentobject->attribute( 'assigned_nodes' );
                                    if ( count( $assignedNodes ) > 0 )
                                    {
                                        foreach ( $assignedNodes as $assignedNode )
                                        {
                                            $depth = $assignedNode->attribute( 'depth' );
                                            if ( in_array( $depth, $limitationArray[$key] ) )
                                            {
                                                $access = 'allowed';
                                                break;
                                            }
                                        }
                                    }
                                    
                                    if ( $access != 'allowed' )
                                    {
                                        $access = 'denied';
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                }
                                break;
                            
                            case 'Section':
                            case 'User_Section':
                                {
                                    if ( in_array( $contentobject->attribute( 'section_id' ), $limitationArray[$key] ) )
                                    {
                                        $access = 'allowed';
                                    }
                                    else
                                    {
                                        $access = 'denied';
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                }
                                break;
                            
                            case 'Language':
                                {
                                    $languageMask = 0;
                                    // If we don't have a language list yet we need to fetch it
                                    // and optionally filter out based on $language.
                                    

                                    if ( $functionName == 'create' )
                                    {
                                        // If the function is 'create' we do not use the language_mask for matching.
                                        if ( $language !== false )
                                        {
                                            $languageMask = $language;
                                        }
                                        else
                                        {
                                            // If the create is used and no language specified then
                                            // we need to match against all possible languages (which
                                            // is all bits set, ie. -1).
                                            $languageMask = - 1;
                                        }
                                    }
                                    else
                                    {
                                        if ( $language !== false )
                                        {
                                            if ( $languageList === false )
                                            {
                                                $languageMask = (int) $contentobject->attribute( 'language_mask' );
                                                // We are restricting language check to just one language
                                                $languageMask &= (int) $language;
                                                // If the resulting mask is 0 it means that the user is trying to
                                                // edit a language which does not exist, ie. translating.
                                                // The mask will then become the language trying to edit.
                                                if ( $languageMask == 0 )
                                                {
                                                    $languageMask = $language;
                                                }
                                            }
                                        }
                                        else
                                        {
                                            $languageMask = - 1;
                                        }
                                    }
                                    // Fetch limit mask for limitation list
                                    $limitMask = eZContentLanguage::maskByLocale( $limitationArray[$key] );
                                    if ( ( $languageMask & $limitMask ) != 0 )
                                    {
                                        $access = 'allowed';
                                    }
                                    else
                                    {
                                        $access = 'denied';
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                }
                                break;
                            
                            case 'Owner':
                            case 'ParentOwner':
                                {
                                    // if limitation value == 2, anonymous limited to current session.
                                    if ( in_array( 2, $limitationArray[$key] ) && $user->isAnonymous() )
                                    {
                                        $createdObjectIDList = eZPreferences::value( 'ObjectCreationIDList' );
                                        if ( $createdObjectIDList && in_array( $contentobject->ID, unserialize( $createdObjectIDList ) ) )
                                        {
                                            $access = 'allowed';
                                        }
                                    }
                                    else 
                                        if ( $contentobject->attribute( 'owner_id' ) == $userID || $contentobject->ID == $userID )
                                        {
                                            $access = 'allowed';
                                        }
                                    if ( $access != 'allowed' )
                                    {
                                        $access = 'denied';
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                }
                                break;
                            
                            case 'Group':
                            case 'ParentGroup':
                                {
                                    $access = $contentobject->checkGroupLimitationAccess( $limitationArray[$key], $userID );
                                    
                                    if ( $access != 'allowed' )
                                    {
                                        $access = 'denied';
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                }
                                break;
                            
                            case 'State':
                                {
                                    if ( count( array_intersect( $limitationArray[$key], $contentobject->attribute( 'state_id_array' ) ) ) == 0 )
                                    {
                                        $access = 'denied';
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                    else
                                    {
                                        $access = 'allowed';
                                    }
                                }
                                break;
                            
                            case 'Node':
                                {
                                    $accessNode = false;
                                    $mainNodeID = $contentobject->attribute( 'main_node_id' );
                                    foreach ( $limitationArray[$key] as $nodeID )
                                    {
                                        $node = eZContentObjectTreeNode::fetch( $nodeID, false, false );
                                        $limitationNodeID = $node['main_node_id'];
                                        if ( $mainNodeID == $limitationNodeID )
                                        {
                                            $access = 'allowed';
                                            $accessNode = true;
                                            break;
                                        }
                                    }
                                    if ( $access != 'allowed' && $checkedSubtree && ! $accessSubtree )
                                    {
                                        $access = 'denied';
                                        // ??? TODO: if there is a limitation on Subtree, return two limitations?
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                    else
                                    {
                                        $access = 'allowed';
                                    }
                                    $checkedNode = true;
                                }
                                break;
                            
                            case 'Subtree':
                                {
                                    $accessSubtree = false;
                                    $assignedNodes = $contentobject->attribute( 'assigned_nodes' );
                                    if ( count( $assignedNodes ) != 0 )
                                    {
                                        foreach ( $assignedNodes as $assignedNode )
                                        {
                                            $path = $assignedNode->attribute( 'path_string' );
                                            $subtreeArray = $limitationArray[$key];
                                            foreach ( $subtreeArray as $subtreeString )
                                            {
                                                if ( strstr( $path, $subtreeString ) )
                                                {
                                                    $access = 'allowed';
                                                    $accessSubtree = true;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    else
                                    {
                                        $parentNodes = $contentobject->attribute( 'parent_nodes' );
                                        if ( count( $parentNodes ) == 0 )
                                        {
                                            if ( $contentobject->attribute( 'owner_id' ) == $userID || $contentobject->ID == $userID )
                                            {
                                                $access = 'allowed';
                                                $accessSubtree = true;
                                            }
                                        }
                                        else
                                        {
                                            foreach ( $parentNodes as $parentNode )
                                            {
                                                $parentNode = eZContentObjectTreeNode::fetch( $parentNode, false, false );
                                                $path = $parentNode['path_string'];
                                                
                                                $subtreeArray = $limitationArray[$key];
                                                foreach ( $subtreeArray as $subtreeString )
                                                {
                                                    if ( strstr( $path, $subtreeString ) )
                                                    {
                                                        $access = 'allowed';
                                                        $accessSubtree = true;
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if ( $access != 'allowed' && $checkedNode && ! $accessNode )
                                    {
                                        $access = 'denied';
                                        // ??? TODO: if there is a limitation on Node, return two limitations?
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                    else
                                    {
                                        $access = 'allowed';
                                    }
                                    $checkedSubtree = true;
                                }
                                break;
                            
                            case 'User_Subtree':
                                {
                                    $assignedNodes = $contentobject->attribute( 'assigned_nodes' );
                                    if ( count( $assignedNodes ) != 0 )
                                    {
                                        foreach ( $assignedNodes as $assignedNode )
                                        {
                                            $path = $assignedNode->attribute( 'path_string' );
                                            $subtreeArray = $limitationArray[$key];
                                            foreach ( $subtreeArray as $subtreeString )
                                            {
                                                if ( strstr( $path, $subtreeString ) )
                                                {
                                                    $access = 'allowed';
                                                }
                                            }
                                        }
                                    }
                                    else
                                    {
                                        $parentNodes = $contentobject->attribute( 'parent_nodes' );
                                        if ( count( $parentNodes ) == 0 )
                                        {
                                            if ( $contentobject->attribute( 'owner_id' ) == $userID || $contentobject->ID == $userID )
                                            {
                                                $access = 'allowed';
                                            }
                                        }
                                        else
                                        {
                                            foreach ( $parentNodes as $parentNode )
                                            {
                                                $parentNode = eZContentObjectTreeNode::fetch( $parentNode, false, false );
                                                $path = $parentNode['path_string'];
                                                
                                                $subtreeArray = $limitationArray[$key];
                                                foreach ( $subtreeArray as $subtreeString )
                                                {
                                                    if ( strstr( $path, $subtreeString ) )
                                                    {
                                                        $access = 'allowed';
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if ( $access != 'allowed' )
                                    {
                                        $access = 'denied';
                                        $limitationList = array( 
                                            'Limitation' => $key , 
                                            'Required' => $limitationArray[$key] 
                                        );
                                    }
                                }
                                break;
                            
                            default:
                                {
                                    if ( strncmp( $key, 'StateGroup_', 11 ) === 0 )
                                    {
                                        if ( count( array_intersect( $limitationArray[$key], $contentobject->attribute( 'state_id_array' ) ) ) == 0 )
                                        {
                                            $access = 'denied';
                                            $limitationList = array( 
                                                'Limitation' => $key , 
                                                'Required' => $limitationArray[$key] 
                                            );
                                        }
                                        else
                                        {
                                            $access = 'allowed';
                                        }
                                    }
                                }
                        }
                        if ( $access == 'denied' )
                        {
                            break;
                        }
                    }
                    
                    $policyList[] = array( 
                        'PolicyID' => $pkey , 
                        'LimitationList' => $limitationList 
                    );
                }
                
                if ( $access == 'denied' )
                {
                    if ( $functionName == 'edit' )
                    {
                        // Check if we have 'create' access under the main parent
                        if ( $contentobject->attribute( 'current_version' ) == 1 && ! $contentobject->attribute( 'status' ) )
                        {
                            $mainNode = eZNodeAssignment::fetchForObject( $contentobject->attribute( 'id' ), $contentobject->attribute( 'current_version' ) );
                            $parentObj = $mainNode[0]->attribute( 'parent_contentobject' );
                            $result = $parentObj->checkAccess( 'create', $contentobject->attribute( 'contentclass_id' ), $parentObj->attribute( 'contentclass_id' ), false, $originalLanguage );
                            if ( $result )
                            {
                                $access = 'allowed';
                            }
                            return $result;
                        }
                    }
                }
                
                if ( $access == 'denied' )
                {
                    if ( $returnAccessList === false )
                    {
                        return 0;
                    }
                    else
                    {
                        return array( 
                            'FunctionRequired' => array( 
                                'Module' => 'content' , 
                                'Function' => $origFunctionName , 
                                'ClassID' => $classID , 
                                'MainNodeID' => $contentobject->attribute( 'main_node_id' ) 
                            ) , 
                            'PolicyList' => $policyList 
                        );
                    }
                }
                else
                {
                    return 1;
                }
            }
        }
    }

}

?>
