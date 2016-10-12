var page = require('webpage').create(),
    system = require('system');

if (system.args.length === 1) {
    console.log('Usage: error.js <some URL>');
    phantom.exit(1);
} else {
    page.address = system.args[1];
    page.resources = [];

    page.onError = function (msg, trace) {
        var message = msg + ' ';

        if (trace && trace.length) {
            message = '###error_begin###message: ' + message + '; file: ' + trace[0].file + '; line: ' + trace[0].line + '###error_end###';
        }
        console.log(message);
    };

    page.open(page.address, function (status) {
        if (status !== 'success') {
            console.log('FAILED to load the address');
            phantom.exit(1);
        } else {
            phantom.exit();
        }
    });
}
