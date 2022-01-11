module.exports = {
    publicPath: process.env.VUE_APP_PUBLIC_PATH,
    configureWebpack: {
        experiments: { futureDefaults: true },
        module: {
            rules: [{
                test: /\.avif/u,
                type: 'asset/resource',
                generator: { filename: 'img/[hash][ext][query]' }
            }]
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
