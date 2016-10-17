// http://stackoverflow.com/questions/16133614/chrome-extensions-that-runs-js-before-every-page-loads-using-content-script
// https://developer.chrome.com/extensions/getstarted
// http://stackoverflow.com/questions/20323600/how-to-get-errors-stack-trace-in-chrome-extension-content-script

localStorage.setItem("js_errors", "");

var jserror_debug = false;

function domToString(element, maxDepth, currentDepth) {

    var result = "";

    if (!currentDepth) {
        result = "ErrorEvent"
        currentDepth = 1;
    }

    if (currentDepth == maxDepth) {
        return '';
    }

    for (var key in element) {
        if (element[key] instanceof Object) {
            result = result + "\n" + " ".repeat(currentDepth * 2) + key + ': ' + domToString(element[key], maxDepth, currentDepth + 1);
        } else {
            result = result + "\n" + " ".repeat(currentDepth * 2) + key + ': ' + element[key];
        }
    }
    return result;
}

window.addEventListener("error", function (error) {
    filename = error.filename;
    if (filename == "") {
        filename = 'NO_FILENAME_SET_BY_CHROME';
    }
    message = error.message + ' (filename: ' + filename + '::' + error.lineno + ')';
    console.log('Leankoala: ' + message);
    if (localStorage.getItem("js_errors") == "") {
        localStorage.setItem("js_errors", message);

        if (jserror_debug) {
            errorString = domToString(error, 3);
            localStorage.setItem("full_errors", errorString);
        }
    } else {
        localStorage.setItem("js_errors", localStorage.getItem("js_errors") + ' ### ' + message);

        if (jserror_debug) {
            localStorage.setItem("full_errors", localStorage.getItem("js_errors") + '###' + domToString(error, 3));
        }
    }
});