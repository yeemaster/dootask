<?php

namespace App\Models;

use App\Exceptions\ApiException;
use App\Tasks\PushTask;
use Carbon\Carbon;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Database\Eloquent\SoftDeletes;
use Request;

/**
 * Class Project
 *
 * @package App\Models
 * @property int $id
 * @property string|null $name 名称
 * @property string|null $desc 描述、备注
 * @property int|null $userid 创建人
 * @property int|mixed $dialog_id 聊天会话ID
 * @property string|null $archived_at 归档时间
 * @property int|null $archived_userid 归档会员
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read int $owner_userid
 * @property-read int $task_complete
 * @property-read int $task_my_complete
 * @property-read int $task_my_num
 * @property-read int $task_my_percent
 * @property-read int $task_num
 * @property-read int $task_percent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProjectColumn[] $projectColumn
 * @property-read int|null $project_column_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProjectLog[] $projectLog
 * @property-read int|null $project_log_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProjectUser[] $projectUser
 * @property-read int|null $project_user_count
 * @method static \Illuminate\Database\Eloquent\Builder|Project authData($userid = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Project newQuery()
 * @method static \Illuminate\Database\Query\Builder|Project onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereArchivedUserid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereDesc($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereDialogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereUserid($value)
 * @method static \Illuminate\Database\Query\Builder|Project withTrashed()
 * @method static \Illuminate\Database\Query\Builder|Project withoutTrashed()
 * @mixin \Eloquent
 */
class Project extends AbstractModel
{
    use SoftDeletes;

    const projectSelect = [
        'projects.*',
        'project_users.owner',
    ];

    protected $appends = [
        'task_num',
        'task_complete',
        'task_percent',
        'task_my_num',
        'task_my_complete',
        'task_my_percent',
        'owner_userid',
    ];

    /**
     * 生成任务数据
     */
    private function generateTaskData()
    {
        if (!isset($this->appendattrs['task_num'])) {
            $builder = ProjectTask::whereProjectId($this->id)->whereParentId(0)->whereNull('archived_at');
            $this->appendattrs['task_num'] = $builder->count();
            $this->appendattrs['task_complete'] = $builder->whereNotNull('complete_at')->count();
            $this->appendattrs['task_percent'] = $this->appendattrs['task_num'] ? intval($this->appendattrs['task_complete'] / $this->appendattrs['task_num'] * 100) : 0;
            //
            $builder = ProjectTask::whereProjectId($this->id)->whereParentId(0)->authData(User::userid())->whereNull('archived_at');
            $this->appendattrs['task_my_num'] = $builder->count();
            $this->appendattrs['task_my_complete'] = $builder->whereNotNull('complete_at')->count();
            $this->appendattrs['task_my_percent'] = $this->appendattrs['task_my_num'] ? intval($this->appendattrs['task_my_complete'] / $this->appendattrs['task_my_num'] * 100) : 0;
        }
    }

    /**
     * 任务数量
     * @return int
     */
    public function getTaskNumAttribute()
    {
        $this->generateTaskData();
        return $this->appendattrs['task_num'];
    }

    /**
     * 任务完成数量
     * @return int
     */
    public function getTaskCompleteAttribute()
    {
        $this->generateTaskData();
        return $this->appendattrs['task_complete'];
    }

    /**
     * 任务完成率
     * @return int
     */
    public function getTaskPercentAttribute()
    {
        $this->generateTaskData();
        return $this->appendattrs['task_percent'];
    }

    /**
     * 任务数量（我的）
     * @return int
     */
    public function getTaskMyNumAttribute()
    {
        $this->generateTaskData();
        return $this->appendattrs['task_my_num'];
    }

    /**
     * 任务完成数量（我的）
     * @return int
     */
    public function getTaskMyCompleteAttribute()
    {
        $this->generateTaskData();
        return $this->appendattrs['task_my_complete'];
    }

    /**
     * 任务完成率（我的）
     * @return int
     */
    public function getTaskMyPercentAttribute()
    {
        $this->generateTaskData();
        return $this->appendattrs['task_my_percent'];
    }

