<?php

class Truonglv_VideoUpload_DataWriter_Video extends XenForo_DataWriter
{

    /* Start auto-generated lines of code. Change made will be overwriten... */

    protected function _getFields()
    {
        return array(
            'xf_truonglv_videoupload_video' => array(
                'video_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'autoIncrement' => true),
                'thread_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
                'attachment_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
                'remote_url' => array('type' => XenForo_DataWriter::TYPE_STRING),
                'remote_upload_date' => array('type' => XenForo_DataWriter::TYPE_UINT, 'default' => 0),
                'upload_date' => array('type' => XenForo_DataWriter::TYPE_UINT, 'default' => 0),
            )
        );
    }

    protected function _getExistingData($data)
    {
        if (!$id = $this->_getExistingPrimaryKey($data, 'video_id')) {
            return false;
        }

        return array('xf_truonglv_videoupload_video' => $this->_getVideoModel()->getVideoById($id));
    }

    protected function _getUpdateCondition($tableName)
    {
        $conditions = array();

        foreach (array('video_id') as $field) {
            $conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
        }

        return implode(' AND ', $conditions);
    }

    protected function _getVideoModel()
    {
        /** @var Truonglv_VideoUpload_Model_Video $model */
        $model = $this->getModelFromCache('Truonglv_VideoUpload_Model_Video');

        return $model;
    }

    /* End auto-generated lines of code. Feel free to make changes below */
}
