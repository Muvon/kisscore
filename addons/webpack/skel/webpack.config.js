var ExtractTextPlugin = require('extract-text-webpack-plugin')
var webpack = require('webpack')
var postcssWillChange = require('postcss-will-change')
var postcssAssets = require('postcss-assets')
var autoprefixer = require('autoprefixer')
var postcssPxtorem = require('postcss-pxtorem')
var path = require('path')

module.exports = {
  cache: true,
  devtool: 'source-map',
  entry: {
    bundle: ['./app/client/main.js', './app/client/main.sass'],
  },
  output: {
    path: path.resolve(__dirname, 'env/var'),
    filename: '[name].js',
    pathinfo: false
  },
  resolve: {
    mainFiles: ['index'],
    alias: {
      app: path.resolve(__dirname, 'app/client'),
      component: path.resolve(__dirname, 'app/client/component'),
      lib: path.resolve(__dirname, 'app/client/lib'),
      asset: path.resolve(__dirname, 'app/client/asset'),
    }
  },
  module: {
    rules: [
       {
        test: /\.js$/,
        exclude: /node_modules/,
        use: [
          {
            loader: 'babel-loader'
          }
        ]
      },
      {
        test: /\.jext/,
        use: [
          {
            loader: 'jext-loader'
          }
        ]
      },
      {
        test: /\.(png|jpe?g|gif|svg)$/,
        use: [
          {
            loader: 'url-loader?limit=4096&name=asset/[name].[ext]'
          }
        ]
      },
      {
        test: /\.(eot|svg|ttf|woff|woff2)$/,
        use: [
          {
            loader: 'file-loader?name=asset/fonts/[name].[ext]'
          }
        ]
      },
      {
        test: /\.(s?css|sass)/,
        use: ExtractTextPlugin.extract({
          fallback: 'style-loader',
          use: [
            {
              loader: 'css-loader'
              // options: {
              //   sourceMap: true
              // }
            }, {
              loader: 'postcss-loader',
              options: {
                plugins: [
                  postcssWillChange(),
                  postcssAssets({basePath: './app/static/img'}),
                  autoprefixer({browsers: [
                    'last 2 versions',
                    'IE >= 9',
                    'opera 12',
                    'safari 7',
                    'Android >= 4',
                    'iOS >= 7'
                  ]}),
                  postcssPxtorem()
                ]
              }
            }, {
              loader: 'sass-loader'
            }
          ]
        })
      }
    ]
  },
  plugins: [
    new ExtractTextPlugin({
      filename: '[name].css',
      allChunks: true
    }),
    new webpack.optimize.UglifyJsPlugin({
      minimize: true,
      sourceMap: true,
      output: {
        comments: false
      },
      compress: {
        warnings: false
      }
    })
  ],
  watchOptions: {
    poll: 1000,
    aggregateTimeout: 500
  }
}
