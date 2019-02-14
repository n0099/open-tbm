<?php

namespace App\Console\Commands;

use App\Tieba\Eloquent\ForumModel;
use Illuminate\Console\Command;

class BatchTableSQLGenerator extends Command
{
    protected $signature = 'tbm:batchSQL';

    protected $description = '基于所有吧贴子表占位生成SQL';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $input = $this->ask('请输入需替换表名SQL 不支持多行文本
        占位符：
        {t_thread} => 主题贴表
        {t_reply} => 回复贴表
        {t_subReply} => 楼中楼表');
        $this->info($input);
        $placeholders = [
            '{t_thread}' => 'tbm_f{fid}_threads',
            '{t_reply}' => 'tbm_f{fid}_replies',
            '{t_subReply}' => 'tbm_f{fid}_subReplies'
        ];
        foreach (ForumModel::select('fid')->get() as $forum) {
            $placeholdersName = array_keys($placeholders);
            $replacedPlaceholders = str_replace('{fid}', $forum->fid, array_values($placeholders));
            $this->warn(str_replace($placeholdersName, $replacedPlaceholders, $input));
        }
    }
}
