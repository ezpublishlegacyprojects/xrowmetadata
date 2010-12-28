<?php /* #?ini charset="utf8"?

[Settings]
# Pings Google, Bing for update.
Ping=true

Sitemap=enabled
NewsSitemap=enabled
VideoSitemap=enabled
# Always add objects even if they do not have a meta datatype
AlwaysAdd=enabled

[SitemapSettings]
# here you need to specify every siteaccess a sitemap shall be created for
# if no siteaccessarray is given, the default siteaccess will be used for generation
# AvailableSiteAccessList[]
# AvailableSiteAccessList[]=ger
# AvailableSiteAccessList[]=eng

# include or exclude objects of classes listed in ClassFilterArray
#ClassFilterType=exclude

# setting array to include/exclude classes in sitemap
#ClassFilterArray[]
#ClassFilterArray[]=folder
#ClassFilterArray[]=article
#ClassFilterArray[]=image
#ClassFilterArray[]=forum
#ClassFilterArray[]=...

# If there is a node which doesn't have xrowmetadata data the priority
# can be set by depth of the node
# root node priority = 1
# depth 2 meeans 0.9, depth 3 0.8 and so on.
AddPriorityToSubtree=true

# Use gzip to compress the sitemap
# Deprecated 
#Gzip=disabled

#Add additional urls which are module views
# Deprecated, use seperate sitemap file
#AddUrlArray[]
#AddUrlArray[0]=/content/search

# Optional, add priority of additional urls which are module views
# The priority of this URL relative to other URLs on your site. Valid values 
# range from 0.0 to 1.0. This value does not affect how your pages are compared 
# to pages on other sites—it only lets the search engines know which pages you 
# deem most important for the crawlers.
# Deprecated, use seperate sitemap file
#AddPriorityArray[]
#AddPriorityArray[0]=0.9

# Optional, Add frequency of additional urls which are module views
# Allowed values: [always|hourly|daily|weekly|monthly|yearly|never]
# Deprecated, use seperate sitemap file
#AddFrequencyArray[]
#AddPriorityArray[0]=always



[NewsSitemapSettings]
# Name of the publication
#Name=Test
# Addtional Keywords 
#AdditionalKeywordList[]=Music
#AdditionalKeywordList[]=xrow GmbH
#Image Alias used for image items
#ImageAlias=rss
# setting array to include classes in sitemap
#ClassFilterArray[]
#ClassFilterArray[]=article
*/?>
