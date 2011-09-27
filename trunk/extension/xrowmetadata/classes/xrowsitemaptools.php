<?php

/** 
 * @author bjoern
 * 
 * 
 */

/* Legacy 4.2 */
require_once "access.php";

class xrowSitemapTools
{
	public static function changeAccess( array $access )
	{
		/* Legacy 4.2 */
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
        $max = 49997; // max. amount of links in 1 sitemap
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
        if ( $ini->variable( 'SiteAccessSettings', 'RemoveSiteAccessIfDefaultAccess' ) == 'enabled' )
        {
            $url = 'http://' . xrowSitemapTools::domain() . $url;
        }
        else
        {
            $url = 'http://' . xrowSitemapTools::domain() . '/' . $GLOBALS['eZCurrentAccess']['name'] . $url;
        }
        
        if ( $meta and $meta->googlemap != '0' )
        {
            $extensions[] = new xrowSitemapItemFrequency( $meta->change );
            $extensions[] = new xrowSitemapItemPriority( $meta->priority );
            $sitemap->add( $url, $extensions );
        }
        elseif ( $meta === false and $googlesitemapsINI->variable( 'Settings', 'AlwaysAdd' ) == 'enabled' )
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
                $object = $subTreeNode->object();
                $meta = xrowMetaDataFunctions::fetchByObject( $object );
                $extensions = array();
                $extensions[] = new xrowSitemapItemModified( $subTreeNode->attribute( 'modified_subnode' ) );

                $url = $subTreeNode->attribute( 'url_alias' );
                eZURI::transformURI( $url, true );
                if ( $ini->variable( 'SiteAccessSettings', 'RemoveSiteAccessIfDefaultAccess' ) == 'enabled' )
                {
                    $url = 'http://' . xrowSitemapTools::domain() . $url;
                }
                else
                {
                    $url = 'http://' . xrowSitemapTools::domain() . '/' . $GLOBALS['eZCurrentAccess']['name'] . $url;
                }

                if ( $meta and $meta->googlemap != '0' )
                {
                    $extensions[] = new xrowSitemapItemFrequency( $meta->change );
                    $extensions[] = new xrowSitemapItemPriority( $meta->priority );
                    $sitemap->add( $url, $extensions );
                }
                elseif ( $meta === false and $googlesitemapsINI->variable( 'Settings', 'AlwaysAdd' ) == 'enabled' )
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
        
        $filename = $dir . '/' . xrowSitemap::BASENAME . '_standard_' . $GLOBALS['eZCurrentAccess']['name'] . '.' . xrowSitemap::SUFFIX;
        
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
            $cli->output( "\n" );
            $cli->output( "Sitemap $filename for siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " has been generated.\n" );
        }
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
        if ( $googlesitemapsINI->hasVariable( 'MobileSitemapSettings', 'ClassFilterType' ) and $googlesitemapsINI->hasVariable( 'MobileSitemapSettings', 'ClassFilterArray' ) )
        {
            $params2 = array( 
                'ClassFilterType' => $googlesitemapsINI->variable( 'MobileSitemapSettings', 'ClassFilterType' ) , 
                'ClassFilterArray' => $googlesitemapsINI->variable( 'MobileSitemapSettings', 'ClassFilterArray' ) 
            );
        }
        $max = 49997; // max. amount of links in 1 sitemap
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
        if ( $googlesitemapsINI->hasVariable( 'MobileSitemapSettings', 'AddPriorityToSubtree' ) and $googlesitemapsINI->variable( 'MobileSitemapSettings', 'AddPriorityToSubtree' ) == 'true' )
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
                
        if ( $meta and $meta->googlemap != '0' )
        {
            $extensions[] = new xrowSitemapItemFrequency( $meta->change );
            $extensions[] = new xrowSitemapItemPriority( $meta->priority );
            $sitemap->add( $url, $extensions );
        }
        elseif ( $meta === false and $googlesitemapsINI->variable( 'Settings', 'AlwaysAdd' ) == 'enabled' )
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
                $object = $subTreeNode->object();
                $meta = xrowMetaDataFunctions::fetchByObject( $object );
                $extensions = array();
                $extensions[] = new xrowSitemapItemModified( $subTreeNode->attribute( 'modified_subnode' ) );

                $url = $subTreeNode->attribute( 'url_alias' );
                eZURI::transformURI( $url );
                $url = 'http://' . xrowSitemapTools::domain() . $url;
                
                if ( $meta and $meta->googlemap != '0' )
                {
                    $extensions[] = new xrowSitemapItemFrequency( $meta->change );
                    $extensions[] = new xrowSitemapItemPriority( $meta->priority );
                    $sitemap->add( $url, $extensions );
                }
                elseif ( $meta === false and $googlesitemapsINI->variable( 'Settings', 'AlwaysAdd' ) == 'enabled' )
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
    if ( function_exists( 'gzencode' ) and $googlesitemapsINI->variable( 'MobileSitemapSettings', 'Gzip' ) == 'enabled' )
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
        global $cli, $isQuiet;
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
        
        $subtreeCount = eZContentObjectTreeNode::subTreeCountByNodeID( $params, $rootNode );
        
        eZDebug::writeDebug( "$subtreeCount items in export tree", __METHOD__ );
        if ( ! $isQuiet )
        {
            $cli->output( "Adding $subtreeCount nodes to the sitemap." );
            $output = new ezcConsoleOutput();
            
            $bar = new ezcConsoleProgressbar( $output, (int) $subtreeCount );
        }
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
                
                /** @TODO subscription functionality 
                 * fetch as member
                 * compare permissions member vs anonymous 
                $user = eZUser::fetch( eZUser::anonymousId() );
                $member = eZUser::fetch( eZUser::anonymousId() );
                if ( !self::checkAccess( $object, $user, 'read') )
                {
                	$news->access = 'Subscription';
                }
                 **/
                
                $news->publication_date = new DateTime( '@' . $object->attribute( 'published' ) );
                $news->title = $object->attribute( 'name' );
                
                $url = $node->attribute( 'url_alias' );
                eZURI::transformURI( $url, true );
                $url = 'http://' . self::domain() . $url;
                $dm = $node->dataMap();
                $news->keywords = array();
				
                foreach ( $dm as $attribute )
                {
                    switch ( $attribute->DataTypeString )
                    {
                        case 'ezimage':
                            if ( $attribute->hasContent() )
                            {
                                
                                $imagedata = $attribute->content();
                                $image = new xrowSitemapItemImage();				
				if ( $ini->hasVariable( 'NewsSitemapSettings', 'ImageAlias' ) )
                                {
					$aliasdata = $imagedata->attribute( $ini->variable( 'NewsSitemapSettings', 'ImageAlias' ) );
					$image->url = 'http://' . self::domain() . '/' . $aliasdata['url'];
				}
				else
				{
					$aliasdata = $imagedata->attribute( 'original' );
					$image->url = 'http://' . self::domain() . '/' . $aliasdata['url'];
				}
                                if ( $imagedata->attribute( 'alternative_text' ) )
                                {
                                    	$image->caption = $imagedata->attribute( 'alternative_text' );
                                }
                                $images[] = $image;
                            }
                            break;
                        case 'xrowmetadata':
                            if ( $attribute->hasContent() )
                            {
                                $keywordattribute = $attribute->content();
                                $news->keywords = array_merge( $news->keywords, $keywordattribute->keywords );
                            }
                        break;
                        case 'ezkeyword':
                            if ( $attribute->hasContent() )
                            {
                                $keywordattribute = $attribute->content();
                                $news->keywords = array_merge( $news->keywords, $keywordattribute->KeywordArray  );
                            }
                            break;
                    }
                }
                if ( $ini->hasVariable( 'NewsSitemapSettings', 'AdditionalKeywordList' ) )
                {
                    $news->keywords = array_merge( $news->keywords, $ini->variable( 'NewsSitemapSettings', 'AdditionalKeywordList' ) );
                }
                $sitemap->add( $url, array_merge( array( 
                    $news 
                ), $images ) );
                if ( isset( $bar ) )
                {
                    $bar->advance();
                }
            }
            eZContentObject::clearCache();
            $params['Offset'] += $params['Limit'];
        }
        
        $sitemap->save( $filename );
        
        if ( ! $isQuiet )
        {
            $cli->output( "\n" );
            $cli->output( "News Sitemap $filename for siteaccess " . $GLOBALS['eZCurrentAccess']['name'] . " has been generated.\n" );
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

?>
