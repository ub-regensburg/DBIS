const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const { WebpackManifestPlugin } = require('webpack-manifest-plugin');
const {CleanWebpackPlugin} = require('clean-webpack-plugin');
// Here, we can add single pages to which we can assign .js files

const pages = ['test',
    'admin_start',
    'admin_login',
    'admin_base',
    'admin_manage_organizations',
    'admin_edit_organization',
    'admin_organization_form',
    'admin_create_database',
    'admin_manage_licenses',
    'admin_create_license',
    'admin_edit_license',
    'admin_search_databases',
    'admin_manage_labels',
    'users_base',
    'users_search',
    'users_results',
    'users_details',
    'users_browse_subjects',
    'users_browse_collections',
    'users_resources_for_subject',
    'admin_manage_databases',
    'admin_manage_relationships',
    'admin_manage_dbis_views',
    'admin_select_subject',
    'admin_manage_collections',
    'admin_create_collection',
    'admin_manage_keywords',
    'admin_manage_drafts',
    'superadmin_manage_privileges',
    'superadmin_manage_privileges_user_select',
    'superadmin_settings',
    'superadmin_free_resources',
    'openapi'
];

module.exports = {
    entry: pages.reduce((config, page) => {
        config[page] = `./app/${page}.js`;
        return config;
    }, {}),
    output: {
        filename: '[name].[contenthash].js',
        sourceMapFilename: '[name].js.map',
        path: path.resolve(__dirname, 'public', 'dist'),
        publicPath: '/dist/'
    },
    optimization: {
        splitChunks: {
            // chunks: "all",
        },
    },
    resolve: {
        /*
        Add an alias for a specific dir, that can be used in js and scss path imports.
         */
        alias: {
            '@': path.resolve(__dirname, 'app')
        },
        extensions: ['*', '.js', '.json']
    },
    module: {
        rules: [
            {
                test: /\.m?js$/,
                exclude: /(node_modules|bower_components)/,
                use: {
                    loader: 'babel-loader'
                }
            },
            {
                test: /\.(scss|css)$/,
                use: [MiniCssExtractPlugin.loader, 'css-loader', 'postcss-loader', {
                    loader: 'sass-loader'
                }]
            },
            {
                test: /\.(png|svg|jpg|gif)$/,
                use: ['file-loader']
            },
            {
                test: /\.(woff|woff2|eot|ttf|otf)$/,
                use: ['file-loader']
            }
        ]
    },
    plugins: [
        new CleanWebpackPlugin(),
        new WebpackManifestPlugin(),
        new MiniCssExtractPlugin({
            filename: '[name].[contenthash].css'
        }),
        new webpack.ProvidePlugin({
            $: 'jquery',
            jQuery: 'jquery',
        })
        /*,
        {
            // "npm run build" did not exit - this should fix it:
            // (https://stackoverflow.com/questions/56053159/webpack-run-build-but-not-exit-command)
            apply: (compiler) => {
                compiler.hooks.done.tap('DonePlugin', (stats) => {
                    console.log('Compile is done !')
                    setTimeout(() => {
                        process.exit(0)
                    })
                });
            }
        }
        */
    ],
    watch: true,
    watchOptions: {
        poll: 1000,
        aggregateTimeout: 300,
        ignored: './node_modules/'
    }
};
