module.exports = {
    publicPath: process.env.VUE_APP_PUBLIC_PATH,
    configureWebpack: {
        experiments: {
            topLevelAwait: true
        }
    }
};
