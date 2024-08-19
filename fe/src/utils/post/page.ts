export interface PostPageProvision {
    getUser: ReturnType<typeof baseGetUser>,
    renderUsername: ReturnType<typeof baseRenderUsername>,
    getLatestReplier: ReturnType<typeof baseGetLatestReplier>
}
export const usePostPageProvision = () => {
    const users = ref<User[]>([]);
    const getUser = computed(() => baseGetUser(users.value));
    const renderUsername = computed(() => baseRenderUsername(getUser.value));
    const latestReplier = ref<LatestReplier[]>([]);
    const getLatestReplier = computed(() => baseGetLatestReplier(latestReplier.value));
    const postPageProvision = { getUser, renderUsername, getLatestReplier };
    const provideUsers = (injectedUsers: User[], injectedLatestRepliers: LatestReplier[]) => {
        users.value = injectedUsers;
        latestReplier.value = injectedLatestRepliers;
        provide<typeof postPageProvision>('postPageProvision', postPageProvision);
    };
    // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
    const injectUsers = () => inject<typeof postPageProvision>('postPageProvision')!;

    return { provide: provideUsers, inject: injectUsers };
};
