<?php

namespace Truonglv\VideoUpload;

use XF\Db\Schema\Create;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use Truonglv\VideoUpload\DevHelper\SetupTrait;
use XF\Util\File;

class Setup extends AbstractSetup
{
    use SetupTrait;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->doCreateTables($this->getTables());
    }

    public function uninstallStep1()
    {
        $this->doDropTables($this->getTables());
        
        File::deleteAbstractedDirectory('internal-data://tvu_video_upload');
    }

    protected function getTables1()
    {
        $tables = [];

        $tables['xf_truonglv_videoupload_video'] = function (Create $table) {
            $table->addColumn('video_id', 'int')->unsigned()->autoIncrement();
            $table->addColumn('thread_id', 'int')->unsigned();
            $table->addColumn('attachment_id', 'int')->unsigned();
            $table->addColumn('remote_url', 'TEXT');
            $table->addColumn('remote_upload_date', 'int')->unsigned()->setDefault(0);
            $table->addColumn('upload_date', 'int')->unsigned()->setDefault(0);

            $table->addKey('attachment_id');
            $table->addKey('thread_id');
        };

        $tables['xf_truonglv_videoupload_video_part'] = function (Create $table) {
            $table->addColumn('path', 'varchar', 255);
            $table->addColumn('upload_date', 'int')->unsigned()->setDefault(0);

            $table->addKey('upload_date');
        };

        return $tables;
    }
}
