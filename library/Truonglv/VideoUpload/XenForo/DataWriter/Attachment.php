<?php

class Truonglv_VideoUpload_XenForo_DataWriter_Attachment extends XFCP_Truonglv_VideoUpload_XenForo_DataWriter_Attachment
{
    protected function _postDelete()
    {
        parent::_postDelete();

        Truonglv_VideoUpload_Model_Video::onDataWriterDelete($this);
    }
}
