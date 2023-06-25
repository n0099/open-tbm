<template>
    <div class="horizontal-mobile-message sticky-top p-2 bg-warning border-bottom text-center">
        请将设备横屏显示以获得最佳体验
    </div>
</template>

<style scoped>
.horizontal-mobile-message {
    z-index: 1040;
}

@media screen and (orientation: portrait) {
    .horizontal-mobile-message {
        display: block
    }
}

@media screen and (orientation: landscape) {
    .horizontal-mobile-message {
        display: none
    }
}
</style>
