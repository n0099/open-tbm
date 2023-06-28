<template>
    <a :href="tiebaPostLink(meta.tid, postTypeID === 'tid' ? undefined : meta[postTypeID])"
       target="_blank" class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="link" size="lg" class="align-bottom" />
    </a>
    <a :data-tippy-content="`<h6>${postTypeID}：${meta[postTypeID]}</h6><hr />
        首次收录时间：${DateTime.fromISO(meta.createdAt).toRelative({ round: false })}（${meta.createdAt}）<br />
        最后更新时间：${DateTime.fromISO(meta.updatedAt).toRelative({ round: false })}（${meta.updatedAt}）`"
       class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="info" size="lg" class="align-bottom" />
    </a>
</template>

<script setup lang="ts">
import type { ReplyRecord, SubReplyRecord, ThreadRecord } from '@/api/index.d';
import type { PostID } from '@/shared';
import { tiebaPostLink } from '@/shared';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { DateTime } from 'luxon';

defineProps<{
    meta: ReplyRecord | SubReplyRecord | ThreadRecord,
    postTypeID: PostID
}>();
</script>
