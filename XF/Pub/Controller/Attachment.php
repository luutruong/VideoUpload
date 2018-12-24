<?php

namespace Truonglv\VideoUpload\XF\Pub\Controller;

use Truonglv\VideoUpload\Callback;
use Truonglv\VideoUpload\Utils\File;
use Truonglv\VideoUpload\Service\Video\Chunk;
use Truonglv\VideoUpload\Service\Video\Uploader;

class Attachment extends XFCP_Attachment
{
    public function actionTVUVideoUpload()
    {
        $this->assertPostOnly();

        $filtered = $this->filter([
            'flowChunkNumber' => 'uint',
            'flowCurrentChunkSize' => 'uint',
            'flowTotalSize' => 'uint',
            'flowTotalChunks' => 'uint',
            'flowFilename' => 'str',

            'attachmentHash' => 'str',
            'contextData' => 'str',
            'isCompleted' => 'bool',
            'contentType' => 'str'
        ]);

        $contextData = json_decode($filtered['contextData'], true);

        if (empty($contextData)
            || empty($filtered['attachmentHash'])
            || !\XF::visitor()->hasPermission('general', 'tvu_uploadVideos')
            || !in_array($filtered['contentType'], Callback::$allowContentTypes, true)
        ) {
            return $this->noPermission();
        }

        if (empty($filtered['flowFilename'])
            || !File::isValidVideo($filtered['flowFilename'])
        ) {
            return $this->noPermission();
        }

        $attachmentRepo = $this->getAttachmentRepo();
        $handler = $attachmentRepo->getAttachmentHandler($filtered['contentType']);

        if (!$handler) {
            return $this->noPermission();
        }

        if (!$handler->canManageAttachments($contextData, $error)) {
            return $this->noPermission($error);
        }

        // Do not allow multiple videos per post
        $attachmentIds = $this->finder('XF:Attachment')
            ->where('temp_hash', $filtered['attachmentHash'])
            ->fetchColumns('attachment_id');
        if ($attachmentIds) {
            $videoTotal = $this->finder('Truonglv\VideoUpload:Video')
                ->where('attachment_id', array_column($attachmentIds, 'attachment_id'))
                ->total();
            if ($videoTotal > 0) {
                return $this->error(\XF::phrase('tvu_you_may_only_upload_an_video_per_post'), 400);
            }
        }

        if (!empty($filtered['isCompleted'])) {
            /** @var Uploader $uploader */
            $uploader = $this->service('Truonglv\VideoUpload:Video\Uploader');
            $uploader
                ->setAttachmentHash($filtered['attachmentHash'])
                ->setFileName($filtered['flowFilename'])
                ->setTotalChunks($filtered['flowTotalChunks'])
                ->setTotalSize($filtered['flowTotalSize']);

            $attachment = $uploader->upload($handler);

            if (!$attachment) {
                return $this->error(\XF::phrase('tvu_an_error_occurred_while_process_video'), 400);
            }

            $json['attachment'] = [
                'attachment_id' => $attachment->attachment_id,
                'filename' => $attachment->filename,
                'file_size' => $attachment->file_size,
                'thumbnail_url' => null,
                'link' => $this->buildLink('attachments', $attachment, ['hash' => $attachment->temp_hash]),
                'is_tvu_video' => true
            ];
            $json['link'] = $json['attachment']['link'];

            $json = $handler->prepareAttachmentJson($attachment, $contextData, $json);

            $reply = $this->redirect($this->buildLink('attachments/upload', null, [
                'type' => 'post',
                'context' => $contextData,
                'hash' => $filtered['attachmentHash']
            ]));
            $reply->setJsonParams($json);

            return $reply;
        } else {
            $file = $this->request()->getFile('file');
            if (!$file) {
                return $this->noPermission();
            }

            /** @var Chunk $chunk */
            $chunk = $this->service('Truonglv\VideoUpload:Video\Chunk', $file);
            $chunk
                ->setAttachmentHash($filtered['attachmentHash'])
                ->setChunkNumber($filtered['flowChunkNumber'])
                ->setChunkSize($filtered['flowCurrentChunkSize'])
                ->setFileName($filtered['flowFilename']);

            if (!$chunk->upload()) {
                return $this->error(
                    $chunk->getLastError() ?: \XF::phrase('tvu_an_error_occurred_while_process_video'),
                    400
                );
            }

            return $this->message(\XF::phrase('ok'));
        }
    }
}
