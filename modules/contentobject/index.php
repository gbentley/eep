<?php
/*
EEP is a command line tool to support developers using ezpublish
Copyright © 2012  Mugo Web
GNU GENERAL PUBLIC LICENSE
Version 3, 29 June 2007
*/
/**
 * eep/modules/contentobject/index.php
 */

class contentobject_commands
{
    const contentobject_clearcache       = "clearcache";
    const contentobject_info             = "info";
    const contentobject_datamap          = "datamap";
    const contentobject_delete           = "delete";
    const contentobject_reverserelated   = "reverserelated";
    const contentobject_contentnode      = "contentnode";
    const contentobject_republish        = "republish";
    const contentobject_sitemapxml       = "sitemapxml";
    const contentobject_deleteversions   = "deleteversions";
    
    //--------------------------------------------------------------------------
    var $availableCommands = array
    (
        "help"
        , self::contentobject_clearcache
        , self::contentobject_contentnode
        , self::contentobject_info
        , self::contentobject_datamap
        , self::contentobject_delete
        , self::contentobject_deleteversions
        , self::contentobject_republish
        , self::contentobject_reverserelated
        , self::contentobject_sitemapxml
    );
    var $help = "";                     // used to dump the help string
    
    //--------------------------------------------------------------------------
    public function __construct()
    {
        $parts = explode( "/", __FILE__ );
        array_pop( $parts );
        $command = array_pop( $parts );
        
$this->help = <<<EOT
clearcache
- clear the content cache for given object
  eep use ezroot <path>
  eep use contentobject <object id>
  eep contentobject clearcache
  or
  eep contentobject clearcache <object id>

contentnode
- convert a content object id into a content node id
  eep use ezroot <path>
  eep contentobject contentnode <content object id>
  or
  eep use ezroot <path>
  eep use contentobject <content object id>
  eep contentobject contentnode

datamap
- dumps most of the datamap
  eep use ezroot <path>
  eep use contentobject <object id>
  eep contentobject datamap
  or
  eep contentobject datamap <object id>

delete
- deletes an object and it's children
  eep use ezroot <path>
  eep use contentobject <object id>
  eep contentobject delete
  or
  eep contentobject delete <object id>

deleteversions
- deletes all the archived versions of an object
  eep contentobject deleteversions <object id>

info
- dumps some info about the content object
  eep use ezroot <path>
  eep use contentobject <object id>
  eep contentobject info
  or
  eep contentobject info <object id>

republish
- republishes an object
  eep use ezroot <path>
  eep use contentobject <object id>
  eep contentobject republish
  or
  eep contentobject republish <object id>

reverserelated
- dumps list of reverserelated objects
- supports use of --limit=n and --offset=m
  eep use ezroot <path>
  eep use contentobject <object id>
  eep contentobject reverserelated
  or
  eep contentobject reverserelated <object id>
  
sitemapxml
- emit line of xml for inclusion in a sitemap
- note domain is only: example.com
- note <change frequency> is one of: always hourly daily weekly monthly yearly never
- note that the sitemap header is: <?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
-           the matching close is: </urlset>
  eep contentobject sitemapxml <object id> <domain> [<change frequency> [<priority>]]
EOT;
    }
    
    //--------------------------------------------------------------------------
    private function fetchContentObjectFromId( $contentobjectId )
    {
        $keepers = array
        (
            "Name"
            , "CurrentLanguage"
            , "ClassIdentifier"
            // wtf , "StateIDArray":"eZContentObject":private
            , "SectionID"
            , "OwnerID"
            , "Published"
            , "Modified"
            , "CurrentVersion"
            , "Status"
            , "RemoteID"
            //, "DataMap"  
        );

        $results[] = array( "key",      "value" );
        
        $contentobject = eZContentObject::fetch( $contentobjectId );
        foreach( $keepers as $key )
        {
            $value = $contentobject->$key;
            // fix false as empty string
            if( false === $value )
            {
                $value = "(false)";
            }
            // fix timestamps
            if( in_array( $key, array("Published","Modified") ) )
            {
                $value = $value . " (".date("Y-m-d H:i:s",$value).")";
            }
            $results[] = array( $key, $value );
        }
        // other values ...
        $results[] = array( "MainNodeID", $contentobject->mainNodeID() );
        // additional locations, only show if there is more than the main node
        $assignedNodes = $contentobject->attribute( 'assigned_nodes' );
        //var_dump( $assignedNodes );
        if( 0 < count($assignedNodes ) )
        {
            $results[] = array( "", "" );
            $results[] = array( "All locations:", "" );
            foreach( $assignedNodes as $otherLocation )
            {
                $results[] = array( $otherLocation->PathString, $otherLocation->PathIdentificationString );
            }
        }

        eep::printTable( $results, "contentobject id [" .$contentobjectId. "]" );
    }

