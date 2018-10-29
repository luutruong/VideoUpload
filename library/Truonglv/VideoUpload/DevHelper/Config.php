<?php

class Truonglv_VideoUpload_DevHelper_Config extends DevHelper_Config_Base
{
    protected $_dataClasses = array(
        'video' => array(
            'name' => 'video',
            'camelCase' => 'Video',
            'camelCasePlural' => false,
            'camelCaseWSpace' => 'Video',
            'camelCasePluralWSpace' => false,
            'fields' => array(
                'video_id' => array('name' => 'video_id', 'type' => 'uint', 'autoIncrement' => true),
                'thread_id' => array('name' => 'thread_id', 'type' => 'uint', 'required' => true),
                'attachment_id' => array('name' => 'attachment_id', 'type' => 'uint', 'required' => true),
                'remote_url' => array('name' => 'remote_url', 'type' => 'string'),
                'remote_upload_date' => array('name' => 'remote_upload_date', 'type' => 'uint', 'default' => 0),
                'upload_date' => array('name' => 'upload_date', 'type' => 'uint', 'default' => 0),
            ),
            'phrases' => array(),
            'title_field' => 'remote_url',
            'primaryKey' => array('video_id'),
            'indeces' => array(
                'attachment_id' => array('name' => 'attachment_id', 'fields' => array('attachment_id'), 'type' => 'NORMAL'),
                'thread_id' => array('name' => 'thread_id', 'fields' => array('thread_id'), 'type' => 'NORMAL'),
            ),
            'files' => array(
                'data_writer' => array('className' => 'Truonglv_VideoUpload_DataWriter_Video', 'hash' => '154703fba448c70df83ec5701774f12c'),
                'model' => array('className' => 'Truonglv_VideoUpload_Model_Video', 'hash' => 'a82bcee1db70d4de2215dab9a1a3e285'),
                'route_prefix_admin' => false,
                'controller_admin' => false,
            ),
        ),
        'video_part' => array(
            'name' => 'video_part',
            'camelCase' => 'VideoPart',
            'camelCasePlural' => false,
            'camelCaseWSpace' => 'Video Part',
            'camelCasePluralWSpace' => false,
            'fields' => array(
                'path' => array('name' => 'path', 'type' => 'string', 'length' => 255, 'required' => true),
                'upload_date' => array('name' => 'upload_date', 'type' => 'uint', 'required' => true),
            ),
            'phrases' => array(),
            'title_field' => 'path',
            'primaryKey' => false,
            'indeces' => array(
                'upload_date' => array('name' => 'upload_date', 'fields' => array('upload_date'), 'type' => 'NORMAL')
            ),
            'files' => array('data_writer' => false, 'model' => false, 'route_prefix_admin' => false, 'controller_admin' => false),
        ),
    );
    protected $_dataPatches = array();
    protected $_exportPath = false;
    protected $_exportIncludes = array();
    protected $_exportExcludes = array();
    protected $_exportAddOns = array();
    protected $_exportStyles = array();
    protected $_options = array();

    /**
     * Return false to trigger the upgrade!
     **/
    protected function _upgrade()
    {
        return true; // remove this line to trigger update

        /*
        $this->addDataClass(
            'name_here',
            array( // fields
                'field_here' => array(
                    'type' => 'type_here',
                    // 'length' => 'length_here',
                    // 'required' => true,
                    // 'allowedValues' => array('value_1', 'value_2'),
                    // 'default' => 0,
                    // 'autoIncrement' => true,
                ),
                // other fields go here
            ),
            array('primary_key_1', 'primary_key_2'), // or 'primary_key', both are okie
            array( // indeces
                array(
                    'fields' => array('field_1', 'field_2'),
                    'type' => 'NORMAL', // UNIQUE or FULLTEXT
                ),
            ),
        );
        */
    }
}
