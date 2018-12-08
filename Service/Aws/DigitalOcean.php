<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Service\Aws;

class DigitalOcean extends AbstractAws
{
    private $apiKey;
    private $apiSecret;
    private $bucket;
    private $region;

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);

        $options = $app->options();

        $this->apiKey = $options->TVU_spacesApiKey;
        $this->apiSecret = $options->TVU_spacesApiSecret;
        $this->bucket = $options->TVU_spacesBucket;
        $this->region = $options->TVU_spacesRegion;
    }

    protected function getBaseUrl()
    {
        return sprintf(
            'https://%s.%s.digitaloceanspaces.com',
            $this->bucket,
            $this->region
        );
    }

    protected function getS3Options()
    {
        return [
            'region' => $this->region,
            'endpoint' => sprintf('https://%s.digitaloceanspaces.com', $this->region),
            'credentials' => [
                'key' => $this->apiKey,
                'secret' => $this->apiSecret
            ]
        ];
    }

    protected function getBucket()
    {
        return $this->bucket;
    }

    protected function isUsable()
    {
        return !empty($this->apiKey) && !empty($this->apiSecret) && !empty($this->bucket) && !empty($this->region);
    }
}
