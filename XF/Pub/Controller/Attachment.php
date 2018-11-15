<?php

namespace Truonglv\VideoUpload\XF\Pub\Controller;

use Truonglv\VideoUpload\Service\Video\Chunk;

class Attachment extends XFCP_Attachment
{
    public function actionTVUVideoUpload()
    {
        $this->assertPostOnly();

        $filtered = $this->filter([
            'flowChunkNumber' => 'uint',
            'flowChunkSize' => 'uint',
            'flowTotalSize' => 'uint',
            'flowTotalChunks' => 'uint',
            'flowFilename' => 'str',

            'attachmentHash' => 'str',
            'contextData' => 'str'
        ]);

        $contextData = json_decode($filtered['contextData'], true);

        if (empty($contextData)
            || empty($filtered['attachmentHash'])
        ) {
            return $this->noPermission();
        }

        $attachmentRepo = $this->getAttachmentRepo();
        $handler = $attachmentRepo->getAttachmentHandler('post');

        if (!$handler) {
            return $this->noPermission();
        }

        if (!$handler->canManageAttachments($contextData, $error)) {
            return $this->noPermission($error);
        }

        $file = $this->request()->getFile('file');
        if (!$file) {
            return $this->noPermission();
        }

        /** @var Chunk $chunk */
        $chunk = $this->service('Truonglv\VideoUpload:Video\Chunk', $file);
        $chunk
            ->setAttachmentHash($filtered['attachmentHash'])
            ->setChunkNumber($filtered['flowChunkNumber'])
            ->setChunkSize($filtered['flowChunkSize'])
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
