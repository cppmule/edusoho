<?php

namespace Biz\Xapi\Job;

use Biz\Activity\Service\ActivityService;
use Biz\Xapi\Service\XapiService;
use Codeages\Biz\Framework\Scheduler\AbstractJob;

class AddActivityWatchToStatementJob extends AbstractJob
{
    public function execute()
    {
        $conditions = array(
            'is_push' => 0,
            'created_time_LT' => time() - 60 * 60,
        );

        $orderBy = array('created_time' => 'ASC');

        $watchLogs = $this->getXapiService()->searchWatchLogs($conditions, $orderBy, 0, 10);

        foreach ($watchLogs as $watchLog) {
            $activity = $this->getActivityService()->getActivity($watchLog['activity_id']);
            $statement = array(
                'version' => '',
                'user_id' => $watchLog['user_id'],
                'verb' => 'watch',
                'target_id' => $watchLog['id'],
                'target_type' => $activity['mediaType']
            );

            $this->getXapiService()->createStatement($statement);
        }
    }

    /**
     * @return XapiService
     */
    protected function getXapiService()
    {
        return $this->biz->service('Xapi:XapiService');
    }

    /**
     * @return ActivityService
     */
    protected function getActivityService()
    {
        return $this->biz->service('Activity:ActivityService');
    }
}
