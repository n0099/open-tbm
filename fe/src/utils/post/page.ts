export interface PostPageProvision {
    getUser: ReturnType<typeof baseGetUser>,
    renderUsername: ReturnType<typeof baseRenderUsername>,
    getLatestReplier: ReturnType<typeof baseGetLatestReplier>,
    currentCursor: Cursor
}
export const usePostPageProvision = () => {
    const users = ref<User[]>([]);
    const getUser = computed(() => baseGetUser(users.value));
    const renderUsername = computed(() => baseRenderUsername(getUser.value));

    const latestRepliers = ref<LatestReplier[]>([]);
    const getLatestReplier = computed(() => baseGetLatestReplier(latestRepliers.value));

    const currentCursor = ref<Cursor>('');
    const postPageProvision = { getUser, renderUsername, getLatestReplier, currentCursor };
    const provideProvision = (provisions: { users: User[], latestRepliers: LatestReplier[], currentCursor: Cursor }) => {
        users.value = provisions.users;
        latestRepliers.value = provisions.latestRepliers;
        currentCursor.value = provisions.currentCursor;
        provide<typeof postPageProvision>('postPageProvision', postPageProvision);
    };
    // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
    const injectProvision = () => inject<typeof postPageProvision>('postPageProvision')!;

    return { provide: provideProvision, inject: injectProvision };
};
