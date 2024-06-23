const users = ref<User[]>([]);
const getUser = computed(() => baseGetUser(users.value));
const renderUsername = computed(() => baseRenderUsername(getUser.value));
const userProvision = { getUser, renderUsername };

export const provideUsers = (injectUsers: User[]) => {
    users.value = injectUsers;
    provide<typeof userProvision>('userProvision', userProvision);
};
// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
export const injectUsers = () => inject<typeof userProvision>('userProvision')!;