    /**
     * 负责人会员ID
     * @return int
     */
    public function getOwnerUseridAttribute()
    {
        if (!isset($this->appendattrs['owner_userid'])) {
            $ownerUser = $this->projectUser->where('owner', 1)->first();
            $this->appendattrs['owner_userid'] = $ownerUser ? $ownerUser->userid : 0;
        }
        return $this->appendattrs['owner_userid'];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function projectColumn(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProjectColumn::class, 'project_id', 'id')->orderBy('sort')->orderBy('id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function projectLog(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProjectLog::class, 'project_id', 'id')->orderByDesc('id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function projectUser(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProjectUser::class, 'project_id', 'id')->orderBy('id');
    }

    /**
     * 查询自己的项目
     * @param self $query
     * @param null $userid
     * @return self
     */
    public function scopeAuthData($query, $userid = null)
    {
        $userid = $userid ?: User::userid();
        $query->join('project_users', 'projects.id', '=', 'project_users.project_id')
            ->where('project_users.userid', $userid);
        return $query;
    }

    /**
     * 加入项目
     * @param int $userid   加入的会员ID
     * @return bool
     */
    public function joinProject($userid)
    {
        if (empty($userid)) {
            return false;
        }
        if (!User::whereUserid($userid)->exists()) {
            return false;
        }
        ProjectUser::updateInsert([
            'project_id' => $this->id,
            'userid' => $userid,
        ]);
        return true;
    }

    /**
     * 同步项目成员至聊天室
     */
    public function syncDialogUser()
    {
        if (empty($this->dialog_id)) {
            return;
        }
        AbstractModel::transaction(function() {
            $userids = $this->relationUserids();
            foreach ($userids as $userid) {
                WebSocketDialogUser::updateInsert([
                    'dialog_id' => $this->dialog_id,
                    'userid' => $userid,
                ]);
            }
            WebSocketDialogUser::whereDialogId($this->dialog_id)->whereNotIn('userid', $userids)->delete();
        });
    }

    /**
     * 获取相关所有人员（项目负责人、项目成员）
     * @return array
     */
    public function relationUserids()
    {
        return $this->projectUser->pluck('userid')->toArray();
    }

    /**
     * 会员id是否在项目里
     * @param int $userid
     * @return int 0:不存在、1存在、2存在且是管理员
     */
    public function useridInTheProject($userid)
    {
        $user = ProjectUser::whereProjectId($this->id)->whereUserid(intval($userid))->first();
        if (empty($user)) {
            return 0;
        }
        return $user->owner ? 2 : 1;
    }

    /**
     * 归档任务、取消归档
     * @param Carbon|null $archived_at 归档时间
     * @return bool
     */
    public function archivedProject($archived_at)
    {
        AbstractModel::transaction(function () use ($archived_at) {
            if ($archived_at === null) {
                // 取消归档
                $this->archived_at = null;
                $this->addLog("项目取消归档");
                $this->pushMsg('add', $this);
            } else {
                // 归档任务
                $this->archived_at = $archived_at;
                $this->archived_userid = User::userid();
                $this->addLog("项目归档");
                $this->pushMsg('archived');
            }
            $this->save();
        });
        return true;
    }

    /**
     * 删除项目
     * @return bool
     */
    public function deleteProject()
    {
        AbstractModel::transaction(function () {
            $dialog = WebSocketDialog::find($this->dialog_id);
            if ($dialog) {
                $dialog->deleteDialog();
            }
            $columns = ProjectColumn::whereProjectId($this->id)->get();
            foreach ($columns as $column) {
                $column->deleteColumn(false);
            }
            $this->delete();
            $this->addLog("删除项目");
        });
        $this->pushMsg('delete');
        return true;
    }

    /**
     * 添加项目日志
     * @param string $detail
     * @param int $userid
     * @return ProjectLog
     */
    public function addLog($detail, $userid = 0)
    {
        $log = ProjectLog::createInstance([
            'project_id' => $this->id,
            'column_id' => 0,
            'task_id' => 0,
            'userid' => $userid ?: User::userid(),
            'detail' => $detail,
        ]);
        $log->save();
        return $log;
    }

    /**
     * 推送消息
     * @param string $action
     * @param array $data       发送内容，默认为[id=>项目ID]
     * @param array $userid     指定会员，默认为项目所有成员
     */
    public function pushMsg($action, $data = null, $userid = null)
    {
        if ($data === null) {
            $data = ['id' => $this->id];
        }
        if ($userid === null) {
            $userid = $this->relationUserids();
        }
        $params = [
            'ignoreFd' => Request::header('fd'),
            'userid' => $userid,
            'msg' => [
                'type' => 'project',
                'action' => $action,
                'data' => $data,
            ]
        ];
        $task = new PushTask($params, false);
        Task::deliver($task);
    }

    /**
     * 根据用户获取项目信息（用于判断会员是否存在项目内）
     * @param int $project_id
     * @param bool $ignoreArchived 排除已归档
     * @return self
     */
    public static function userProject($project_id, $ignoreArchived = true)
    {
        $builder = self::select(self::projectSelect)->authData()->where('projects.id', intval($project_id));
        if ($ignoreArchived) {
            $builder->whereNull('projects.archived_at');
        }
        $project = $builder->first();
        if (empty($project)) {
            throw new ApiException('项目不存在或不在成员列表内');
        }
        return $project;
    }
}