    //--------------------------------------------------------------------------
    private function fetchDataMapFromId( $contentobjectId )
    {
        $contentobject = eZContentObject::fetch( $contentobjectId );
        $dataMap = $contentobject->dataMap();
        
        $results[] = array
        (
            "identifier"
            , "ID"
            , "DataTypeString"
            , "DataText"
            , "DataInt"
            , "DataFloat"
            , "SortKeyInt"
            , "SortKeyString"
            , "Ver."
        );
        foreach( $dataMap as $name => $attr )
        {
            $results[] = array
            (
                $name
                , $attr->ID
                , $attr->DataTypeString
                , (35<strlen($attr->DataText))?substr( $attr->DataText, 0, 35 )."...":$attr->DataText
                , $attr->DataInt
                , $attr->DataFloat
                , $attr->SortKeyInt
                , (15<strlen($attr->SortKeyString))?substr( $attr->SortKeyString, 0, 15 )."...":$attr->SortKeyString
                , $attr->Version
            );
        }
        eep::printTable( $results, "contentobject datamap id [" .$contentobjectId. "]" );
    }
    
    //--------------------------------------------------------------------------
    // todo, this does not return the full list of reverse related stuff
    // todo, this supports Limit and Offset; should use those
    private function fetchReverseRelated( $objectId, $additional )
    {
        $object = eZContentObject::fetch( $objectId );

        // some other parameters:
        // LoadDataMap
        // Limit
        // Offset
        // AsObject
        // SortBy
        // IgnoreVisibility
        $parameters = array();
        $parameters[ "AllRelations" ] = true;
        $parameters[ "IgnoreVisibility" ] = true;
        if( isset($additional["limit"]) )
        {
            $parameters[ "Limit" ] = $additional["limit"];
        }
        if( isset($additional["offset"]) )
        {
            $parameters[ "Offset" ] = $additional["offset"];
        }
        
        $reverseRelated = $object->relatedObjects
        (
            false                           // use current version of object
            , false                         // use current object id
            , 0                             // attribute id, but we are going to use 'all relations' instead
            , false                         // return array of objects or a grouped list ... ?
            , $parameters
            , true                          // true->reverse-related and false->related
        );
        
        $keepers = array
        (
            "ObjectID"
            , "MainNodeID"
            , "ClassIdentifier"
            //, "StateIDArray"
            , "SID"
            , "Name"
        );
        
        $results[] = $keepers;
        foreach( $reverseRelated as $revObject )
        {
            $row = array
            (
                $revObject->ID
                , $revObject->mainNodeId()
                , $revObject->ClassIdentifier
                //, serialize( $revObject->stateIdentifierArray() )
                , $revObject->SectionID
                , $revObject->Name
            );
            $results[] = $row;
        }
        eep::printTable( $results, "Reverse related objects [" .$objectId. "]" );
    }
    
    //--------------------------------------------------------------------------
    private function delete( $objectId )
    {
        $adminUserObject = eZUser::fetch( eepSetting::PrivilegedAccountId );
        $adminUserObject->loginCurrent();
        
        //$object = eZContentObject::fetch( $objectId );
        $result = eZContentObjectOperations::remove( $objectId, true );

        $adminUserObject->logoutCurrent();
        if( $result )
        {
            echo "Deleted ok\n";
        }
        else
        {
            echo "Failed to delete\n";
        }
    }
    
    //--------------------------------------------------------------------------
    private function convertToNodeId( $objectId )
    {
        $object = eZContentObject::fetch( $objectId, true );
        return $object->MainNodeId();
    }
    
    //--------------------------------------------------------------------------
    private function clearObjectCache( $objectId )
    {
        // todo: the first one is suspect:
        //eZContentObject::clearCache( array( $objectId ) );
        
        // ... so, todo: does this work any better?
        eZContentCacheManager::clearContentCacheIfNeeded( $objectId );
    }
    
    //--------------------------------------------------------------------------
    // <url>
    // <loc>http://www.example.com/</loc>
    // <lastmod>2005-01-01</lastmod>
    // <changefreq>monthly</changefreq>
    // <priority>0.8</priority>
    // </url>
    private function sitemapxml( $objectId, $domain, $changeFrequency, $priority )
    {
        $object = eZContentObject::fetch( $objectId, true );
        $node = eZContentObjectTreeNode::fetch( $object->MainNodeId() );
        $pathNodes = explode( "/", $node->PathString );
        array_shift( $pathNodes );
        array_shift( $pathNodes );
        array_shift( $pathNodes );
        array_pop( $pathNodes );
        
        $location = "http://" . $domain;
        foreach( $pathNodes as $nodeId )
        {
            $node = eZContentObjectTreeNode::fetch( $nodeId );
            $location .= "/" . $node->pathWithNames( true );
        }
        $lastModified = date( DATE_ATOM, $object->Modified ); // 1997-07-16T19:20:30.45+01:00

        // ["Modified"]=>string(10) "1270075155"
        // ["Published"]=>string(10) "1270075155"
        // hourly daily weekly monthly yearly never
        
        if( !$changeFrequency )
        {
            $changeFrequency  = "weekly";
        }
        if( !$priority )
        {
            $priority = 0.5;
        }
        $xml = "<url>";
        $xml .= "<loc>" . $location . "</loc>";
        $xml .= "<lastmod>" . $lastModified . "</lastmod>";
        $xml .= "<changefreq>" . $changeFrequency . "</changefreq>";
        $xml .= "<priority>" .$priority. "</priority>";
        $xml .= "</url>\n";
        
        echo $xml;
    }
    
