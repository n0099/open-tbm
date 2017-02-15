{include "header.tpl"}
<body>
    <div class="row">
        <div class="container">
            <table class="highlight">
                <thead>
                <tr>
                    <th class="col s1" data-field="id">贴子PID</th>
                    <th class="col s2" data-field="title">贴子标题</th>
                    <th class="col s1" data-field="type">贴子类型</th>
                    <th class="col s4" data-field="text">贴子内容</th>
                    <th class="col s2" data-field="url">传送门</th>
                    <th class="col s1" data-field="author">发帖人</th>
                    <th class="col s2" data-field="time">发帖时间</th>
                </tr>
                </thead>

                <tbody>
                {foreach $forum->getPosties() as $post}
                    <tr>
                        <td>{$post->getId()}</td>
                        <td>{$post->getTitle()}</td>
                        <td>{$post->getType()}</td>
                        <td>{$post->getText()}</td>
                        <td><a href="http://tieba.baidu.com/p/{$post->getId()}">传送门</a></td>
                        <td>{$post->getAuthor()}</td>
                        <td>{$post->getPostTimeString()}</td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</body>
{include "footer.tpl"}