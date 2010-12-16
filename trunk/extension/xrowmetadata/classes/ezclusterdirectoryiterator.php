<?php

class eZClusterDirectoryIterator implements Iterator
{

    function __construct( $dirname, $scope )
    {
        $handler = eZClusterFileHandler::instance();
        if ( $handler instanceof eZFSFileHandler )
        {
        	$dir = new DirectoryIterator( $dirname );
            foreach ( $dir as $file )
            {
                if ( $file->isDot() and $file->isDir() )
                {
                    continue;
                }
                
                $this->array[] = eZClusterFileHandler::instance( $dirname . '/' . $file->getFilename() );
            }
        }
        elseif ( $handler instanceof eZDFSFileHandler )
        {
        	$db = eZDB::instance();
        	$dir = $db->arrayQuery( "SELECT name from ezdfsfile WHERE scope = 'sitemap' AND expired = 0 AND name like '" . $db->escapeString( $dirname ) . "/%'" );
            foreach ( $dir as $file )
            {               
                $this->array[] = eZClusterFileHandler::instance( $file['name'] );
            }
        }
        $this->position = 0;
    }
    private $position = 0;
    private $array = array();

    function rewind()
    {
        
        $this->position = 0;
    }

    function current()
    {
        
        return $this->array[$this->position];
    }

    function key()
    {
        
        return $this->position;
    }

    function next()
    {
        
        ++ $this->position;
    }

    function valid()
    {
        
        return isset( $this->array[$this->position] );
    }
}