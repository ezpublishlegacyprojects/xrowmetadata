<?php
$db = eZDB::instance();
$db->begin();
$cond = array( 'data_type_string' => xrowMetaDataType::DATA_TYPE_STRING );
$list = eZPersistentObject::fetchObjectList(eZContentObjectAttribute::definition(), null, $cond );
/* var eZContentObjectAttribute */
foreach( $list as $attribute )
{
	/* var xrowMetaData */
	$data = $attribute->content();
	$data->priority = null;
	$attribute->setContent( $data );
	$attribute->store();
}

$db->commit();