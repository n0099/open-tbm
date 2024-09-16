<?php

namespace App\Console\Commands;

use App\Eloquent\Model\Forum;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

class BatchTableSQLGenerator extends Command
{
    protected $signature = 'tbm:batchSQL';

    protected $description = '基于所有吧帖子表占位生成SQL';

    public function __construct(private readonly DatabaseManager $db)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $input = $this->ask('请输入需替换表名SQL 不支持多行文本
            占位符：
            {fid} => 吧ID
            {t_thread} => 主题帖表
            {t_reply} => 回复帖表
            {t_subReply} => 楼中楼表');
        $placeholders = [
            '{fid}' => '{fid}',
            '{t_thread}' => 'tbmc_f{fid}_thread',
            '{t_reply}' => 'tbmc_f{fid}_reply',
            '{t_subReply}' => 'tbmc_f{fid}_subReply',
        ];
        $outputSQLs = [];
        foreach (Forum::select('fid')->get() as $forum) {
            $placeholdersName = array_keys($placeholders);
            $replacedPlaceholders = str_replace('{fid}', $forum->fid, array_values($placeholders));
            $replacedInput = str_replace($placeholdersName, $replacedPlaceholders, $input);
            $outputSQLs[] = $replacedInput;
            $this->warn($replacedInput);
        }
        if ($this->confirm('是否执行上述SQL？')) {
            foreach ($outputSQLs as $outputSQL) {
                try {
                    $affectedRows = $this->db->statement($outputSQL);
                    $this->info($outputSQL . '  影响行数：' . $affectedRows ?? 0);
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
        }
    }
}
