Redoc 2.5.4 (https://github.com/Redocly/redoc), MIT licence, from the npm package `redoc`:
bundles/redoc.standalone.js, copied unmodified. Served from here instead of the CDN so the
docs page works under the site Content-Security-Policy, whose script-src does not allow
cdn.redoc.ly. To upgrade: npm pack redoc@<version>, replace the three files, change the
version in App\Http\Controllers\ApiDocsController, check the page in a browser.
