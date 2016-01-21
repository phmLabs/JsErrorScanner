var page = require('webpage').create(),
    system = require('system');

if (system.args.length === 1) {
    console.log('Usage: error.js <some URL>');
    phantom.exit(1);
} else {

    page.settings.userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36';
    page.settings.resourceTimeout = 1000;

    page.address = system.args[1];
    page.resources = [];

    page.onError = function (msg, trace) {
        var message = msg + ' ';

        if (trace && trace.length) {
            message = message + '(' + trace[0].file + ':' + trace[0].line + ')';
        }
        console.log(message);
    };

    page.onResourceTimeout = function (request) {
        console.log('Response (#' + request.id + '): ' + JSON.stringify(request));
    };

    page.open(page.address, function (status) {
        if (status !== 'success') {
            console.log('FAIL to load the address');
            phantom.exit(1);
        } else {
            phantom.exit();
        }
    });
}