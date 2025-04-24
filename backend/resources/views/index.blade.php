<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Darsboard</title>
        <!-- Fonts -->
        <link href  = "https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">
        <link rel   = "stylesheet" href="{{ asset('assets/css/site.css') }}">
        <link rel   = "stylesheet" href="{{ asset('assets/css/chooser.css') }}">
        <link rel   = "stylesheet" href="{{ asset('assets/fonts/fontawesome5-overrides.min.css') }}">
        <link rel   = "stylesheet" href="{{ asset('assets/css/toastr.min.css') }}">
        {{-- <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css"> --}}
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/material-design-icons/3.0.1/iconfont/material-icons.min.css">
        <script src="{{ asset('assets/js/socket.io.js') }}"></script>
        <script src="{{ asset('assets/js/jquery-3.3.1.min.js') }}"></script>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
        <script src="{{ asset('assets/js/toastr.min.js') }}"></script>
        <script src="{{ asset('assets/js/hash.min.js') }}"></script>
        <script type="text/javascript">
            var Ext = Ext || {}; // Ext namespace won't be defined yet...

            // This function is called by the Microloader after it has performed basic
            // device detection. The results are provided in the "tags" object. You can
            // use these tags here or even add custom tags. These can be used by platform
            // filters in your manifest or by platformConfig expressions in your app.
            //
            Ext.beforeLoad = function (tags) {
                var s = location.search,  // the query string (ex "?foo=1&bar")
                    profile;

                // For testing look for "?classic" or "?modern" in the URL to override
                // device detection default.
                //
                if (s.match(/\bclassic\b/)) {
                    profile = 'classic';
                }
                else if (s.match(/\bmodern\b/)) {
                    profile = 'modern';
                }
                // uncomment this if you have added native build profiles to your app.json
                /*else if (tags.webview) {
                    if (tags.ios) {
                        profile = 'ios';
                    }
                    // add other native platforms here
                }*/
                else {
                    //profile = tags.desktop ? 'classic' : 'modern';
                    profile = tags.phone ? 'modern' : 'classic';
                }

                Ext.manifest = profile; // this name must match a build profile name

                // This function is called once the manifest is available but before
                // any data is pulled from it.
                //
                //return function (manifest) {
                    // peek at / modify the manifest object
                //};
            };
        </script>
        <!-- The line below must be kept intact for Sencha Cmd to build your application -->
        <script src="https://kit.fontawesome.com/451fee806f.js" crossorigin="anonymous"></script>
        <script id="microloader" type="text/javascript" src="bootstrap.js" ></script>
    </head>
    <body>

    <div id="global-spinner" class="spinner">
        <div class="blob blob-0"></div>
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="blob blob-4"></div>
        <div class="blob blob-5"></div>
    </div>

    </body>
</html>
