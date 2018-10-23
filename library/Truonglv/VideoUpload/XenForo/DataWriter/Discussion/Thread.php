<?php

class Truonglv_VideoUpload_XenForo_DataWriter_Discussion_Thread extends XFCP_Truonglv_VideoUpload_XenForo_DataWriter_Discussion_Thread
{
    protected function _discussionPostDelete()
    {
        parent::_discussionPostDelete();

        Truonglv_VideoUpload_Model_Video::onDataWriterDelete($this);
    }
}
