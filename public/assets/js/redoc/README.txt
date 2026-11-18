Redoc 2.5.4 (https://github.com/Redocly/redoc), MIT licence, from the npm package `redoc`:
bundles/redoc.standalone.js, copied with ONE change: the sample value its example generator gives
for an `ipv4` field, a private-range address, is `192.0.2.1` here (a documentation address, RFC 5737). The
repository's leak scan rejects private addresses in tracked files, and this is the only one in the
bundle. Re-apply it when upgrading:
  sed -i 's/ipv4:function(){return"192\.168\.0\.1"}/ipv4:function(){return"192.0.2.1"}/' redoc.standalone-<version>.js
Otherwise unmodified. Served from here instead of the CDN so the
docs page works under the site Content-Security-Policy, whose script-src does not allow
cdn.redoc.ly. To upgrade: npm pack redoc@<version>, replace the three files, change the
version in App\Http\Controllers\ApiDocsController, check the page in a browser.
