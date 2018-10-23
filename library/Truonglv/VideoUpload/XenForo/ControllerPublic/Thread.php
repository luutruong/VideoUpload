<?php

class Truonglv_VideoUpload_XenForo_ControllerPublic_Thread extends XFCP_Truonglv_VideoUpload_XenForo_ControllerPublic_Thread
{
    protected function _getDefaultViewParams(array $forum, array $thread, array $posts, $page = 1, array $viewParams = array())
    {
        $params = parent::_getDefaultViewParams($forum, $thread, $posts, $page, $viewParams);

        /** @var Truonglv_VideoUpload_Model_Video $videoModel */
        $videoModel = $this->getModelFromCache('Truonglv_VideoUpload_Model_Video');
        $videos = $videoModel->getAllVideo(array(
            'thread_id' => $thread['thread_id']
        ));

        if (empty($videos)) {
            return $params;
        }

        $videosGrouped = array();
        foreach ($videos as $video) {
            $videosGrouped[$video['attachment_id']] = $video;
        }

        Truonglv_VideoUpload_Callback::$videos = $videosGrouped;

        return $params;
    }
}