    //--------------------------------------------------------------------------
    private function deleteversions( $objectId )
    {
        $contentObject = eZContentObject::fetch( $objectId );
        $versionCount = $contentObject->getVersionCount();
        $params = array( 'conditions'=> array( 'status' => eZContentObjectVersion::STATUS_ARCHIVED ) );
        $versions = $contentObject->versions( true, $params );
        if( count( $versions ) > 0 )
        {
            echo "Deleting ". count( $versions ) . " versions\n";
            foreach( $versions as $version )
            {
                $version->removeThis();
            }
        }
    }
    
    //--------------------------------------------------------------------------
    public function run( $argv, $additional )
    {
        $command = @$argv[2];
        $param1 = @$argv[3];
        $param2 = @$argv[4];

        if( !in_array( $command, $this->availableCommands ) )
        {
            throw new Exception( "Command '" . $command . "' not recognized." );
        }

        $eepCache = eepCache::getInstance();

        switch( $command )
        {
            case "help":
                echo "\nAvailable commands:: " . implode( ", ", $this->availableCommands ) . "\n";
                echo "\n".$this->help."\n";
                break;
            
            case self::contentobject_info:
                $objectId = $eepCache->readFromCache( eepCache::use_key_object );
                if( $param1 )
                {
                    $objectId = $param1;
                }
                if( !eepValidate::validateContentObjectId( $objectId ) )
                    throw new Exception( "This is not an object id: [" .$objectId. "]" );
                $this->fetchContentObjectFromId( $objectId );
                break;
            
            case self::contentobject_datamap:
                $objectId = $eepCache->readFromCache( eepCache::use_key_object );
                if( $param1 )
                {
                    $objectId = $param1;
                }
                if( !eepValidate::validateContentObjectId( $objectId ) )
                    throw new Exception( "This is not an object id: [" .$objectId. "]" );
                $this->fetchDataMapFromId( $objectId );
                break;
            
            case self::contentobject_reverserelated:
                $objectId = $eepCache->readFromCache( eepCache::use_key_object );
                if( $param1 )
                {
                    $objectId = $param1;
                }
                if( !eepValidate::validateContentObjectId( $objectId ) )
                    throw new Exception( "This is not an object id: [" .$objectId. "]" );
                $this->fetchReverseRelated( $objectId, $additional );
                break;
            
            case self::contentobject_delete:
                $objectId = $eepCache->readFromCache( eepCache::use_key_object );
                if( $param1 )
                {
                    $objectId = $param1;
                }
                if( !eepValidate::validateContentObjectId( $objectId ) )
                    throw new Exception( "This is not an object id: [" .$objectId. "]" );
                $this->delete( $objectId );
                break;
            
            case self::contentobject_contentnode:
                $objectId = $eepCache->readFromCache( eepCache::use_key_object );
                if( $param1 )
                {
                    $objectId = $param1;
                }
                if( !eepValidate::validateContentObjectId( $objectId ) )
                    throw new Exception( "This is not an object id: [" .$objectId. "]" );
                echo $this->convertToNodeId( $objectId );
                break;
            
            case self::contentobject_republish:
                $objectId = $eepCache->readFromCache( eepCache::use_key_object );
                if( $param1 )
                {
                    $objectId = $param1;
                }
                if( !eepValidate::validateContentObjectId( $objectId ) )
                    throw new Exception( "This is not an object id: [" .$objectId. "]" );
                eep::republishObject( $objectId );
                break;
            
            case self::contentobject_clearcache:
                $objectId = $eepCache->readFromCache( eepCache::use_key_object );
                if( $param1 )
                {
                    $objectId = $param1;
                }
                if( !eepValidate::validateContentObjectId( $objectId ) )
                    throw new Exception( "This is not an object id: [" .$objectId. "]" );
                $this->clearObjectCache( $objectId );
                break;
            
            case self::contentobject_sitemapxml:
                $objectId = $eepCache->readFromCache( eepCache::use_key_object );
                if( $param1 )
                {
                    $objectId = $param1;
                }
                if( !eepValidate::validateContentObjectId( $objectId ) )
                    throw new Exception( "This is not an object id: [" .$objectId. "]" );
                $this->sitemapxml( $objectId, $param2, $param3, $param4 ); // objid, domain, change-frequency, priority
                break;
            
            case self::contentobject_deleteversions:
                $objectId = $eepCache->readFromCache( eepCache::use_key_object );
                if( $param1 )
                {
                    $objectId = $param1;
                }
                if( !eepValidate::validateContentObjectId( $objectId ) )
                    throw new Exception( "This is not an object id: [" .$objectId. "]" );
                $this->deleteversions( $objectId );
                break;
        }
    }
}

//------------------------------------------------------------------------------
$operation = new contentobject_commands();
if( !isset($argv[2]) )
{
    $argv[2] = "help";
}
$additional = eep::extractAdditionalParams( $argv );
$operation->run( $argv, $additional );
?>