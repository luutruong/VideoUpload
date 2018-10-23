<?php

class Truonglv_VideoUpload_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_Truonglv_VideoUpload_XenForo_DataWriter_DiscussionMessage_Post
{
    protected function _associateAttachments($attachmentHash)
    {
        parent::_associateAttachments($attachmentHash);

        $db = $this->_db;
        $associatedAttachIds = $db->fetchCol('
            SELECT attachment_id
            FROM xf_attachment
            WHERE content_type = ? AND content_id = ?
        ', array('post', $this->get('post_id')));

        $thread = $this->getDiscussionData();

        if (!empty($associatedAttachIds)) {
            $db->update(
                'xf_truonglv_videoupload_video',
                array('thread_id' => $thread['thread_id']),
                'attachment_id IN (' . $db->quote($associatedAttachIds) . ')'
            );
        }
    }
}
