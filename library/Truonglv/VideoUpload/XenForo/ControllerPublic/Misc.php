<?php

class Truonglv_VideoUpload_XenForo_ControllerPublic_Misc extends XFCP_Truonglv_VideoUpload_XenForo_ControllerPublic_Misc
{
    public function actionTVUVideoUpload()
    {
        $this->_assertPostOnly();

        $file = XenForo_Upload::getUploadedFile('file');
        $filtered = $this->_input->filter(array(
            'flowChunkNumber' => XenForo_Input::UINT,
            'flowTotalSize' => XenForo_Input::UINT,
            'flowFilename' => XenForo_Input::STRING,
            'flowTotalChunks' => XenForo_Input::UINT,
            'hash' => XenForo_Input::STRING,
            'content_data' => XenForo_Input::STRING,
            'is_completed' => XenForo_Input::BOOLEAN
        ));

        if (!XenForo_Visitor::getInstance()->hasPermission('general', 'tvu_uploadVideos')) {
            return $this->responseNoPermission();
        }

        if (strlen($filtered['hash']) !== 32) {
            return $this->responseNoPermission();
        }

        $contentData = json_decode($filtered['content_data'], true);
        if (empty($contentData)) {
            return $this->responseNoPermission();
        }

        /** @var XenForo_Model_Attachment $attachmentModel */
        $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
        $postHandler = $attachmentModel->getAttachmentHandler('post');

        if (!$postHandler->canUploadAndManageAttachments($contentData)) {
            return $this->responseNoPermission();
        }

        $contentId = $postHandler->getContentIdFromContentData($contentData);

        $existingAttachments = ($contentId
            ? $attachmentModel->getAttachmentsByContentId('post', $contentId)
            : array()
        );
        $newAttachments = $attachmentModel->getAttachmentsByTempHash($filtered['hash']);
        $attachmentConstraints = $postHandler->getAttachmentConstraints();

        /** @var Truonglv_VideoUpload_Model_Video $videoModel */
        $videoModel = $this->getModelFromCache('Truonglv_VideoUpload_Model_Video');

        $attachmentIds = array_merge(array_keys($existingAttachments), array_keys($newAttachments));
        if (!empty($attachmentIds)) {
            $videos = $videoModel->getAllVideo(array(
                'attachment_id' => $attachmentIds
            ));

            if (count($videos) > 0) {
                return $this->responseError(
                    new XenForo_Phrase('tvu_you_may_only_upload_an_video_per_post'),
                    400
                );
            }
        }

        if ($attachmentConstraints['count'] > 0) {
            $remainingUploads = $attachmentConstraints['count'] - (count($existingAttachments) + count($newAttachments));
            if ($remainingUploads <= 0) {
                return $this->responseError(new XenForo_Phrase(
                    'you_may_not_upload_more_files_with_message_allowed_x',
                    array('total' => $attachmentConstraints['count'])
                ), 400);
            }
        }

        if ($filtered['is_completed']) {
            $attachmentId = $videoModel->uploadVideo(
                $filtered['flowTotalChunks'],
                $filtered['flowTotalSize'],
                $filtered['hash'],
                $filtered['flowFilename']
            );

            if (empty($attachmentId)) {
                return $this->responseError(
                    new XenForo_Phrase('tvu_an_error_occurred_while_process_video'),
                    400
                );
            }

            $attachment = $attachmentModel->getAttachmentById($attachmentId);
            $message = new XenForo_Phrase('upload_completed_successfully');

            $params = array(
                'attachment' => $attachmentModel->prepareAttachment($attachment),
                'message' => $message,
                'hash' => $filtered['hash'],
                'content_type' => 'post',
                'content_data' => $contentData,
                'key' => ''
            );

            return $this->responseView('XenForo_ViewPublic_Attachment_DoUpload', '', $params);
        } else {
            $success = $videoModel->uploadChunkFile(
                $file,
                $filtered['hash'],
                $filtered['flowFilename'],
                $filtered['flowChunkNumber']
            );

            if ($success === false || ($success instanceof XenForo_Phrase)) {
                $error = ($success === false)
                    ? new XenForo_Phrase('tvu_an_error_occurred_while_process_video'):
                    $success;

                return $this->responseError($error, 400);
            }


            return $this->responseMessage(new XenForo_Phrase('changes_saved'));
        }
    }
}
