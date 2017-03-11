{include "header.tpl"}
<body>
    {include "navbar.tpl"}
    <div class="section">
        <div class="container">
            <table class="highlight">
                <thead>
                <tr class="container">
                    <th class="col s1" data-field="id">贴子PID</th>
                    <th class="col s2" data-field="title">贴子标题</th>
                    <th class="col s1" data-field="type">贴子类型</th>
                    <th class="col s4" data-field="text">贴子内容</th>
                    <th class="col s1" data-field="url">传送门</th>
                    <th class="col s1" data-field="author">发帖人</th>
                    <th class="col s2" data-field="time">发帖时间</th>
                </tr>
                </thead>

                <tbody>
                {foreach $forum->getPosties() as $post}
                    <tr class="container">
                        <td class="col s1">{$post->getId()}</td>
                        <td class="col s2">{$post->getTitle()}</td>
                        <td class="col s1">{$post->getType()}</td>
                        <td class="col s4">{$post->getText()}</td>
                        <td class="col s1"><a href="http://tieba.baidu.com/p/{$post->getId()}">传送门</a></td>
                        <td class="col s1">{$post->getAuthor()}</td>
                        <td class="col s2">{$post->getPostTimeString()}</td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</body>
{include "footer.tpl"}