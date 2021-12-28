<template>
    <a :href="tiebaPostLink(meta.tid, postTypeID === 'tid' ? undefined : meta[postTypeID])"
       target="_blank" class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="link" size="lg" class="align-bottom" />
    </a>
    <a :data-tippy-content="`<h6>${postTypeID}：${meta[postTypeID]}</h6><hr />
        首次收录时间：${DateTime.fromISO(meta.created_at).toRelative({ round: false })}（${meta.created_at}）<br />
        最后更新时间：${DateTime.fromISO(meta.updated_at).toRelative({ round: false })}（${meta.updated_at}）`"
       class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="info" size="lg" class="align-bottom" />
    </a>
</template>

<script lang="ts">
import type { PostRecord } from '@/api/index.d';
import type { PostID } from '@/shared';
import { tiebaPostLink } from '@/shared';
import { dateTimeFromUTC8 } from '@/shared/echarts';

import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { DateTime } from 'luxon';

export default defineComponent({
    components: { FontAwesomeIcon },
    props: {
        meta: { type: Object as PropType<PostRecord>, required: true },
        postTypeID: { type: String as PropType<PostID>, required: true }
    },
    setup() {
        return { DateTime, dateTimeFromUTC8, tiebaPostLink };
    }
});
</script>
