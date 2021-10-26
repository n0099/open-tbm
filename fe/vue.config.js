module.exports = {
    publicPath: process.env.VUE_APP_PUBLIC_PATH,
    configureWebpack: {
        experiments: {
            topLevelAwait: true
        }
    },
    devServer: {
        client: {
            webSocketURL: 'ws://0.0.0.0/ws'
        }
    },
    pluginOptions: {
        webpackBundleAnalyzer: {
            analyzerMode: 'static'
        }
    }
};
