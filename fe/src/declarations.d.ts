import type {
    ComponentCustomOptions as _ComponentCustomOptions,
    ComponentCustomProperties as _ComponentCustomProperties
} from 'vue';

/* eslint-disable @typescript-eslint/no-empty-object-type */
declare module '@vue/runtime-core' {
    interface ComponentCustomProperties extends _ComponentCustomProperties {}
    interface ComponentCustomOptions extends _ComponentCustomOptions {}
}
