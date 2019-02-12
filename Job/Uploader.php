<?php
/**
 * @license
 * Copyright 2019 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Job;

use XF\Timer;
use XF\Job\AbstractJob;
use Truonglv\VideoUpload\Service\Aws\AbstractAws;

class Uploader extends AbstractJob
{
    public function run($maxRunTime)
    {
        $timer = new Timer($maxRunTime);

        /** @var AbstractAws $service */
        $service = \XF::service('Truonglv\VideoUpload:Aws\DigitalOcean');
        $service->bulkUploads($timer);

        return $this->complete();
    }

    public function getStatusMessage()
    {
        return '';
    }

    public function canCancel()
    {
        return false;
    }

    public function canTriggerByChoice()
    {
        return false;
    }
}
